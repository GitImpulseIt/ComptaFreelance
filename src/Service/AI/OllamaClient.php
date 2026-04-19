<?php

declare(strict_types=1);

namespace App\Service\AI;

/**
 * Client minimal pour l'API native d'Ollama : POST /api/chat avec format JSON forcé.
 */
class OllamaClient
{
    public function __construct(
        private string $url,
        private string $model,
        private string $apiKey = '',
        private string $cfClientId = '',
        private string $cfClientSecret = '',
        private int $timeout = 120,
    ) {}

    /**
     * Envoie un chat et retourne le contenu texte de la réponse assistant.
     *
     * @param array<int,array{role:string,content:string}> $messages
     */
    public function chatJson(array $messages): string
    {
        // Streaming NDJSON : évite le timeout Cloudflare de 100s en maintenant
        // la connexion active via des chunks réguliers, et accumule les tokens
        // jusqu'au message done=true.
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'format' => 'json',
            'stream' => true,
            'options' => ['temperature' => 0.2],
        ];

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        if ($this->cfClientId !== '' && $this->cfClientSecret !== '') {
            $headers[] = 'CF-Access-Client-Id: ' . $this->cfClientId;
            $headers[] = 'CF-Access-Client-Secret: ' . $this->cfClientSecret;
        }

        $accumulated = '';
        $buffer = '';
        $lastError = null;

        $ch = curl_init($this->url . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_WRITEFUNCTION => function ($ch, string $data) use (&$accumulated, &$buffer, &$lastError) {
                $buffer .= $data;
                while (($nl = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $nl));
                    $buffer = substr($buffer, $nl + 1);
                    if ($line === '') continue;
                    $decoded = json_decode($line, true);
                    if (!is_array($decoded)) {
                        $lastError = 'Ligne NDJSON invalide : ' . substr($line, 0, 200);
                        continue;
                    }
                    if (isset($decoded['error'])) {
                        $lastError = 'Erreur Ollama : ' . $decoded['error'];
                        continue;
                    }
                    $accumulated .= (string) ($decoded['message']['content'] ?? '');
                }
                return strlen($data);
            },
        ]);

        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($ok === false) {
            throw new \RuntimeException('Erreur réseau Ollama : ' . $err);
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Ollama a répondu HTTP {$httpCode}");
        }
        if ($accumulated === '' && $lastError !== null) {
            throw new \RuntimeException($lastError);
        }

        return $accumulated;
    }
}

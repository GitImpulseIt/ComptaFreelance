<?php

declare(strict_types=1);

namespace Admin\Controller;

use PDO;
use Twig\Environment;

class AiSettingsController
{
    private const KEYS = [
        'ai_model',
        'ai_think_enabled',
        'ai_history_limit',
        'ai_system_prompt',
    ];

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
    ) {}

    public function index(): void
    {
        echo $this->twig->render('ai-settings/index.html.twig', [
            'active_page' => 'ai-settings',
            'settings' => $this->load(),
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function update(): void
    {
        $model = trim((string) ($_POST['ai_model'] ?? ''));
        $think = !empty($_POST['ai_think_enabled']);
        $history = max(0, min(100, (int) ($_POST['ai_history_limit'] ?? 15)));
        $prompt = trim((string) ($_POST['ai_system_prompt'] ?? ''));

        if ($model === '') {
            $_SESSION['error'] = 'Le nom du modèle est requis.';
            header('Location: /ai-settings');
            exit;
        }

        $this->upsert('ai_model', $model);
        $this->upsert('ai_think_enabled', $think ? 't' : 'f');
        $this->upsert('ai_history_limit', (string) $history);
        $this->upsert('ai_system_prompt', $prompt);

        header('Location: /ai-settings?success=updated');
        exit;
    }

    /** @return array<string, string> */
    private function load(): array
    {
        $stmt = $this->pdo->query("SELECT key, value FROM admin_settings WHERE key LIKE 'ai_%'");
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['key']] = (string) $row['value'];
        }
        foreach (self::KEYS as $k) {
            $out[$k] = $out[$k] ?? '';
        }
        return $out;
    }

    private function upsert(string $key, string $value): void
    {
        $this->pdo->prepare(
            "INSERT INTO admin_settings (key, value, updated_at)
             VALUES (:k, :v, now())
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = now()"
        )->execute(['k' => $key, 'v' => $value]);
    }
}

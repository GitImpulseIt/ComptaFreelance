<?php

declare(strict_types=1);

namespace App\Service\AI;

use App\Repository\LienDocumentRepository;
use App\Repository\LigneComptableRepository;
use App\Repository\TransactionBancaireRepository;

/**
 * Construit le prompt pour Ollama à partir du contexte de qualification,
 * appelle l'IA et renvoie une proposition de lignes comptables.
 */
class PropositionQualificationService
{
    public function __construct(
        private OllamaClient $client,
        private TransactionBancaireRepository $transactionRepo,
        private LigneComptableRepository $ligneRepo,
        private LienDocumentRepository $lienRepo,
        private int $historyLimit = 25,
    ) {}

    /**
     * @param array{id:int,date:string,libelle:string,montant:string|float,type:string} $transaction
     * @param list<array{numero:string,libelle:string,sens:?string}> $plan
     * @return array{lignes: list<array{compte:string,montant_ht:float,tva:float,type:string}>, explication:string}
     */
    public function proposer(array $transaction, int $entrepriseId, array $plan): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => $this->userPrompt($transaction, $entrepriseId, $plan)],
        ];

        $raw = $this->client->chatJson($messages);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Réponse IA non JSON : ' . substr($raw, 0, 300));
        }

        $lignes = [];
        foreach (($decoded['lignes'] ?? []) as $l) {
            if (!is_array($l)) continue;
            $lignes[] = [
                'compte' => (string) ($l['compte'] ?? ''),
                'montant_ht' => (float) ($l['montant_ht'] ?? 0),
                'tva' => (float) ($l['tva'] ?? 0),
                'type' => in_array($l['type'] ?? '', ['DBT', 'CRD'], true) ? $l['type'] : 'DBT',
            ];
        }

        return [
            'lignes' => $lignes,
            'explication' => (string) ($decoded['explication'] ?? ''),
        ];
    }

    private function systemPrompt(): string
    {
        return <<<TEXT
Tu es un assistant comptable expert pour freelances français (régime réel).
Tu proposes les lignes comptables pour qualifier une opération bancaire.

Règles strictes :
1. Utilise UNIQUEMENT les numéros de comptes fournis dans la liste "COMPTES DISPONIBLES".
2. Les débits et crédits doivent s'équilibrer : somme des (montant_ht + tva) des lignes DBT = somme des lignes CRD.
3. Si la TVA s'applique et que le régime le permet, isole la TVA sur un compte dédié (44566 TVA déductible, 44571 TVA collectée).
4. La contrepartie de trésorerie (512000 ou équivalent) équilibre toujours l'opération.
5. type = "DBT" pour un débit comptable, "CRD" pour un crédit.
6. Tu peux t'inspirer de l'historique fourni pour des opérations similaires.
7. Si tu hésites, privilégie les comptes utilisés dans l'historique.

Réponds UNIQUEMENT avec un JSON valide (pas de texte avant/après) au format :
{
  "lignes": [
    {"compte": "627", "montant_ht": 11.00, "tva": 2.20, "type": "DBT"},
    {"compte": "512000", "montant_ht": 13.20, "tva": 0, "type": "CRD"}
  ],
  "explication": "Brève justification en français."
}
TEXT;
    }

    private function userPrompt(array $transaction, int $entrepriseId, array $plan): string
    {
        $history = $this->buildHistory($entrepriseId, (int) $transaction['id']);
        $planStr = $this->buildPlan($plan);
        $docs = $this->lienRepo->findByTransaction((int) $transaction['id']);
        $docsStr = empty($docs)
            ? 'Aucun'
            : implode("\n", array_map(fn($d) => '- ' . $d['url'], $docs));

        $sens = $transaction['type'] === 'debit' ? 'Débit (argent sortant)' : 'Crédit (argent entrant)';
        $montant = number_format((float) $transaction['montant'], 2, '.', '');

        return <<<TEXT
OPÉRATION À QUALIFIER
- Date : {$transaction['date']}
- Libellé : "{$transaction['libelle']}"
- Montant : {$montant} €
- Sens : {$sens}
- Documents attachés :
{$docsStr}

HISTORIQUE DES OPÉRATIONS DÉJÀ QUALIFIÉES (pour t'inspirer)
{$history}

COMPTES DISPONIBLES
{$planStr}

Propose la qualification de l'opération au format JSON strict.
TEXT;
    }

    private function buildHistory(int $entrepriseId, int $excludeTransactionId): string
    {
        $stmt = $this->transactionRepo;
        $transactions = $stmt->findAllByEntreprise($entrepriseId, []);

        $lines = [];
        $count = 0;
        foreach ($transactions as $t) {
            if ((int) $t['id'] === $excludeTransactionId) continue;
            $lignes = $this->ligneRepo->findByTransaction((int) $t['id']);
            if (empty($lignes)) continue;

            $sens = $t['type'] === 'debit' ? 'Débit' : 'Crédit';
            $m = number_format((float) $t['montant'], 2, '.', '');
            $block = "* [{$t['date']}] \"{$t['libelle']}\" ({$m}€ {$sens})";
            foreach ($lignes as $l) {
                $ht = number_format((float) $l['montant_ht'], 2, '.', '');
                $tva = number_format((float) $l['tva'], 2, '.', '');
                $block .= "\n    → {$l['compte']} : HT={$ht} TVA={$tva} {$l['type']}";
            }
            $lines[] = $block;
            if (++$count >= $this->historyLimit) break;
        }

        return empty($lines) ? '(aucun historique pour l\'instant)' : implode("\n", $lines);
    }

    /** @param list<array{numero:string,libelle:string,sens:?string}> $plan */
    private function buildPlan(array $plan): string
    {
        $out = [];
        foreach ($plan as $p) {
            $sens = $p['sens'] ?? '';
            $out[] = "- {$p['numero']} — {$p['libelle']}" . ($sens ? " ({$sens})" : '');
        }
        return implode("\n", $out);
    }
}

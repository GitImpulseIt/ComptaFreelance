<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\EntrepriseRepository;
use PDO;
use Twig\Environment;

class ClotureController
{
    private EntrepriseRepository $entrepriseRepo;

    /**
     * Arrondi fiscal : à l'euro le plus proche, 0.50 compté pour 1.
     */
    private function arrondisFiscal(float $val): int
    {
        return (int)round($val, 0, PHP_ROUND_HALF_UP);
    }

    private const TABS_IS = [
        ['slug' => 'bilan',           'label' => 'Bilan'],
        ['slug' => 'compte-resultat', 'label' => 'Compte de résultat'],
    ];

    private const TABS_IR = [
        ['slug' => '2035',            'label' => '2035'],
        ['slug' => '2035-a',          'label' => '2035-A'],
        ['slug' => '2035-b',          'label' => '2035-B'],
        ['slug' => '2035-e',          'label' => '2035-E'],
    ];

    // Mapping slug onglet → colonne JSONB en base
    private const TAB_COLUMN = [
        'bilan'           => 'data_bilan',
        'compte-resultat' => 'data_compte_resultat',
        '2035'            => 'data_2035',
        '2035-a'          => 'data_2035_a_p1',
        '2035-b'          => 'data_2035_b',
        '2035-e'          => 'data_2035_e',
    ];

    private function getTabsForEntreprise(array $entreprise): array
    {
        $isIR = !empty($entreprise['option_ir']);
        return $isIR ? self::TABS_IR : self::TABS_IS;
    }

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->entrepriseRepo = new EntrepriseRepository($pdo);
    }

    public function index(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;
        $isIR = !empty($entreprise['option_ir']);
        $defaultTab = $isIR ? '2035' : 'bilan';
        header('Location: /app/cloture/' . $defaultTab . '?annee=' . $annee);
        exit;
    }

    public function tabBilan(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (!empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/2035');
            exit;
        }
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        $raw = $this->computeBilanRaw($entrepriseId, $annee);
        $rawN1 = $this->computeBilanRaw($entrepriseId, $annee - 1);

        // Format computed values for N (brut & amort)
        $computed = [];
        foreach ($raw as $k => $v) {
            $computed[$k] = $v != 0 ? (string)$this->arrondisFiscal($v) : '';
        }

        // Compute N-1 net values for ACTIF lines (brut - amort)
        $computedN1 = [];
        $pairs = ['010' => '012', '014' => '016', '028' => '030', '040' => '042'];
        foreach ($pairs as $brut => $amort) {
            $net = round(($rawN1[$brut] ?? 0) - ($rawN1[$amort] ?? 0));
            $computedN1[$brut] = $net != 0 ? (string)(int)$net : '';
        }
        if (($rawN1['084'] ?? 0) != 0) {
            $computedN1['084'] = (string)$this->arrondisFiscal($rawN1['084']);
        }

        // Passif et créances N-1
        foreach (['072', '120', '136', '156', '172', '199'] as $case) {
            if (($rawN1[$case] ?? 0) != 0) {
                $computedN1[$case] = (string)$this->arrondisFiscal($rawN1[$case]);
            }
        }

        $this->renderTab('bilan', [
            'computed' => $computed,
            'computed_n1' => $computedN1,
            'raw' => $raw,
            'raw_n1' => $rawN1,
        ]);
    }
    public function tabCompteResultat(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (!empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/2035');
            exit;
        }
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        $computed = $this->computeCompteResultatRaw($entrepriseId, $annee);
        $computedN1 = $this->computeCompteResultatRaw($entrepriseId, $annee - 1);

        $this->renderTab('compte-resultat', [
            'computed' => $computed,
            'computed_n1' => $computedN1,
        ]);
    }
    public function tab2035(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/bilan');
            exit;
        }
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;
        $immobilisations = $this->computeImmobilisations2035($entrepriseId, $annee);
        $this->renderTab('2035', ['immobilisations' => $immobilisations]);
    }

    public function tab2035A(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/bilan');
            exit;
        }
        $this->renderTab('2035-a');
    }

    public function tab2035B(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/bilan');
            exit;
        }
        $this->renderTab('2035-b');
    }

    public function tab2035E(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/bilan');
            exit;
        }
        $this->renderTab('2035-e');
    }

    public function detailCompteResultat(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (!empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/2035');
            exit;
        }
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        // Mapping case → label + filtres SQL sur lignes_comptables
        $cases = [
            '214' => ['label' => 'Production vendue — Biens', 'comptes' => ['701%','702%','703%','704%','705%','709%']],
            '218' => ['label' => 'Production vendue — Services', 'comptes' => ['706%','707%','708%']],
            '242' => ['label' => 'Autres charges externes', 'comptes' => ['61%','62%']],
            '254' => ['label' => 'Dotations aux amortissements', 'source' => 'immobilisations'],
            '280' => ['label' => 'Produits financiers', 'comptes' => ['76%']],
            '290' => ['label' => 'Produits exceptionnels', 'comptes' => ['77%']],
            '294' => ['label' => 'Charges financières', 'comptes' => ['66%']],
            '300' => ['label' => 'Charges exceptionnelles', 'comptes' => ['67%']],
            '374' => ['label' => 'TVA collectée', 'source' => 'tva_collectee'],
            '378' => ['label' => 'TVA déductible sur biens et services', 'source' => 'tva_deductible'],
        ];

        $debut = $annee . '-01-01';
        $fin = $annee . '-12-31';
        $sections = [];

        // Pré-calculer la TVA pour les cases 374/378
        $stmtTva = $this->pdo->prepare(
            "SELECT t.date, t.libelle, lc.compte, lc.tva
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND lc.tva != 0
             ORDER BY t.date, t.id"
        );
        $stmtTva->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $lignesTvaCollectee = [];
        $lignesTvaDeductible = [];
        $totalTvaCollectee = 0.0;
        $totalTvaDeductible = 0.0;
        foreach ($stmtTva->fetchAll(\PDO::FETCH_ASSOC) as $l) {
            $tva = abs((float)$l['tva']);
            $ligne = ['date' => $l['date'], 'libelle' => $l['libelle'], 'compte' => $l['compte'], 'montant' => $tva];
            if (str_starts_with($l['compte'], '70')) {
                $lignesTvaCollectee[] = $ligne;
                $totalTvaCollectee += $tva;
            } else {
                $lignesTvaDeductible[] = $ligne;
                $totalTvaDeductible += $tva;
            }
        }

        foreach ($cases as $caseNum => $def) {
            if (isset($def['source']) && $def['source'] === 'tva_collectee') {
                if (!empty($lignesTvaCollectee)) {
                    $sections[] = [
                        'case' => $caseNum,
                        'label' => $def['label'],
                        'lignes' => $lignesTvaCollectee,
                        'total' => round($totalTvaCollectee, 2),
                    ];
                }
                continue;
            }

            if (isset($def['source']) && $def['source'] === 'tva_deductible') {
                if (!empty($lignesTvaDeductible)) {
                    $sections[] = [
                        'case' => $caseNum,
                        'label' => $def['label'],
                        'lignes' => $lignesTvaDeductible,
                        'total' => round($totalTvaDeductible, 2),
                    ];
                }
                continue;
            }

            if (isset($def['source']) && $def['source'] === 'immobilisations') {
                $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);
                $lignes = [];
                foreach ($immos as $immo) {
                    if ($immo['dotation'] > 0) {
                        $lignes[] = [
                            'date' => $immo['date_acquisition'],
                            'libelle' => $immo['designation'],
                            'compte' => $immo['compte'],
                            'montant' => $immo['dotation'],
                        ];
                    }
                }
                if (!empty($lignes)) {
                    $total = 0;
                    foreach ($lignes as $l) { $total += $l['montant']; }
                    $sections[] = [
                        'case' => $caseNum,
                        'label' => $def['label'],
                        'lignes' => $lignes,
                        'total' => round($total, 2),
                    ];
                }
                continue;
            }

            // Construire le WHERE pour les comptes
            $conditions = [];
            foreach ($def['comptes'] as $i => $pattern) {
                $conditions[] = "lc.compte LIKE :p{$caseNum}_{$i}";
            }
            $where = '(' . implode(' OR ', $conditions) . ')';

            $stmt = $this->pdo->prepare(
                "SELECT t.date, t.libelle, lc.compte, lc.montant_ht AS montant
                 FROM lignes_comptables lc
                 JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
                 JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
                 WHERE cb.entreprise_id = :eid
                   AND t.date >= :debut AND t.date <= :fin
                   AND {$where}
                 ORDER BY t.date, t.id"
            );
            $params = ['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin];
            foreach ($def['comptes'] as $i => $pattern) {
                $params["p{$caseNum}_{$i}"] = $pattern;
            }
            $stmt->execute($params);
            $lignes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($lignes)) {
                $total = 0;
                foreach ($lignes as $l) { $total += (float)$l['montant']; }
                $sections[] = [
                    'case' => $caseNum,
                    'label' => $def['label'],
                    'lignes' => $lignes,
                    'total' => round($total, 2),
                ];
            }
        }

        // ── Totaux du compte de résultat ──
        $computed = $this->computeCompteResultatRaw($entrepriseId, $annee);
        $val = fn(string $k): int => (int)($computed[$k] ?? 0);

        // Total produits exploitation (I) = 214 + 218
        $totalProduitsExpl = $val('214') + $val('218');
        $sections[] = [
            'case' => '232',
            'label' => 'Total des produits d\'exploitation (I)',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '214', 'label' => 'Production vendue — Biens', 'montant' => $val('214')],
                ['case' => '218', 'label' => 'Production vendue — Services', 'montant' => $val('218')],
            ],
            'total' => $totalProduitsExpl,
        ];

        // Total charges exploitation (II) = 242 + 254
        $totalChargesExpl = $val('242') + $val('254');
        $sections[] = [
            'case' => '264',
            'label' => 'Total des charges d\'exploitation (II)',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '242', 'label' => 'Autres charges externes', 'montant' => $val('242')],
                ['case' => '254', 'label' => 'Dotations aux amortissements', 'montant' => $val('254')],
            ],
            'total' => $totalChargesExpl,
        ];

        // Résultat d'exploitation (I – II)
        $resultatExpl = $totalProduitsExpl - $totalChargesExpl;
        $sections[] = [
            'case' => '270',
            'label' => 'Résultat d\'exploitation (I – II)',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '232', 'label' => 'Total produits exploitation (I)', 'montant' => $totalProduitsExpl],
                ['case' => '264', 'label' => 'Total charges exploitation (II)', 'montant' => -$totalChargesExpl],
            ],
            'total' => $resultatExpl,
        ];

        // Bénéfice ou perte = (I + III + IV) – (II + V + VI)
        $benefice = ($totalProduitsExpl + $val('280') + $val('290'))
                  - ($totalChargesExpl + $val('294') + $val('300'));
        $sections[] = [
            'case' => '310',
            'label' => 'Bénéfice ou perte',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '232', 'label' => 'Produits exploitation (I)', 'montant' => $totalProduitsExpl],
                ['case' => '280', 'label' => 'Produits financiers (III)', 'montant' => $val('280')],
                ['case' => '290', 'label' => 'Produits exceptionnels (IV)', 'montant' => $val('290')],
                ['case' => '264', 'label' => 'Charges exploitation (II)', 'montant' => -$totalChargesExpl],
                ['case' => '294', 'label' => 'Charges financières (V)', 'montant' => -$val('294')],
                ['case' => '300', 'label' => 'Charges exceptionnelles (VI)', 'montant' => -$val('300')],
            ],
            'total' => $benefice,
        ];

        echo $this->twig->render('app/cloture/compte-resultat-detail.html.twig', [
            'active_page' => 'cloture',
            'active_tab' => 'compte-resultat',
            'tabs' => $this->getTabsForEntreprise($entreprise),
            'annee' => $annee,
            'annees' => [(int) date('Y') - 1],
            'sections' => $sections,
        ]);
    }

    public function detailBilan(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        if (!empty($entreprise['option_ir'])) {
            header('Location: /app/cloture/2035');
            exit;
        }
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        $debut = $annee . '-01-01';
        $fin = $annee . '-12-31';
        $sections = [];

        // ── Immobilisations par catégorie ──
        $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);

        $categories = [
            '010' => ['label' => 'Fonds commercial', 'filter' => fn($c) => str_starts_with($c, '207')],
            '014' => ['label' => 'Autres immobilisations incorporelles', 'filter' => fn($c) => str_starts_with($c, '20') && !str_starts_with($c, '207')],
            '028' => ['label' => 'Immobilisations corporelles', 'filter' => fn($c) => str_starts_with($c, '21')],
            '040' => ['label' => 'Immobilisations financières', 'filter' => fn($c) => str_starts_with($c, '26') || str_starts_with($c, '27')],
        ];

        foreach ($categories as $caseNum => $def) {
            $lignes = [];
            foreach ($immos as $immo) {
                $compte = $immo['compte'] ?? '218';
                if ($def['filter']($compte)) {
                    $lignes[] = [
                        'date' => $immo['date_acquisition'],
                        'libelle' => $immo['designation'],
                        'compte' => $compte,
                        'brut' => $immo['valeur'],
                        'amort' => $immo['cumul_total'],
                        'net' => $immo['vnc'],
                    ];
                }
            }
            if (!empty($lignes)) {
                $totalBrut = 0;
                $totalAmort = 0;
                $totalNet = 0;
                foreach ($lignes as $l) {
                    $totalBrut += $l['brut'];
                    $totalAmort += $l['amort'];
                    $totalNet += $l['net'];
                }
                $sections[] = [
                    'case' => $caseNum,
                    'label' => $def['label'],
                    'type' => 'immobilisations',
                    'lignes' => $lignes,
                    'total_brut' => round($totalBrut, 2),
                    'total_amort' => round($totalAmort, 2),
                    'total_net' => round($totalNet, 2),
                ];
            }
        }

        // ── Total I — Actif immobilisé (044) ──
        $raw = $this->computeBilanRaw($entrepriseId, $annee);
        $totalIBrut = $this->arrondisFiscal(($raw['010'] ?? 0) + ($raw['014'] ?? 0) + ($raw['028'] ?? 0) + ($raw['040'] ?? 0));
        $totalIAmort = $this->arrondisFiscal(($raw['012'] ?? 0) + ($raw['016'] ?? 0) + ($raw['030'] ?? 0) + ($raw['042'] ?? 0));
        $sections[] = [
            'case' => '044',
            'label' => 'Total I — Actif immobilisé',
            'type' => 'total',
            'lignes' => [
                ['case' => '010', 'label' => 'Fonds commercial', 'brut' => $this->arrondisFiscal($raw['010'] ?? 0), 'amort' => $this->arrondisFiscal($raw['012'] ?? 0)],
                ['case' => '014', 'label' => 'Autres immo. incorporelles', 'brut' => $this->arrondisFiscal($raw['014'] ?? 0), 'amort' => $this->arrondisFiscal($raw['016'] ?? 0)],
                ['case' => '028', 'label' => 'Immo. corporelles', 'brut' => $this->arrondisFiscal($raw['028'] ?? 0), 'amort' => $this->arrondisFiscal($raw['030'] ?? 0)],
                ['case' => '040', 'label' => 'Immo. financières', 'brut' => $this->arrondisFiscal($raw['040'] ?? 0), 'amort' => $this->arrondisFiscal($raw['042'] ?? 0)],
            ],
            'total_brut' => $totalIBrut,
            'total_amort' => $totalIAmort,
            'total_net' => $totalIBrut - $totalIAmort,
        ];

        // ── Disponibilités (084) : solde par compte bancaire ──
        $stmt = $this->pdo->prepare(
            "SELECT cb.nom, cb.iban,
                    COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0) AS solde
             FROM comptes_bancaires cb
             LEFT JOIN transactions_bancaires t ON t.compte_bancaire_id = cb.id AND t.date <= :fin
             WHERE cb.entreprise_id = :eid
             GROUP BY cb.id, cb.nom, cb.iban
             ORDER BY cb.nom"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $fin]);
        $comptesBank = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $lignesBank = [];
        $totalBank = 0;
        foreach ($comptesBank as $cb) {
            $solde = (float)$cb['solde'];
            $lignesBank[] = [
                'libelle' => $cb['nom'],
                'compte' => $cb['iban'] ?? '',
                'montant' => $solde,
            ];
            $totalBank += $solde;
        }
        if (!empty($lignesBank)) {
            $sections[] = [
                'case' => '084',
                'label' => 'Disponibilités',
                'type' => 'comptes',
                'lignes' => $lignesBank,
                'total' => round($totalBank, 2),
            ];
        }

        // ── Total II — Actif circulant (096) ──
        $totalIIBrut = $this->arrondisFiscal($raw['072'] ?? 0) + $this->arrondisFiscal($raw['084'] ?? 0);
        $sections[] = [
            'case' => '096',
            'label' => 'Total II — Actif circulant',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '072', 'label' => 'Autres créances', 'montant' => $this->arrondisFiscal($raw['072'] ?? 0)],
                ['case' => '084', 'label' => 'Disponibilités', 'montant' => $this->arrondisFiscal($raw['084'] ?? 0)],
            ],
            'total' => $totalIIBrut,
        ];

        // ── Total Général — Actif (110) ──
        $sections[] = [
            'case' => '110',
            'label' => 'Total Général — Actif (I + II)',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '044', 'label' => 'Total I — Actif immobilisé (net)', 'montant' => $totalIBrut - $totalIAmort],
                ['case' => '096', 'label' => 'Total II — Actif circulant', 'montant' => $totalIIBrut],
            ],
            'total' => ($totalIBrut - $totalIAmort) + $totalIIBrut,
        ];

        // ── Autres créances (072) : compte courant d'associé 455 débiteur ──
        $stmt = $this->pdo->prepare(
            "SELECT t.date, t.libelle, lc.compte, lc.montant_ht AS montant, lc.type AS sens
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '455%'
               AND t.date <= :fin
             ORDER BY t.date, t.id"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $fin]);
        $lignes455 = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($lignes455)) {
            $solde455 = 0;
            $lignesFormatted = [];
            foreach ($lignes455 as $l) {
                $montant = (float)$l['montant'];
                $signed = $l['sens'] === 'DBT' ? $montant : -$montant;
                $solde455 += $signed;
                $lignesFormatted[] = [
                    'date' => $l['date'],
                    'libelle' => $l['libelle'],
                    'compte' => $l['compte'],
                    'montant' => $signed,
                ];
            }
            if ($solde455 > 0) {
                $sections[] = [
                    'case' => '072',
                    'label' => 'Autres créances — Comptes courants d\'associés',
                    'type' => 'lignes',
                    'lignes' => $lignesFormatted,
                    'total' => round($solde455, 2),
                ];
            } elseif ($solde455 < 0) {
                $sections[] = [
                    'case' => '156',
                    'label' => 'Emprunts et dettes assimilées — Comptes courants d\'associés',
                    'type' => 'lignes',
                    'lignes' => $lignesFormatted,
                    'total' => round(-$solde455, 2),
                ];
            }
        }

        // ── Capital social (120) : augmentations de capital (4561) ──
        $stmt = $this->pdo->prepare(
            "SELECT t.date, t.libelle, lc.compte, lc.montant_ht AS montant
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '4561%'
               AND t.date >= :debut AND t.date <= :fin
             ORDER BY t.date, t.id"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $lignes4561 = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($lignes4561)) {
            $total4561 = 0;
            foreach ($lignes4561 as $l) { $total4561 += (float)$l['montant']; }
            $sections[] = [
                'case' => '120',
                'label' => 'Capital social — Augmentations de l\'exercice',
                'type' => 'lignes',
                'lignes' => $lignes4561,
                'total' => round($total4561, 2),
            ];
        }

        // ── Résultat de l'exercice (136) : produits - charges - dotation ──
        $stmt = $this->pdo->prepare(
            "SELECT
               COALESCE(SUM(CASE WHEN lc.compte LIKE '7%' AND lc.type = 'CRD' THEN lc.montant_ht ELSE 0 END), 0) AS produits,
               COALESCE(SUM(CASE WHEN lc.compte LIKE '6%' AND lc.type = 'DBT' THEN lc.montant_ht ELSE 0 END), 0) AS charges
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $produits = (float)$row['produits'];
        $charges = (float)$row['charges'];
        $dotation = 0.0;
        foreach ($immos as $immo) { $dotation += $immo['dotation']; }
        $dotationArrondie = $this->arrondisFiscal($dotation);
        $resultat = $produits - $charges - $dotationArrondie;

        $sections[] = [
            'case' => '136',
            'label' => 'Résultat de l\'exercice',
            'type' => 'resultat',
            'produits' => round($produits, 2),
            'charges' => round($charges, 2),
            'dotation' => $dotationArrondie,
            'total' => round($resultat, 2),
        ];

        // ── Autres dettes (172) : TVA non encore payée à l'État ──
        $stmtTva = $this->pdo->prepare(
            "SELECT l.compte, l.tva
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND l.tva != 0"
        );
        $stmtTva->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);

        $tvaCollectee = 0.0;
        $tvaDeductible = 0.0;
        foreach ($stmtTva->fetchAll() as $l) {
            $tva = abs((float)$l['tva']);
            if (str_starts_with($l['compte'], '70')) {
                $tvaCollectee += $tva;
            } else {
                $tvaDeductible += $tva;
            }
        }

        // Paiements effectifs = opérations qualifiées sur le compte 44551
        $stmtPaye = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '44551%'
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmtPaye->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $tvaDejaPaye = (float)$stmtPaye->fetchColumn();

        $tvaDue = round($tvaCollectee - $tvaDeductible, 2);
        $tvaRestante = max(0, $tvaDue - $tvaDejaPaye);

        if ($tvaRestante > 0) {
            $sections[] = [
                'case' => '172',
                'label' => 'Autres dettes — TVA restant due',
                'type' => 'resultat',
                'produits' => round($tvaCollectee, 2),
                'charges' => round($tvaDeductible, 2),
                'dotation' => round($tvaDejaPaye, 2),
                'total' => round($tvaRestante, 2),
                'labels' => [
                    'produits' => 'TVA collectée (comptes 70x)',
                    'charges' => 'TVA déductible (autres comptes)',
                    'dotation' => 'Paiements TVA effectifs (compte 44551)',
                    'total' => 'TVA restant due à l\'État',
                ],
            ];
        }

        // ── Total I — Capitaux propres (142) ──
        $capitalSocial = $this->arrondisFiscal($raw['120'] ?? 0);
        $resultatArrondi = $this->arrondisFiscal($raw['136'] ?? 0);
        $totalICp = $capitalSocial + $resultatArrondi;
        $sections[] = [
            'case' => '142',
            'label' => 'Total I — Capitaux propres',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '120', 'label' => 'Capital social', 'montant' => $capitalSocial],
                ['case' => '136', 'label' => 'Résultat de l\'exercice', 'montant' => $resultatArrondi],
            ],
            'total' => $totalICp,
        ];

        // ── Total III — Dettes (176) ──
        $empruntsArrondi = $this->arrondisFiscal($raw['156'] ?? 0);
        $autresDettesArrondi = $this->arrondisFiscal($raw['172'] ?? 0);
        $totalIIIDettes = $empruntsArrondi + $autresDettesArrondi;
        $sections[] = [
            'case' => '176',
            'label' => 'Total III — Dettes',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '156', 'label' => 'Emprunts et dettes assimilées', 'montant' => $empruntsArrondi],
                ['case' => '172', 'label' => 'Autres dettes', 'montant' => $autresDettesArrondi],
            ],
            'total' => $totalIIIDettes,
        ];

        // ── Total Général — Passif (180) ──
        $sections[] = [
            'case' => '180',
            'label' => 'Total Général — Passif (I + II + III)',
            'type' => 'total_simple',
            'lignes' => [
                ['case' => '142', 'label' => 'Total I — Capitaux propres', 'montant' => $totalICp],
                ['case' => '176', 'label' => 'Total III — Dettes', 'montant' => $totalIIIDettes],
            ],
            'total' => $totalICp + $totalIIIDettes,
        ];

        echo $this->twig->render('app/cloture/bilan-detail.html.twig', [
            'active_page' => 'cloture',
            'active_tab' => 'bilan',
            'tabs' => $this->getTabsForEntreprise($entreprise),
            'annee' => $annee,
            'annees' => [(int) date('Y') - 1],
            'sections' => $sections,
        ]);
    }

    public function exportCsvCompteResultat(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        if (($entreprise['regime_benefices'] ?? '') !== 'BNC') {
            header('Location: /app');
            exit;
        }

        $cases = [
            '214' => ['label' => 'Production vendue — Biens', 'comptes' => ['701%','702%','703%','704%','705%','709%']],
            '218' => ['label' => 'Production vendue — Services', 'comptes' => ['706%','707%','708%']],
            '242' => ['label' => 'Autres charges externes', 'comptes' => ['61%','62%']],
            '254' => ['label' => 'Dotations aux amortissements', 'source' => 'immobilisations'],
            '280' => ['label' => 'Produits financiers', 'comptes' => ['76%']],
            '290' => ['label' => 'Produits exceptionnels', 'comptes' => ['77%']],
            '294' => ['label' => 'Charges financières', 'comptes' => ['66%']],
            '300' => ['label' => 'Charges exceptionnelles', 'comptes' => ['67%']],
            '374' => ['label' => 'TVA collectée', 'source' => 'tva_collectee'],
            '378' => ['label' => 'TVA déductible sur biens et services', 'source' => 'tva_deductible'],
        ];

        $debut = $annee . '-01-01';
        $fin = $annee . '-12-31';

        // Pré-calculer TVA pour les cases 374/378
        $stmtTva = $this->pdo->prepare(
            "SELECT t.date, t.libelle, lc.compte, lc.tva
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND lc.tva != 0
             ORDER BY t.date, t.id"
        );
        $stmtTva->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $csvTvaCollectee = [];
        $csvTvaDeductible = [];
        foreach ($stmtTva->fetchAll(\PDO::FETCH_ASSOC) as $l) {
            $tva = abs((float)$l['tva']);
            $row = ['date' => $l['date'], 'libelle' => $l['libelle'], 'compte' => $l['compte'], 'tva' => $tva];
            if (str_starts_with($l['compte'], '70')) {
                $csvTvaCollectee[] = $row;
            } else {
                $csvTvaDeductible[] = $row;
            }
        }

        $filename = 'compte-resultat-detail_' . $annee . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Case', 'Section', 'Date', 'Libellé', 'Compte', 'Montant'], ';');

        foreach ($cases as $caseNum => $def) {
            if (isset($def['source']) && in_array($def['source'], ['tva_collectee', 'tva_deductible'])) {
                $rows = $def['source'] === 'tva_collectee' ? $csvTvaCollectee : $csvTvaDeductible;
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $caseNum, $def['label'],
                        $r['date'], $r['libelle'],
                        $r['compte'], number_format($r['tva'], 2, ',', ''),
                    ], ';');
                }
                continue;
            }

            if (isset($def['source']) && $def['source'] === 'immobilisations') {
                $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);
                foreach ($immos as $immo) {
                    if ($immo['dotation'] > 0) {
                        fputcsv($out, [
                            $caseNum, $def['label'],
                            $immo['date_acquisition'], $immo['designation'],
                            $immo['compte'], number_format($immo['dotation'], 2, ',', ''),
                        ], ';');
                    }
                }
                continue;
            }

            $conditions = [];
            foreach ($def['comptes'] as $i => $pattern) {
                $conditions[] = "lc.compte LIKE :p{$caseNum}_{$i}";
            }
            $where = '(' . implode(' OR ', $conditions) . ')';

            $stmt = $this->pdo->prepare(
                "SELECT t.date, t.libelle, lc.compte, lc.montant_ht AS montant
                 FROM lignes_comptables lc
                 JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
                 JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
                 WHERE cb.entreprise_id = :eid
                   AND t.date >= :debut AND t.date <= :fin
                   AND {$where}
                 ORDER BY t.date, t.id"
            );
            $params = ['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin];
            foreach ($def['comptes'] as $i => $pattern) {
                $params["p{$caseNum}_{$i}"] = $pattern;
            }
            $stmt->execute($params);
            $lignes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($lignes as $l) {
                fputcsv($out, [
                    $caseNum, $def['label'],
                    $l['date'], $l['libelle'],
                    $l['compte'], number_format((float)$l['montant'], 2, ',', ''),
                ], ';');
            }
        }

        fclose($out);
        exit;
    }

    public function exportCsvBilan(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        if (($entreprise['regime_benefices'] ?? '') !== 'BNC') {
            header('Location: /app');
            exit;
        }

        $debut = $annee . '-01-01';
        $fin = $annee . '-12-31';

        $filename = 'bilan-detail_' . $annee . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        // ── Immobilisations ──
        $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);

        $categories = [
            '010' => ['label' => 'Fonds commercial', 'filter' => fn($c) => str_starts_with($c, '207')],
            '014' => ['label' => 'Autres immobilisations incorporelles', 'filter' => fn($c) => str_starts_with($c, '20') && !str_starts_with($c, '207')],
            '028' => ['label' => 'Immobilisations corporelles', 'filter' => fn($c) => str_starts_with($c, '21')],
            '040' => ['label' => 'Immobilisations financières', 'filter' => fn($c) => str_starts_with($c, '26') || str_starts_with($c, '27')],
        ];

        fputcsv($out, ['Case', 'Section', 'Date', 'Libellé', 'Compte', 'Brut', 'Amortissement', 'VNC'], ';');

        foreach ($categories as $caseNum => $def) {
            foreach ($immos as $immo) {
                $compte = $immo['compte'] ?? '218';
                if ($def['filter']($compte)) {
                    fputcsv($out, [
                        $caseNum, $def['label'],
                        $immo['date_acquisition'], $immo['designation'], $compte,
                        number_format($immo['valeur'], 2, ',', ''),
                        number_format($immo['cumul_total'], 2, ',', ''),
                        number_format($immo['vnc'], 2, ',', ''),
                    ], ';');
                }
            }
        }

        // ── Disponibilités ──
        $stmt = $this->pdo->prepare(
            "SELECT cb.nom, cb.iban,
                    COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0) AS solde
             FROM comptes_bancaires cb
             LEFT JOIN transactions_bancaires t ON t.compte_bancaire_id = cb.id AND t.date <= :fin
             WHERE cb.entreprise_id = :eid
             GROUP BY cb.id, cb.nom, cb.iban
             ORDER BY cb.nom"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $fin]);
        $comptesBank = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($comptesBank as $cb) {
            fputcsv($out, [
                '084', 'Disponibilités',
                '', $cb['nom'], $cb['iban'] ?? '',
                number_format((float)$cb['solde'], 2, ',', ''), '', '',
            ], ';');
        }

        // ── Comptes courants d'associés (455) ──
        $stmt = $this->pdo->prepare(
            "SELECT t.date, t.libelle, lc.compte, lc.montant_ht AS montant, lc.type AS sens
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '455%'
               AND t.date <= :fin
             ORDER BY t.date, t.id"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $fin]);
        $lignes455 = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $solde455 = 0;
        foreach ($lignes455 as $l) {
            $montant = (float)$l['montant'];
            $signed = $l['sens'] === 'DBT' ? $montant : -$montant;
            $solde455 += $signed;
            $caseLabel = $solde455 > 0
                ? ['072', 'Autres créances — CC associés']
                : ['156', 'Emprunts — CC associés'];
            fputcsv($out, [
                $caseLabel[0], $caseLabel[1],
                $l['date'], $l['libelle'], $l['compte'],
                number_format($signed, 2, ',', ''), '', '',
            ], ';');
        }

        // ── Capital social augmentations (4561) ──
        $stmt = $this->pdo->prepare(
            "SELECT t.date, t.libelle, lc.compte, lc.montant_ht AS montant
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '4561%'
               AND t.date >= :debut AND t.date <= :fin
             ORDER BY t.date, t.id"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $lignes4561 = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($lignes4561 as $l) {
            fputcsv($out, [
                '120', 'Capital social — Augmentations',
                $l['date'], $l['libelle'], $l['compte'],
                number_format((float)$l['montant'], 2, ',', ''), '', '',
            ], ';');
        }

        // ── Résultat ──
        $stmt = $this->pdo->prepare(
            "SELECT
               COALESCE(SUM(CASE WHEN lc.compte LIKE '7%' AND lc.type = 'CRD' THEN lc.montant_ht ELSE 0 END), 0) AS produits,
               COALESCE(SUM(CASE WHEN lc.compte LIKE '6%' AND lc.type = 'DBT' THEN lc.montant_ht ELSE 0 END), 0) AS charges
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $produits = (float)$row['produits'];
        $charges = (float)$row['charges'];
        $dotation = 0.0;
        foreach ($immos as $immo) { $dotation += $immo['dotation']; }
        $dotationArrondie = $this->arrondisFiscal($dotation);
        $resultat = $produits - $charges - $dotationArrondie;

        fputcsv($out, ['136', 'Résultat de l\'exercice', '', 'Total produits (7x)', '', number_format($produits, 2, ',', ''), '', ''], ';');
        fputcsv($out, ['136', 'Résultat de l\'exercice', '', 'Total charges (6x)', '', number_format(-$charges, 2, ',', ''), '', ''], ';');
        fputcsv($out, ['136', 'Résultat de l\'exercice', '', 'Dotations aux amortissements', '', number_format(-$dotationArrondie, 2, ',', ''), '', ''], ';');
        fputcsv($out, ['136', 'Résultat de l\'exercice', '', 'RÉSULTAT', '', number_format($resultat, 2, ',', ''), '', ''], ';');

        // ── Autres dettes (172) : TVA non payée ──
        $stmtTva = $this->pdo->prepare(
            "SELECT l.compte, l.tva
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND l.tva != 0"
        );
        $stmtTva->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $csvCollectee = 0.0;
        $csvDeductible = 0.0;
        foreach ($stmtTva->fetchAll() as $l) {
            $tva = abs((float)$l['tva']);
            if (str_starts_with($l['compte'], '70')) { $csvCollectee += $tva; } else { $csvDeductible += $tva; }
        }
        $stmtPaye = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '44551%'
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmtPaye->execute(['eid' => $entrepriseId, 'debut' => $debut, 'fin' => $fin]);
        $csvDejaPaye = (float)$stmtPaye->fetchColumn();
        $csvTvaRestante = max(0, round($csvCollectee - $csvDeductible, 2) - $csvDejaPaye);

        fputcsv($out, ['172', 'Autres dettes — TVA restant due', '', 'TVA collectée', '', number_format($csvCollectee, 2, ',', ''), '', ''], ';');
        fputcsv($out, ['172', 'Autres dettes — TVA restant due', '', 'TVA déductible', '', number_format(-$csvDeductible, 2, ',', ''), '', ''], ';');
        fputcsv($out, ['172', 'Autres dettes — TVA restant due', '', 'Paiements TVA (44551)', '', number_format(-$csvDejaPaye, 2, ',', ''), '', ''], ';');
        fputcsv($out, ['172', 'Autres dettes — TVA restant due', '', 'TVA RESTANT DUE', '', number_format($csvTvaRestante, 2, ',', ''), '', ''], ';');

        fclose($out);
        exit;
    }

    public function save(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $annee = (int) ($_POST['annee'] ?? date('Y') - 1);
        $tab = $_POST['tab'] ?? '';
        $fields = $_POST['fields'] ?? [];

        $column = self::TAB_COLUMN[$tab] ?? null;
        if (!$column) {
            header('Location: /app/cloture?annee=' . $annee);
            exit;
        }

        $this->getOrCreateDeclaration($entrepriseId, $annee);

        $json = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            "UPDATE declarations_2035 SET {$column} = :data, updated_at = NOW()
             WHERE entreprise_id = :eid AND annee = :annee"
        );
        $stmt->execute(['data' => $json, 'eid' => $entrepriseId, 'annee' => $annee]);

        header('Location: /app/cloture/' . $tab . '?annee=' . $annee . '&success=1');
        exit;
    }

    private function renderTab(string $slug, array $extraData = []): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y') - 1;

        $tabs = $this->getTabsForEntreprise($entreprise);

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT EXTRACT(YEAR FROM t.date)::int AS annee
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
             ORDER BY annee DESC"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        $annees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (empty($annees)) {
            $annees = [(int) date('Y') - 1];
        }

        $dataColumn = self::TAB_COLUMN[$slug];
        $declaration = $this->getOrCreateDeclaration($entrepriseId, $annee);

        // Réinitialiser : vider les données sauvegardées pour recalculer
        if (isset($_GET['reset'])) {
            $stmt = $this->pdo->prepare(
                "UPDATE declarations_2035 SET {$dataColumn} = '{}', updated_at = NOW()
                 WHERE entreprise_id = :eid AND annee = :annee"
            );
            $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);
            $savedData = [];
        } else {
            $savedData = json_decode($declaration[$dataColumn] ?? '{}', true) ?: [];
        }

        echo $this->twig->render('app/cloture/' . $slug . '.html.twig', array_merge([
            'active_page' => 'cloture',
            'active_tab' => $slug,
            'tabs' => $this->getTabsForEntreprise($entreprise),
            'annee' => $annee,
            'annees' => $annees,
            'entreprise_data' => $entreprise,
            'declaration' => $declaration,
            'data' => $savedData,
            'success' => isset($_GET['success']),
        ], $extraData));
    }

    private function computeCompteResultatRaw(int $entrepriseId, int $annee): array
    {
        $result = [];

        // Dotations aux amortissements depuis les immobilisations
        $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);
        $dotation = 0.0;
        foreach ($immos as $immo) {
            $dotation += $immo['dotation'];
        }
        if ($dotation != 0) {
            $result['254'] = (string)$this->arrondisFiscal($dotation);
        }

        // Production vendue depuis les lignes comptables
        $stmt = $this->pdo->prepare(
            "SELECT lc.compte, SUM(lc.montant_ht) AS total
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND lc.compte LIKE '70%'
             GROUP BY lc.compte"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $comptes70 = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $biens = 0.0;    // 701-705, 709
        $services = 0.0;  // 706-708
        foreach ($comptes70 as $compte => $montant) {
            $p3 = substr((string)$compte, 0, 3);
            if (in_array($p3, ['706', '707', '708'])) {
                $services += (float)$montant;
            } elseif ($p3 >= '701' && $p3 <= '705' || $p3 === '709') {
                $biens += (float)$montant;
            }
        }

        if ($biens != 0) {
            $result['214'] = (string)$this->arrondisFiscal($biens);
        }
        if ($services != 0) {
            $result['218'] = (string)$this->arrondisFiscal($services);
        }

        // Autres charges externes (comptes 61 et 62)
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND (lc.compte LIKE '61%' OR lc.compte LIKE '62%')"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $chargesExternes = (float)$stmt->fetchColumn();
        if ($chargesExternes != 0) {
            $result['242'] = (string)$this->arrondisFiscal($chargesExternes);
        }

        // Produits financiers (comptes 76), Produits exceptionnels (comptes 77)
        // Charges financières (comptes 66), Charges exceptionnelles (comptes 67)
        $mappingDivers = [
            '280' => ['76%'],  // Produits financiers
            '290' => ['77%'],  // Produits exceptionnels
            '294' => ['66%'],  // Charges financières
            '300' => ['67%'],  // Charges exceptionnelles
        ];
        foreach ($mappingDivers as $case => $patterns) {
            $conditions = [];
            foreach ($patterns as $i => $p) {
                $conditions[] = "lc.compte LIKE :pd{$case}_{$i}";
            }
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(lc.montant_ht), 0)
                 FROM lignes_comptables lc
                 JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
                 JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
                 WHERE cb.entreprise_id = :eid
                   AND t.date >= :debut AND t.date <= :fin
                   AND (" . implode(' OR ', $conditions) . ")"
            );
            $params = ['eid' => $entrepriseId, 'debut' => $annee . '-01-01', 'fin' => $annee . '-12-31'];
            foreach ($patterns as $i => $p) {
                $params["pd{$case}_{$i}"] = $p;
            }
            $stmt->execute($params);
            $val = (float)$stmt->fetchColumn();
            if ($val != 0) {
                $result[$case] = (string)$this->arrondisFiscal($val);
            }
        }

        // TVA collectée (374) et TVA déductible (378)
        $stmt = $this->pdo->prepare(
            "SELECT l.compte, l.tva
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND l.tva != 0"
        );
        $stmt->execute(['eid' => $entrepriseId, 'debut' => $annee . '-01-01', 'fin' => $annee . '-12-31']);

        $tvaCollectee = 0.0;
        $tvaDeductible = 0.0;
        foreach ($stmt->fetchAll() as $l) {
            $tva = abs((float)$l['tva']);
            if (str_starts_with($l['compte'], '70')) {
                $tvaCollectee += $tva;
            } else {
                $tvaDeductible += $tva;
            }
        }
        if ($tvaCollectee != 0) {
            $result['374'] = (string)$this->arrondisFiscal($tvaCollectee);
        }
        if ($tvaDeductible != 0) {
            $result['378'] = (string)$this->arrondisFiscal($tvaDeductible);
        }

        return $result;
    }

    private function computeImmobilisations2035(int $entrepriseId, int $annee): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM immobilisations WHERE entreprise_id = :eid ORDER BY date_acquisition ASC"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $immo) {
            $valeur = (float) $immo['valeur_acquisition'];
            $duree = (int) $immo['duree_amortissement'];
            $type = $immo['type_amortissement'];
            $dateRef = $immo['date_mise_en_service'] ?? $immo['date_acquisition'];
            $coeff = (float) ($immo['coeff_degressif'] ?? 1.25);

            if ($duree <= 0 || $valeur <= 0) {
                continue;
            }

            // Cession avant ou pendant l'année → exclure si avant
            if ($immo['cession_date'] && substr($immo['cession_date'], 0, 4) < (string) $annee) {
                continue;
            }

            $debut = new \DateTime($dateRef);
            $moisDebut = (int) $debut->format('n');
            $jourDebut = min((int) $debut->format('j'), 30);
            $anneeDebut = (int) $debut->format('Y');

            $tauxAnnuel = round(100 / $duree, 2);
            $tauxEffectif = $tauxAnnuel;
            $mode = 'L';

            if ($type === 'degressif') {
                $tauxEffectif = round($tauxAnnuel * $coeff, 2);
                $mode = 'D';
            }

            // Calcul annuité par annuité jusqu'à $annee
            $cumul = 0;
            $annuiteAnnee = 0;
            $vnc = $valeur;

            if ($type === 'degressif') {
                $tauxDeg = (1 / $duree) * $coeff;
                $joursProrata = (12 - $moisDebut) * 30 + (30 - $jourDebut);
                $prorata1 = $joursProrata / 360;
                $dureeRestante = $duree;

                for ($a = $anneeDebut; $a <= $annee && $vnc > 0; $a++) {
                    $tauxLinRestant = $dureeRestante > 0 ? 1 / $dureeRestante : 1;
                    $taux = max($tauxDeg, $tauxLinRestant);
                    $dot = round($vnc * $taux, 2);
                    if ($a === $anneeDebut) {
                        $dot = round($vnc * $tauxDeg * $prorata1, 2);
                    }
                    $dot = min($dot, $vnc);
                    if ($a === $annee) {
                        $annuiteAnnee = $dot;
                    }
                    $cumul += $dot;
                    $vnc = round($valeur - $cumul, 2);
                    $dureeRestante--;
                }
            } else {
                $annuiteBase = round($valeur / $duree, 2);
                $joursProrata = (12 - $moisDebut) * 30 + (30 - $jourDebut);
                $prorata1 = $joursProrata / 360;

                for ($a = $anneeDebut; $a <= $annee && $cumul < $valeur; $a++) {
                    $dot = $annuiteBase;
                    if ($a === $anneeDebut) {
                        $dot = round($annuiteBase * $prorata1, 2);
                    }
                    $dot = min($dot, round($valeur - $cumul, 2));
                    if ($a === $annee) {
                        $annuiteAnnee = $dot;
                    }
                    $cumul += $dot;
                }
            }

            $cumulAnterieurs = round($cumul - $annuiteAnnee, 2);

            $montantTtc = $immo['montant_ttc'] !== null ? (float) $immo['montant_ttc'] : $valeur;
            $tvaDeduite = round($montantTtc - $valeur, 2);

            $result[] = [
                'nature' => $immo['nature'] ?? $immo['designation'],
                'designation' => $immo['designation'],
                'date_acquisition' => $immo['date_acquisition'],
                'prix_ttc' => $montantTtc,
                'tva_deduite' => $tvaDeduite,
                'base_amortissable' => $valeur,
                'mode' => $mode,
                'taux' => $tauxEffectif,
                'amort_anterieurs' => $cumulAnterieurs,
                'annuite' => $annuiteAnnee,
            ];
        }

        return $result;
    }

    private function computeBilanRaw(int $entrepriseId, int $annee): array
    {
        $immos = $this->getImmobilisationsAvecAmortissement($entrepriseId, $annee);

        $fc = ['brut' => 0.0, 'amort' => 0.0]; // Fonds commercial (207)
        $ai = ['brut' => 0.0, 'amort' => 0.0]; // Autres incorporelles (20x sauf 207)
        $co = ['brut' => 0.0, 'amort' => 0.0]; // Corporelles (21x)
        $fi = ['brut' => 0.0, 'amort' => 0.0]; // Financières (26x, 27x)
        $dotationAnnee = 0.0;

        foreach ($immos as $immo) {
            $compte = $immo['compte'] ?? '218';
            $p2 = substr($compte, 0, 2);
            $p3 = substr($compte, 0, 3);

            if ($p3 === '207') {
                $g = &$fc;
            } elseif ($p2 === '20') {
                $g = &$ai;
            } elseif (in_array($p2, ['26', '27'])) {
                $g = &$fi;
            } else {
                $g = &$co;
            }

            $g['brut'] += $immo['valeur'];
            $g['amort'] += $immo['cumul_total'];
            $dotationAnnee += $immo['dotation'];
            unset($g);
        }

        // Disponibilités = solde bancaire au 31/12
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0)
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid AND t.date <= :fin"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $annee . '-12-31']);
        $dispo = (float)$stmt->fetchColumn();

        // Capital social = capital N-1 + augmentations de capital (compte 4561) de l'année N
        $capitalN1 = 0.0;
        try {
            $stmtDecl = $this->pdo->prepare(
                "SELECT data_bilan FROM declarations_2035
                 WHERE entreprise_id = :eid AND annee = :annee"
            );
            $stmtDecl->execute(['eid' => $entrepriseId, 'annee' => $annee - 1]);
            $declN1 = $stmtDecl->fetch();
            if ($declN1 && !empty($declN1['data_bilan'])) {
                $bilanN1 = json_decode($declN1['data_bilan'], true) ?: [];
                $capitalN1 = (float)($bilanN1['120'] ?? 0);
            }
        } catch (\PDOException $e) {
            // Colonne data_bilan absente si migration 0013 non exécutée
        }

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '4561%'
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $augmentationCapital = (float)$stmt->fetchColumn();
        $capitalSocial = $capitalN1 + $augmentationCapital;

        // Compte courant d'associé (455) : solde = débits - crédits
        $stmt = $this->pdo->prepare(
            "SELECT
               COALESCE(SUM(CASE WHEN lc.type = 'DBT' THEN lc.montant_ht ELSE 0 END), 0)
             - COALESCE(SUM(CASE WHEN lc.type = 'CRD' THEN lc.montant_ht ELSE 0 END), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '455%'
               AND t.date <= :fin"
        );
        $stmt->execute(['eid' => $entrepriseId, 'fin' => $annee . '-12-31']);
        $solde455 = (float)$stmt->fetchColumn();

        // Solde débiteur → autres créances (072) + renvoi comptes courants débiteurs (199)
        // Solde créditeur → emprunts et dettes assimilées (156)
        $autresCreances = $solde455 > 0 ? $solde455 : 0.0;
        $emprunts = $solde455 < 0 ? -$solde455 : 0.0;

        // Autres dettes (172) = TVA non encore payée à l'État au 31/12
        // TVA due annuelle (collectée - déductible) - acomptes payés avant le 31/12
        $autresDettes = $this->calculerTvaNonPayee($entrepriseId, $annee);

        // Résultat de l'exercice = produits (comptes 7x CRD) - charges (comptes 6x DBT)
        $stmt = $this->pdo->prepare(
            "SELECT
               COALESCE(SUM(CASE WHEN lc.compte LIKE '7%' AND lc.type = 'CRD' THEN lc.montant_ht ELSE 0 END), 0)
             - COALESCE(SUM(CASE WHEN lc.compte LIKE '6%' AND lc.type = 'DBT' THEN lc.montant_ht ELSE 0 END), 0)
             AS resultat
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $resultat = (float)$stmt->fetchColumn();

        // Soustraire la dotation aux amortissements (charge non enregistrée en ligne comptable)
        $resultat -= $this->arrondisFiscal($dotationAnnee);

        return [
            '010' => $fc['brut'],  '012' => $fc['amort'],
            '014' => $ai['brut'],  '016' => $ai['amort'],
            '028' => $co['brut'],  '030' => $co['amort'],
            '040' => $fi['brut'],  '042' => $fi['amort'],
            '072' => $autresCreances,
            '084' => $dispo,
            '120' => $capitalSocial,
            '136' => $resultat,
            '156' => $emprunts,
            '172' => $autresDettes,
            '199' => $autresCreances,
        ];
    }

    private function getImmobilisationsAvecAmortissement(int $entrepriseId, int $annee): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM immobilisations WHERE entreprise_id = :eid ORDER BY date_acquisition"
        );
        $stmt->execute(['eid' => $entrepriseId]);
        $immos = $stmt->fetchAll();

        $finAnnee = $annee . '-12-31';
        $result = [];

        foreach ($immos as $immo) {
            $dateAcq = $immo['date_mise_en_service'] ?? $immo['date_acquisition'];
            $anneeAcq = (int) (new \DateTime($dateAcq))->format('Y');

            // Ignorer les immos acquises après l'année
            if ($anneeAcq > $annee) {
                continue;
            }

            // Ignorer les immos cédées avant l'année
            if ($immo['cession_date'] && (new \DateTime($immo['cession_date']))->format('Y') < $annee) {
                continue;
            }

            $valeur = (float) $immo['valeur_acquisition'];
            $duree = (int) $immo['duree_amortissement'];
            $debutDate = new \DateTime($dateAcq);
            $moisDebut = (int) $debutDate->format('n');
            $jourDebut = min((int) $debutDate->format('j'), 30);

            // Calcul amortissement cumulé fin N-1 et dotation N (convention 30/360)
            $annuiteLineaire = $duree > 0 ? round($valeur / $duree, 2) : 0;
            $joursProrata1 = (12 - $moisDebut) * 30 + (30 - $jourDebut);
            $prorata1 = $joursProrata1 / 360;

            $cumulFinN1 = 0;
            $dotationN = 0;

            if ($immo['type_amortissement'] === 'degressif') {
                $coeff = (float) ($immo['coeff_degressif'] ?? 1.25);
                $tauxDeg = (1 / $duree) * $coeff;
                $anneeFin = $anneeAcq + $duree - 1;
                $vnc = $valeur;
                $dureeRestante = $duree;

                for ($a = $anneeAcq; $a <= min($annee, $anneeFin) && $vnc > 0; $a++) {
                    $tauxLinRestant = $dureeRestante > 0 ? 1 / $dureeRestante : 1;
                    $taux = max($tauxDeg, $tauxLinRestant);
                    $dot = round($vnc * $taux, 2);

                    if ($a === $anneeAcq) {
                        $dot = round($vnc * $tauxDeg * $prorata1, 2);
                    }

                    $dot = min($dot, $vnc);

                    if ($a < $annee) {
                        $cumulFinN1 += $dot;
                    } else {
                        $dotationN = $dot;
                    }
                    $vnc = round($valeur - $cumulFinN1 - ($a >= $annee ? $dotationN : 0), 2);
                    $dureeRestante--;
                }
            } else {
                // Linéaire
                for ($a = $anneeAcq; $a <= $annee; $a++) {
                    $dot = $annuiteLineaire;
                    if ($a === $anneeAcq) {
                        $dot = round($annuiteLineaire * $prorata1, 2);
                    }
                    $restant = $valeur - $cumulFinN1 - ($a >= $annee ? $dotationN : 0);
                    $dot = min($dot, max(0, $valeur - $cumulFinN1));

                    if ($a < $annee) {
                        $cumulFinN1 += $dot;
                    } else {
                        $dotationN = $dot;
                    }
                }
            }

            $cumulFinN1 = min($cumulFinN1, $valeur);
            $dotationN = min($dotationN, $valeur - $cumulFinN1);
            $cumulFinN = $cumulFinN1 + $dotationN;
            $vnc = round($valeur - $cumulFinN, 2);

            $result[] = [
                'designation' => $immo['designation'],
                'date_acquisition' => $immo['date_acquisition'],
                'date_mise_en_service' => $immo['date_mise_en_service'],
                'valeur' => $valeur,
                'duree' => $duree,
                'type' => $immo['type_amortissement'],
                'compte' => $immo['compte'],
                'cumul_anterieur' => round($cumulFinN1, 2),
                'dotation' => round($dotationN, 2),
                'cumul_total' => round($cumulFinN, 2),
                'vnc' => max(0, $vnc),
                'cession_date' => $immo['cession_date'],
                'cession_montant' => $immo['cession_montant'],
            ];
        }

        return $result;
    }

    /**
     * TVA non encore payée à l'État au 31/12 de l'année.
     * = TVA collectée - TVA déductible - paiements effectifs (compte 44551 sorti du compte bancaire).
     */
    private function calculerTvaNonPayee(int $entrepriseId, int $annee): float
    {
        // TVA collectée et déductible sur l'année depuis les lignes comptables
        $stmt = $this->pdo->prepare(
            "SELECT l.compte, l.tva
             FROM lignes_comptables l
             JOIN transactions_bancaires t ON t.id = l.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND t.date >= :debut AND t.date <= :fin
               AND l.tva != 0"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);

        $collectee = 0.0;
        $deductible = 0.0;
        foreach ($stmt->fetchAll() as $l) {
            $tva = abs((float)$l['tva']);
            if (str_starts_with($l['compte'], '70')) {
                $collectee += $tva;
            } else {
                $deductible += $tva;
            }
        }

        $tvaDue = round($collectee - $deductible, 2);

        // Paiements effectifs de TVA à l'État = opérations qualifiées sur le compte 44551
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(lc.montant_ht), 0)
             FROM lignes_comptables lc
             JOIN transactions_bancaires t ON t.id = lc.transaction_bancaire_id
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :eid
               AND lc.compte LIKE '44551%'
               AND t.date >= :debut AND t.date <= :fin"
        );
        $stmt->execute([
            'eid' => $entrepriseId,
            'debut' => $annee . '-01-01',
            'fin' => $annee . '-12-31',
        ]);
        $dejaPaye = (float)$stmt->fetchColumn();

        return max(0, $tvaDue - $dejaPaye);
    }

    private function getOrCreateDeclaration(int $entrepriseId, int $annee): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO declarations_2035 (entreprise_id, annee) VALUES (:eid, :annee)
             ON CONFLICT (entreprise_id, annee) DO NOTHING"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);

        $stmt = $this->pdo->prepare(
            "SELECT * FROM declarations_2035 WHERE entreprise_id = :eid AND annee = :annee"
        );
        $stmt->execute(['eid' => $entrepriseId, 'annee' => $annee]);
        return $stmt->fetch() ?: [];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Middleware\AuthMiddleware;
use App\Repository\EntrepriseRepository;
use App\Repository\LigneComptableRepository;
use App\Repository\PlanComptablePersoRepository;
use App\Repository\PlanComptablePrefRepository;
use App\Repository\PlanComptableRepository;
use App\Repository\PlanComptableSimplifieRepository;
use App\Service\PlanComptableEffectifService;
use PDO;
use Twig\Environment;

class ParametrePlanComptableController
{
    private PlanComptableEffectifService $planEffectif;
    private PlanComptablePersoRepository $persoRepo;
    private PlanComptablePrefRepository $prefRepo;
    private EntrepriseRepository $entrepriseRepo;

    public function __construct(
        private Environment $twig,
        private PDO $pdo,
        private AuthMiddleware $auth,
    ) {
        $this->persoRepo = new PlanComptablePersoRepository($pdo);
        $this->prefRepo = new PlanComptablePrefRepository($pdo);
        $this->entrepriseRepo = new EntrepriseRepository($pdo);
        $this->planEffectif = new PlanComptableEffectifService(
            new PlanComptableRepository($pdo),
            new PlanComptableSimplifieRepository($pdo),
            $this->persoRepo,
            $this->prefRepo,
            $this->entrepriseRepo,
            new LigneComptableRepository($pdo),
        );
    }

    public function index(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $rows = $this->planEffectif->findAllForConfig($entrepriseId);

        // Filtres
        $q = trim((string) ($_GET['q'] ?? ''));
        $sens = $_GET['sens'] ?? '';
        $origine = $_GET['origine'] ?? '';
        $visible = $_GET['visible'] ?? '';

        $filtered = array_filter($rows, function ($r) use ($q, $sens, $origine, $visible, $entreprise) {
            if ($q !== '') {
                $needle = mb_strtolower($q);
                if (stripos($r['numero'], $q) !== 0
                    && mb_stripos((string) $r['libelle_effectif'], $needle) === false) {
                    return false;
                }
            }
            if (in_array($sens, ['D', 'C'], true) && $r['sens'] !== $sens) {
                return false;
            }
            if (in_array($origine, ['base', 'perso', 'pref', 'used'], true) && $r['origine'] !== $origine) {
                return false;
            }
            $effective = $r['pref_enabled'] ?? $r['enabled_default'];
            if ($visible === 'on' && !$effective) return false;
            if ($visible === 'off' && $effective) return false;
            return true;
        });

        echo $this->twig->render('app/parametres/plan-comptable.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'plan-comptable',
            'entreprise' => $entreprise,
            'rows' => array_values($filtered),
            'total' => count($rows),
            'filtres' => compact('q', 'sens', 'origine', 'visible'),
            'success' => $_GET['success'] ?? null,
        ]);
    }

    public function toggleEnabled(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $numero = trim((string) ($_POST['numero'] ?? ''));
        $enabled = $_POST['enabled'] ?? '';

        if (!preg_match('/^\d{2,10}$/', $numero)) {
            http_response_code(400);
            return;
        }

        $current = $this->prefRepo->findAllByEntreprise($entrepriseId)[$numero] ?? null;
        $newEnabled = $enabled === '' ? null : ($enabled === '1');

        $this->prefRepo->upsert(
            $entrepriseId,
            $numero,
            $newEnabled,
            $current['libelle_source'] ?? null,
            $current['libelle_perso'] ?? null,
        );

        http_response_code(204);
    }

    public function editPref(int $numero): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $rows = $this->planEffectif->findAllForConfig($entrepriseId);
        $row = null;
        foreach ($rows as $r) {
            if ($r['numero'] === (string) $numero) { $row = $r; break; }
        }
        if (!$row) {
            header('Location: /app/parametres/plan-comptable');
            exit;
        }

        echo $this->twig->render('app/parametres/plan-comptable-pref.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'plan-comptable',
            'row' => $row,
        ]);
    }

    public function updatePref(int $numero): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $numeroStr = (string) $numero;

        $source = $_POST['libelle_source'] ?? '';
        $libellePerso = trim((string) ($_POST['libelle_perso'] ?? ''));
        $source = in_array($source, ['simplifie', 'general', 'personnalise'], true) ? $source : null;
        $libellePerso = $source === 'personnalise' ? ($libellePerso !== '' ? $libellePerso : null) : null;

        // Invalide si source = personnalise mais libelle_perso vide
        if ($source === 'personnalise' && $libellePerso === null) {
            $_SESSION['error'] = 'Un libellé personnalisé est requis.';
            header('Location: /app/parametres/plan-comptable/' . $numero . '/edit');
            exit;
        }

        // Préserver enabled
        $current = $this->prefRepo->findAllByEntreprise($entrepriseId)[$numeroStr] ?? null;
        $this->prefRepo->upsert(
            $entrepriseId,
            $numeroStr,
            $current['enabled'] ?? null,
            $source,
            $libellePerso,
        );

        header('Location: /app/parametres/plan-comptable?success=updated');
        exit;
    }

    // --- Comptes personnalisés ---

    public function createPerso(): void
    {
        echo $this->twig->render('app/parametres/plan-comptable-perso.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'plan-comptable',
            'compte' => null,
        ]);
    }

    public function storePerso(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $data = $this->sanitizePersoPost($_POST);
        $error = $this->validatePerso($data);
        if ($error) {
            $_SESSION['error'] = $error;
            header('Location: /app/parametres/plan-comptable/perso/create');
            exit;
        }
        if ($this->persoRepo->findByNumero($entrepriseId, $data['numero']) !== null) {
            $_SESSION['error'] = "Le compte {$data['numero']} existe déjà dans vos comptes personnalisés.";
            header('Location: /app/parametres/plan-comptable/perso/create');
            exit;
        }
        $this->persoRepo->create($entrepriseId, $data['numero'], $data['libelle'], $data['sens'], $data['categorie']);
        header('Location: /app/parametres/plan-comptable?success=perso_created');
        exit;
    }

    public function editPerso(int $numero): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $compte = $this->persoRepo->findByNumero($entrepriseId, (string) $numero);
        if (!$compte) {
            header('Location: /app/parametres/plan-comptable');
            exit;
        }
        echo $this->twig->render('app/parametres/plan-comptable-perso.html.twig', [
            'active_page' => 'parametres',
            'active_tab' => 'plan-comptable',
            'compte' => $compte,
        ]);
    }

    public function updatePerso(int $numero): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $numeroStr = (string) $numero;
        $data = $this->sanitizePersoPost($_POST);
        $data['numero'] = $numeroStr;
        $error = $this->validatePerso($data, skipNumero: true);
        if ($error) {
            $_SESSION['error'] = $error;
            header('Location: /app/parametres/plan-comptable/perso/' . $numero . '/edit');
            exit;
        }
        if (!$this->persoRepo->findByNumero($entrepriseId, $numeroStr)) {
            header('Location: /app/parametres/plan-comptable');
            exit;
        }
        $this->persoRepo->update($entrepriseId, $numeroStr, $data['libelle'], $data['sens'], $data['categorie']);
        header('Location: /app/parametres/plan-comptable?success=perso_updated');
        exit;
    }

    public function deletePerso(int $numero): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $this->persoRepo->delete($entrepriseId, (string) $numero);
        header('Location: /app/parametres/plan-comptable?success=perso_deleted');
        exit;
    }

    public function toggleAutoInclude(): void
    {
        $entrepriseId = $this->auth->getEntrepriseId();
        $enabled = !empty($_POST['enabled']);
        $this->pdo->prepare(
            "UPDATE entreprises SET plan_auto_include_used = :v, updated_at = now() WHERE id = :id"
        )->execute(['v' => $enabled ? 't' : 'f', 'id' => $entrepriseId]);
        header('Location: /app/parametres/plan-comptable?success=auto_include');
        exit;
    }

    private function sanitizePersoPost(array $post): array
    {
        return [
            'numero' => trim((string) ($post['numero'] ?? '')),
            'libelle' => trim((string) ($post['libelle'] ?? '')),
            'sens' => $post['sens'] ?? '',
            'categorie' => trim((string) ($post['categorie'] ?? '')) ?: null,
        ];
    }

    private function validatePerso(array $data, bool $skipNumero = false): ?string
    {
        if (!$skipNumero && !preg_match('/^\d{2,10}$/', $data['numero'])) {
            return 'Le numéro doit contenir 2 à 10 chiffres.';
        }
        if ($data['libelle'] === '') {
            return 'Le libellé est requis.';
        }
        if (!in_array($data['sens'], ['D', 'C'], true)) {
            return 'Le sens doit être Débit ou Crédit.';
        }
        return null;
    }
}

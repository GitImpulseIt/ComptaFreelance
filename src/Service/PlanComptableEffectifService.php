<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\EntrepriseRepository;
use App\Repository\LigneComptableRepository;
use App\Repository\PlanComptablePersoRepository;
use App\Repository\PlanComptablePrefRepository;
use App\Repository\PlanComptableRepository;
use App\Repository\PlanComptableSimplifieRepository;

/**
 * Résout le plan comptable effectif d'une entreprise en combinant :
 *   • le plan actif (simplifié ou général) avec les préférences (enabled, libelle_source)
 *   • les comptes personnalisés créés par l'utilisateur
 *   • les préférences qui force-incluent un compte hors plan actif
 *   • les comptes utilisés en lignes comptables (si option auto_include_used)
 */
class PlanComptableEffectifService
{
    public function __construct(
        private PlanComptableRepository $planRepo,
        private PlanComptableSimplifieRepository $simpRepo,
        private PlanComptablePersoRepository $persoRepo,
        private PlanComptablePrefRepository $prefRepo,
        private EntrepriseRepository $entrepriseRepo,
        private LigneComptableRepository $ligneRepo,
    ) {}

    /**
     * Liste prête pour l'autocomplete.
     *
     * @return list<array{numero:string,libelle:string,sens:?string}>
     */
    public function findSelectable(int $entrepriseId): array
    {
        $ctx = $this->buildContext($entrepriseId);

        $result = [];
        $seen = [];

        // Base plan
        foreach ($ctx['base'] as $e) {
            $pref = $ctx['prefs'][$e['numero']] ?? null;
            if (($pref['enabled'] ?? true) === false) continue;
            $libelle = $this->applyLabelSource($e['numero'], $e['libelle'], $pref, $ctx['simpMap'], $ctx['genMap']);
            $result[] = ['numero' => $e['numero'], 'libelle' => $libelle, 'sens' => $e['sens']];
            $seen[$e['numero']] = true;
        }

        // Perso
        foreach ($ctx['perso'] as $p) {
            if (isset($seen[$p['numero']])) continue;
            $pref = $ctx['prefs'][$p['numero']] ?? null;
            if (($pref['enabled'] ?? true) === false) continue;
            $libelle = $this->applyLabelSource($p['numero'], $p['libelle'], $pref, $ctx['simpMap'], $ctx['genMap']);
            $result[] = ['numero' => $p['numero'], 'libelle' => $libelle, 'sens' => $p['sens']];
            $seen[$p['numero']] = true;
        }

        // Prefs force-enabled (compte hors plan actif mais utilisateur le veut)
        foreach ($ctx['prefs'] as $num => $pref) {
            $num = (string) $num;
            if (isset($seen[$num]) || $pref['enabled'] !== true) continue;
            $libelle = $this->applyLabelSource($num, '', $pref, $ctx['simpMap'], $ctx['genMap']);
            if ($libelle === '') $libelle = $this->parentLookup($num, $ctx['genMap']);
            $result[] = ['numero' => $num, 'libelle' => $libelle, 'sens' => $this->deriveSens($num, $ctx['simpBySN'])];
            $seen[$num] = true;
        }

        // Auto-include used
        if ($ctx['autoInclude']) {
            $used = $this->ligneRepo->findDistinctComptesByEntreprise($entrepriseId);
            foreach ($used as $num) {
                $num = (string) $num;
                if (isset($seen[$num])) continue;
                $pref = $ctx['prefs'][$num] ?? null;
                if (($pref['enabled'] ?? true) === false) continue;
                $libelle = $this->applyLabelSource($num, '', $pref, $ctx['simpMap'], $ctx['genMap']);
                if ($libelle === '') $libelle = $this->parentLookup($num, $ctx['genMap']);
                $result[] = ['numero' => $num, 'libelle' => $libelle, 'sens' => $this->deriveSens($num, $ctx['simpBySN'])];
                $seen[$num] = true;
            }
        }

        usort($result, fn($a, $b) => strcmp($a['numero'], $b['numero']));
        return $result;
    }

    /**
     * Tous les comptes pertinents pour la page de configuration (même désactivés).
     * Chaque entrée contient les métadonnées utiles au rendu : libellés disponibles,
     * source effective, état enabled, origine.
     *
     * @return list<array{
     *   numero:string, libelle_effectif:string, sens:?string, origine:string,
     *   libelles:array{simplifie:?string,general:?string,perso:?string},
     *   pref_enabled:?bool, pref_libelle_source:?string, pref_libelle_perso:?string,
     *   enabled_default:bool, is_used:bool
     * }>
     */
    public function findAllForConfig(int $entrepriseId): array
    {
        $ctx = $this->buildContext($entrepriseId);
        $used = array_flip($this->ligneRepo->findDistinctComptesByEntreprise($entrepriseId));

        // Rassembler tous les numeros candidats :
        $numeros = [];
        foreach ($ctx['base'] as $e) $numeros[$e['numero']] = 'base';
        foreach ($ctx['perso'] as $p) $numeros[$p['numero']] = 'perso';
        foreach ($ctx['prefs'] as $n => $_) $numeros[$n] ??= 'pref';
        foreach (array_keys($used) as $n) $numeros[$n] ??= 'used';

        ksort($numeros);

        $rows = [];
        foreach ($numeros as $num => $origine) {
            $pref = $ctx['prefs'][$num] ?? null;
            $simpLibelle = $ctx['simpMap'][$num] ?? null;
            $genLibelle = $ctx['genMap'][$num] ?? null;
            $persoEntry = $ctx['persoByNumero'][$num] ?? null;

            $baseEntry = $ctx['baseByNumero'][$num] ?? null;
            $defaultLibelle = $baseEntry['libelle']
                ?? ($persoEntry['libelle'] ?? ($simpLibelle ?? $genLibelle ?? $this->parentLookup($num, $ctx['genMap'])));
            $libelleEffectif = $this->applyLabelSource($num, $defaultLibelle, $pref, $ctx['simpMap'], $ctx['genMap']);

            $sens = $baseEntry['sens'] ?? ($persoEntry['sens'] ?? $this->deriveSens($num, $ctx['simpBySN']));

            $enabledDefault = $baseEntry !== null || $persoEntry !== null;

            $rows[] = [
                'numero' => (string) $num,
                'libelle_effectif' => $libelleEffectif,
                'sens' => $sens,
                'origine' => $origine,
                'libelles' => [
                    'simplifie' => $simpLibelle,
                    'general' => $genLibelle,
                    'perso' => $persoEntry['libelle'] ?? null,
                ],
                'pref_enabled' => $pref['enabled'] ?? null,
                'pref_libelle_source' => $pref['libelle_source'] ?? null,
                'pref_libelle_perso' => $pref['libelle_perso'] ?? null,
                'enabled_default' => $enabledDefault,
                'is_used' => isset($used[$num]),
            ];
        }
        return $rows;
    }

    private function buildContext(int $entrepriseId): array
    {
        $entreprise = $this->entrepriseRepo->findById($entrepriseId);
        $mode = $entreprise['plan_comptable'] ?? 'simplifie';
        $autoInclude = !empty($entreprise['plan_auto_include_used']);

        $simpMap = $this->simpRepo->findAllAsMap();
        $genMap = $this->planRepo->findAllAsMap();
        $simpAll = $this->simpRepo->findAll();
        $genSelectable = $this->planRepo->findSelectable();
        $perso = $this->persoRepo->findAllByEntreprise($entrepriseId);
        $prefs = $this->prefRepo->findAllByEntreprise($entrepriseId);

        $simpBySN = [];
        foreach ($simpAll as $s) {
            $simpBySN[$s['numero']] = $s;
        }

        // Plan de base normalisé : {numero, libelle, sens}
        if ($mode === 'simplifie') {
            $base = array_map(fn($e) => [
                'numero' => $e['numero'],
                'libelle' => $e['libelle'],
                'sens' => $e['sens'],
            ], $simpAll);
        } else {
            $base = array_map(fn($e) => [
                'numero' => $e['numero'],
                'libelle' => $e['libelle'],
                'sens' => null,
            ], $genSelectable);
        }

        $baseByNumero = [];
        foreach ($base as $b) {
            $baseByNumero[$b['numero']] = $b;
        }
        $persoByNumero = [];
        foreach ($perso as $p) {
            $persoByNumero[$p['numero']] = $p;
        }

        return compact('entreprise', 'mode', 'autoInclude', 'simpMap', 'genMap', 'simpBySN', 'base', 'baseByNumero', 'perso', 'persoByNumero', 'prefs');
    }

    private function applyLabelSource(string $numero, string $defaultLabel, ?array $pref, array $simpMap, array $genMap): string
    {
        $src = $pref['libelle_source'] ?? null;
        if ($src === 'personnalise' && !empty($pref['libelle_perso'])) {
            return $pref['libelle_perso'];
        }
        if ($src === 'simplifie' && isset($simpMap[$numero])) {
            return $simpMap[$numero];
        }
        if ($src === 'general' && isset($genMap[$numero])) {
            return $genMap[$numero];
        }
        return $defaultLabel;
    }

    private function parentLookup(string $numero, array $map): string
    {
        $p = $numero;
        while (strlen($p) > 2) {
            $p = substr($p, 0, -1);
            if (isset($map[$p])) return $map[$p];
        }
        return '';
    }

    /** @param array<string,array{sens:string}> $simpBySN */
    private function deriveSens(string $numero, array $simpBySN): ?string
    {
        if (isset($simpBySN[$numero])) return $simpBySN[$numero]['sens'];
        $class = (int) substr($numero, 0, 1);
        if ($class === 6) return 'D';
        if ($class === 7) return 'C';
        return null;
    }
}

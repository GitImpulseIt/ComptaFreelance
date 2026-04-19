# Plan comptable général 2026

## Source

Règlement **ANC n° 2014-03** (version au 1er janvier 2026), PDF officiel Autorité des Normes Comptables.

## Schéma

Table `plan_comptable` (migration [`0016_plan_comptable.sql`](../database/migrations/0016_plan_comptable.sql)) :

| Colonne | Type | Rôle |
|---|---|---|
| `numero` | `VARCHAR(10)` PK | Numéro de compte (2 à 6 chiffres, ex. `512000`) |
| `libelle` | `VARCHAR(255)` | Libellé officiel |
| `classe` | `SMALLINT` | 1 à 9 (hors 8-9 pour le PCG minimal) |
| `optionnel` | `BOOLEAN` | Facultatif selon le règlement (italique dans le PDF) — exclu du sélecteur |
| `created_at` / `updated_at` | `TIMESTAMP` | Audit |

Index : `classe` (filtre admin), `LOWER(libelle)` (recherche texte).

## Volumétrie

- **831 comptes** au total (7 classes présentes dans le PCG : 1, 2, 3, 4, 5, 6, 7)
- **509 marqués `optionnel=TRUE`** (facultatifs au règlement)
- **60 rubriques niveau 2** (numéros à 2 chiffres : `10`, `11`, `20`, … — non postables, rôle de tête de section)

## Filtrage pour la sélection app

Lors de la qualification d'une opération bancaire, seul un sous-ensemble est sélectionnable :

```sql
WHERE optionnel = FALSE
  AND classe <> 3               -- stocks, rare en compta freelance
  AND LENGTH(numero) > 2        -- exclure les rubriques niveau 2
```

Résultat : **248 comptes sélectionnables**. Repository : [`src/Repository/PlanComptableRepository.php`](../src/Repository/PlanComptableRepository.php).

## Administration

CRUD complet dans l'app admin (`http://localhost:8081/plan-comptable`) :

- Liste par classe (onglets) + recherche texte sur numéro ou libellé
- Création / édition / suppression d'un compte
- Basculement du flag `optionnel` (contrôle la sélectabilité côté app)
- Le numéro ne peut pas être modifié après création

Fichiers :
- [`admin/src/Controller/PlanComptableController.php`](../admin/src/Controller/PlanComptableController.php)
- [`admin/templates/plan-comptable/`](../admin/templates/plan-comptable/)
- Routes : `admin/config/routes.php` sous `/plan-comptable*`

## Intégration app (qualification d'opération)

Page `/app/banque/{id}` ([`templates/app/banque/show.html.twig`](../templates/app/banque/show.html.twig)) :

1. Le contrôleur [`BanqueController::show`](../src/Controller/App/BanqueController.php) appelle `PlanComptableRepository::findSelectable()` et passe le résultat au template.
2. Le template injecte les données en JSON global : `<script>window.PLAN_COMPTABLE = [...]</script>` (~22 KB, 248 entrées).
3. [`public/js/transaction.js`](../public/js/transaction.js) lit `window.PLAN_COMPTABLE` et implémente l'autocomplete sur tous les inputs `.compta-input-compte` :
   - Dropdown filtré en temps réel (numéro OU libellé, insensible à la casse)
   - Navigation clavier : ↑↓ Enter Esc
   - Click pour sélectionner
   - La valeur envoyée au POST est **uniquement le numéro**

Le dropdown `<ul class="compte-dropdown hidden absolute …">` est ajouté dans chaque cellule Compte et rendu visible au focus de l'input frère.

## Régénérer le seed depuis un PDF ANC futur

Le seed a été produit à partir d'un CSV extrait du PDF ANC. Pour une mise à jour annuelle, régénérer la liste d'INSERT en exportant le PCG de l'année en CSV avec colonnes `Numero;Libelle;Classe;Niveau;Compte_parent;Facultatif`, puis créer une nouvelle migration qui remplace le contenu de la table (la colonne `numero` est la clé stable).

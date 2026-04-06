# Format FEC - Fichier des Ecritures Comptables

Synthese basee sur l'Article A47 A-1 du Livre des Procedures Fiscales et le valideur officiel Test-Compta-Demat v1.00.10b.

## Contexte reglementaire

Le FEC est obligatoire pour tout contribuable soumis a un controle fiscal. Il contient l'ensemble des ecritures comptables d'un exercice, classees par ordre chronologique de validation.

**Contenu obligatoire :**
- Ecritures apres operations d'inventaire
- Ecritures de reprise des soldes de l'exercice anterieur (en premier)

**Exclusions :**
- Ecritures de centralisation
- Ecritures de solde des comptes de charges et de produits

## Nommage du fichier

```
{SIREN}FEC{AAAAMMJJ}
```

- **SIREN** : 9 chiffres du contribuable
- **AAAAMMJJ** : date de cloture de l'exercice

Exemple : `123456789FEC20251231`

## Format du fichier

| Propriete | Valeur |
|---|---|
| **Type** | Fichier plat, organisation sequentielle |
| **Encodage** | ISO 8859-15 ou UTF-8 (Unicode ISO/CEI 10646) |
| **Separateur de champs** | Tabulation (`\t`) ou pipe (`\|`) |
| **Separateur d'enregistrements** | Retour chariot et/ou fin de ligne (CRLF ou LF) |
| **Premiere ligne** | Noms des champs (en-tete obligatoire) |
| **Separateur decimal** | Virgule (`,`) |
| **Separateur de milliers** | Interdit |

> **Note valideur :** les separateurs virgule (`,`) et point-virgule (`;`) sont refuses par le valideur officiel depuis 2013. Utiliser la tabulation.

## Structure des champs

### 18 champs obligatoires (BIC/IS - comptabilite d'engagement)

| # | Champ | Nom technique | Type | Obligatoire | Description |
|---|---|---|---|---|---|
| 1 | Code journal | `JournalCode` | Alphanum | Oui | Code du journal comptable |
| 2 | Libelle journal | `JournalLib` | Alphanum | Oui | Libelle du journal |
| 3 | Numero d'ecriture | `EcritureNum` | Alphanum | Oui | Sequence continue |
| 4 | Date comptable | `EcritureDate` | Date | Oui | Date de comptabilisation |
| 5 | Numero de compte | `CompteNum` | Alphanum | Oui | 3 premiers caracteres = chiffres (PCG) |
| 6 | Libelle du compte | `CompteLib` | Alphanum | Oui | Conforme au PCG |
| 7 | Compte auxiliaire | `CompAuxNum` | Alphanum | Non | A blanc si non utilise |
| 8 | Libelle auxiliaire | `CompAuxLib` | Alphanum | Non | A blanc si non utilise |
| 9 | Reference piece | `PieceRef` | Alphanum | Oui | Reference du justificatif |
| 10 | Date piece | `PieceDate` | Date | Oui | Date du justificatif |
| 11 | Libelle ecriture | `EcritureLib` | Alphanum | Oui | Libelle de l'ecriture |
| 12 | Debit | `Debit` | Numerique | Oui | Montant au debit |
| 13 | Credit | `Credit` | Numerique | Oui | Montant au credit |
| 14 | Lettrage | `EcritureLet` | Alphanum | Non | A blanc si non utilise |
| 15 | Date lettrage | `DateLet` | Date | Non | A blanc si non utilise |
| 16 | Date validation | `ValidDate` | Date | Oui | Date de validation |
| 17 | Montant en devise | `Montantdevise` | Numerique | Non | A blanc si non utilise |
| 18 | Identifiant devise | `Idevise` | Alphanum | Non | A blanc si non utilise |

### Champs supplementaires (comptabilite de tresorerie BNC)

| # | Champ | Nom technique | Type | Description |
|---|---|---|---|---|
| 19 | Date reglement | `DateRglt` | Date | Date du reglement |
| 20 | Mode reglement | `ModeRglt` | Alphanum | Mode de reglement |
| 21 | Nature operation | `NatOp` | Alphanum | A blanc si non utilise |
| 22 | Identification client | `IdClient` | Alphanum | A blanc si non utilise (BNC uniquement) |

### Alternative Montant/Sens

Si le systeme comptable ne gere pas debit/credit separement, les champs 12 et 13 peuvent etre remplaces par :

| # | Champ | Nom technique | Type | Valeurs |
|---|---|---|---|---|
| 12 | Montant | `Montant` | Numerique | Montant de l'operation |
| 13 | Sens | `Sens` | Alphanum | `D`/`C` ou `+1`/`-1` |

## Regles de formatage

### Dates
- Format : `AAAAMMJJ` sans separateur
- Exemple : `20251231` pour le 31 decembre 2025
- Annee entre 1900 et 2099

### Valeurs numeriques
- Base decimale, en mode caractere
- Separateur decimal : **virgule** (`,`)
- Pas de separateur de milliers
- Cadrees a droite, completees a gauche par des zeros (longueur fixe)
- Le signe peut etre en debut ou en fin de valeur
- Exemples valides : `1234,56` / `0,00` / `-500,00` / `500,00-`

### Champs alphanumeriques
- Cadres a gauche
- Completes a droite par des espaces (longueur fixe)
- Les champs facultatifs non utilises sont laisses a blanc (vide entre deux separateurs)

### Numero de compte (`CompteNum`)
- Les 3 premiers caracteres **doivent etre des chiffres** conformes au Plan Comptable General
- Pattern valideur : `^[0-9]{3}[0-9a-zA-Z]*$`

## Regles metier

### Equilibre debit/credit
- Chaque ligne doit avoir **soit un debit, soit un credit**, pas les deux non-nuls simultanement
- Pour chaque ecriture (meme `EcritureNum`), le total des debits doit egal le total des credits

### Ordre chronologique
- Les ecritures sont classees par **ordre chronologique de validation** (`ValidDate`)
- Les numeros d'ecriture (`EcritureNum`) forment une **sequence continue**

### Ecritures de reprise
- Les premieres ecritures du fichier correspondent aux reprises de soldes de l'exercice anterieur

## Variantes selon le regime fiscal

| Regime | Champs | Particularites |
|---|---|---|
| **BIC/IS** (VII) | 18 champs | Comptabilite d'engagement, tous champs obligatoires |
| **BNC/BA droit commercial** (VIII-3) | 18 champs | JournalCode/Lib et CompteNum peuvent etre a blanc |
| **BA tresorerie** (VIII-5) | 21 champs | +DateRglt, ModeRglt, NatOp |
| **BNC tresorerie** (VIII-7) | 22 champs | +DateRglt, ModeRglt, NatOp, IdClient |

> **Pour ComptaV2** : le regime pertinent est **BIC/IS** (freelance en comptabilite d'engagement), donc 18 champs avec tabulation comme separateur.

## Exemple de fichier

```
JournalCode	JournalLib	EcritureNum	EcritureDate	CompteNum	CompteLib	CompAuxNum	CompAuxLib	PieceRef	PieceDate	EcritureLib	Debit	Credit	EcritureLet	DateLet	ValidDate	Montantdevise	Idevise
BQ	Banque	001	20250101	512000	Banque	AXABANQUE	Axa Banque	OD001	20250101	A nouveau	10000,00	0,00			20250101		
BQ	Banque	001	20250101	120000	Resultat	AXABANQUE	Axa Banque	OD001	20250101	A nouveau	0,00	10000,00			20250101		
VE	Ventes	002	20250115	411000	Clients			FA2025-001	20250115	Facture prestation	1200,00	0,00			20250115		
VE	Ventes	002	20250115	706000	Prestations de services			FA2025-001	20250115	Facture prestation	0,00	1000,00			20250115		
VE	Ventes	002	20250115	445710	TVA collectee			FA2025-001	20250115	Facture prestation	0,00	200,00			20250115		
```

## Valideur officiel

Le fichier genere peut etre verifie avec **Test-Compta-Demat** (fourni par la DGFiP). Les controles effectues :

1. **Structure** : nombre de colonnes constant, en-tete conforme
2. **Types** : formats date/numerique/alphanum respectes
3. **Comptes** : 3 premiers caracteres numeriques
4. **Coherence** : debit/credit non remplis simultanement, sequence continue
5. **Encodage** : ISO 8859-15 ou UTF-8 attendu

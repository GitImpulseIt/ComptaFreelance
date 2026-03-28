<?php
/**
 * Script de migration des données ComptaPerso vers ComptaV2.
 * Usage: docker exec comptav2-php php /var/www/html/database/import_compta_perso.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dbConfig = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $dbConfig['driver'], $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

// Trouver le compte bancaire de l'entreprise Impulse Tech
$stmt = $pdo->query("SELECT cb.id FROM comptes_bancaires cb JOIN entreprises e ON e.id = cb.entreprise_id WHERE e.raison_sociale ILIKE '%impulse%' AND cb.active = TRUE LIMIT 1");
$compteId = $stmt->fetchColumn();

if (!$compteId) {
    echo "ERREUR: Aucun compte bancaire trouvé pour Impulse Tech.\n";
    exit(1);
}

echo "Compte bancaire ID: {$compteId}\n";

// 1. Importer les transactions depuis le CSV Shine (avec reference_externe)
$csvPath = '/var/www/html/database/BQ_export.csv';
if (!file_exists($csvPath)) {
    echo "ERREUR: Fichier CSV non trouvé à {$csvPath}\n";
    exit(1);
}

$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle, 0, ';', '"', '\\');
$header = array_map(fn($col) => trim($col, "\xEF\xBB\xBF \t\n\r\0\x0B"), $header);

// Créer un import
$stmt = $pdo->prepare("INSERT INTO imports_bancaires (compte_bancaire_id, source, format, fichier, statut, nb_transactions) VALUES (:cid, 'fichier', 'csv_shine', 'migration_compta_perso', 'termine', 0)");
$stmt->execute(['cid' => $compteId]);
$importId = (int)$pdo->lastInsertId();

$stmtInsert = $pdo->prepare(
    "INSERT INTO transactions_bancaires (compte_bancaire_id, import_bancaire_id, date, libelle, montant, type, reference_externe)
     VALUES (:cid, :iid, :date, :libelle, :montant, :type, :ref)"
);

$txCount = 0;
while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
    if (count($row) < count($header)) continue;
    $data = array_combine($header, $row);

    $debit = (float)str_replace([' ', ','], ['', '.'], $data['Débit'] ?? '0');
    $credit = (float)str_replace([' ', ','], ['', '.'], $data['Crédit'] ?? '0');

    if ($debit == 0 && $credit == 0) continue;

    $isCredit = $credit > 0;
    $montant = $isCredit ? $credit : $debit;

    // Parser la date DD/MM/YYYY -> YYYY-MM-DD
    $dateRaw = trim($data["Date d'opération"] ?? $data['Date de la valeur'] ?? '');
    preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $dateRaw, $m);
    $date = $m ? "{$m[3]}-{$m[2]}-{$m[1]}" : $dateRaw;

    $ref = trim($data['Transaction ID'] ?? '');

    $stmtInsert->execute([
        'cid' => $compteId,
        'iid' => $importId,
        'date' => $date,
        'libelle' => trim($data['Libellé'] ?? ''),
        'montant' => round($montant, 2),
        'type' => $isCredit ? 'credit' : 'debit',
        'ref' => $ref,
    ]);
    $txCount++;
}
fclose($handle);

// Mettre à jour le nb_transactions
$pdo->prepare("UPDATE imports_bancaires SET nb_transactions = :nb WHERE id = :id")->execute(['nb' => $txCount, 'id' => $importId]);
echo "Transactions importées: {$txCount}\n";

// 2. Charger les lignes comptables de ComptaPerso
$lignesPath = '/var/www/html/database/lignes_comptables_export.csv';
if (!file_exists($lignesPath)) {
    echo "ERREUR: Fichier lignes_comptables non trouvé à {$lignesPath}\n";
    exit(1);
}

// Construire un index reference_externe -> transaction_id dans ComptaV2
$stmt = $pdo->query("SELECT id, reference_externe FROM transactions_bancaires WHERE reference_externe IS NOT NULL");
$refMap = [];
while ($row = $stmt->fetch()) {
    $refMap[$row['reference_externe']] = (int)$row['id'];
}

echo "Transactions avec référence: " . count($refMap) . "\n";

// Parser les lignes comptables
$handle = fopen($lignesPath, 'r');
$header = fgetcsv($handle, 0, ';');

$stmtLigne = $pdo->prepare(
    "INSERT INTO lignes_comptables (transaction_bancaire_id, compte, montant_ht, type, tva)
     VALUES (:tid, :compte, :montant_ht, :type, :tva)"
);

$ligneCount = 0;
$skipped = 0;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    if (count($row) < 4) continue;

    $refExterne = trim($row[0]);
    $compte = trim($row[1]);
    $montantHt = (float)str_replace(',', '.', trim($row[2]));
    $type = trim($row[3]);
    $tva = isset($row[4]) ? (float)str_replace(',', '.', trim($row[4])) : 0.0;

    // Chercher la transaction dans ComptaV2
    if (!isset($refMap[$refExterne])) {
        $skipped++;
        continue;
    }

    $transactionId = $refMap[$refExterne];

    $stmtLigne->execute([
        'tid' => $transactionId,
        'compte' => $compte,
        'montant_ht' => $montantHt,
        'type' => $type,
        'tva' => $tva,
    ]);
    $ligneCount++;
}
fclose($handle);

echo "Lignes comptables importées: {$ligneCount}\n";
echo "Lignes ignorées (transaction non trouvée): {$skipped}\n";
echo "Migration terminée.\n";

<?php
/**
 * Import des liens documentaires depuis l'export CSV Shine.
 * Les pièces jointes (colonne "Pièces") sont stockées comme liens documents.
 * Usage: docker exec comptav2-php php /var/www/html/database/import_liens_documents.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dbConfig = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $dbConfig['driver'], $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

// Index reference_externe -> transaction_id
$stmt = $pdo->query("SELECT id, reference_externe FROM transactions_bancaires WHERE reference_externe IS NOT NULL");
$refMap = [];
while ($row = $stmt->fetch()) {
    $refMap[$row['reference_externe']] = (int)$row['id'];
}
echo "Transactions indexées: " . count($refMap) . "\n";

// Lire le CSV Shine
$csvPath = '/var/www/html/database/BQ_export.csv';
$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle, 0, ';', '"', '\\');
$header = array_map(fn($col) => trim($col, "\xEF\xBB\xBF \t\n\r\0\x0B"), $header);

$stmtInsert = $pdo->prepare(
    "INSERT INTO liens_documents (transaction_bancaire_id, url) VALUES (:tid, :url)"
);

$count = 0;

while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
    if (count($row) < count($header)) continue;
    $data = array_combine($header, $row);

    $ref = trim($data['Transaction ID'] ?? '');
    $pieces = trim($data['Pièces'] ?? '');

    if ($pieces === '' || !isset($refMap[$ref])) continue;

    $transactionId = $refMap[$ref];

    // Les pièces peuvent contenir plusieurs fichiers séparés par des virgules ou des pipes
    // Dans Shine, c'est un seul fichier par transaction dans les données observées
    $fichiers = array_filter(array_map('trim', preg_split('/[,|]/', $pieces)));

    foreach ($fichiers as $fichier) {
        $stmtInsert->execute([
            'tid' => $transactionId,
            'url' => $fichier,
        ]);
        $count++;
    }
}
fclose($handle);

echo "Liens documentaires importés: {$count}\n";
echo "Terminé.\n";

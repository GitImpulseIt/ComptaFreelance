<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

class TransactionBancaireRepository
{
    public function __construct(private PDO $pdo) {}

    public function findAllByEntreprise(int $entrepriseId, array $filtres = []): array
    {
        $sql = "SELECT t.*, cb.nom AS compte_nom, cb.banque, cb.iban
                FROM transactions_bancaires t
                JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
                WHERE cb.entreprise_id = :entreprise_id";
        $params = ['entreprise_id' => $entrepriseId];

        if (!empty($filtres['compte_id'])) {
            $sql .= " AND t.compte_bancaire_id = :compte_id";
            $params['compte_id'] = $filtres['compte_id'];
        }

        if (!empty($filtres['type'])) {
            $sql .= " AND t.type = :type";
            $params['type'] = $filtres['type'];
        }

        if (!empty($filtres['statut'])) {
            $sql .= " AND t.statut = :statut";
            $params['statut'] = $filtres['statut'];
        }

        if (!empty($filtres['date_debut'])) {
            $sql .= " AND t.date >= :date_debut";
            $params['date_debut'] = $filtres['date_debut'];
        }

        if (!empty($filtres['date_fin'])) {
            $sql .= " AND t.date <= :date_fin";
            $params['date_fin'] = $filtres['date_fin'];
        }

        if (!empty($filtres['recherche'])) {
            $sql .= " AND LOWER(t.libelle) LIKE :recherche";
            $params['recherche'] = '%' . mb_strtolower($filtres['recherche']) . '%';
        }

        $sql .= " ORDER BY t.date DESC, t.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countByEntreprise(int $entrepriseId): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE t.type = 'credit') AS nb_credits,
                    COUNT(*) FILTER (WHERE t.type = 'debit') AS nb_debits,
                    COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE 0 END), 0) AS total_credits,
                    COALESCE(SUM(CASE WHEN t.type = 'debit' THEN t.montant ELSE 0 END), 0) AS total_debits,
                    COALESCE(SUM(CASE WHEN t.type = 'credit' THEN t.montant ELSE -t.montant END), 0) AS solde,
                    COUNT(*) FILTER (WHERE t.statut = 'non_rapproche') AS nb_non_rapprochees
                FROM transactions_bancaires t
                JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
                WHERE cb.entreprise_id = :entreprise_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['entreprise_id' => $entrepriseId]);
        return $stmt->fetch() ?: [
            'total' => 0, 'nb_credits' => 0, 'nb_debits' => 0,
            'total_credits' => 0, 'total_debits' => 0, 'nb_non_rapprochees' => 0,
        ];
    }

    public function findAllByCompte(int $compteId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM transactions_bancaires WHERE compte_bancaire_id = :compte_id ORDER BY date DESC, id DESC"
        );
        $stmt->execute(['compte_id' => $compteId]);
        return $stmt->fetchAll();
    }

    public function findNonRapprochees(int $entrepriseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, cb.nom AS compte_nom
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE cb.entreprise_id = :entreprise_id AND t.statut = 'non_rapproche'
             ORDER BY t.date DESC"
        );
        $stmt->execute(['entreprise_id' => $entrepriseId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, cb.nom AS compte_nom, cb.banque, cb.iban
             FROM transactions_bancaires t
             JOIN comptes_bancaires cb ON cb.id = t.compte_bancaire_id
             WHERE t.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createBatch(array $transactions): int
    {
        $count = 0;
        $stmt = $this->pdo->prepare(
            "INSERT INTO transactions_bancaires (compte_bancaire_id, import_bancaire_id, date, libelle, montant, type, reference_externe)
             VALUES (:compte_bancaire_id, :import_bancaire_id, :date, :libelle, :montant, :type, :reference_externe)"
        );

        foreach ($transactions as $t) {
            $stmt->execute([
                'compte_bancaire_id' => $t['compte_bancaire_id'],
                'import_bancaire_id' => $t['import_bancaire_id'] ?? null,
                'date' => $t['date'],
                'libelle' => $t['libelle'],
                'montant' => $t['montant'],
                'type' => $t['type'],
                'reference_externe' => $t['reference_externe'] ?? null,
            ]);
            $count++;
        }

        return $count;
    }

    public function rapprocher(int $id, ?int $factureId, ?int $depenseId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE transactions_bancaires
             SET statut = 'rapproche', facture_id = :facture_id, depense_id = :depense_id
             WHERE id = :id"
        );
        $stmt->execute([
            'id' => $id,
            'facture_id' => $factureId,
            'depense_id' => $depenseId,
        ]);
    }
}

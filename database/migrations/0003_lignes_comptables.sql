-- Lignes comptables pour la qualification des opérations bancaires
CREATE TABLE IF NOT EXISTS lignes_comptables (
    id SERIAL PRIMARY KEY,
    transaction_bancaire_id INTEGER NOT NULL REFERENCES transactions_bancaires(id) ON DELETE CASCADE,
    compte VARCHAR(20) NOT NULL,
    montant_ht NUMERIC(12,2) NOT NULL DEFAULT 0,
    type VARCHAR(3) NOT NULL DEFAULT 'DBT',
    tva NUMERIC(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_lignes_comptables_transaction ON lignes_comptables(transaction_bancaire_id);

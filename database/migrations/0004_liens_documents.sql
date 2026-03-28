-- Liens documentaires pour les opérations bancaires
CREATE TABLE IF NOT EXISTS liens_documents (
    id SERIAL PRIMARY KEY,
    transaction_bancaire_id INTEGER NOT NULL REFERENCES transactions_bancaires(id) ON DELETE CASCADE,
    url TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_liens_documents_transaction ON liens_documents(transaction_bancaire_id);

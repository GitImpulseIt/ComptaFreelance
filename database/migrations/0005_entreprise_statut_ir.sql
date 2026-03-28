-- Statut juridique et option IR pour les entreprises
ALTER TABLE entreprises ADD COLUMN IF NOT EXISTS statut_juridique VARCHAR(10) NOT NULL DEFAULT 'SASU';
ALTER TABLE entreprises ADD COLUMN IF NOT EXISTS option_ir BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE entreprises ADD COLUMN IF NOT EXISTS option_ir_fin DATE;

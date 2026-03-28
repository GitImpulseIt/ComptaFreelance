-- Régime des bénéfices (BNC ou BIC) pour les entreprises à l'IR
ALTER TABLE entreprises ADD COLUMN IF NOT EXISTS regime_benefices VARCHAR(3) NOT NULL DEFAULT 'BNC';

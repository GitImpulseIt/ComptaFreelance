-- Nature de l'immobilisation (liste libre)
ALTER TABLE immobilisations ADD COLUMN IF NOT EXISTS nature VARCHAR(100);

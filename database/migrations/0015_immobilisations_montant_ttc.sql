-- Montant TTC de l'immobilisation
ALTER TABLE immobilisations ADD COLUMN IF NOT EXISTS montant_ttc NUMERIC(12,2);

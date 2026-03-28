-- Coefficient dégressif pour amortissement dégressif
ALTER TABLE immobilisations ADD COLUMN IF NOT EXISTS coeff_degressif NUMERIC(4,2) NOT NULL DEFAULT 1.25;

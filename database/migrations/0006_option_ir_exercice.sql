-- Remplacer la date de fin IR par un exercice (année)
ALTER TABLE entreprises DROP COLUMN IF EXISTS option_ir_fin;
ALTER TABLE entreprises ADD COLUMN IF NOT EXISTS option_ir_fin_exercice INTEGER;

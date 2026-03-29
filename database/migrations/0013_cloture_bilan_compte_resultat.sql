-- Ajout colonnes bilan et compte de résultat pour la clôture d'exercice
ALTER TABLE declarations_2035
    ADD COLUMN IF NOT EXISTS data_bilan JSONB NOT NULL DEFAULT '{}',
    ADD COLUMN IF NOT EXISTS data_compte_resultat JSONB NOT NULL DEFAULT '{}';

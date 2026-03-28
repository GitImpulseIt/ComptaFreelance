-- Déclarations 2035 (clôture d'exercice BNC)
CREATE TABLE IF NOT EXISTS declarations_2035 (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    annee INTEGER NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    data_2035 JSONB NOT NULL DEFAULT '{}',
    data_2035_suite JSONB NOT NULL DEFAULT '{}',
    data_2035_a_p1 JSONB NOT NULL DEFAULT '{}',
    data_2035_a_p2 JSONB NOT NULL DEFAULT '{}',
    data_2035_b JSONB NOT NULL DEFAULT '{}',
    data_2035_e JSONB NOT NULL DEFAULT '{}',
    data_2035_g JSONB NOT NULL DEFAULT '{}',
    data_2035_rci JSONB NOT NULL DEFAULT '{}',
    data_2049 JSONB NOT NULL DEFAULT '{}',
    data_annexe_libre JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE(entreprise_id, annee)
);

CREATE INDEX idx_declarations_2035_entreprise ON declarations_2035(entreprise_id);

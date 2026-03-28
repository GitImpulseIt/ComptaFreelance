-- Immobilisations (actifs immobilisés)
CREATE TABLE IF NOT EXISTS immobilisations (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    designation VARCHAR(255) NOT NULL,
    date_acquisition DATE NOT NULL,
    date_mise_en_service DATE,
    valeur_acquisition NUMERIC(12,2) NOT NULL DEFAULT 0,
    duree_amortissement INTEGER NOT NULL DEFAULT 5,
    type_amortissement VARCHAR(20) NOT NULL DEFAULT 'lineaire',
    compte VARCHAR(20) NOT NULL DEFAULT '218',
    cession_date DATE,
    cession_montant NUMERIC(12,2),
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_immobilisations_entreprise ON immobilisations(entreprise_id);

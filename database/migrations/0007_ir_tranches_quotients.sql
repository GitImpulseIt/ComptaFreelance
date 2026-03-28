-- Barème IR et quotients familiaux
CREATE TABLE IF NOT EXISTS ir_tranches (
    id SERIAL PRIMARY KEY,
    annee INTEGER NOT NULL,
    tranche_min NUMERIC(12,2) NOT NULL DEFAULT 0,
    tranche_max NUMERIC(12,2),
    taux NUMERIC(5,2) NOT NULL DEFAULT 0
);

INSERT INTO ir_tranches (annee, tranche_min, tranche_max, taux) VALUES
(2025, 0, 11497, 0),
(2025, 11497, 29315, 11),
(2025, 29315, 83823, 30),
(2025, 83823, 180294, 41),
(2025, 180294, NULL, 45),
(2026, 0, 11497, 0),
(2026, 11497, 29315, 11),
(2026, 29315, 83823, 30),
(2026, 83823, 180294, 41),
(2026, 180294, NULL, 45)
ON CONFLICT DO NOTHING;

CREATE TABLE IF NOT EXISTS quotients_familiaux (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    annee INTEGER NOT NULL,
    quotient NUMERIC(4,1) NOT NULL DEFAULT 1.0,
    UNIQUE(entreprise_id, annee)
);

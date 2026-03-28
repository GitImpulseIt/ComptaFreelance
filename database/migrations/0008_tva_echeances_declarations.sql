-- Calendrier TVA configurable par année
CREATE TABLE IF NOT EXISTS tva_echeances (
    id SERIAL PRIMARY KEY,
    annee INTEGER NOT NULL,
    code VARCHAR(20) NOT NULL,
    libelle VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'acompte',
    periode VARCHAR(20) NOT NULL,
    date_echeance DATE NOT NULL,
    UNIQUE(annee, code)
);

-- Déclarations TVA
CREATE TABLE IF NOT EXISTS tva_declarations (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    echeance_id INTEGER NOT NULL REFERENCES tva_echeances(id),
    case_01 NUMERIC(12,2) NOT NULL DEFAULT 0,
    case_02 NUMERIC(12,2) NOT NULL DEFAULT 0,
    case_03 NUMERIC(12,2) NOT NULL DEFAULT 0,
    case_05 NUMERIC(12,2) NOT NULL DEFAULT 0,
    case_06 NUMERIC(12,2) NOT NULL DEFAULT 0,
    case_07 NUMERIC(12,2) NOT NULL DEFAULT 0,
    case_08 NUMERIC(12,2) NOT NULL DEFAULT 0,
    montant_paye NUMERIC(12,2) NOT NULL DEFAULT 0,
    statut VARCHAR(20) NOT NULL DEFAULT 'a_payer',
    date_paiement DATE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE(entreprise_id, echeance_id)
);

-- Échéances par défaut régime simplifié
INSERT INTO tva_echeances (annee, code, libelle, type, periode, date_echeance) VALUES
(2025, '2025-S1', 'Acompte TVA 1er semestre', 'acompte', 'S1', '2025-07-24'),
(2025, '2025-S2', 'Acompte TVA 2ème semestre', 'acompte', 'S2', '2025-12-24'),
(2025, '2025-REG', 'Régularisation annuelle', 'regularisation', 'ANNUEL', '2026-05-04'),
(2026, '2026-S1', 'Acompte TVA 1er semestre', 'acompte', 'S1', '2026-07-24'),
(2026, '2026-S2', 'Acompte TVA 2ème semestre', 'acompte', 'S2', '2026-12-24'),
(2026, '2026-REG', 'Régularisation annuelle', 'regularisation', 'ANNUEL', '2027-05-04')
ON CONFLICT DO NOTHING;

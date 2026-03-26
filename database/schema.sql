-- ComptaV2 - Schéma initial PostgreSQL

CREATE TABLE IF NOT EXISTS entreprises (
    id SERIAL PRIMARY KEY,
    raison_sociale VARCHAR(255) NOT NULL,
    siret VARCHAR(14) NOT NULL UNIQUE,
    adresse TEXT DEFAULT '',
    code_postal VARCHAR(10) DEFAULT '',
    ville VARCHAR(100) DEFAULT '',
    telephone VARCHAR(20) DEFAULT '',
    email VARCHAR(255) DEFAULT '',
    regime_tva VARCHAR(20) NOT NULL DEFAULT 'franchise',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER REFERENCES entreprises(id),
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'freelance',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS clients (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    nom VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT '',
    adresse TEXT DEFAULT '',
    code_postal VARCHAR(10) DEFAULT '',
    ville VARCHAR(100) DEFAULT '',
    siret VARCHAR(14) DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS categories_depenses (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS factures (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    client_id INTEGER NOT NULL REFERENCES clients(id),
    numero VARCHAR(50) NOT NULL,
    date_emission DATE NOT NULL,
    date_echeance DATE NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    montant_ht NUMERIC(12,2) NOT NULL DEFAULT 0,
    taux_tva NUMERIC(5,2) NOT NULL DEFAULT 0,
    montant_ttc NUMERIC(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE(entreprise_id, numero)
);

CREATE TABLE IF NOT EXISTS lignes_factures (
    id SERIAL PRIMARY KEY,
    facture_id INTEGER NOT NULL REFERENCES factures(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    quantite NUMERIC(10,2) NOT NULL DEFAULT 1,
    prix_unitaire NUMERIC(12,2) NOT NULL DEFAULT 0,
    montant_ht NUMERIC(12,2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS depenses (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    categorie_id INTEGER REFERENCES categories_depenses(id),
    date DATE NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    montant_ht NUMERIC(12,2) NOT NULL DEFAULT 0,
    taux_tva NUMERIC(5,2) NOT NULL DEFAULT 0,
    montant_ttc NUMERIC(12,2) NOT NULL DEFAULT 0,
    transaction_bancaire_id INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS comptes_bancaires (
    id SERIAL PRIMARY KEY,
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id),
    nom VARCHAR(100) NOT NULL,
    banque VARCHAR(100) DEFAULT '',
    iban VARCHAR(34) DEFAULT '',
    type_connexion VARCHAR(10) NOT NULL DEFAULT 'manuel',
    provider_account_id VARCHAR(255),
    derniere_synchro TIMESTAMP,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS imports_bancaires (
    id SERIAL PRIMARY KEY,
    compte_bancaire_id INTEGER NOT NULL REFERENCES comptes_bancaires(id),
    source VARCHAR(20) NOT NULL DEFAULT 'fichier',
    format VARCHAR(10) NOT NULL DEFAULT '',
    fichier VARCHAR(255),
    nb_transactions INTEGER NOT NULL DEFAULT 0,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_cours',
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS transactions_bancaires (
    id SERIAL PRIMARY KEY,
    compte_bancaire_id INTEGER NOT NULL REFERENCES comptes_bancaires(id),
    import_bancaire_id INTEGER REFERENCES imports_bancaires(id),
    date DATE NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    montant NUMERIC(12,2) NOT NULL,
    type VARCHAR(10) NOT NULL DEFAULT 'debit',
    statut VARCHAR(20) NOT NULL DEFAULT 'non_rapproche',
    facture_id INTEGER REFERENCES factures(id),
    depense_id INTEGER REFERENCES depenses(id),
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- FK retour depenses → transactions_bancaires
ALTER TABLE depenses
    ADD CONSTRAINT fk_depenses_transaction
    FOREIGN KEY (transaction_bancaire_id)
    REFERENCES transactions_bancaires(id);

-- Index utiles
CREATE INDEX idx_users_entreprise ON users(entreprise_id);
CREATE INDEX idx_clients_entreprise ON clients(entreprise_id);
CREATE INDEX idx_factures_entreprise ON factures(entreprise_id);
CREATE INDEX idx_factures_client ON factures(client_id);
CREATE INDEX idx_depenses_entreprise ON depenses(entreprise_id);
CREATE INDEX idx_comptes_bancaires_entreprise ON comptes_bancaires(entreprise_id);
CREATE INDEX idx_transactions_compte ON transactions_bancaires(compte_bancaire_id);
CREATE INDEX idx_transactions_statut ON transactions_bancaires(statut);
CREATE INDEX idx_transactions_date ON transactions_bancaires(date);

-- Catégories de dépenses par défaut
INSERT INTO categories_depenses (nom, code) VALUES
    ('Fournitures de bureau', 'FOURNITURES'),
    ('Frais de déplacement', 'DEPLACEMENT'),
    ('Télécommunications', 'TELECOM'),
    ('Logiciels et abonnements', 'LOGICIELS'),
    ('Honoraires', 'HONORAIRES'),
    ('Assurances', 'ASSURANCES'),
    ('Formation', 'FORMATION'),
    ('Repas et restauration', 'REPAS'),
    ('Hébergement web', 'HEBERGEMENT'),
    ('Autres', 'AUTRES')
ON CONFLICT (code) DO NOTHING;

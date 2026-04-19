-- Personnalisation du plan comptable par entreprise :
--   • plan_comptable_perso : comptes créés par l'utilisateur (hors plans officiels)
--   • plan_comptable_pref  : préférences sur des comptes existants (enabled + source du libellé)
--   • entreprises.plan_auto_include_used : ajoute automatiquement les comptes déjà
--     utilisés dans les lignes comptables mais absents du plan actif

CREATE TABLE plan_comptable_perso (
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id) ON DELETE CASCADE,
    numero VARCHAR(10) NOT NULL,
    libelle VARCHAR(255) NOT NULL,
    sens CHAR(1) NOT NULL CHECK (sens IN ('D', 'C')),
    categorie VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT now(),
    updated_at TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY (entreprise_id, numero)
);

CREATE TABLE plan_comptable_pref (
    entreprise_id INTEGER NOT NULL REFERENCES entreprises(id) ON DELETE CASCADE,
    numero VARCHAR(10) NOT NULL,
    -- NULL = comportement par défaut, TRUE = force inclure, FALSE = force exclure
    enabled BOOLEAN,
    -- NULL = libellé du plan actif, sinon 'simplifie' / 'general' / 'personnalise'
    libelle_source VARCHAR(20),
    libelle_perso VARCHAR(255),
    updated_at TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY (entreprise_id, numero),
    CHECK (libelle_source IS NULL OR libelle_source IN ('simplifie', 'general', 'personnalise')),
    CHECK (libelle_source <> 'personnalise' OR libelle_perso IS NOT NULL)
);

ALTER TABLE entreprises
    ADD COLUMN plan_auto_include_used BOOLEAN NOT NULL DEFAULT TRUE;

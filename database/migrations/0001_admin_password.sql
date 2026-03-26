-- Migration: stocker le mot de passe admin en BDD
CREATE TABLE IF NOT EXISTS admin_settings (
    key VARCHAR(50) PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Mot de passe par défaut : "admin" en SHA256
INSERT INTO admin_settings (key, value) VALUES
    ('password_hash', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918')
ON CONFLICT (key) DO NOTHING;

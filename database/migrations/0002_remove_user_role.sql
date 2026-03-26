-- Migration: retirer la notion de rôle des utilisateurs
ALTER TABLE users DROP COLUMN IF EXISTS role;

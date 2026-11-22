-- Pupitre : création de la base et de l'utilisateur MySQL.
-- À lancer avec un compte administrateur (root, phpMyAdmin ou client mysql). Rejouable :
-- rien n'est écrasé (ni la base, ni le mot de passe d'un utilisateur existant).
--
-- Avant de lancer : remplacer MOT_DE_PASSE_A_CHOISIR (par exemple : openssl rand -hex 24),
-- puis reporter base, utilisateur et mot de passe dans pupitre/.env (BD_NOM, BD_UTILISATEUR,
-- BD_MOT_DE_PASSE). Le mot de passe réel n'est jamais commité.
-- Pour changer le mot de passe d'un utilisateur existant :
--   ALTER USER 'pupitre'@'%' IDENTIFIED BY 'MOT_DE_PASSE_A_CHOISIR';
--
-- Nom de base : `pupitre_pierre-bleuse` (dev partagé du VPS, mutualisé). Sur le MAMP de cet
-- ordinateur, la base s'appelle `pupitre` et l'utilisateur est 'pupitre'@'127.0.0.1' :
-- adapter les trois noms ci-dessous si l'on recrée ce poste à l'identique.

CREATE DATABASE IF NOT EXISTS `pupitre_pierre-bleuse`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- '%' : PHP se connecte depuis une adresse qui change (autre conteneur, autre ordinateur).
CREATE USER IF NOT EXISTS 'pupitre'@'%' IDENTIFIED BY 'MOT_DE_PASSE_A_CHOISIR';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
  ON `pupitre_pierre-bleuse`.* TO 'pupitre'@'%';

FLUSH PRIVILEGES;

-- Contrôle : USAGE ON *.* et les droits sur `pupitre_pierre-bleuse`.*
SHOW GRANTS FOR 'pupitre'@'%';

-- Pupitre : structure complète de la base, écrite par sql/structure.php le 28/11/2026.
-- 57 tables, après les migrations 001-langues-traductions à 012-apercus-depots-veille-courrier.
-- Structure seule : aucune donnée n'est exportée. Fichier de référence : une installation se fait en
-- restaurant une sauvegarde complète (php pupitre/sql/restaurer.php), qui porte aussi les langues,
-- les blocs et les libellés posés par les migrations.
-- Rejouable : chaque table est créée si elle manque, rien n'est détruit.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `alertes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` enum('prioritaire','action','info') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'action' COMMENT 'alerte prioritaire, action à traiter, information',
  `cle` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'type et objet : une alerte par sujet',
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lien` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `etat` enum('ouverte','reportee','ignoree') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `reportee_au` date DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maj_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `annuaire` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categorie` enum('orchestre','ensemble','salle','festival','soliste','compositeur','maison_de_disques','media','photographe','producteur','agence') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_provisoire` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'le vrai nom reste à fournir : le site n''affiche que la ville',
  `autres_noms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'un par ligne',
  `ville_en` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_en` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `langue` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'média : langue principale',
  `latitude` decimal(8,5) DEFAULT NULL,
  `longitude` decimal(8,5) DEFAULT NULL,
  `verifie` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'ville et coordonnées de référence vérifiées',
  `fuseau` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `site` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `premiere_collaboration` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
  `mots_diese` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `credit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `logo_id` int unsigned DEFAULT NULL COMMENT 'médiathèque',
  `typo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'média sans logo : traitement typographique de son nom sur le site',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `categorie` (`categorie`),
  KEY `nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `annuaire_comptes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned NOT NULL,
  `reseau` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `etat` enum('propose','verifie','a_revoir') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'propose',
  `origine` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main' COMMENT 'main, ia',
  `source` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'la page où l''IA l''a trouvé',
  `ordre` int NOT NULL DEFAULT '0',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fiche_id` (`fiche_id`),
  KEY `etat` (`etat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `annuaire_contacts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned NOT NULL,
  `nom` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fonction` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `telephone` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `apercus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `jeton` char(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'nom du dossier public : <site>/apercu/<jeton>/',
  `titre` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `note` text COLLATE utf8mb4_unicode_ci COMMENT 'ce que le pilote dit de ces évolutions : montré au destinataire, repris au procès-verbal',
  `empreinte` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'SHA-256 de ce qui serait publié : ce qui a été montré, à l''octet près',
  `resume` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : fichiers modifiés, médias, représentations',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_par_nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expire_le` datetime NOT NULL,
  `retire_le` datetime DEFAULT NULL COMMENT 'dossier supprimé : à l''expiration, ou à la main',
  PRIMARY KEY (`id`),
  UNIQUE KEY `jeton` (`jeton`),
  KEY `expire_le` (`expire_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `apercus_validations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `apercu_id` int unsigned NOT NULL,
  `compte_id` int unsigned DEFAULT NULL,
  `nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `jeton` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 du jeton du lien personnel : le jeton lui-même ne se relit pas',
  `envoye_le` datetime DEFAULT NULL,
  `vu_le` datetime DEFAULT NULL,
  `decision` enum('attente','validee','corrections') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'attente',
  `decide_le` datetime DEFAULT NULL,
  `remarques` text COLLATE utf8mb4_unicode_ci,
  `declaration` text COLLATE utf8mb4_unicode_ci COMMENT 'le texte exact que la personne a accepté',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `agent` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jeton` (`jeton`),
  KEY `apercu_id` (`apercu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appels_ia` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `objet` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modele` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `jetons_entree` int unsigned NOT NULL DEFAULT '0',
  `jetons_sortie` int unsigned NOT NULL DEFAULT '0',
  `duree_ms` int unsigned NOT NULL DEFAULT '0',
  `resultat` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `erreur` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tache_id` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'critique_concert, critique_album, portrait, interview, actualite, autre',
  `media_id` int unsigned DEFAULT NULL COMMENT 'annuaire : le titre de presse, qui porte aussi le logo',
  `titre_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `chapeau_en` text COLLATE utf8mb4_unicode_ci,
  `auteur` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_article` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AAAA-MM-JJ ou AAAA-MM ; vide si inconnue',
  `verbatim` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'cité dans sa langue',
  `langue` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resume_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `infos_en` text COLLATE utf8mb4_unicode_ci COMMENT 'informations complémentaires',
  `lien` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url_canonique` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `publie` tinyint(1) NOT NULL DEFAULT '1',
  `une` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'à la une : marque éditoriale, indépendante de la publication',
  `accueil` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'sélectionné pour la page d''accueil',
  `ordre_accueil` int NOT NULL DEFAULT '0',
  `largeur` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'sur l''accueil : 1, 2 ou 4 colonnes',
  `exemple` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'faux article de maquette, à supprimer avant la mise en production',
  `origine` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main' COMMENT 'main, url (importé d''une adresse), texte (texte collé)',
  `texte_source` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'texte de l''article récupéré ou collé : sert à vérifier les citations, ne sort jamais sur le site',
  `ordre` int NOT NULL DEFAULT '0',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles_citations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int unsigned NOT NULL,
  `texte` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'la citation, dans sa langue et sa formulation d''origine, avec ses guillemets',
  `langue` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'langue d''origine de la citation',
  `traduction_en` text COLLATE utf8mb4_unicode_ci COMMENT 'traduction dans la langue de rédaction ; vide si la citation est déjà dans cette langue',
  `principale` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'la citation mise en avant : une par article',
  `verifiee` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 : proposée par l''IA, formulation à vérifier dans l''article',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `article_id` (`article_id`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles_concerts` (
  `article_id` int unsigned NOT NULL,
  `concert_id` int unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`concert_id`),
  KEY `concert_id` (`concert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `articles_disques` (
  `article_id` int unsigned NOT NULL,
  `disque_id` int unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`disque_id`),
  KEY `disque_id` (`disque_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blocs` (
  `cle` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'identifiant de la section dans la page : agenda, visuel-1…',
  `nom` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom du bloc dans Pupitre',
  `parent` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'section qui le contient, s''il n''est qu''une partie de section',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `masquable` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 : toujours affiché (pied de page et ses liens légaux)',
  `parametres` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : réglages propres au bloc',
  `ordre` int NOT NULL DEFAULT '0',
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `collaborations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned DEFAULT NULL COMMENT 'le concert ou l''événement qu''elle met en avant',
  `fiche_id` int unsigned DEFAULT NULL COMMENT 'annuaire : l''orchestre',
  `intitule_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'intitulé éditorial ; vide : le nom de l''orchestre ou le titre du concert',
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `annee` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
  `une` tinyint(1) NOT NULL DEFAULT '0',
  `verifiee` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 : collaboration à confirmer',
  `publie` tinyint(1) NOT NULL DEFAULT '0',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `communications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `canal` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'instagram, facebook, youtube, linkedin, lettre, autre',
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'objet de la lettre, ou intitulé court de la publication',
  `texte` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'texte prévu, ou texte publié',
  `statut` enum('a_preparer','programmee','publiee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_preparer',
  `prevue_le` datetime DEFAULT NULL COMMENT 'date prévue de publication ou d''envoi',
  `publiee_le` datetime DEFAULT NULL COMMENT 'date réelle de publication ou d''envoi',
  `lien` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'adresse de la publication en ligne',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'notes internes',
  `origine` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main' COMMENT 'main ; plus tard, la plateforme connectée',
  `reference_externe` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'identifiant sur la plateforme, pour une synchronisation future',
  `marque` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'marque de la plateforme à laquelle l''action a été envoyée',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `statut` (`statut`,`prevue_le`),
  KEY `canal` (`canal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `communications_concerts` (
  `communication_id` int unsigned NOT NULL,
  `concert_id` int unsigned NOT NULL,
  PRIMARY KEY (`communication_id`,`concert_id`),
  KEY `concert_id` (`concert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comptes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('pilote','editeur','artiste') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'empreinte password_hash ; vide pour l''artiste, qui entre par lien',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `derniere_connexion` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `preferences` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON : mise en page du tableau de bord',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `concerts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'identifiant de l''événement dans les fichiers du site',
  `type` enum('concert','opera','tournee','disque') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'concert',
  `titre_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ensemble_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `ensemble_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `maison_id` int unsigned DEFAULT NULL,
  `salle_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `salle_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_en` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_en` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fuseau` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'fuseau de la salle : sert au seul calcul de l''heure de l''artiste',
  `mentions_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `lien_billetterie` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `page_organisateur` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `temps_fort` tinyint(1) NOT NULL DEFAULT '0',
  `publie` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 : dépublié, aucune de ses dates ne sort sur le site',
  `notes_internes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `contact_com_nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'contact communication : la personne qui partagera photos et vidéos',
  `contact_com_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact_com_langue` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en' COMMENT 'langue du mail d''invitation',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`),
  KEY `ensemble_id` (`ensemble_id`),
  KEY `salle_id` (`salle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `concerts_orchestres` (
  `concert_id` int unsigned NOT NULL,
  `fiche_id` int unsigned NOT NULL COMMENT 'annuaire : orchestre ou ensemble',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`concert_id`,`fiche_id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contacts_groupes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `structure` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lien` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `langue` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'mention et fonctions écrites dans cette langue, non traduites',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contacts_personnes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `groupe_id` int unsigned NOT NULL,
  `nom` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `telephone` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `groupe_id` (`groupe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `courriers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sens` enum('envoye','recu') COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'envoyé : envoye, non_envoye, echec, puis ce que le routeur en dit (remis, ouvert, clique, rejete, spam, bloque) ; reçu : a_lire, lu',
  `type_retour` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'reçu : reponse, absence, rejet, autre',
  `correspondant_email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'le destinataire d''un envoi, l''expéditeur d''un retour',
  `correspondant_nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sujet` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `texte` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'vide pour un envoi qui porte un lien personnel',
  `nature` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'connexion, apercu, depot…',
  `objet_table` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `objet_id` int unsigned DEFAULT NULL,
  `identifiant` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'envoyé : identifiant du message chez le routeur ; reçu : Message-ID',
  `uid` int unsigned DEFAULT NULL COMMENT 'reçu : numéro du message dans la boîte',
  `reponse_a` int unsigned DEFAULT NULL COMMENT 'reçu : l''envoi auquel ce retour répond',
  `date_message` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message` (`sens`,`identifiant`),
  KEY `correspondant` (`correspondant_email`),
  KEY `date_message` (`sens`,`date_message`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `depots` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `concert_id` int unsigned DEFAULT NULL,
  `jeton` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `expire_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `invite_le` datetime DEFAULT NULL COMMENT 'envoi du mail d''invitation',
  `invite_a` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'adresse à laquelle l''invitation est partie',
  `ouvert_le` datetime DEFAULT NULL COMMENT 'première visite de la zone',
  `ferme` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'zone fermée à la main : plus aucun dépôt',
  PRIMARY KEY (`id`),
  KEY `jeton` (`jeton`),
  KEY `concert_id` (`concert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `depots_fichiers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `depot_id` int unsigned NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom du fichier tel qu''il a été déposé',
  `fichier` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom sous lequel il est rangé (stockage/depots/<zone>/)',
  `type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'type du contenu, relu sur le fichier',
  `taille` bigint unsigned NOT NULL DEFAULT '0',
  `deposant` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `credit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'crédit à mentionner, donné par le déposant',
  `legende` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `statut` enum('recu','retenu','ecarte') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'recu',
  `media_id` int unsigned DEFAULT NULL COMMENT 'le média de la médiathèque, une fois le fichier retenu',
  `depose_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `depot_id` (`depot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `disques` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sous_titre_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `annee` smallint unsigned DEFAULT NULL,
  `date_sortie` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
  `maison_id` int unsigned DEFAULT NULL COMMENT 'annuaire : maison de disques',
  `compositeurs` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'un par ligne',
  `oeuvres_en` text COLLATE utf8mb4_unicode_ci COMMENT 'une œuvre par ligne',
  `ensemble` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'orchestre ou ensemble, appellation officielle',
  `ensemble_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `interpretes_en` text COLLATE utf8mb4_unicode_ci COMMENT 'interprètes et collaborateurs, un par ligne',
  `descriptif_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `presentation_en` text COLLATE utf8mb4_unicode_ci COMMENT 'présentation générale',
  `mot_en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `commander` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'un lien par ligne : Nom | adresse',
  `streaming` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'un lien par ligne : Nom | adresse',
  `lien` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `publie` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `disques_liens` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `disque_id` int unsigned NOT NULL,
  `nature` enum('ecoute','commande') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `plateforme` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'spotify, apple-music, deezer, youtube, qobuz… : choisit le pictogramme ; vide : lien simple',
  `nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom affiché : la plateforme, ou le distributeur',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `disque_id` (`disque_id`,`nature`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `distinctions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `disque_id` int unsigned DEFAULT NULL COMMENT 'album distingué',
  `intitule_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `organisme` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'organisme ou média qui la décerne',
  `annee` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
  `logo_id` int unsigned DEFAULT NULL COMMENT 'médiathèque',
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `une` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'mise en avant : montrée dès la vignette de l''album',
  `publie` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `disque_id` (`disque_id`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fonctions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `intitule_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `institution_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `institution_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `debut` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AAAA, AAAA-MM ou AAAA-MM-JJ',
  `fin` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actuelle` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 : fonction actuelle ; 0 : fonction passée',
  `une` tinyint(1) NOT NULL DEFAULT '0',
  `lien` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `publie` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `import_lignes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `import_id` int unsigned NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `engagement` int NOT NULL DEFAULT '0' COMMENT 'rang de l''engagement dans l''extrait',
  `nature` enum('nouvelle','modifiee','programme','disparue','douteuse','inchangee','annulee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `concert_id` int unsigned DEFAULT NULL,
  `representation_id` int unsigned DEFAULT NULL,
  `propose` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : ce que dit l''extrait',
  `avant` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : ce que dit l''agenda',
  `controles` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : remarques du contrôle',
  `candidats` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : cas douteux, représentations possibles',
  `decision` enum('en_attente','validee','refusee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `decide_par` int unsigned DEFAULT NULL,
  `decide_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_id` (`import_id`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `imports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nature` enum('pdf','tableur','texte') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fichier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom dans stockage/imports/',
  `nom_origine` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_extrait` date DEFAULT NULL,
  `statut` enum('depose','lecture','a_valider','termine','echec') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'depose',
  `tache_id` int unsigned DEFAULT NULL,
  `erreur` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bilan` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : décompte des lignes et des contrôles',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `termine_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `compte_id` int unsigned DEFAULT NULL,
  `compte_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `action` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `objet_type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `objet_id` int unsigned DEFAULT NULL,
  `objet_libelle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `avant` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : les champs avant la modification',
  `apres` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : les mêmes champs après',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `objet` (`objet_type`,`objet_id`),
  KEY `cree_le` (`cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `langues` (
  `code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'fr, en, de…',
  `nom` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom de la langue dans cette langue : Français, English',
  `nom_fr` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom de la langue pour Pupitre',
  `locale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'pour les dates du site : fr-FR, en-GB',
  `active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'proposée sur le site',
  `reference` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'langue de rédaction des contenus : une seule',
  `defaut` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'langue du site à la première visite : une seule',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lettre_inscrits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `langue` char(2) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fr',
  `statut` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_confirmer',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `libelles` (
  `cle` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'clé du libellé dans le site : nav.agenda',
  `texte_en` text COLLATE utf8mb4_unicode_ci COMMENT 'texte dans la langue de rédaction',
  `groupe` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'partie du site : nav, agenda, presse…',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `liens_connexion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `compte_id` int unsigned NOT NULL,
  `jeton` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 du jeton : le jeton lui-même n''est jamais conservé',
  `objet` enum('connexion','mot_de_passe') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expire_le` datetime NOT NULL,
  `utilise_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jeton` (`jeton`),
  KEY `compte_id` (`compte_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `maisons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_en` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('image','son','video','document') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fichier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nom dans stockage/medias/ ; vide si le fichier ne vit que dans le site',
  `chemin_site` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'chemin dans le site une fois publié (assets/…)',
  `url_externe` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'adresse externe (image, vidéo, YouTube) : aucun fichier n''est alors copié',
  `nom_origine` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom du média dans Pupitre',
  `mime` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `taille` int unsigned NOT NULL DEFAULT '0',
  `largeur` int unsigned DEFAULT NULL,
  `hauteur` int unsigned DEFAULT NULL,
  `alt_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `credit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `droits` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'droits d''usage, en texte libre',
  `droits_valides` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0 : droits d''utilisation à confirmer, le média ne sort pas sur le site',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medias_usages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `media_id` int unsigned NOT NULL COMMENT 'médiathèque',
  `objet` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'table de l''objet qui emploie le média : blocs, disques, articles…',
  `objet_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'clé de la ligne',
  `role` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'à quel titre : element, visuel…',
  `nature` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'image, animation, video : comment le média est montré',
  `affiche_id` int unsigned DEFAULT NULL COMMENT 'vidéo : image d''attente (médiathèque)',
  `titre_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `alt_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'vide : le texte alternatif du média',
  `credit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'vide : le crédit du média',
  `parametres` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : cadrage, module d''animation et ses réglages, son, opacité',
  `publie` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` int NOT NULL DEFAULT '0',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `objet` (`objet`,`objet_id`,`role`,`ordre`),
  KEY `media_id` (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `titre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passee_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `musiques` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_id` int unsigned DEFAULT NULL COMMENT 'médiathèque',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'adresse du fichier son, s''il n''est pas dans la médiathèque',
  `piste` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'analyse de la bande-son (data/….json) ; vide tant qu''elle n''est pas faite',
  `titre_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `compositeur_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `oeuvre_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mouvement_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `orchestre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'appellation officielle, non traduite',
  `orchestre_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `interpretes_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nature` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'studio, live, autre ; vide : à préciser',
  `credit` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `publie` tinyint(1) NOT NULL DEFAULT '1',
  `ouverture` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'morceau d''ouverture du site : un seul',
  `mention_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notes_artiste` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `compte_id` int unsigned NOT NULL,
  `texte` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('a_traiter','traitee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_traiter',
  `traitee_par` int unsigned DEFAULT NULL,
  `traitee_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`),
  KEY `statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `parcours_lignes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `liste` enum('fonctions','opera') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `texte_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `liste` (`liste`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `parcours_orchestres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned NOT NULL COMMENT 'annuaire',
  `ville_en` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `latitude` decimal(8,5) DEFAULT NULL,
  `longitude` decimal(8,5) DEFAULT NULL,
  `zone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'ameriques, europe-afrique, asie-oceanie ; vide : déduite de la longitude',
  `affiche` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'affichée sur la carte du site',
  `verifie` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'position vérifiée ; 0 : à confirmer',
  `origine` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reprise' COMMENT 'reprise, main, concert, ia',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `participants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `nom` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `participants_fiches` (
  `participant_id` int unsigned NOT NULL,
  `fiche_id` int unsigned NOT NULL,
  PRIMARY KEY (`participant_id`,`fiche_id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posts_sociaux` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `marque` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'identifiant de la marque sur la plateforme (Metricool : blogId)',
  `reseau` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `identifiant` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'identifiant de la publication sur le réseau',
  `nature` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'post' COMMENT 'post, reel, story, video',
  `publie_le` datetime DEFAULT NULL,
  `texte` text COLLATE utf8mb4_unicode_ci,
  `lien` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `visuel` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'adresse de la vignette, telle que la plateforme la donne',
  `impressions` int unsigned DEFAULT NULL,
  `portee` int unsigned DEFAULT NULL,
  `interactions` int unsigned DEFAULT NULL,
  `likes` int unsigned DEFAULT NULL,
  `commentaires` int unsigned DEFAULT NULL,
  `partages` int unsigned DEFAULT NULL,
  `enregistrements` int unsigned DEFAULT NULL,
  `clics` int unsigned DEFAULT NULL,
  `vues` int unsigned DEFAULT NULL,
  `engagement` decimal(8,3) DEFAULT NULL COMMENT 'taux d''engagement, en pour cent, tel que la plateforme le calcule',
  `communication_id` int unsigned DEFAULT NULL COMMENT 'l''action de communication de Pupitre dont cette publication vient',
  `theme` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'ce dont elle parle : concert, presse, album…',
  `concert_id` int unsigned DEFAULT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `releve_le` datetime DEFAULT NULL COMMENT 'dernière lecture des mesures',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `publication` (`marque`,`reseau`,`identifiant`),
  KEY `publie_le` (`publie_le`),
  KEY `communication_id` (`communication_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `programme` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `compositeur_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `compositeur_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `oeuvre_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `publications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nature` enum('publication','retour') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publication',
  `compte_id` int unsigned DEFAULT NULL,
  `compte_nom` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dossier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'sauvegarde des fichiers remplacés, dans stockage/publications/',
  `fichiers` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : fichiers remplacés et fichiers créés',
  `representations` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : représentations passées à « publiee »',
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `annulee` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'un retour en arrière l''a défaite',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reglages` (
  `cle` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `representations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `heure` char(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'HH:MM, heure locale de la salle',
  `statut` enum('connue','embargo','annoncee','publiee','annulee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'connue',
  `embargo_le` date DEFAULT NULL COMMENT 'date à laquelle l''annonce est prévue',
  `lien_source` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'page où l''organisateur annonce la date',
  `raison` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'annulation ou report',
  `salle_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_en` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_en` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lien_billetterie` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `origine` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main' COMMENT 'main, reprise, import',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`),
  KEY `date` (`date`),
  KEY `statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reseaux` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'instagram, facebook… : choisit le pictogramme',
  `nom` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `barre` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'figure aussi dans la barre du haut du site',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `taches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `charge` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON : ce dont la tâche a besoin',
  `statut` enum('en_attente','en_cours','terminee','echec') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `avancement` tinyint unsigned NOT NULL DEFAULT '0',
  `message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `debut_le` datetime DEFAULT NULL,
  `fin_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `statut` (`statut`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `telechargements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'biographie, photographie, document, autre',
  `libelle_en` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description_en` text COLLATE utf8mb4_unicode_ci,
  `langue` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'langue du document, vide : sans objet',
  `media_id` int unsigned DEFAULT NULL COMMENT 'médiathèque ; vide : « à venir »',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'fichier hébergé ailleurs',
  `credit` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `conditions_en` text COLLATE utf8mb4_unicode_ci,
  `publie` tinyint(1) NOT NULL DEFAULT '1',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tentatives_connexion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reussie` tinyint(1) NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`,`cree_le`),
  KEY `ip` (`ip`,`cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `textes` (
  `cle` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'description, citation, biographie, saison, lettre, credit',
  `en` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `traductions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `objet` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'table de l''objet traduit',
  `objet_id` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'clé de la ligne : identifiant, ou clé texte',
  `champ` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `langue` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `texte` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statut` enum('validee','proposee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'validee' COMMENT 'proposee : proposée par l''IA, ne sort pas sur le site',
  `origine` enum('main','ia','reprise') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `empreinte` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'MD5 du texte de référence au moment de la traduction : s''il a changé, la traduction est à revoir',
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `objet` (`objet`,`objet_id`,`champ`,`langue`),
  KEY `langue` (`langue`,`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `veille` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manuel' COMMENT 'actualites (flux), recherche (IA), reseaux (plateforme), manuel',
  `nature` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web' COMMENT 'presse, web, social',
  `reseau` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `titre` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `site` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'le média ou le compte qui publie',
  `auteur` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extrait` text COLLATE utf8mb4_unicode_ci,
  `langue` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention` enum('identifie','cite','a_verifier') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_verifier' COMMENT 'l''artiste est identifié (compte mentionné), ou seulement cité par son nom',
  `requete` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'la recherche qui l''a trouvé',
  `empreinte` char(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'SHA-1 de l''adresse ramenée à sa forme canonique : un signalement ne se note qu''une fois',
  `lien` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `paru_le` date DEFAULT NULL,
  `statut` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_lire',
  `note` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `article_id` int unsigned DEFAULT NULL COMMENT 'l''article de la revue de presse qui en a été tiré',
  `communication_id` int unsigned DEFAULT NULL COMMENT 'l''action de communication qui le partage',
  `traite_par` int unsigned DEFAULT NULL,
  `traite_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empreinte` (`empreinte`),
  KEY `statut` (`statut`,`paru_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `visuels` (
  `cle` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'emplacement dans la page : visuel-1…',
  `type` enum('image','video','youtube','couleur') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `media_id` int unsigned DEFAULT NULL COMMENT 'médiathèque',
  `affiche_id` int unsigned DEFAULT NULL COMMENT 'vidéo : image d''attente',
  `position_x` tinyint unsigned DEFAULT NULL COMMENT 'point focal, en pourcentage',
  `position_y` tinyint unsigned DEFAULT NULL,
  `mobile_x` tinyint unsigned DEFAULT NULL COMMENT 'point focal sur téléphone',
  `mobile_y` tinyint unsigned DEFAULT NULL,
  `lumiere` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'préréglage de lumière ; vide : l''image telle quelle',
  `alt_en` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opacite` tinyint unsigned NOT NULL DEFAULT '100',
  `nuee` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'arriere-plan, premier-plan ; vide : valeur par défaut du site',
  `son` tinyint(1) NOT NULL DEFAULT '0',
  `piste` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'analyse de la bande-son de la vidéo',
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'YouTube : identifiant ou adresse ; couleur : valeur CSS',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

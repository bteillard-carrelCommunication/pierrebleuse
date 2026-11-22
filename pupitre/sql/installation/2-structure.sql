-- Pupitre : structure complète de la base, exportée du MySQL local (MAMP 8.0.40) le 22/11/2026,
-- commit ec77699. Structure seule : aucune donnée de dev n'est exportée.
-- À importer dans la base créée par 1-base-et-utilisateur.sql, si sql/depart.php ne peut pas
-- tourner (depart.php applique déjà sql/schema.sql, équivalent : mêmes 37 tables).
-- Rejouable : chaque table est créée si elle manque, rien n'est détruit.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `alertes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cle` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'type et objet : une alerte par sujet',
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lien` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `etat` enum('ouverte','reportee','ignoree') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ouverte',
  `reportee_au` date DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `maj_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `annuaire` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `categorie` enum('orchestre','ensemble','salle','festival','soliste','compositeur','maison_de_disques','media','photographe','producteur','agence') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_provisoire` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'le vrai nom reste à fournir : le site n''affiche que la ville',
  `autres_noms` text COLLATE utf8mb4_unicode_ci COMMENT 'un par ligne',
  `ville_fr` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_fr` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `latitude` decimal(8,5) DEFAULT NULL,
  `longitude` decimal(8,5) DEFAULT NULL,
  `fuseau` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `site` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mots_diese` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `credit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `logo_id` int unsigned DEFAULT NULL COMMENT 'médiathèque',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `categorie` (`categorie`),
  KEY `nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `annuaire_comptes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned NOT NULL,
  `reseau` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etat` enum('propose','verifie','a_revoir') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'propose',
  `ordre` int NOT NULL DEFAULT '0',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fiche_id` (`fiche_id`),
  KEY `etat` (`etat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `annuaire_contacts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned NOT NULL,
  `nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fonction` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `telephone` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `appels_ia` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `objet` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modele` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `jetons_entree` int unsigned NOT NULL DEFAULT '0',
  `jetons_sortie` int unsigned NOT NULL DEFAULT '0',
  `duree_ms` int unsigned NOT NULL DEFAULT '0',
  `resultat` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `erreur` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tache_id` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `articles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_id` int unsigned DEFAULT NULL COMMENT 'annuaire : le titre de presse, qui porte aussi le logo',
  `titre_fr` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titre_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `auteur` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_article` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AAAA-MM-JJ ou AAAA-MM ; vide si inconnue',
  `verbatim` text COLLATE utf8mb4_unicode_ci COMMENT 'cité dans sa langue',
  `langue` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resume_fr` text COLLATE utf8mb4_unicode_ci,
  `resume_en` text COLLATE utf8mb4_unicode_ci,
  `lien` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `comptes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('pilote','editeur','artiste') COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'empreinte password_hash ; vide pour l''artiste, qui entre par lien',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `derniere_connexion` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `concerts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'identifiant de l''événement dans les fichiers du site',
  `type` enum('concert','opera','tournee','disque') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'concert',
  `titre_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titre_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ensemble_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `ensemble_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ensemble_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `maison_id` int unsigned DEFAULT NULL,
  `salle_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `salle_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `salle_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_fr` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_fr` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fuseau` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'fuseau de la salle : sert au seul calcul de l''heure de l''artiste',
  `mentions_fr` text COLLATE utf8mb4_unicode_ci,
  `mentions_en` text COLLATE utf8mb4_unicode_ci,
  `lien_billetterie` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `page_organisateur` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `temps_fort` tinyint(1) NOT NULL DEFAULT '0',
  `notes_internes` text COLLATE utf8mb4_unicode_ci,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`),
  KEY `ensemble_id` (`ensemble_id`),
  KEY `salle_id` (`salle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `contacts_groupes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `structure` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lien` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `langue` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'mention et fonctions écrites dans cette langue, non traduites',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `contacts_personnes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `groupe_id` int unsigned NOT NULL,
  `nom` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `telephone` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `groupe_id` (`groupe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `depots` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `concert_id` int unsigned DEFAULT NULL,
  `jeton` char(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `expire_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `disques` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `annee` smallint unsigned DEFAULT NULL,
  `maison_id` int unsigned DEFAULT NULL COMMENT 'annuaire : maison de disques',
  `descriptif_fr` text COLLATE utf8mb4_unicode_ci COMMENT 'une ligne par ligne affichée',
  `descriptif_en` text COLLATE utf8mb4_unicode_ci,
  `mot_fr` text COLLATE utf8mb4_unicode_ci COMMENT 'le mot de l''artiste',
  `mot_en` text COLLATE utf8mb4_unicode_ci,
  `commander` text COLLATE utf8mb4_unicode_ci COMMENT 'un lien par ligne : Nom | adresse',
  `streaming` text COLLATE utf8mb4_unicode_ci COMMENT 'un lien par ligne : Nom | adresse',
  `lien` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `import_lignes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `import_id` int unsigned NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `engagement` int NOT NULL DEFAULT '0' COMMENT 'rang de l''engagement dans l''extrait',
  `nature` enum('nouvelle','modifiee','programme','disparue','douteuse','inchangee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `concert_id` int unsigned DEFAULT NULL,
  `representation_id` int unsigned DEFAULT NULL,
  `propose` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : ce que dit l''extrait',
  `avant` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : ce que dit l''agenda',
  `controles` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : remarques du contrôle',
  `candidats` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : cas douteux, représentations possibles',
  `decision` enum('en_attente','validee','refusee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `decide_par` int unsigned DEFAULT NULL,
  `decide_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_id` (`import_id`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `imports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nature` enum('pdf','tableur','texte') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fichier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'nom dans stockage/imports/',
  `nom_origine` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_extrait` date DEFAULT NULL,
  `statut` enum('depose','lecture','a_valider','termine','echec') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'depose',
  `tache_id` int unsigned DEFAULT NULL,
  `erreur` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bilan` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : décompte des lignes et des contrôles',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `termine_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `journal` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `compte_id` int unsigned DEFAULT NULL,
  `compte_nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `action` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objet_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objet_id` int unsigned DEFAULT NULL,
  `objet_libelle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `avant` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : les champs avant la modification',
  `apres` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : les mêmes champs après',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `objet` (`objet_type`,`objet_id`),
  KEY `cree_le` (`cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `lettre_inscrits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `langue` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fr',
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_confirmer',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `liens_connexion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `compte_id` int unsigned NOT NULL,
  `jeton` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 du jeton : le jeton lui-même n''est jamais conservé',
  `objet` enum('connexion','mot_de_passe') COLLATE utf8mb4_unicode_ci NOT NULL,
  `expire_le` datetime NOT NULL,
  `utilise_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jeton` (`jeton`),
  KEY `compte_id` (`compte_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `maisons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_fr` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom_en` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `medias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('image','son','video','document') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fichier` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'nom dans stockage/medias/ ; vide si le fichier ne vit que dans le site',
  `chemin_site` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'chemin dans le site une fois publié (assets/…)',
  `nom_origine` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mime` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `taille` int unsigned NOT NULL DEFAULT '0',
  `largeur` int unsigned DEFAULT NULL,
  `hauteur` int unsigned DEFAULT NULL,
  `alt_fr` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alt_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `credit` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `droits` text COLLATE utf8mb4_unicode_ci COMMENT 'droits d''usage, en texte libre',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `musiques` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_id` int unsigned DEFAULT NULL COMMENT 'médiathèque',
  `piste` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'analyse de la bande-son (data/….json) ; vide tant qu''elle n''est pas faite',
  `titre_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titre_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_fr` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `notes_artiste` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `compte_id` int unsigned NOT NULL,
  `texte` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `statut` enum('a_traiter','traitee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_traiter',
  `traitee_par` int unsigned DEFAULT NULL,
  `traitee_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`),
  KEY `statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `parcours_lignes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `liste` enum('fonctions','opera') COLLATE utf8mb4_unicode_ci NOT NULL,
  `texte_fr` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `texte_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `liste` (`liste`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `parcours_orchestres` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `fiche_id` int unsigned NOT NULL COMMENT 'annuaire',
  `ville_fr` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `latitude` decimal(8,5) DEFAULT NULL,
  `longitude` decimal(8,5) DEFAULT NULL,
  `zone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'ameriques, europe-afrique, asie-oceanie ; vide : déduite de la longitude',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `participants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `role_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `participants_fiches` (
  `participant_id` int unsigned NOT NULL,
  `fiche_id` int unsigned NOT NULL,
  PRIMARY KEY (`participant_id`,`fiche_id`),
  KEY `fiche_id` (`fiche_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `programme` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `ordre` int NOT NULL DEFAULT '0',
  `compositeur_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `compositeur_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `compositeur_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `oeuvre_fr` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `oeuvre_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mention_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `publications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nature` enum('publication','retour') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'publication',
  `compte_id` int unsigned DEFAULT NULL,
  `compte_nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dossier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'sauvegarde des fichiers remplacés, dans stockage/publications/',
  `fichiers` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : fichiers remplacés et fichiers créés',
  `representations` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : représentations passées à « publiee »',
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `annulee` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'un retour en arrière l''a défaite',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `publications_reseaux` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned DEFAULT NULL,
  `reseau` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `texte` text COLLATE utf8mb4_unicode_ci,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'brouillon',
  `prevue_le` datetime DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `reglages` (
  `cle` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valeur` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `representations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `concert_id` int unsigned NOT NULL,
  `date` date NOT NULL,
  `heure` char(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'HH:MM, heure locale de la salle',
  `statut` enum('connue','embargo','annoncee','publiee','annulee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'connue',
  `embargo_le` date DEFAULT NULL COMMENT 'date à laquelle l''annonce est prévue',
  `lien_source` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'page où l''organisateur annonce la date',
  `raison` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'annulation ou report',
  `salle_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'tournée : lieu propre à cette date',
  `salle_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_fr` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ville_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_fr` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pays_en` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lien_billetterie` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `origine` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main' COMMENT 'main, reprise, import',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `concert_id` (`concert_id`),
  KEY `date` (`date`),
  KEY `statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `reseaux` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'instagram, facebook… : choisit le pictogramme',
  `nom` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `barre` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'figure aussi dans la barre du haut du site',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `taches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `charge` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'JSON : ce dont la tâche a besoin',
  `statut` enum('en_attente','en_cours','terminee','echec') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_attente',
  `avancement` tinyint unsigned NOT NULL DEFAULT '0',
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cree_par` int unsigned DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `debut_le` datetime DEFAULT NULL,
  `fin_le` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `statut` (`statut`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `telechargements` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cle` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `libelle_fr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `libelle_en` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_id` int unsigned DEFAULT NULL COMMENT 'médiathèque ; vide : « à venir »',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `tentatives_connexion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reussie` tinyint(1) NOT NULL,
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`,`cree_le`),
  KEY `ip` (`ip`,`cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `textes` (
  `cle` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'description, citation, biographie, saison, lettre, credit',
  `fr` text COLLATE utf8mb4_unicode_ci,
  `en` text COLLATE utf8mb4_unicode_ci,
  `modifie_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `veille` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `media_id` int unsigned DEFAULT NULL COMMENT 'annuaire',
  `titre` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lien` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `paru_le` date DEFAULT NULL,
  `statut` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'a_lire',
  `cree_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `visuels` (
  `cle` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'emplacement dans la page : visuel-1…',
  `type` enum('image','video','youtube','couleur') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `media_id` int unsigned DEFAULT NULL COMMENT 'médiathèque',
  `affiche_id` int unsigned DEFAULT NULL COMMENT 'vidéo : image d''attente',
  `position_x` tinyint unsigned DEFAULT NULL COMMENT 'point focal, en pourcentage',
  `position_y` tinyint unsigned DEFAULT NULL,
  `mobile_x` tinyint unsigned DEFAULT NULL COMMENT 'point focal sur téléphone',
  `mobile_y` tinyint unsigned DEFAULT NULL,
  `lumiere` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'préréglage de lumière ; vide : l''image telle quelle',
  `alt_fr` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alt_en` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `opacite` tinyint unsigned NOT NULL DEFAULT '100',
  `nuee` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'arriere-plan, premier-plan ; vide : valeur par défaut du site',
  `son` tinyint(1) NOT NULL DEFAULT '0',
  `piste` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'analyse de la bande-son de la vidéo',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'YouTube : identifiant ou adresse ; couleur : valeur CSS',
  `ordre` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`cle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


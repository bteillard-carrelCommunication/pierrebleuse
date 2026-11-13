-- Pupitre : structure complète de la base.
-- Rejouable : chaque table est créée si elle manque, rien n'est détruit.
-- MySQL 8 ou MariaDB 10.4 et suivantes, utf8mb4. Tables et colonnes en français.
-- Les dates et les heures des représentations sont celles de la salle, jamais converties :
-- `date` est un DATE, `heure` une chaîne « HH:MM ».

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------------------------
-- Socle : réglages, comptes, connexion, journal, tâches
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reglages (
  cle         VARCHAR(60)  NOT NULL PRIMARY KEY,
  valeur      TEXT         NOT NULL,
  modifie_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comptes (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email               VARCHAR(190) NOT NULL,
  nom                 VARCHAR(120) NOT NULL,
  role                ENUM('pilote','editeur','artiste') NOT NULL,
  mot_de_passe        VARCHAR(255) NULL COMMENT 'empreinte password_hash ; vide pour l''artiste, qui entre par lien',
  actif               TINYINT(1)   NOT NULL DEFAULT 1,
  derniere_connexion  DATETIME     NULL,
  cree_le             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens à usage unique : connexion de l'artiste, choix du mot de passe d'un compte de l'agence.
CREATE TABLE IF NOT EXISTS liens_connexion (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  compte_id   INT UNSIGNED NOT NULL,
  jeton       CHAR(64)     NOT NULL COMMENT 'SHA-256 du jeton : le jeton lui-même n''est jamais conservé',
  objet       ENUM('connexion','mot_de_passe') NOT NULL,
  expire_le   DATETIME     NOT NULL,
  utilise_le  DATETIME     NULL,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY jeton (jeton),
  KEY compte_id (compte_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentatives_connexion (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email    VARCHAR(190) NOT NULL,
  ip       VARCHAR(45)  NOT NULL,
  reussie  TINYINT(1)   NOT NULL,
  cree_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY email (email, cree_le),
  KEY ip (ip, cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des modifications : qui, quoi, quand, avant, après.
CREATE TABLE IF NOT EXISTS journal (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  compte_id      INT UNSIGNED NULL,
  compte_nom     VARCHAR(120) NOT NULL DEFAULT '',
  action         VARCHAR(40)  NOT NULL,
  objet_type     VARCHAR(40)  NOT NULL,
  objet_id       INT UNSIGNED NULL,
  objet_libelle  VARCHAR(255) NOT NULL DEFAULT '',
  avant          MEDIUMTEXT   NULL COMMENT 'JSON : les champs avant la modification',
  apres          MEDIUMTEXT   NULL COMMENT 'JSON : les mêmes champs après',
  cree_le        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY objet (objet_type, objet_id),
  KEY cree_le (cree_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tâches de fond, exécutées par cron/taches.php.
CREATE TABLE IF NOT EXISTS taches (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type        VARCHAR(40)  NOT NULL,
  charge      MEDIUMTEXT   NULL COMMENT 'JSON : ce dont la tâche a besoin',
  statut      ENUM('en_attente','en_cours','terminee','echec') NOT NULL DEFAULT 'en_attente',
  avancement  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  message     VARCHAR(500) NOT NULL DEFAULT '',
  cree_par    INT UNSIGNED NULL,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  debut_le    DATETIME     NULL,
  fin_le      DATETIME     NULL,
  KEY statut (statut, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des appels à l'IA : objet, modèle, jetons, durée, résultat. Jamais le contenu.
CREATE TABLE IF NOT EXISTS appels_ia (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  objet          VARCHAR(80)  NOT NULL,
  modele         VARCHAR(80)  NOT NULL DEFAULT '',
  jetons_entree  INT UNSIGNED NOT NULL DEFAULT 0,
  jetons_sortie  INT UNSIGNED NOT NULL DEFAULT 0,
  duree_ms       INT UNSIGNED NOT NULL DEFAULT 0,
  resultat       VARCHAR(20)  NOT NULL,
  erreur         VARCHAR(255) NOT NULL DEFAULT '',
  tache_id       INT UNSIGNED NULL,
  cree_le        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alertes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type         VARCHAR(40)  NOT NULL,
  cle          VARCHAR(120) NOT NULL COMMENT 'type et objet : une alerte par sujet',
  message      VARCHAR(500) NOT NULL,
  lien         VARCHAR(255) NOT NULL DEFAULT '',
  etat         ENUM('ouverte','reportee','ignoree') NOT NULL DEFAULT 'ouverte',
  reportee_au  DATE         NULL,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  maj_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY cle (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- Médiathèque
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS medias (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type         ENUM('image','son','video','document') NOT NULL,
  fichier      VARCHAR(255) NULL COMMENT 'nom dans stockage/medias/ ; vide si le fichier ne vit que dans le site',
  chemin_site  VARCHAR(255) NULL COMMENT 'chemin dans le site une fois publié (assets/…)',
  nom_origine  VARCHAR(255) NOT NULL DEFAULT '',
  mime         VARCHAR(100) NOT NULL DEFAULT '',
  taille       INT UNSIGNED NOT NULL DEFAULT 0,
  largeur      INT UNSIGNED NULL,
  hauteur      INT UNSIGNED NULL,
  alt_fr       VARCHAR(500) NOT NULL DEFAULT '',
  alt_en       VARCHAR(500) NOT NULL DEFAULT '',
  credit       VARCHAR(255) NOT NULL DEFAULT '',
  droits       TEXT         NULL COMMENT 'droits d''usage, en texte libre',
  cree_par     INT UNSIGNED NULL,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- Annuaire
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS annuaire (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  categorie       ENUM('orchestre','ensemble','salle','festival','soliste','compositeur','maison_de_disques',
                       'media','photographe','producteur','agence') NOT NULL,
  nom             VARCHAR(190) NOT NULL,
  nom_provisoire  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'le vrai nom reste à fournir : le site n''affiche que la ville',
  autres_noms     TEXT         NULL COMMENT 'un par ligne',
  ville_fr        VARCHAR(120) NOT NULL DEFAULT '',
  ville_en        VARCHAR(120) NOT NULL DEFAULT '',
  pays_fr         VARCHAR(120) NOT NULL DEFAULT '',
  pays_en         VARCHAR(120) NOT NULL DEFAULT '',
  latitude        DECIMAL(8,5) NULL,
  longitude       DECIMAL(8,5) NULL,
  fuseau          VARCHAR(60)  NOT NULL DEFAULT '',
  site            VARCHAR(500) NOT NULL DEFAULT '',
  mots_diese      VARCHAR(500) NOT NULL DEFAULT '',
  credit          VARCHAR(255) NOT NULL DEFAULT '',
  logo_id         INT UNSIGNED NULL COMMENT 'médiathèque',
  notes           TEXT         NULL,
  dans_parcours   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'orchestre invité affiché dans le parcours du site',
  parcours_zone   VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'ameriques, europe-afrique, asie-oceanie ; vide : déduite de la longitude',
  parcours_ordre  INT          NOT NULL DEFAULT 0,
  cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY categorie (categorie),
  KEY nom (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annuaire_comptes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fiche_id    INT UNSIGNED NOT NULL,
  reseau      VARCHAR(30)  NOT NULL,
  adresse     VARCHAR(255) NOT NULL,
  etat        ENUM('propose','verifie','a_revoir') NOT NULL DEFAULT 'propose',
  ordre       INT          NOT NULL DEFAULT 0,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY fiche_id (fiche_id),
  KEY etat (etat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS annuaire_contacts (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fiche_id   INT UNSIGNED NOT NULL,
  nom        VARCHAR(190) NOT NULL DEFAULT '',
  fonction   VARCHAR(190) NOT NULL DEFAULT '',
  email      VARCHAR(190) NOT NULL DEFAULT '',
  telephone  VARCHAR(60)  NOT NULL DEFAULT '',
  ordre      INT          NOT NULL DEFAULT 0,
  KEY fiche_id (fiche_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- Agenda
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS maisons (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cle     VARCHAR(40)  NOT NULL,
  nom_fr  VARCHAR(190) NOT NULL,
  nom_en  VARCHAR(190) NOT NULL DEFAULT '',
  ordre   INT          NOT NULL DEFAULT 0,
  UNIQUE KEY cle (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS concerts (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cle                VARCHAR(120) NOT NULL COMMENT 'identifiant de l''événement dans les fichiers du site',
  type               ENUM('concert','opera','tournee','disque') NOT NULL DEFAULT 'concert',
  titre_fr           VARCHAR(255) NOT NULL DEFAULT '',
  titre_en           VARCHAR(255) NOT NULL DEFAULT '',
  ensemble_id        INT UNSIGNED NULL COMMENT 'annuaire',
  ensemble_fr        VARCHAR(255) NOT NULL DEFAULT '',
  ensemble_en        VARCHAR(255) NOT NULL DEFAULT '',
  maison_id          INT UNSIGNED NULL,
  salle_id           INT UNSIGNED NULL COMMENT 'annuaire',
  salle_fr           VARCHAR(255) NOT NULL DEFAULT '',
  salle_en           VARCHAR(255) NOT NULL DEFAULT '',
  ville_fr           VARCHAR(120) NOT NULL DEFAULT '',
  ville_en           VARCHAR(120) NOT NULL DEFAULT '',
  pays_fr            VARCHAR(120) NOT NULL DEFAULT '',
  pays_en            VARCHAR(120) NOT NULL DEFAULT '',
  fuseau             VARCHAR(60)  NOT NULL DEFAULT '' COMMENT 'fuseau de la salle : sert au seul calcul de l''heure de l''artiste',
  mentions_fr        TEXT         NULL,
  mentions_en        TEXT         NULL,
  lien_billetterie   VARCHAR(500) NOT NULL DEFAULT '',
  page_organisateur  VARCHAR(500) NOT NULL DEFAULT '',
  temps_fort         TINYINT(1)   NOT NULL DEFAULT 0,
  notes_internes     TEXT         NULL,
  cree_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY cle (cle),
  KEY ensemble_id (ensemble_id),
  KEY salle_id (salle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le statut se règle par représentation. « publiee » est posé par la publication, jamais à la main.
-- Seules les représentations « annoncee » et « publiee » sortent dans les fichiers du site.
CREATE TABLE IF NOT EXISTS representations (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  concert_id        INT UNSIGNED NOT NULL,
  date              DATE         NOT NULL,
  heure             CHAR(5)      NULL COMMENT 'HH:MM, heure locale de la salle',
  statut            ENUM('connue','embargo','annoncee','publiee','annulee') NOT NULL DEFAULT 'connue',
  embargo_le        DATE         NULL COMMENT 'date à laquelle l''annonce est prévue',
  lien_source       VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'page où l''organisateur annonce la date',
  raison            VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'annulation ou report',
  salle_fr          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'tournée : lieu propre à cette date',
  salle_en          VARCHAR(255) NOT NULL DEFAULT '',
  ville_fr          VARCHAR(120) NOT NULL DEFAULT '',
  ville_en          VARCHAR(120) NOT NULL DEFAULT '',
  pays_fr           VARCHAR(120) NOT NULL DEFAULT '',
  pays_en           VARCHAR(120) NOT NULL DEFAULT '',
  lien_billetterie  VARCHAR(500) NOT NULL DEFAULT '',
  origine           VARCHAR(20)  NOT NULL DEFAULT 'main' COMMENT 'main, reprise, import',
  cree_le           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_le        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY concert_id (concert_id),
  KEY date (date),
  KEY statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS programme (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  concert_id      INT UNSIGNED NOT NULL,
  ordre           INT          NOT NULL DEFAULT 0,
  compositeur_id  INT UNSIGNED NULL COMMENT 'annuaire',
  compositeur_fr  VARCHAR(255) NOT NULL DEFAULT '',
  compositeur_en  VARCHAR(255) NOT NULL DEFAULT '',
  oeuvre_fr       VARCHAR(500) NOT NULL DEFAULT '',
  oeuvre_en       VARCHAR(500) NOT NULL DEFAULT '',
  mention_fr      VARCHAR(255) NOT NULL DEFAULT '',
  mention_en      VARCHAR(255) NOT NULL DEFAULT '',
  KEY concert_id (concert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne « avec » du concert. Elle peut réunir plusieurs fiches de l'annuaire (« A, B »).
CREATE TABLE IF NOT EXISTS participants (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  concert_id  INT UNSIGNED NOT NULL,
  ordre       INT          NOT NULL DEFAULT 0,
  nom         VARCHAR(255) NOT NULL DEFAULT '',
  role_fr     VARCHAR(255) NOT NULL DEFAULT '',
  role_en     VARCHAR(255) NOT NULL DEFAULT '',
  KEY concert_id (concert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participants_fiches (
  participant_id  INT UNSIGNED NOT NULL,
  fiche_id        INT UNSIGNED NOT NULL,
  PRIMARY KEY (participant_id, fiche_id),
  KEY fiche_id (fiche_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- La seule chose que l'artiste écrit : une note sur un concert, qui arrive dans « À valider ».
CREATE TABLE IF NOT EXISTS notes_artiste (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  concert_id   INT UNSIGNED NOT NULL,
  compte_id    INT UNSIGNED NOT NULL,
  texte        TEXT         NOT NULL,
  statut       ENUM('a_traiter','traitee') NOT NULL DEFAULT 'a_traiter',
  traitee_par  INT UNSIGNED NULL,
  traitee_le   DATETIME     NULL,
  cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY concert_id (concert_id),
  KEY statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- Contenus du site
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS textes (
  cle         VARCHAR(40) NOT NULL PRIMARY KEY COMMENT 'description, citation, biographie, saison, lettre, credit',
  fr          TEXT        NULL,
  en          TEXT        NULL,
  modifie_le  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parcours_lignes (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  liste     ENUM('fonctions','opera') NOT NULL,
  texte_fr  VARCHAR(500) NOT NULL DEFAULT '',
  texte_en  VARCHAR(500) NOT NULL DEFAULT '',
  ordre     INT          NOT NULL DEFAULT 0,
  KEY liste (liste, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS disques (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cle            VARCHAR(120) NOT NULL DEFAULT '',
  titre          VARCHAR(255) NOT NULL DEFAULT '',
  annee          SMALLINT UNSIGNED NULL,
  maison_id      INT UNSIGNED NULL COMMENT 'annuaire : maison de disques',
  descriptif_fr  TEXT         NULL COMMENT 'une ligne par ligne affichée',
  descriptif_en  TEXT         NULL,
  mot_fr         TEXT         NULL COMMENT 'le mot de l''artiste',
  mot_en         TEXT         NULL,
  commander      TEXT         NULL COMMENT 'un lien par ligne : Nom | adresse',
  streaming      TEXT         NULL COMMENT 'un lien par ligne : Nom | adresse',
  lien           VARCHAR(500) NOT NULL DEFAULT '',
  ordre          INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS articles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cle           VARCHAR(120) NOT NULL DEFAULT '',
  media_id      INT UNSIGNED NULL COMMENT 'annuaire : le titre de presse, qui porte aussi le logo',
  titre_fr      VARCHAR(500) NOT NULL DEFAULT '',
  titre_en      VARCHAR(500) NOT NULL DEFAULT '',
  auteur        VARCHAR(190) NOT NULL DEFAULT '',
  date_article  VARCHAR(10)  NOT NULL DEFAULT '' COMMENT 'AAAA-MM-JJ ou AAAA-MM ; vide si inconnue',
  verbatim      TEXT         NULL COMMENT 'cité dans sa langue',
  langue        CHAR(2)      NOT NULL DEFAULT '',
  resume_fr     TEXT         NULL,
  resume_en     TEXT         NULL,
  lien          VARCHAR(500) NOT NULL DEFAULT '',
  ordre         INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visuels (
  cle          VARCHAR(40)  NOT NULL PRIMARY KEY COMMENT 'emplacement dans la page : visuel-1…',
  type         ENUM('image','video','youtube','couleur') NOT NULL DEFAULT 'image',
  media_id     INT UNSIGNED NULL COMMENT 'médiathèque',
  affiche_id   INT UNSIGNED NULL COMMENT 'vidéo : image d''attente',
  position_x   TINYINT UNSIGNED NULL COMMENT 'point focal, en pourcentage',
  position_y   TINYINT UNSIGNED NULL,
  mobile_x     TINYINT UNSIGNED NULL COMMENT 'point focal sur téléphone',
  mobile_y     TINYINT UNSIGNED NULL,
  lumiere      VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'préréglage de lumière ; vide : l''image telle quelle',
  alt_fr       VARCHAR(500) NOT NULL DEFAULT '',
  alt_en       VARCHAR(500) NOT NULL DEFAULT '',
  opacite      TINYINT UNSIGNED NOT NULL DEFAULT 100,
  nuee         VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'arriere-plan, premier-plan ; vide : valeur par défaut du site',
  son          TINYINT(1)   NOT NULL DEFAULT 0,
  piste        VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'analyse de la bande-son de la vidéo',
  source       VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'YouTube : identifiant ou adresse ; couleur : valeur CSS',
  ordre        INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS musiques (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cle         VARCHAR(60)  NOT NULL DEFAULT '',
  media_id    INT UNSIGNED NULL COMMENT 'médiathèque',
  piste       VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'analyse de la bande-son (data/….json) ; vide tant qu''elle n''est pas faite',
  titre_fr    VARCHAR(255) NOT NULL DEFAULT '',
  titre_en    VARCHAR(255) NOT NULL DEFAULT '',
  mention_fr  VARCHAR(500) NOT NULL DEFAULT '',
  mention_en  VARCHAR(500) NOT NULL DEFAULT '',
  ordre       INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS telechargements (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cle         VARCHAR(60)  NOT NULL DEFAULT '',
  libelle_fr  VARCHAR(255) NOT NULL DEFAULT '',
  libelle_en  VARCHAR(255) NOT NULL DEFAULT '',
  media_id    INT UNSIGNED NULL COMMENT 'médiathèque ; vide : « à venir »',
  ordre       INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts_groupes (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  structure   VARCHAR(190) NOT NULL DEFAULT '',
  lien        VARCHAR(500) NOT NULL DEFAULT '',
  mention_fr  VARCHAR(255) NOT NULL DEFAULT '',
  mention_en  VARCHAR(255) NOT NULL DEFAULT '',
  langue      CHAR(2)      NOT NULL DEFAULT '' COMMENT 'mention et fonctions écrites dans cette langue, non traduites',
  ordre       INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacts_personnes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  groupe_id  INT UNSIGNED NOT NULL,
  nom        VARCHAR(190) NOT NULL DEFAULT '',
  role_fr    VARCHAR(255) NOT NULL DEFAULT '',
  role_en    VARCHAR(255) NOT NULL DEFAULT '',
  telephone  VARCHAR(60)  NOT NULL DEFAULT '',
  email      VARCHAR(190) NOT NULL DEFAULT '',
  ordre      INT          NOT NULL DEFAULT 0,
  KEY groupe_id (groupe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reseaux (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  cle    VARCHAR(30)  NOT NULL DEFAULT '' COMMENT 'instagram, facebook… : choisit le pictogramme',
  nom    VARCHAR(60)  NOT NULL DEFAULT '',
  url    VARCHAR(500) NOT NULL DEFAULT '',
  barre  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'figure aussi dans la barre du haut du site',
  ordre  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- Publication vers le site
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS publications (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nature           ENUM('publication','retour') NOT NULL DEFAULT 'publication',
  compte_id        INT UNSIGNED NULL,
  compte_nom       VARCHAR(120) NOT NULL DEFAULT '',
  dossier          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'sauvegarde des fichiers remplacés, dans stockage/publications/',
  fichiers         MEDIUMTEXT   NULL COMMENT 'JSON : fichiers remplacés et fichiers créés',
  representations  MEDIUMTEXT   NULL COMMENT 'JSON : représentations passées à « publiee »',
  version          VARCHAR(20)  NOT NULL DEFAULT '',
  annulee          TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'un retour en arrière l''a défaite',
  cree_le          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- Import de l'extrait de l'agence
-- ---------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS imports (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nature        ENUM('pdf','tableur','texte') NOT NULL,
  fichier       VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'nom dans stockage/imports/',
  nom_origine   VARCHAR(255) NOT NULL DEFAULT '',
  date_extrait  DATE         NULL,
  statut        ENUM('depose','lecture','a_valider','termine','echec') NOT NULL DEFAULT 'depose',
  tache_id      INT UNSIGNED NULL,
  erreur        VARCHAR(500) NOT NULL DEFAULT '',
  bilan         MEDIUMTEXT   NULL COMMENT 'JSON : décompte des lignes et des contrôles',
  cree_par      INT UNSIGNED NULL,
  cree_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  termine_le    DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Une ligne par représentation lue ou disparue. Rien n'entre dans l'agenda avant validation.
CREATE TABLE IF NOT EXISTS import_lignes (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  import_id          INT UNSIGNED NOT NULL,
  ordre              INT          NOT NULL DEFAULT 0,
  engagement         INT          NOT NULL DEFAULT 0 COMMENT 'rang de l''engagement dans l''extrait',
  nature             ENUM('nouvelle','modifiee','programme','disparue','douteuse','inchangee') NOT NULL,
  concert_id         INT UNSIGNED NULL,
  representation_id  INT UNSIGNED NULL,
  propose            MEDIUMTEXT   NULL COMMENT 'JSON : ce que dit l''extrait',
  avant              MEDIUMTEXT   NULL COMMENT 'JSON : ce que dit l''agenda',
  controles          MEDIUMTEXT   NULL COMMENT 'JSON : remarques du contrôle',
  candidats          MEDIUMTEXT   NULL COMMENT 'JSON : cas douteux, représentations possibles',
  decision           ENUM('en_attente','validee','refusee') NOT NULL DEFAULT 'en_attente',
  decide_par         INT UNSIGNED NULL,
  decide_le          DATETIME     NULL,
  KEY import_id (import_id, ordre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- Places gardées pour les versions suivantes. Aucune de ces tables n'est lue ni écrite.
-- ---------------------------------------------------------------------------------------------

-- Publications sur les réseaux (rédaction, validation, envoi par Metricool).
CREATE TABLE IF NOT EXISTS publications_reseaux (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  concert_id  INT UNSIGNED NULL,
  reseau      VARCHAR(30)  NOT NULL DEFAULT '',
  texte       TEXT         NULL,
  statut      VARCHAR(20)  NOT NULL DEFAULT 'brouillon',
  prevue_le   DATETIME     NULL,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inscrits à la lettre.
CREATE TABLE IF NOT EXISTS lettre_inscrits (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email    VARCHAR(190) NOT NULL,
  langue   CHAR(2)      NOT NULL DEFAULT 'fr',
  statut   VARCHAR(20)  NOT NULL DEFAULT 'a_confirmer',
  cree_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens de dépôt remis aux photographes.
CREATE TABLE IF NOT EXISTS depots (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  fiche_id    INT UNSIGNED NULL COMMENT 'annuaire',
  concert_id  INT UNSIGNED NULL,
  jeton       CHAR(64)     NOT NULL DEFAULT '',
  expire_le   DATETIME     NULL,
  cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Veille et revue de presse.
CREATE TABLE IF NOT EXISTS veille (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  media_id   INT UNSIGNED NULL COMMENT 'annuaire',
  titre      VARCHAR(500) NOT NULL DEFAULT '',
  lien       VARCHAR(500) NOT NULL DEFAULT '',
  paru_le    DATE         NULL,
  statut     VARCHAR(20)  NOT NULL DEFAULT 'a_lire',
  cree_le    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

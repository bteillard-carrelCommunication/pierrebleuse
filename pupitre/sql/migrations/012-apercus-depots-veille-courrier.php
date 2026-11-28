<?php
/**
 * Migration 012 : aperçus à valider, zones de dépôt, publications des réseaux, veille, courrier,
 * mise en page du tableau de bord, discographie allégée.
 *
 * Aperçus (`apercus`, `apercus_validations`). Un aperçu est une copie figée du site tel qu'il serait
 * publié, rangée dans <site>/apercu/<jeton>/ jusqu'à son expiration. Une demande de validation
 * s'adresse à un compte de Pupitre, par un lien personnel : sa décision (valider, demander des
 * corrections), sa déclaration, la date, l'adresse IP et le navigateur font le procès-verbal.
 *
 * Zones de dépôt (`depots`, `depots_fichiers`). La table `depots`, place gardée depuis la version 1
 * et jamais lue ni écrite, prend son service : une zone par concert, ouverte par un lien, où le
 * contact « communication » du concert (trois colonnes de plus sur `concerts`) dépose ses photos et
 * ses courtes vidéos. Les fichiers restent sur le serveur, hors du web (stockage/depots/).
 *
 * Publications des réseaux (`posts_sociaux`). Ce que la plateforme connectée (Metricool) dit de ce
 * qui a été publié sur les comptes de l'artiste : texte, date, mesures. Une ligne par publication et
 * par marque, rattachée à l'action de communication de Pupitre quand elle en vient.
 *
 * Veille (`veille`). La table gardée de la version 1 (vide) reçoit ses colonnes : d'où vient le
 * signalement, sa nature, un extrait, si l'artiste y est identifié ou seulement cité, ce qu'on en a fait.
 *
 * Courrier (`courriers`). Chaque mail envoyé par Pupitre, et chaque retour lu dans la boîte de
 * l'expéditeur (réponse, absence, rejet), rapproché du mail auquel il répond.
 *
 * Comptes : `preferences` (JSON) garde, par compte, la mise en page du tableau de bord.
 * Annuaire : un compte de réseau social dit d'où il vient (saisi, ou proposé par l'IA, avec sa source).
 * Discographie : les rubriques « contexte », « citations » et « informations sur les œuvres » sont
 * retirées (aucun album ne les employait) : restent la présentation et la note de l'artiste.
 *
 * Défaire : tout est retiré, y compris ce qui aurait été saisi depuis dans ces tables. Les dossiers
 * d'aperçus et les fichiers déposés ne sont pas touchés (stockage/depots/, <site>/apercu/).
 */

defined('PUPITRE') or exit;

const MIGRATION_012_DISQUES = ['contexte', 'citations', 'infos'];

return [
    'titre' => 'Aperçus à valider, zones de dépôt, publications des réseaux, veille, courrier, mise en page du tableau de bord',

    'monter' => function () {
        $moteur = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // ---------- aperçus et validations ----------
        bd()->exec("CREATE TABLE IF NOT EXISTS apercus (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            jeton         CHAR(32)     NOT NULL COMMENT 'nom du dossier public : <site>/apercu/<jeton>/',
            titre         VARCHAR(190) NOT NULL DEFAULT '',
            note          TEXT         NULL COMMENT 'ce que le pilote dit de ces évolutions : montré au destinataire, repris au procès-verbal',
            empreinte     CHAR(64)     NOT NULL DEFAULT '' COMMENT 'SHA-256 de ce qui serait publié : ce qui a été montré, à l''octet près',
            resume        MEDIUMTEXT   NULL COMMENT 'JSON : fichiers modifiés, médias, représentations',
            cree_par      INT UNSIGNED NULL,
            cree_par_nom  VARCHAR(190) NOT NULL DEFAULT '',
            cree_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expire_le     DATETIME     NOT NULL,
            retire_le     DATETIME     NULL COMMENT 'dossier supprimé : à l''expiration, ou à la main',
            UNIQUE KEY jeton (jeton),
            KEY expire_le (expire_le)
        ) $moteur");
        bd()->exec("CREATE TABLE IF NOT EXISTS apercus_validations (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            apercu_id    INT UNSIGNED NOT NULL,
            compte_id    INT UNSIGNED NULL,
            nom          VARCHAR(190) NOT NULL DEFAULT '',
            email        VARCHAR(190) NOT NULL DEFAULT '',
            jeton        CHAR(64)     NOT NULL COMMENT 'SHA-256 du jeton du lien personnel : le jeton lui-même ne se relit pas',
            envoye_le    DATETIME     NULL,
            vu_le        DATETIME     NULL,
            decision     ENUM('attente', 'validee', 'corrections') NOT NULL DEFAULT 'attente',
            decide_le    DATETIME     NULL,
            remarques    TEXT         NULL,
            declaration  TEXT         NULL COMMENT 'le texte exact que la personne a accepté',
            ip           VARCHAR(45)  NOT NULL DEFAULT '',
            agent        VARCHAR(255) NOT NULL DEFAULT '',
            cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY jeton (jeton),
            KEY apercu_id (apercu_id)
        ) $moteur");

        // ---------- zones de dépôt ----------
        bd_colonne_ajouter('concerts', 'contact_com_nom', "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'contact communication : la personne qui partagera photos et vidéos'");
        bd_colonne_ajouter('concerts', 'contact_com_email', "VARCHAR(190) NOT NULL DEFAULT ''");
        bd_colonne_ajouter('concerts', 'contact_com_langue', "VARCHAR(8) NOT NULL DEFAULT 'en' COMMENT 'langue du mail d''invitation'");
        if (!bd_table_existe('depots')) {
            bd()->exec("CREATE TABLE depots (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                fiche_id    INT UNSIGNED NULL,
                concert_id  INT UNSIGNED NULL,
                jeton       CHAR(64)     NOT NULL DEFAULT '',
                expire_le   DATETIME     NULL,
                cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $moteur");
        }
        bd_colonne_ajouter('depots', 'invite_le', "DATETIME NULL COMMENT 'envoi du mail d''invitation'");
        bd_colonne_ajouter('depots', 'invite_a', "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'adresse à laquelle l''invitation est partie'");
        bd_colonne_ajouter('depots', 'ouvert_le', "DATETIME NULL COMMENT 'première visite de la zone'");
        bd_colonne_ajouter('depots', 'ferme', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'zone fermée à la main : plus aucun dépôt'");
        if (!bd_index_existe('depots', 'jeton')) bd()->exec('ALTER TABLE depots ADD KEY jeton (jeton)');
        if (!bd_index_existe('depots', 'concert_id')) bd()->exec('ALTER TABLE depots ADD KEY concert_id (concert_id)');
        bd()->exec("CREATE TABLE IF NOT EXISTS depots_fichiers (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            depot_id   INT UNSIGNED NOT NULL,
            nom        VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'nom du fichier tel qu''il a été déposé',
            fichier    VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'nom sous lequel il est rangé (stockage/depots/<zone>/)',
            type       VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'type du contenu, relu sur le fichier',
            taille     BIGINT UNSIGNED NOT NULL DEFAULT 0,
            deposant   VARCHAR(190) NOT NULL DEFAULT '',
            credit     VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'crédit à mentionner, donné par le déposant',
            legende    VARCHAR(500) NOT NULL DEFAULT '',
            ip         VARCHAR(45)  NOT NULL DEFAULT '',
            statut     ENUM('recu', 'retenu', 'ecarte') NOT NULL DEFAULT 'recu',
            media_id   INT UNSIGNED NULL COMMENT 'le média de la médiathèque, une fois le fichier retenu',
            depose_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY depot_id (depot_id)
        ) $moteur");

        // ---------- publications des réseaux ----------
        bd()->exec("CREATE TABLE IF NOT EXISTS posts_sociaux (
            id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            marque            VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'identifiant de la marque sur la plateforme (Metricool : blogId)',
            reseau            VARCHAR(30)  NOT NULL DEFAULT '',
            identifiant       VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'identifiant de la publication sur le réseau',
            nature            VARCHAR(20)  NOT NULL DEFAULT 'post' COMMENT 'post, reel, story, video',
            publie_le         DATETIME     NULL,
            texte             TEXT         NULL,
            lien              VARCHAR(1000) NOT NULL DEFAULT '',
            visuel            VARCHAR(1000) NOT NULL DEFAULT '' COMMENT 'adresse de la vignette, telle que la plateforme la donne',
            impressions       INT UNSIGNED NULL,
            portee            INT UNSIGNED NULL,
            interactions      INT UNSIGNED NULL,
            likes             INT UNSIGNED NULL,
            commentaires      INT UNSIGNED NULL,
            partages          INT UNSIGNED NULL,
            enregistrements   INT UNSIGNED NULL,
            clics             INT UNSIGNED NULL,
            vues              INT UNSIGNED NULL,
            engagement        DECIMAL(8,3) NULL COMMENT 'taux d''engagement, en pour cent, tel que la plateforme le calcule',
            communication_id  INT UNSIGNED NULL COMMENT 'l''action de communication de Pupitre dont cette publication vient',
            theme             VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'ce dont elle parle : concert, presse, album…',
            concert_id        INT UNSIGNED NULL,
            note              VARCHAR(500) NOT NULL DEFAULT '',
            releve_le         DATETIME     NULL COMMENT 'dernière lecture des mesures',
            cree_le           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY publication (marque, reseau, identifiant),
            KEY publie_le (publie_le),
            KEY communication_id (communication_id)
        ) $moteur");
        bd_colonne_ajouter('communications', 'marque', "VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'marque de la plateforme à laquelle l''action a été envoyée' AFTER reference_externe");

        // ---------- annuaire : d'où vient un compte de réseau social ----------
        bd_colonne_ajouter('annuaire_comptes', 'origine', "VARCHAR(20) NOT NULL DEFAULT 'main' COMMENT 'main, ia' AFTER etat");
        bd_colonne_ajouter('annuaire_comptes', 'source', "VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'la page où l''IA l''a trouvé' AFTER origine");

        // ---------- veille ----------
        if (!bd_table_existe('veille')) {
            bd()->exec("CREATE TABLE veille (
                id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                media_id  INT UNSIGNED NULL,
                titre     VARCHAR(500) NOT NULL DEFAULT '',
                lien      VARCHAR(500) NOT NULL DEFAULT '',
                paru_le   DATE         NULL,
                statut    VARCHAR(20)  NOT NULL DEFAULT 'a_lire',
                cree_le   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) $moteur");
        }
        bd()->exec("ALTER TABLE veille MODIFY lien VARCHAR(1000) NOT NULL DEFAULT ''");
        bd_colonne_ajouter('veille', 'source', "VARCHAR(30) NOT NULL DEFAULT 'manuel' COMMENT 'actualites (flux), recherche (IA), reseaux (plateforme), manuel' AFTER id");
        bd_colonne_ajouter('veille', 'nature', "VARCHAR(20) NOT NULL DEFAULT 'web' COMMENT 'presse, web, social' AFTER source");
        bd_colonne_ajouter('veille', 'reseau', "VARCHAR(30) NOT NULL DEFAULT '' AFTER nature");
        bd_colonne_ajouter('veille', 'site', "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'le média ou le compte qui publie' AFTER titre");
        bd_colonne_ajouter('veille', 'auteur', "VARCHAR(190) NOT NULL DEFAULT '' AFTER site");
        bd_colonne_ajouter('veille', 'extrait', "TEXT NULL AFTER auteur");
        bd_colonne_ajouter('veille', 'langue', "VARCHAR(8) NOT NULL DEFAULT '' AFTER extrait");
        bd_colonne_ajouter('veille', 'mention', "ENUM('identifie', 'cite', 'a_verifier') NOT NULL DEFAULT 'a_verifier' COMMENT 'l''artiste est identifié (compte mentionné), ou seulement cité par son nom' AFTER langue");
        bd_colonne_ajouter('veille', 'requete', "VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'la recherche qui l''a trouvé' AFTER mention");
        bd_colonne_ajouter('veille', 'empreinte', "CHAR(40) NOT NULL DEFAULT '' COMMENT 'SHA-1 de l''adresse ramenée à sa forme canonique : un signalement ne se note qu''une fois' AFTER requete");
        bd_colonne_ajouter('veille', 'note', "VARCHAR(500) NOT NULL DEFAULT '' AFTER statut");
        bd_colonne_ajouter('veille', 'article_id', "INT UNSIGNED NULL COMMENT 'l''article de la revue de presse qui en a été tiré'");
        bd_colonne_ajouter('veille', 'communication_id', "INT UNSIGNED NULL COMMENT 'l''action de communication qui le partage'");
        bd_colonne_ajouter('veille', 'traite_par', "INT UNSIGNED NULL");
        bd_colonne_ajouter('veille', 'traite_le', "DATETIME NULL");
        if (!bd_index_existe('veille', 'empreinte')) bd()->exec('ALTER TABLE veille ADD KEY empreinte (empreinte)');
        if (!bd_index_existe('veille', 'statut')) bd()->exec('ALTER TABLE veille ADD KEY statut (statut, paru_le)');

        // ---------- courrier ----------
        bd()->exec("CREATE TABLE IF NOT EXISTS courriers (
            id                   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            sens                 ENUM('envoye', 'recu') NOT NULL,
            statut               VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'envoyé : envoye, non_envoye, echec, puis ce que le routeur en dit (remis, ouvert, clique, rejete, spam, bloque) ; reçu : a_lire, lu',
            type_retour          VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'reçu : reponse, absence, rejet, autre',
            correspondant_email  VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'le destinataire d''un envoi, l''expéditeur d''un retour',
            correspondant_nom    VARCHAR(190) NOT NULL DEFAULT '',
            sujet                VARCHAR(255) NOT NULL DEFAULT '',
            texte                MEDIUMTEXT   NULL COMMENT 'vide pour un envoi qui porte un lien personnel',
            nature               VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'connexion, apercu, depot…',
            objet_table          VARCHAR(40)  NOT NULL DEFAULT '',
            objet_id             INT UNSIGNED NULL,
            identifiant          VARCHAR(255) NULL COMMENT 'envoyé : identifiant du message chez le routeur ; reçu : Message-ID',
            uid                  INT UNSIGNED NULL COMMENT 'reçu : numéro du message dans la boîte',
            reponse_a            INT UNSIGNED NULL COMMENT 'reçu : l''envoi auquel ce retour répond',
            date_message         DATETIME     NULL,
            cree_le              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY message (sens, identifiant),
            KEY correspondant (correspondant_email),
            KEY date_message (sens, date_message)
        ) $moteur");

        // ---------- comptes : préférences d'interface ----------
        bd_colonne_ajouter('comptes', 'preferences', "TEXT NULL COMMENT 'JSON : mise en page du tableau de bord'");

        // ---------- discographie : trois rubriques retirées (présentation et note de l'artiste restent) ----------
        $gardes = [];
        foreach (MIGRATION_012_DISQUES as $champ) {
            foreach (bd_colonnes('disques') as $colonne) {
                if ($colonne !== $champ && !str_starts_with($colonne, $champ . '_')) continue;
                // rien ne se perd : ce qui aurait été saisi est gardé dans un réglage avant de retirer la colonne
                foreach (bd_lignes('SELECT id, ' . bd_nom($colonne) . ' AS texte FROM disques WHERE ' . bd_nom($colonne) . " IS NOT NULL AND " . bd_nom($colonne) . " <> ''") as $l) {
                    $gardes[] = ['id' => (int) $l['id'], 'colonne' => $colonne, 'texte' => $l['texte']];
                }
                bd_colonne_retirer('disques', $colonne);
            }
            foreach (bd_lignes("SELECT * FROM traductions WHERE objet = 'disques' AND champ = ?", [$champ]) as $t) $gardes[] = ['traduction' => $t];
            bd_requete("DELETE FROM traductions WHERE objet = 'disques' AND champ = ?", [$champ]);
        }
        reglage_ecrire('migration_012_disques', json_encode($gardes, JSON_UNESCAPED_UNICODE));
    },

    'descendre' => function () {
        // discographie : les colonnes reviennent, avec ce qu'elles portaient
        $reference = function_exists('langue_reference') ? langue_reference() : 'en';
        foreach (MIGRATION_012_DISQUES as $champ) bd_colonne_ajouter('disques', $champ . '_' . $reference, 'TEXT NULL');
        foreach (json_decode((string) reglage('migration_012_disques'), true) ?: [] as $g) {
            if (isset($g['traduction'])) {
                $t = $g['traduction'];
                unset($t['id']);
                try { bd_inserer('traductions', $t); } catch (Throwable $e) { /* déjà là */ }
            } elseif (bd_colonne_existe('disques', $g['colonne'])) {
                bd_requete('UPDATE disques SET ' . bd_nom($g['colonne']) . ' = ? WHERE id = ?', [$g['texte'], $g['id']]);
            }
        }
        bd_requete("DELETE FROM reglages WHERE cle = 'migration_012_disques'");

        bd_colonne_retirer('comptes', 'preferences');
        bd_table_retirer('courriers');

        foreach (['source', 'nature', 'reseau', 'site', 'auteur', 'extrait', 'langue', 'mention', 'requete', 'empreinte', 'note', 'article_id', 'communication_id', 'traite_par', 'traite_le'] as $c) {
            if ($c === 'empreinte' && bd_index_existe('veille', 'empreinte')) bd()->exec('ALTER TABLE veille DROP KEY empreinte');
            bd_colonne_retirer('veille', $c);
        }
        if (bd_index_existe('veille', 'statut')) bd()->exec('ALTER TABLE veille DROP KEY statut');
        bd_requete('DELETE FROM veille WHERE CHAR_LENGTH(lien) > 500');
        bd()->exec("ALTER TABLE veille MODIFY lien VARCHAR(500) NOT NULL DEFAULT ''");

        bd_colonne_retirer('annuaire_comptes', 'source');
        bd_colonne_retirer('annuaire_comptes', 'origine');
        bd_colonne_retirer('communications', 'marque');
        bd_table_retirer('posts_sociaux');

        bd_table_retirer('depots_fichiers');
        foreach (['jeton', 'concert_id'] as $index) if (bd_index_existe('depots', $index)) bd()->exec('ALTER TABLE depots DROP KEY ' . $index);
        foreach (['invite_le', 'invite_a', 'ouvert_le', 'ferme'] as $c) bd_colonne_retirer('depots', $c);
        bd_requete('DELETE FROM depots');
        foreach (['contact_com_nom', 'contact_com_email', 'contact_com_langue'] as $c) bd_colonne_retirer('concerts', $c);

        bd_table_retirer('apercus_validations');
        bd_table_retirer('apercus');
        bd_requete("DELETE FROM alertes WHERE type IN ('apercu', 'depot', 'veille', 'courrier')");
    },
];

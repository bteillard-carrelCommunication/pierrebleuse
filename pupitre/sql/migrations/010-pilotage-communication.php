<?php
/**
 * Migration 010 : pilotage de la communication, niveaux des alertes.
 *
 * Communication : une table `communications` reçoit les actions de communication, quel que soit
 * leur canal (publication sur un réseau social, lettre d'information) : ce qui est à préparer, ce
 * qui est programmé, ce qui est publié. Une action peut concerner plusieurs concerts
 * (`communications_concerts`), et un concert plusieurs actions. Rien n'est repris : aucune donnée
 * de communication n'existait. Les colonnes `origine` et `reference_externe` attendent la
 * connexion aux plateformes, qui viendra dans une seconde phase ; d'ici là tout se saisit à la main.
 *
 * La table `publications_reseaux`, place gardée depuis la version 1 et jamais lue ni écrite, est
 * remplacée par `communications` : elle est retirée si elle est vide (défaire la migration la remet).
 *
 * Alertes : chaque alerte porte désormais un niveau (prioritaire, action, information), posé à son
 * calcul. Les alertes en place prennent le niveau « action » jusqu'à leur prochain recalcul.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Pilotage de la communication : actions, concerts associés ; niveaux des alertes',

    'monter' => function () {
        bd()->exec("CREATE TABLE IF NOT EXISTS communications (
            id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            canal              VARCHAR(30)  NOT NULL DEFAULT '' COMMENT 'instagram, facebook, youtube, linkedin, lettre, autre',
            titre              VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'objet de la lettre, ou intitulé court de la publication',
            texte              TEXT         NULL COMMENT 'texte prévu, ou texte publié',
            statut             ENUM('a_preparer', 'programmee', 'publiee') NOT NULL DEFAULT 'a_preparer',
            prevue_le          DATETIME     NULL COMMENT 'date prévue de publication ou d''envoi',
            publiee_le         DATETIME     NULL COMMENT 'date réelle de publication ou d''envoi',
            lien               VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'adresse de la publication en ligne',
            notes              TEXT         NULL COMMENT 'notes internes',
            origine            VARCHAR(30)  NOT NULL DEFAULT 'main' COMMENT 'main ; plus tard, la plateforme connectée',
            reference_externe  VARCHAR(190) NOT NULL DEFAULT '' COMMENT 'identifiant sur la plateforme, pour une synchronisation future',
            cree_par           INT UNSIGNED NULL,
            cree_le            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modifie_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY statut (statut, prevue_le),
            KEY canal (canal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        bd()->exec("CREATE TABLE IF NOT EXISTS communications_concerts (
            communication_id  INT UNSIGNED NOT NULL,
            concert_id        INT UNSIGNED NOT NULL,
            PRIMARY KEY (communication_id, concert_id),
            KEY concert_id (concert_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // la place gardée de la version 1 : retirée seulement si elle n'a jamais servi
        if (bd_table_existe('publications_reseaux') && !(int) bd_valeur('SELECT COUNT(*) FROM publications_reseaux')) {
            bd_table_retirer('publications_reseaux');
        }

        bd_colonne_ajouter('alertes', 'niveau', "ENUM('prioritaire', 'action', 'info') NOT NULL DEFAULT 'action' COMMENT 'alerte prioritaire, action à traiter, information' AFTER type");
    },

    'descendre' => function () {
        bd_colonne_retirer('alertes', 'niveau');
        bd_requete("DELETE FROM alertes WHERE type IN ('communication', 'publication', 'echeance', 'technique')");
        bd_table_retirer('communications_concerts');
        bd_table_retirer('communications');
        bd()->exec("CREATE TABLE IF NOT EXISTS publications_reseaux (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            concert_id  INT UNSIGNED NULL,
            reseau      VARCHAR(30)  NOT NULL DEFAULT '',
            texte       TEXT         NULL,
            statut      VARCHAR(20)  NOT NULL DEFAULT 'brouillon',
            prevue_le   DATETIME     NULL,
            cree_le     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },
];

<?php
/**
 * Migration 003 : une médiathèque commune, des médias distincts de leurs usages.
 *
 * Un média est un fichier déposé, un fichier déjà présent dans le site, ou désormais une adresse
 * externe (image, vidéo, YouTube). Il porte ce qui lui est propre : titre, crédit, droits, texte
 * alternatif. La table `medias_usages` dit où il sert : dans quel objet, à quel titre, à quel rang,
 * avec quels réglages (cadrage, animation, son). Un même média sert à plusieurs endroits sans être
 * copié ; aucun fichier existant n'est déplacé ni renommé.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Médias : adresses externes, titres, usages',

    'monter' => function () {
        bd_colonne_ajouter('medias', 'url_externe', "VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'adresse externe (image, vidéo, YouTube) : aucun fichier n''est alors copié' AFTER chemin_site");
        bd_colonne_ajouter('medias', 'titre', "VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'nom du média dans Pupitre' AFTER nom_origine");
        bd_colonne_ajouter('medias', 'droits_valides', "TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 : droits d''utilisation à confirmer, le média ne sort pas sur le site' AFTER droits");

        bd()->exec("CREATE TABLE IF NOT EXISTS medias_usages (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            media_id        INT UNSIGNED NOT NULL COMMENT 'médiathèque',
            objet           VARCHAR(40)  NOT NULL COMMENT 'table de l''objet qui emploie le média : blocs, disques, articles…',
            objet_id        VARCHAR(80)  NOT NULL COMMENT 'clé de la ligne',
            role            VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'à quel titre : element, visuel…',
            nature          VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'image, animation, video : comment le média est montré',
            affiche_id      INT UNSIGNED NULL COMMENT 'vidéo : image d''attente (médiathèque)',
            titre_fr        VARCHAR(255) NOT NULL DEFAULT '',
            description_fr  TEXT         NULL,
            alt_fr          VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'vide : le texte alternatif du média',
            credit          VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'vide : le crédit du média',
            parametres      TEXT         NULL COMMENT 'JSON : cadrage, module d''animation et ses réglages, son, opacité',
            publie          TINYINT(1)   NOT NULL DEFAULT 1,
            ordre           INT          NOT NULL DEFAULT 0,
            cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            modifie_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY objet (objet, objet_id, role, ordre),
            KEY media_id (media_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'descendre' => function () {
        bd_table_retirer('medias_usages');
        bd_requete("DELETE FROM traductions WHERE objet = 'medias_usages'");
        bd_colonne_retirer('medias', 'droits_valides');
        bd_colonne_retirer('medias', 'titre');
        bd_colonne_retirer('medias', 'url_externe');
    },
];

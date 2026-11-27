<?php
/**
 * Migration 002 : les blocs du site.
 *
 * Un bloc est une section de la page d'accueil. Sa clé est stable : c'est l'identifiant de la
 * section dans le HTML (id="agenda"), quel que soit son contenu. Chaque bloc porte sa visibilité
 * et, plus tard, ses paramètres. L'ordre des blocs est celui de la page : ce n'est pas un
 * constructeur de pages.
 *
 * Valeurs de départ : tous les blocs sont visibles, comme sur le site d'aujourd'hui.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Blocs du site et leur visibilité',

    'monter' => function () {
        bd()->exec("CREATE TABLE IF NOT EXISTS blocs (
            cle         VARCHAR(40)  NOT NULL PRIMARY KEY COMMENT 'identifiant de la section dans la page : agenda, visuel-1…',
            nom         VARCHAR(80)  NOT NULL DEFAULT '' COMMENT 'nom du bloc dans Pupitre',
            parent      VARCHAR(40)  NOT NULL DEFAULT '' COMMENT 'section qui le contient, s''il n''est qu''une partie de section',
            visible     TINYINT(1)   NOT NULL DEFAULT 1,
            masquable   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 : toujours affiché (pied de page et ses liens légaux)',
            parametres  TEXT         NULL COMMENT 'JSON : réglages propres au bloc',
            ordre       INT          NOT NULL DEFAULT 0,
            modifie_le  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $blocs = [
            ['ouverture', 'Hero', ''],
            ['bio', 'Édito', ''],
            ['visuel-1', 'Visuel 1', ''],
            ['agenda', 'Agenda', ''],
            ['visuel-2', 'Visuel 2', ''],
            ['disques', 'Discographie', ''],
            ['parcours-fonctions', 'Fonctions', 'parcours'],
            ['parcours-collaborations', 'Grands débuts et collaborations', 'parcours'],
            ['parcours-monde', 'Présence dans le monde', 'parcours'],
            ['visuel-3', 'Visuel 3', ''],
            ['presse', 'Presse', ''],
            ['telechargement', 'Téléchargements', ''],
            ['suivre', 'Restons en contact', ''],
            ['contact', 'Contact', ''],
            ['pied', 'Pied de page', ''],
        ];
        foreach ($blocs as $i => [$cle, $nom, $parent]) {
            bd_requete('INSERT IGNORE INTO blocs (cle, nom, parent, visible, masquable, ordre) VALUES (?, ?, ?, 1, ?, ?)', [$cle, $nom, $parent, $cle === 'pied' ? 0 : 1, $i]);
        }
    },

    'descendre' => function () {
        bd_table_retirer('blocs');
    },
];

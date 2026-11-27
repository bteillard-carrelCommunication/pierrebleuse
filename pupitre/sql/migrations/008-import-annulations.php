<?php
/**
 * Migration 008 : import des concerts, annulation annoncée par le document.
 *
 * Une ligne d'import peut désormais être une « annulation annoncée » : le document dit en toutes
 * lettres qu'une date est annulée. C'est autre chose qu'une date « disparue », simplement absente
 * du document, que rien n'annule d'office. Dans les deux cas, l'agenda ne change qu'à la validation.
 */

defined('PUPITRE') or exit;

return [
    'titre' => 'Import des concerts : annulation annoncée par le document',

    'monter' => function () {
        bd()->exec("ALTER TABLE import_lignes MODIFY nature ENUM('nouvelle','modifiee','programme','disparue','douteuse','inchangee','annulee') NOT NULL");
    },

    'descendre' => function () {
        // une annulation annoncée encore en attente redevient un cas douteux : elle n'est pas perdue
        bd()->exec("UPDATE import_lignes SET nature = 'douteuse' WHERE nature = 'annulee'");
        bd()->exec("ALTER TABLE import_lignes MODIFY nature ENUM('nouvelle','modifiee','programme','disparue','douteuse','inchangee') NOT NULL");
    },
];

<?php
/**
 * Rôles et droits : tout est ici, et nulle part ailleurs.
 *
 *   Pilote    Carrel, direction du projet : tout (réglages, comptes, validations, publication).
 *   Éditeur   équipe Carrel : agenda, fiches, annuaire, contenus, médias, validation des imports.
 *             Ni réglages, ni comptes, ni publication.
 *   Artiste   sa vue, en lecture. Une seule action : laisser une note sur un concert.
 *
 * Chaque page et chaque action vérifie son droit côté serveur (exiger_droit).
 */

defined('PUPITRE') or exit;

const ROLES = ['pilote' => 'Pilote', 'editeur' => 'Éditeur', 'artiste' => 'Artiste'];

const DROITS = [
    'bord'          => ['pilote', 'editeur'],   // tableau de bord, alertes, « à valider »
    'agenda'        => ['pilote', 'editeur'],   // agenda et fiches concert
    'annuaire'      => ['pilote', 'editeur'],
    'contenus'      => ['pilote', 'editeur'],   // contenus du site
    'medias'        => ['pilote', 'editeur'],   // médiathèque
    'import'        => ['pilote', 'editeur'],   // extrait de l'agence : dépôt et validation
    'site.voir'     => ['pilote', 'editeur'],   // écran « Le site » : écarts, prévisualisation
    'site.publier'  => ['pilote'],              // publier, revenir à la publication précédente
    'journal'       => ['pilote', 'editeur'],
    'reglages'      => ['pilote'],
    'comptes'       => ['pilote'],
    'artiste.vue'   => ['pilote', 'editeur', 'artiste'],
    'artiste.note'  => ['artiste'],
];

function role_peut(?string $role, string $droit): bool
{
    return $role !== null && in_array($role, DROITS[$droit] ?? [], true);
}

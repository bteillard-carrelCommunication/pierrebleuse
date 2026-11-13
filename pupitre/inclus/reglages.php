<?php
/**
 * Réglages de l'installation, en base : rien de propre à l'artiste n'est écrit dans le code.
 * Une installation sert un artiste.
 */

defined('PUPITRE') or exit;

/** Les réglages et leur valeur par défaut. */
const REGLAGES = [
    'artiste_nom'   => '',               // nom de l'artiste
    'sous_titre'    => '',               // affiché sous « Pupitre »
    'langues'       => 'fr,en',          // langues des contenus, la première est la langue de référence
    'fuseau'        => 'Europe/Paris',   // fuseau de l'artiste et de l'agence
    'site_racine'   => '..',             // racine du site à alimenter, relative à pupitre/ ou absolue
    'site_adresse'  => '',               // adresse du site, pour les liens « voir sur le site »
    'saison_debut'  => '',               // AAAA-MM-JJ ; vide : 1er septembre de la saison en cours
    'saison_fin'    => '',               // AAAA-MM-JJ ; vide : 31 août suivant
];

function reglages_tous(): array
{
    static $r = null;
    if ($r === null || !empty($GLOBALS['reglages_perimes'])) {
        $r = REGLAGES;
        try {
            foreach (bd_lignes('SELECT cle, valeur FROM reglages') as $l) $r[$l['cle']] = $l['valeur'];
        } catch (Throwable $e) {
            // base absente ou pas encore installée : valeurs par défaut
        }
        $GLOBALS['reglages_perimes'] = false;
    }
    return $r;
}

function reglage(string $cle): string
{
    return (string) (reglages_tous()[$cle] ?? '');
}

function reglage_ecrire(string $cle, string $valeur): void
{
    bd_requete('INSERT INTO reglages (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)', [$cle, $valeur]);
    $GLOBALS['reglages_perimes'] = true;
}

/** Langues des contenus : ['fr', 'en']. */
function langues(): array
{
    $l = array_values(array_filter(array_map('trim', explode(',', reglage('langues')))));
    return $l ?: ['fr'];
}

/** Racine du site à alimenter, en chemin absolu ; '' si elle n'existe pas. */
function site_racine(): string
{
    $r = reglage('site_racine') ?: '..';
    $chemin = ($r[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $r)) ? $r : RACINE . '/' . $r;
    $reel = realpath($chemin);
    return ($reel && is_dir($reel)) ? $reel : '';
}

/** Adresse du site : le réglage, sinon le dossier parent de Pupitre. */
function site_adresse(): string
{
    $a = rtrim(reglage('site_adresse'), '/');
    if ($a !== '') return $a . '/';
    $p = adresse_pupitre();
    return $p === '' ? '' : preg_replace('#/[^/]+$#', '/', $p);
}

/** Bornes de la saison en cours : [début, fin]. Sans réglage : du 1er septembre au 31 août. */
function saison(): array
{
    $d = reglage('saison_debut');
    $f = reglage('saison_fin');
    if (!date_valide($d) || !date_valide($f)) {
        $a = (int) date('Y') - ((int) date('n') >= 9 ? 0 : 1);
        $d = $a . '-09-01';
        $f = ($a + 1) . '-08-31';
    }
    return [$d, $f];
}

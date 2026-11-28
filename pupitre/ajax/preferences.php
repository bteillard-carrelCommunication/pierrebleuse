<?php
/**
 * Préférences d'interface du compte : la mise en page du tableau de bord, enregistrée au fil des gestes.
 *
 *     POST, JSON : { "cle": "bord", "valeur": { "haut": ["raccourcis"], "gauche": […], "droite": […] } }
 *
 * Seules les clés connues sont acceptées, et leur valeur est ramenée à sa forme attendue : une
 * préférence ne porte jamais autre chose que des noms de blocs.
 */

require __DIR__ . '/../inclus/amorce.php';

if (!est_post()) repondre_json(['erreur' => 'Méthode refusée.'], 405);
verifier_csrf();
if (!compte()) repondre_json(['erreur' => 'Session expirée : rechargez la page.'], 403);

$d = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($d) || ($d['cle'] ?? '') !== 'bord' || !is_array($d['valeur'] ?? null)) repondre_json(['erreur' => 'Demande illisible.'], 400);

$disposition = [];
foreach (['haut', 'gauche', 'droite'] as $zone) {
    $disposition[$zone] = [];
    foreach (is_array($d['valeur'][$zone] ?? null) ? $d['valeur'][$zone] : [] as $bloc) {
        if (is_string($bloc) && preg_match('/^[a-z-]{1,30}$/', $bloc)) $disposition[$zone][] = $bloc;
    }
}
preference_ecrire('bord', $disposition);
repondre_json(['ok' => true, 'message' => 'Disposition enregistrée.']);

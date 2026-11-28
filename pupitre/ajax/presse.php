<?php
/**
 * Composition de l'accueil de la revue de presse, au fil des gestes : l'écran envoie l'ordre des
 * articles et leur largeur à chaque déplacement (glisser-déposer, flèches) ou changement de largeur.
 *
 *     POST, JSON : { "ordre": [12, 4, 9], "largeurs": { "12": 2, "4": 1, "9": 4 } }
 */

require __DIR__ . '/../inclus/amorce.php';

if (!est_post()) repondre_json(['erreur' => 'Méthode refusée.'], 405);
verifier_csrf();
if (!peut('contenus')) repondre_json(['erreur' => 'Accès refusé.'], 403);

$d = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($d) || !is_array($d['ordre'] ?? null)) repondre_json(['erreur' => 'Demande illisible.'], 400);
$largeurs = [];
foreach (is_array($d['largeurs'] ?? null) ? $d['largeurs'] : [] as $id => $l) $largeurs[(int) $id] = (int) $l;

$n = accueil_composer($d['ordre'], $largeurs);
repondre_json(['ok' => true, 'modifies' => $n, 'message' => $n ? 'Composition enregistrée. Le site suivra à la prochaine publication.' : 'Rien n\'a changé.']);

<?php
/** Recherche de la barre du haut : écrans, concerts, articles, albums, fiches, actions de communication, selon les droits du compte. */

require __DIR__ . '/../inclus/amorce.php';

$c = exiger_connexion();
if ($c['role'] === 'artiste') repondre_json(['erreur' => 'Accès refusé.'], 403);
$groupes = [];
foreach (recherche(mb_substr(recu('q'), 0, 80)) as [$titre, $resultats]) {
    $groupes[] = ['titre' => $titre, 'resultats' => array_map(fn($r) => ['libelle' => $r[0], 'detail' => $r[1], 'url' => $r[2], 'icone' => icone($r[3])], $resultats)];
}
repondre_json(['groupes' => $groupes]);

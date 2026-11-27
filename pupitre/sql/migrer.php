<?php
/**
 * Migrations de la base (inclus/migrations.php).
 *
 *     php pupitre/sql/migrer.php            passe les migrations en attente (la base est sauvegardée avant)
 *     php pupitre/sql/migrer.php --etat     dit où en est la base, sans rien toucher
 *     php pupitre/sql/migrer.php --defaire  défait la dernière migration passée (la base est sauvegardée avant)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ligne de commande seulement.'); }

require __DIR__ . '/../inclus/amorce.php';

$dire = fn(string $s) => print($s . "\n");
migrations_table();

if (in_array('--etat', $argv, true)) {
    $passees = migrations_passees();
    foreach (migrations_disponibles() as $nom => $chemin) {
        $m = require $chemin;
        $dire(sprintf('  %-8s %s  %s', in_array($nom, $passees, true) ? 'passée' : 'ATTEND', $nom, $m['titre'] ?? ''));
    }
    exit(0);
}
if (in_array('--defaire', $argv, true)) {
    if (migration_defaire($dire) === null) $dire('Aucune migration à défaire.');
    exit(0);
}
$faites = migrer($dire);
$dire($faites ? pluriel(count($faites), 'migration passée', 'migrations passées') . '.' : 'La base est à jour : aucune migration en attente.');

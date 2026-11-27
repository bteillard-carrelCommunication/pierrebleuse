<?php
/**
 * Restaure une sauvegarde de la base écrite par Pupitre (inclus/sauvegarde.php).
 *
 *     php pupitre/sql/restaurer.php stockage/sauvegardes/<fichier>.sql --oui
 *
 * Toutes les tables du fichier sont remplacées par celles de la sauvegarde. L'état d'avant est
 * d'abord sauvegardé à son tour. Sans --oui, le script dit ce qu'il ferait et s'arrête.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ligne de commande seulement.'); }

require __DIR__ . '/../inclus/amorce.php';

$fichier = '';
foreach (array_slice($argv, 1) as $a) if ($a !== '--oui') $fichier = $a;
if ($fichier === '') { fwrite(STDERR, "Usage : php pupitre/sql/restaurer.php <fichier.sql> --oui\n"); exit(1); }
$chemin = is_file($fichier) ? $fichier : (is_file(RACINE . '/' . $fichier) ? RACINE . '/' . $fichier : sauvegardes_dossier() . '/' . basename($fichier));
if (!is_file($chemin)) { fwrite(STDERR, "Fichier introuvable : $fichier\n"); exit(1); }
if (!in_array('--oui', $argv, true)) {
    echo "Ce fichier remplacerait toutes les tables qu'il contient : " . $chemin . "\nPour le faire : ajoutez --oui. La base d'aujourd'hui sera sauvegardée avant.\n";
    exit(0);
}
try {
    echo 'Sauvegarde de la base d\'aujourd\'hui : ' . basename(base_sauvegarder('avant-restauration')) . "\n";
} catch (Throwable $e) {
    echo "Base vide ou illisible : rien à sauvegarder avant.\n";
}
$n = base_restaurer($chemin);
echo "Restauration faite : $n instructions jouées. Contrôle : php pupitre/sql/migrer.php --etat\n";

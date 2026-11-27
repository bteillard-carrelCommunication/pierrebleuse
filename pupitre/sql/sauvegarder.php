<?php
/**
 * Sauvegarde complète de la base dans stockage/sauvegardes/ (inclus/sauvegarde.php).
 *
 *     php pupitre/sql/sauvegarder.php [motif]
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ligne de commande seulement.'); }

require __DIR__ . '/../inclus/amorce.php';

$chemin = base_sauvegarder((string) ($argv[1] ?? 'manuelle'));
echo 'Sauvegarde écrite : ' . $chemin . ' (' . taille_lisible((int) filesize($chemin)) . ")\n";

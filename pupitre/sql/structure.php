<?php
/**
 * Écrit la structure complète de la base, telle qu'elle est après les migrations, dans
 * sql/installation/2-structure.sql. Structure seule : aucune donnée n'est exportée.
 *
 *     php pupitre/sql/structure.php
 *
 * À relancer après chaque nouvelle migration : le fichier sert de référence (relecture, comparaison
 * d'une base avec ce qu'elle devrait être). Une installation se fait en restaurant une sauvegarde
 * (sql/restaurer.php), pas avec ce fichier : la structure seule ne porte ni les langues, ni les
 * blocs, ni les libellés que les migrations ont posés.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ligne de commande seulement.'); }

require __DIR__ . '/../inclus/amorce.php';

$tables = bd_tables();
$passees = migrations_passees();
$s = "-- Pupitre : structure complète de la base, écrite par sql/structure.php le " . date('d/m/Y') . ".\n"
    . "-- " . count($tables) . " tables, après les migrations " . ($passees ? reset($passees) . ' à ' . end($passees) : '(aucune)') . ".\n"
    . "-- Structure seule : aucune donnée n'est exportée. Fichier de référence : une installation se fait en\n"
    . "-- restaurant une sauvegarde complète (php pupitre/sql/restaurer.php), qui porte aussi les langues,\n"
    . "-- les blocs et les libellés posés par les migrations.\n"
    . "-- Rejouable : chaque table est créée si elle manque, rien n'est détruit.\n\n"
    . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
foreach ($tables as $t) {
    $c = bd()->query('SHOW CREATE TABLE ' . bd_nom($t))->fetch(PDO::FETCH_NUM)[1];
    $c = preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $c);
    $c = preg_replace('/ AUTO_INCREMENT=\d+/', '', $c);
    $s .= $c . ";\n\n";
}
$s .= "SET FOREIGN_KEY_CHECKS = 1;\n";
$f = __DIR__ . '/installation/2-structure.sql';
file_put_contents($f, $s);
echo 'Structure écrite : ' . $f . ' (' . count($tables) . " tables)\n";

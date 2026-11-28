<?php
/**
 * Écrit le script de mise à jour de la base d'un autre poste de développement :
 * <dépôt>/mise-a-jour-base/pupitre-mise-a-jour-AAAAMMJJ.sql, tiré de la base de ce poste (la référence).
 *
 *     php pupitre/sql/mise-a-jour.php            écrit le script du jour (et dit comment l'appliquer)
 *     php pupitre/sql/mise-a-jour.php --voir     dit ce qu'il écrirait, table par table, sans rien écrire
 *
 * Le script part dans git : il ne porte donc ni compte, ni lien personnel, ni adresse de correspondant.
 * Trois sortes de tables :
 *   gardées      les comptes (MAJ_GARDEES). Le poste d'arrivée garde les siens : la table n'est créée, vide,
 *                que si elle manque, et une colonne qu'une migration lui a ajoutée est posée si elle manque ;
 *   vidées       ce qui tient à un poste ou à des personnes (MAJ_VIDEES) : file des tâches, aperçus et leurs
 *                validations, zones de dépôt, courrier, inscrits à la lettre. La structure arrive, pas le contenu ;
 *   remplacées   tout le reste, structure et contenu : ce qui a été saisi sur le poste d'arrivée est perdu.
 * Les réglages qui ne disent que « quand ce poste a fait ceci pour la dernière fois » ne voyagent pas.
 *
 * Sur l'autre poste, après « git pull » : php pupitre/sql/restaurer.php mise-a-jour-base/<script> --oui
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ligne de commande seulement.'); }

require __DIR__ . '/../inclus/amorce.php';

const MAJ_GARDEES = ['comptes', 'liens_connexion', 'tentatives_connexion'];
const MAJ_VIDEES = ['taches', 'apercus', 'apercus_validations', 'depots', 'depots_fichiers', 'courriers', 'lettre_inscrits'];
const MAJ_REGLAGES_DU_POSTE = ['nuit_le', 'travaux_le', 'veille_le', 'courrier_le', 'courrier_uid', 'metricool_releve_le'];

if (migrations_en_attente()) { fwrite(STDERR, "Des migrations attendent sur ce poste : passez-les d'abord (php pupitre/sql/migrer.php).\n"); exit(1); }

$pdo = bd();
$tables = bd_tables();
$voir = in_array('--voir', $argv, true);
$passees = migrations_passees();
$version_site = (string) (bd_valeur("SELECT version FROM publications WHERE nature = 'publication' AND annulee = 0 ORDER BY id DESC LIMIT 1") ?: '');
$remplacees = array_values(array_diff($tables, MAJ_GARDEES, MAJ_VIDEES));
$videes = array_values(array_intersect($tables, MAJ_VIDEES));
$gardees = array_values(array_intersect($tables, MAJ_GARDEES));

if ($voir) {
    foreach ($tables as $t) {
        $sort = in_array($t, MAJ_GARDEES, true) ? 'gardée   ' : (in_array($t, MAJ_VIDEES, true) ? 'vidée    ' : 'remplacée');
        printf("  %s  %-28s %s\n", $sort, $t, in_array($t, $remplacees, true) ? pluriel((int) bd_valeur('SELECT COUNT(*) FROM ' . bd_nom($t)), 'ligne', 'lignes') : '');
    }
    exit(0);
}

$depot = dirname(RACINE);
$dossier = dossier($depot . '/mise-a-jour-base');
if (!is_file($dossier . '/.htaccess')) file_put_contents($dossier . '/.htaccess', "Require all denied\n");
$nom = 'pupitre-mise-a-jour-' . date('Ymd') . '.sql';
$f = fopen($dossier . '/' . $nom, 'wb');
if (!$f) { fwrite(STDERR, "Écriture impossible : mise-a-jour-base/$nom\n"); exit(1); }

$ligne = fn(string $s) => fwrite($f, $s . "\n");
$ligne('-- Pupitre : mise à jour d\'une base de développement, écrite le ' . date('d/m/Y à H:i') . ' depuis la base de référence (Pupitre ' . VERSION_PUPITRE . ').');
$ligne('-- État : migrations passées jusqu\'à « ' . end($passees) . ' »' . ($version_site !== '' ? ' ; site publié en version ' . $version_site : '') . '.');
$ligne('--');
$ligne('-- CE QUE FAIT CE SCRIPT');
$ligne('--   Il remplace ' . count($remplacees) . ' tables par celles de la base de référence, structure et contenu : tout ce qui a été saisi');
$ligne('--   dans ces tables sur le poste d\'arrivée est perdu.');
$ligne('--   Il ne touche pas aux comptes (' . implode(', ', $gardees) . ') : vous gardez votre compte et votre mot de passe.');
$ligne('--   Ces tables ne sont créées, vides, que si elles manquent ; une colonne ajoutée depuis par une migration leur est posée.');
$ligne('--   ' . count($videes) . ' tables arrivent vides, parce qu\'elles tiennent à un poste ou à des personnes et n\'ont pas leur place dans git :');
$ligne('--   ' . implode(', ', $videes) . '.');
$ligne('--');
$ligne('-- POUR L\'APPLIQUER, après « git pull », depuis le dossier pierrebleuse :');
$ligne('--   php pupitre/sql/restaurer.php mise-a-jour-base/' . $nom . ' --oui');
$ligne('--   (la base d\'avant est d\'abord sauvegardée dans pupitre/stockage/sauvegardes/), puis : php pupitre/sql/migrer.php --etat');
$ligne('--   Sans ligne de commande : importer ce fichier dans la base de Pupitre avec phpMyAdmin. MySQL 8 est requis.');
$ligne('--   Ensuite, dans Pupitre : « Le site » doit dire « aucun écart » (les fichiers du site viennent du même commit).');
$ligne('SET NAMES utf8mb4;');
$ligne('SET FOREIGN_KEY_CHECKS = 0;');

$lignes = 0;
foreach ($tables as $table) {
    $t = bd_nom($table);
    $creation = preg_replace('/ AUTO_INCREMENT=\d+/', '', $pdo->query('SHOW CREATE TABLE ' . $t)->fetch(PDO::FETCH_NUM)[1]);

    if (in_array($table, MAJ_GARDEES, true)) {
        // la table du poste d'arrivée est gardée ; créée si elle manque, complétée colonne par colonne si elle date d'avant une migration
        $ligne(preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $creation) . ';');
        foreach (explode("\n", $creation) as $l) {
            if (!preg_match('/^\s*`([^`]+)`\s+(.*?),?$/', $l, $m)) continue;
            $ajout = 'ALTER TABLE ' . $t . ' ADD COLUMN ' . bd_nom($m[1]) . ' ' . $m[2];
            $ligne('SET @pupitre_maj = (SELECT IF(COUNT(*) = 0, ' . $pdo->quote($ajout) . ", 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($table) . ' AND column_name = ' . $pdo->quote($m[1]) . ');');
            $ligne('PREPARE pupitre_maj FROM @pupitre_maj;');
            $ligne('EXECUTE pupitre_maj;');
            $ligne('DEALLOCATE PREPARE pupitre_maj;');
        }
        continue;
    }

    $ligne('DROP TABLE IF EXISTS ' . $t . ';');
    $ligne($creation . ';');
    if (in_array($table, MAJ_VIDEES, true)) continue;

    $requete = $pdo->query('SELECT * FROM ' . $t . ($table === 'reglages' ? ' WHERE cle NOT IN (' . implode(',', array_map([$pdo, 'quote'], MAJ_REGLAGES_DU_POSTE)) . ')' : ''), PDO::FETCH_NUM);
    $lot = [];
    $ecrire = function () use (&$lot, $ligne, $t) {
        if ($lot) $ligne('INSERT INTO ' . $t . ' VALUES ' . implode(',', $lot) . ';');
        $lot = [];
    };
    while ($l = $requete->fetch()) {
        $lot[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $l)) . ')';
        $lignes++;
        if (count($lot) >= 200) $ecrire();
    }
    $ecrire();
}
$ligne('SET FOREIGN_KEY_CHECKS = 1;');
fclose($f);

echo 'Script écrit : mise-a-jour-base/' . $nom . ' (' . taille_lisible((int) filesize($dossier . '/' . $nom)) . ', ' . count($remplacees) . ' tables remplacées, ' . $lignes . " lignes).\n";
echo 'Comptes gardés sur le poste d\'arrivée : ' . implode(', ', $gardees) . ".\n";
echo 'Arrivent vides : ' . implode(', ', $videes) . ".\n";
$anciens = array_values(array_filter(glob($dossier . '/pupitre-mise-a-jour-*.sql') ?: [], fn($x) => basename($x) !== $nom));
if ($anciens) echo 'Scripts plus anciens, que celui-ci remplace : ' . implode(', ', array_map('basename', $anciens)) . ".\n";
echo "Sur l'autre poste, après « git pull » : php pupitre/sql/restaurer.php mise-a-jour-base/$nom --oui\n";

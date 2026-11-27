<?php
/**
 * Sauvegarde et restauration de la base, en PHP seul : aucun outil du serveur n'est nécessaire.
 *
 * base_sauvegarder() écrit dans stockage/sauvegardes/ un fichier SQL complet (structure et données de
 * toutes les tables), une instruction par ligne. base_restaurer() le rejoue. Le dossier stockage/ est
 * interdit au web et hors git : une sauvegarde contient les comptes et leurs empreintes de mot de passe.
 *
 *     php pupitre/sql/sauvegarder.php [motif]
 *     php pupitre/sql/restaurer.php stockage/sauvegardes/<fichier>.sql
 */

defined('PUPITRE') or exit;

/** Dossier des sauvegardes de la base. */
function sauvegardes_dossier(): string
{
    return dossier(STOCKAGE . '/sauvegardes');
}

/** Tables de la base, dans l'ordre alphabétique. */
function bd_tables(): array
{
    return bd_colonne("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name");
}

/**
 * Écrit une sauvegarde complète de la base. $motif nomme le fichier (« avant-migration »…).
 * Rend le chemin du fichier.
 */
function base_sauvegarder(string $motif = ''): string
{
    $nom = 'pupitre-' . date('Ymd-His') . ($motif !== '' ? '-' . (slug($motif, 60) ?: 'sauvegarde') : '') . '.sql';
    $chemin = sauvegardes_dossier() . '/' . $nom;
    $f = @fopen($chemin, 'wb');
    if (!$f) throw new RuntimeException('Sauvegarde impossible : le dossier stockage/sauvegardes n\'accepte pas d\'écriture.');
    @chmod($chemin, 0600);
    $pdo = bd();
    $tables = bd_tables();
    fwrite($f, "-- Pupitre : sauvegarde de la base, " . date('d/m/Y H:i:s') . ($motif !== '' ? " ($motif)" : '') . "\n");
    fwrite($f, "-- " . count($tables) . " tables. À restaurer par : php pupitre/sql/restaurer.php <ce fichier>\n");
    fwrite($f, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n");
    foreach ($tables as $table) {
        $t = bd_nom($table);
        $creation = $pdo->query('SHOW CREATE TABLE ' . $t)->fetch(PDO::FETCH_NUM)[1];
        fwrite($f, "DROP TABLE IF EXISTS $t;\n" . $creation . ";\n");
        $requete = $pdo->query('SELECT * FROM ' . $t, PDO::FETCH_NUM);
        $lot = [];
        $ecrire = function () use (&$lot, $f, $t) {
            if ($lot) fwrite($f, "INSERT INTO $t VALUES " . implode(',', $lot) . ";\n");
            $lot = [];
        };
        while ($ligne = $requete->fetch()) {
            $valeurs = array_map(fn($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $ligne);
            $lot[] = '(' . implode(',', $valeurs) . ')';
            if (count($lot) >= 200) $ecrire();
        }
        $ecrire();
    }
    fwrite($f, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fclose($f);
    return $chemin;
}

/** Les sauvegardes présentes, de la plus récente à la plus ancienne : [[nom, taille, date], …]. */
function sauvegardes_liste(): array
{
    $l = [];
    foreach (glob(sauvegardes_dossier() . '/*.sql') ?: [] as $f) $l[] = [basename($f), (int) filesize($f), (int) filemtime($f)];
    usort($l, fn($a, $b) => $b[2] <=> $a[2]);
    return $l;
}

/**
 * Rejoue une sauvegarde écrite par base_sauvegarder() : chaque table du fichier est supprimée puis
 * recréée avec ses données. Les tables absentes du fichier ne sont pas touchées. Rend le nombre
 * d'instructions jouées.
 */
function base_restaurer(string $chemin): int
{
    if (!is_readable($chemin)) throw new RuntimeException('Fichier de sauvegarde illisible.');
    $sql = file_get_contents($chemin);
    // une instruction se termine par « ; » en fin de ligne, la suivante commence par un mot-clé connu
    $instructions = preg_split('/;\n(?=(?:DROP TABLE|CREATE TABLE|INSERT INTO|SET |--|\s*$))/', $sql);
    $pdo = bd();
    $n = 0;
    foreach ($instructions as $i) {
        $i = trim(preg_replace('/^--.*$/m', '', $i));
        if ($i === '') continue;
        $pdo->exec($i);
        $n++;
    }
    return $n;
}

<?php
/**
 * Contrôle, sans base : les zones de repli que Pupitre écrit sont exactement celles des pages.
 *
 *     php pupitre/tests/repli.php
 *
 * Relit les fichiers de data/ du site, régénère chaque zone (inclus/site/repli.php) et la
 * replace dans chaque page : la page ne doit pas changer d'un octet. Si ce contrôle passe, une
 * publication sans modification ne touche pas au HTML.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require __DIR__ . '/../inclus/config.php';
require RACINE . '/inclus/fonctions.php';
require RACINE . '/inclus/site/export.php';
require RACINE . '/inclus/site/repli.php';

$racine = realpath($argv[1] ?? RACINE . '/..');
$exports = [];
foreach (FICHIERS_DU_SITE as $chemin => [$variable]) {
    $exports[$variable] = site_lire_donnees($racine . '/' . $chemin);
    if ($exports[$variable] === null) { fwrite(STDERR, "illisible : $chemin\n"); exit(1); }
}
// si les faux articles de maquette sont chargés, le repli garde les vrais : rien à changer ici

$zones = repli_zones($exports);
$ok = true;
foreach (site_pages($racine) as $nom) {
    $page = file_get_contents($racine . '/' . $nom);
    $trouves = [];
    $nouvelle = repli_appliquer($page, $zones, $trouves);
    $identique = ($nouvelle === $page);
    $ok = $ok && $identique;
    printf("%-24s %2d zones  %s\n", $nom, count($trouves), $identique ? 'identique' : 'DIFFÉRENTE');
    if (!$identique) {
        $a = explode("\n", $page);
        $b = explode("\n", $nouvelle);
        $n = 0;
        foreach ($a as $i => $ligne) {
            if (($b[$i] ?? null) !== $ligne) {
                echo "  ligne " . ($i + 1) . "\n    page   : " . mb_substr($ligne, 0, 150) . "\n    repli  : " . mb_substr($b[$i] ?? '(absente)', 0, 150) . "\n";
                if (++$n >= 4) break;
            }
        }
    }
}
echo $ok ? "repli : toutes les pages sont identiques\n" : "repli : des zones diffèrent\n";
exit($ok ? 0 : 1);

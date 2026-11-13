<?php
/**
 * Contrôle : ce que Pupitre publierait est équivalent à ce que le site a déjà.
 *
 *     php pupitre/tests/equivalence.php
 *
 * À lancer juste après la reprise (sql/depart.php), avant toute modification. Chaque fichier de
 * data/ doit être « identique » ou « équivalent » (mêmes données, autre écriture), chaque page
 * « identique ». Sort avec le code 1 sinon, en montrant les premières différences.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../inclus/amorce.php';

$etat = publication_etat();
$ok = true;
foreach ($etat['fichiers'] as $chemin => $f) {
    $bon = in_array($f['etat'], ['identique', 'equivalent'], true) && !(str_ends_with($chemin, '.html') && $f['etat'] !== 'identique');
    $ok = $ok && $bon;
    printf("%-28s %s\n", $chemin, ['identique' => 'identique', 'equivalent' => 'équivalent', 'modifie' => 'DIFFÉRENT', 'nouveau' => 'NOUVEAU'][$f['etat']]);
    if (!$bon) {
        $n = 0;
        foreach (differences_lignes($f['avant'], $f['apres'], 0) as [$signe, $ligne]) {
            if ($signe === ' ' || $signe === '…') continue;
            echo '    ' . $signe . ' ' . mb_substr(trim($ligne), 0, 140) . "\n";
            if (++$n >= 8) { echo "    …\n"; break; }
        }
    }
}
foreach ($etat['medias'] as $source => $cible) { echo "média à copier : $cible\n"; $ok = false; }
echo $ok ? "équivalence : une publication ne changerait rien au contenu du site\n" : "équivalence : des différences sont à examiner\n";
exit($ok ? 0 : 1);

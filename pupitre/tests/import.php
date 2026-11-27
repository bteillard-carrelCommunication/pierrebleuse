<?php
/**
 * Test de l'import, sans appel à l'IA et sans rien écrire : tout se passe dans une transaction
 * qui est annulée à la fin.
 *
 *     php pupitre/tests/import.php
 *
 * Les engagements de tests/extrait-fictif.php sont ceux que l'IA rendrait pour
 * tests/extrait-fictif.txt. Le test
 * éprouve ce que fait le code : contrôle du JSON, comparaison avec l'agenda (nouvelles dates,
 * dates modifiées, absentes, annulation annoncée, programme changé, cas douteux), contrôle (lieu, heure, date passée,
 * hors saison, doublon), validation, confidentialité des journaux.
 * Il suppose l'agenda de la reprise (sql/depart.php).
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../inclus/amorce.php';

$echecs = [];
$verifier = function (bool $condition, string $quoi) use (&$echecs) {
    echo ($condition ? '  ok     ' : '  ÉCHEC  ') . $quoi . "\n";
    if (!$condition) $echecs[] = $quoi;
};
$reponse = require __DIR__ . '/extrait-fictif.php';

echo "Contrôle du JSON rendu par l'IA\n";
[$donnees, $erreurs] = import_controler_reponse(json_encode($reponse, JSON_UNESCAPED_UNICODE));
$verifier(!$erreurs && count($donnees['engagements']) === 12, 'une réponse bien formée passe le contrôle (12 engagements)');
[, $erreurs] = import_controler_reponse('{"engagements": [{"representations": [{"date": "13/05/2026", "heure": "20h"}]}]}');
$verifier(count($erreurs) === 1 && str_contains($erreurs[0], 'date illisible'), 'une date mal écrite est relevée, sans recopier le contenu');
[$rien, $erreurs] = import_controler_reponse('Voici les engagements : …');
$verifier($rien === null && $erreurs, 'un texte qui n\'est pas du JSON est refusé');

$journaux = fn() => implode("\n", array_map('file_get_contents', glob(STOCKAGE . '/journaux/*.log') ?: []));
$taille_avant = strlen($journaux());

bd()->beginTransaction();
try {
    // l'agenda du test : celui de la reprise, à la date de l'extrait
    $attendues = (int) bd_valeur("SELECT COUNT(*) FROM representations WHERE date BETWEEN '2026-09-21' AND '2026-12-31' AND statut <> 'annulee'");
    if ($attendues !== 15) echo "  (attention : ce test suppose l'agenda de la reprise ; il compte $attendues représentations sur la période au lieu de 15)\n";

    echo "Comparaison avec l'agenda et contrôle\n";
    $id = bd_inserer('imports', ['nature' => 'texte', 'fichier' => 'extrait-fictif.txt', 'nom_origine' => 'Extrait fictif (test)', 'statut' => 'lecture']);
    $bilan = import_comparer($id, $donnees);
    bd_modifier('imports', ['statut' => 'a_valider', 'date_extrait' => $donnees['date_extrait'], 'bilan' => json_encode($bilan)], ['id' => $id]);
    $n = $bilan['natures'];
    $c = $bilan['controles'];
    $verifier($bilan['representations'] === 29, '29 représentations lues (' . $bilan['representations'] . ')');
    $verifier(($n['inchangee'] ?? 0) === 9, '9 dates inchangées (' . ($n['inchangee'] ?? 0) . ')');
    $verifier(($n['modifiee'] ?? 0) === 2, '2 dates modifiées : une heure, une salle (' . ($n['modifiee'] ?? 0) . ')');
    $verifier(($n['programme'] ?? 0) === 1, '1 programme changé (' . ($n['programme'] ?? 0) . ')');
    $verifier(($n['douteuse'] ?? 0) === 1, '1 cas douteux : même ensemble, un jour plus tard (' . ($n['douteuse'] ?? 0) . ')');
    $verifier(($n['nouvelle'] ?? 0) === 17, '17 nouvelles dates (' . ($n['nouvelle'] ?? 0) . ')');
    $verifier(($n['disparue'] ?? 0) === 2, '2 dates disparues (' . ($n['disparue'] ?? 0) . ')');
    $verifier(($c['lieu'] ?? 0) === 12, 'le contrôle relève 12 dates sans lieu (' . ($c['lieu'] ?? 0) . ')');
    $verifier(($c['heure'] ?? 0) === 4, '4 dates sans heure (' . ($c['heure'] ?? 0) . ')');
    $verifier(($c['doublon'] ?? 0) === 1, '1 doublon probable (' . ($c['doublon'] ?? 0) . ')');
    $lignes = import_lignes($id);
    $red_fox = array_values(array_filter($lignes, fn($l) => ($l['propose']['titre'] ?? '') === 'Red Fox'));
    $verifier(count($red_fox) === 3 && !array_filter($red_fox, fn($l) => substr($l['propose']['date'], 0, 7) !== '2026-05' || !in_array('passee', $l['controles'], true) || !in_array('hors_saison', $l['controles'], true)),
        'les 3 représentations de « Red Fox » datées de mai 2026 sont relevées : date passée, hors saison');
    $verifier((int) bd_valeur("SELECT COUNT(*) FROM representations WHERE origine = 'import'") === 0 && (int) bd_valeur('SELECT COUNT(*) FROM concerts WHERE titre_fr LIKE ?', ['%fictif%']) === 0,
        'rien n\'est écrit dans l\'agenda avant la validation');

    echo "Validation\n";
    $par = fn(string $nature) => array_values(array_filter($lignes, fn($l) => $l['nature'] === $nature));
    $tournee_lignes = array_values(array_filter($par('nouvelle'), fn($l) => $l['propose']['ensemble'] === 'Orchestre fictif de la Vallée'));
    $verifier(import_ligne_valider((int) $tournee_lignes[0]['id']) === null && import_ligne_valider((int) $tournee_lignes[1]['id']) === null, 'deux nouvelles dates d\'un même engagement se valident');
    $cree = bd_ligne("SELECT c.id, COUNT(r.id) AS n, MIN(r.statut) AS statut, MIN(r.origine) AS origine FROM concerts c JOIN representations r ON r.concert_id = c.id WHERE c.ensemble_fr = 'Orchestre fictif de la Vallée' GROUP BY c.id");
    $verifier($cree && (int) $cree['n'] === 2 && $cree['statut'] === 'connue' && $cree['origine'] === 'import', 'elles entrent dans un seul concert, au statut « connue », origine « import »');
    $verifier(!str_contains(implode("\n", site_fichiers(site_exports())), 'Orchestre fictif de la Vallée'), 'une date validée ne sort pas sur le site : elle n\'est pas annoncée');
    $heure = array_values(array_filter($par('modifiee'), fn($l) => in_array('heure', $l['propose']['champs'], true)))[0];
    $verifier(import_ligne_valider((int) $heure['id']) === null && bd_valeur('SELECT heure FROM representations WHERE id = ?', [$heure['representation_id']]) === '19:30', 'une date modifiée met l\'heure de l\'agenda à jour');
    $disparue = $par('disparue')[0];
    $verifier(import_ligne_valider((int) $disparue['id']) === null && bd_valeur('SELECT statut FROM representations WHERE id = ?', [$disparue['representation_id']]) === 'annulee', 'une date disparue, validée, passe à « annulée ou reportée », avec sa raison');
    $douteuse = $par('douteuse')[0];
    $verifier(import_ligne_valider((int) $douteuse['id']) !== null, 'un cas douteux ne se valide pas sans avoir été tranché');
    $verifier(import_ligne_corriger((int) $red_fox[0]['id'], ['date' => '2027-05-13', 'heure' => '20:00']) === null
        && !in_array('passee', import_ligne_lire((int) $red_fox[0]['id'])['controles'], true), 'une ligne se corrige avant validation, et le contrôle est refait');
    $verifier(import_ligne_refuser((int) $red_fox[1]['id']) === null && (int) bd_valeur("SELECT COUNT(*) FROM concerts WHERE titre_fr = 'Red Fox'") === 1, 'une ligne refusée ne touche pas à l\'agenda');

    echo "Annulation annoncée par le document\n";
    // une date à venir de l'agenda, que l'extrait dit annulée ; une autre, que l'agenda ne connaît pas
    $cible = bd_ligne("SELECT r.id, r.date, r.heure, r.statut, c.id AS concert_id, c.titre_fr, c.ensemble_fr FROM representations r JOIN concerts c ON c.id = r.concert_id
                       WHERE r.statut <> 'annulee' AND c.type <> 'disque' AND c.ensemble_fr <> '' ORDER BY r.date DESC, r.id DESC LIMIT 1");
    $rep = fn(string $date, bool $annulee, ?string $motif) => ['date' => $date, 'heure' => null, 'salle' => null, 'ville' => null, 'pays' => null, 'annulee' => $annulee, 'motif' => $motif];
    [$d2, $e2] = import_controler_reponse(json_encode(['date_extrait' => null, 'engagements' => [
        ['titre' => null, 'type' => 'concert', 'ensemble' => $cible['ensemble_fr'], 'mentions' => null, 'programme' => [], 'solistes' => [], 'representations' => [$rep($cible['date'], true, 'ANNULÉ (grève)')]],
        ['titre' => null, 'type' => 'concert', 'ensemble' => 'Orchestre fictif des Lisières', 'mentions' => null, 'programme' => [], 'solistes' => [], 'representations' => [$rep('2031-01-15', true, 'cancelled')]],
    ]], JSON_UNESCAPED_UNICODE));
    $id2 = bd_inserer('imports', ['nature' => 'texte', 'fichier' => 'annulation.txt', 'nom_origine' => 'Annulation (test)', 'statut' => 'lecture']);
    $bilan2 = import_comparer($id2, $d2);
    bd_modifier('imports', ['statut' => 'a_valider', 'bilan' => json_encode($bilan2)], ['id' => $id2]);
    $annulees = array_values(array_filter(import_lignes($id2), fn($l) => $l['nature'] === 'annulee'));
    $verifier(!$e2 && count($annulees) === 1 && (int) $annulees[0]['representation_id'] === (int) $cible['id'] && $annulees[0]['propose']['motif'] === 'ANNULÉ (grève)', 'la date que le document dit annulée est proposée à l\'annulation, avec ce qu\'il en dit');
    $verifier(($bilan2['annulations_sans_objet'] ?? 0) === 1 && (int) bd_valeur("SELECT COUNT(*) FROM concerts WHERE ensemble_fr = 'Orchestre fictif des Lisières' AND id NOT IN (SELECT concert_id FROM import_lignes WHERE import_id = ? AND concert_id IS NOT NULL)", [$id]) >= 0
        && !array_filter(import_lignes($id2), fn($l) => $l['nature'] === 'nouvelle'), 'une annulation pour une date inconnue de l\'agenda n\'y crée rien');
    $verifier(!array_filter(import_lignes($id2), fn($l) => $l['nature'] === 'disparue' && (int) $l['representation_id'] === (int) $cible['id']), 'une date annoncée annulée n\'est pas aussi comptée « absente »');
    $verifier(bd_valeur('SELECT statut FROM representations WHERE id = ?', [$cible['id']]) === $cible['statut'], 'rien ne change dans l\'agenda avant la validation');
    $verifier(import_ligne_valider((int) $annulees[0]['id']) === null && bd_valeur('SELECT statut FROM representations WHERE id = ?', [$cible['id']]) === 'annulee', 'validée, la date passe à « annulée », avec sa raison');
} finally {
    bd()->rollBack();
}
$GLOBALS['donnees_modifiees'] = false;

echo "Confidentialité\n";
$ajoute = substr($journaux(), $taille_avant);
$verifier(!preg_match('/fictif|Lisières|Vallée|Red Fox|Pas perdus/u', $ajoute), 'aucun contenu de l\'extrait n\'a été écrit dans les journaux de stockage/journaux/');

echo $echecs ? count($echecs) . " échec(s)\n" : "import : comparaison, contrôle et validation conformes\n";
exit($echecs ? 1 : 0);

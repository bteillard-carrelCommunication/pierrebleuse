<?php
/**
 * Test : le site ne reçoit que les représentations annoncées par leur organisateur.
 *
 *     php pupitre/tests/annonce.php
 *
 * Crée, dans une transaction qui est annulée à la fin, un concert dont les représentations
 * portent chacun des statuts, puis fabrique tout ce qu'une publication écrirait (fichiers de
 * data/ et pages). Les dates connues, sous embargo et annulées ne doivent figurer nulle part ;
 * les dates annoncées et publiées doivent y être. Vérifie aussi qu'aucune représentation de la
 * base, hors « annoncée » et « publiée », ne sort dans l'agenda du site. Rien n'est écrit.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../inclus/amorce.php';

$echecs = [];
$verifier = function (bool $condition, string $quoi) use (&$echecs) {
    echo ($condition ? '  ok     ' : '  ÉCHEC  ') . $quoi . "\n";
    if (!$condition) $echecs[] = $quoi;
};

bd()->beginTransaction();
try {
    // des dates que rien d'autre ne porte : l'an 2091
    $dates = ['connue' => '2091-03-01', 'embargo' => '2091-03-02', 'annoncee' => '2091-03-03', 'annulee' => '2091-03-04', 'publiee' => '2091-03-05'];
    [$id, $erreurs] = concert_enregistrer(null, [
        'type' => 'concert', 'titre_en' => 'Concert témoin du test', 'ensemble_en' => 'Ensemble témoin',
        'salle_en' => 'Salle témoin', 'ville_en' => 'Ville témoin', 'pays_en' => 'France',
        'reps' => array_map(fn($d) => ['date' => $d, 'heure' => '20:00'], array_values($dates)),
        'prog' => [['compositeur_en' => 'Compositeur témoin', 'oeuvre_en' => 'Œuvre témoin']],
    ]);
    if ($erreurs) throw new RuntimeException(implode(' ', $erreurs));
    $reps = [];
    foreach (bd_lignes('SELECT id, date FROM representations WHERE concert_id = ?', [$id]) as $r) $reps[$r['date']] = (int) $r['id'];

    echo "Règles des statuts\n";
    $verifier(statut_changer($reps[$dates['annoncee']], 'annoncee') !== null, 'annoncer sans lien source est refusé');
    $verifier(statut_changer($reps[$dates['annoncee']], 'annoncee', ['lien_source' => 'https://exemple.test/annonce']) === null, 'annoncer avec un lien source passe');
    $verifier(statut_changer($reps[$dates['embargo']], 'embargo') !== null, 'un embargo sans date est refusé');
    $verifier(statut_changer($reps[$dates['embargo']], 'embargo', ['embargo_le' => '2091-01-15']) === null, 'un embargo daté passe');
    $verifier(statut_changer($reps[$dates['annulee']], 'annulee') !== null, 'annuler sans raison est refusé');
    $verifier(statut_changer($reps[$dates['annulee']], 'annulee', ['raison' => 'Test']) === null, 'annuler avec une raison passe');
    $verifier(statut_changer($reps[$dates['connue']], 'publiee') !== null, '« publiée » ne se pose pas à la main');
    // « publiée » est posé par la publication : ici, on l'imite directement en base
    bd_modifier('representations', ['statut' => 'publiee', 'lien_source' => 'https://exemple.test/annonce'], ['id' => $reps[$dates['publiee']]]);

    echo "Ce qu'une publication écrirait\n";
    $exports = site_exports();
    $tout = implode("\n", site_fichiers($exports));
    // le HTML de repli, dans chacune des deux langues d'origine du site : il est publié dans la langue de la première visite
    $replis = [];
    foreach (['fr', 'en'] as $langue) {
        $zones = repli_zones($exports, $langue);
        $replis[$langue] = '';
        foreach (site_pages(site_racine()) as $page) $replis[$langue] .= "\n" . repli_appliquer(file_get_contents(site_racine() . '/' . $page), $zones);
        $tout .= $replis[$langue];
    }
    foreach (['connue', 'embargo', 'annulee'] as $statut) {
        $verifier(!str_contains($tout, $dates[$statut]), 'la date « ' . STATUTS[$statut] . ' » (' . $dates[$statut] . ') ne sort dans aucun fichier');
    }
    foreach (['annoncee', 'publiee'] as $statut) {
        $verifier(str_contains($tout, $dates[$statut]), 'la date « ' . STATUTS[$statut] . ' » (' . $dates[$statut] . ') sort dans l\'agenda');
    }
    $verifier(!str_contains($replis['fr'], '4 mars 2091') && !str_contains($replis['fr'], '1er mars 2091') && !str_contains($replis['fr'], '1er–5 mars 2091')
        && !str_contains($replis['en'], '4 March 2091') && !str_contains($replis['en'], '1 March 2091') && !str_contains($replis['en'], '1–5 March 2091'),
        'le repli sans JavaScript ne cite pas les dates non annoncées, en français comme en anglais');
    $verifier(str_contains($replis['fr'], '3–5 mars 2091'), 'le repli en français donne la plage des seules dates annoncées (3–5 mars 2091)');
    $verifier(str_contains($replis['en'], '3–5 March 2091'), 'le repli en anglais, langue de la première visite, aussi (3–5 March 2091)');

    // sur toute la base : chaque date de l'agenda exporté est une représentation annoncée ou publiée
    $sorties = [];
    foreach (json_decode(json_encode($exports['AGENDA_DONNEES']), true)['evenements'] as $e) {
        foreach ($e['representations'] as $r) $sorties[] = $e['id'] . ' ' . $r['date'] . ' ' . ($r['heure'] ?? '');
    }
    $permises = array_map(fn($r) => $r['cle'] . ' ' . $r['date'] . ' ' . ($r['heure'] ?? ''),
        bd_lignes("SELECT c.cle, r.date, r.heure FROM representations r JOIN concerts c ON c.id = r.concert_id WHERE r.statut IN ('annoncee', 'publiee')"));
    sort($sorties);
    sort($permises);
    $verifier($sorties === $permises, 'l\'agenda exporté contient exactement les représentations annoncées ou publiées (' . count($sorties) . ')');
    $autres = (int) bd_valeur("SELECT COUNT(*) FROM representations WHERE statut NOT IN ('annoncee', 'publiee')");
    $verifier($autres >= 3, $autres . ' représentation(s) d\'un autre statut restent dans Pupitre');
} finally {
    bd()->rollBack();
}
$GLOBALS['donnees_modifiees'] = false;
echo $echecs ? count($echecs) . " échec(s)\n" : "annonce : le site ne reçoit que les représentations annoncées\n";
exit($echecs ? 1 : 0);

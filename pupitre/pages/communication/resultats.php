<?php
/**
 * Résultats des publications : ce qui a été publié sur les réseaux de l'artiste, par Pupitre ou
 * autrement, et ce que chaque publication a donné. Les chiffres viennent de Metricool, relevés à
 * la demande (bouton « Relever ») ou par le cron ; rien n'est estimé.
 *
 * Lecture : chaque publication est rapportée à l'habitude de son réseau sur la période (médiane des
 * interactions). « × 2 » : deux fois plus d'interactions que d'habitude. Un thème se pose à la main
 * (annonce, retour de concert, presse…) pour comparer ce qui marche.
 */

defined('PUPITRE') or exit;

$titre = 'Résultats des publications';
$marque = metricool_marque();
$mode_test = $marque['mode'] === 'test';

if (est_post()) {
    $action = recu('action');
    if ($action === 'relever') {
        if (!metricool_configure()) message('erreur', 'Metricool n\'est pas configuré : voir les réglages.');
        elseif ($marque['id'] === '') message('erreur', 'Aucune marque n\'est choisie pour ce mode : réglez-la dans les réglages.');
        else {
            $tache = tache_creer('metricool_releve', ['jours' => max(7, min(365, (int) recu('jours') ?: 90))]);
            if (!taches_lancer_en_fond()) message('alerte', 'Le relevé attend le prochain passage des tâches de fond (cron).');
            rediriger(url('resultats', ['tache' => $tache]));
        }
    } elseif ($action === 'classer' && ($p = bd_ligne('SELECT * FROM posts_sociaux WHERE id = ?', [recu_entier('id') ?? 0]))) {
        $theme = isset(THEMES_POST[recu('theme')]) ? recu('theme') : '';
        $concert = recu_entier('concert_id');
        bd_modifier('posts_sociaux', ['theme' => $theme, 'concert_id' => $concert && bd_valeur('SELECT id FROM concerts WHERE id = ?', [$concert]) ? $concert : null, 'note' => mb_substr(recu('note'), 0, 500)], ['id' => $p['id']]);
        message('ok', 'Publication classée.');
        rediriger(url('resultats', array_filter(['reseau' => recu('f_reseau'), 'origine' => recu('f_origine'), 'periode' => recu('f_periode')], 'strlen')) . '#p-' . $p['id']);
    }
    rediriger(url('resultats'));
}

$periodes = [30 => '30 jours', 90 => '3 mois', 180 => '6 mois', 365 => '12 mois'];
$periode = isset($periodes[(int) recu('periode')]) ? (int) recu('periode') : 90;
$filtres = ['reseau' => isset(RESEAUX_SOCIAUX[recu('reseau')]) ? recu('reseau') : '', 'origine' => in_array(recu('origine'), ['pupitre', 'autre'], true) ? recu('origine') : '',
    'theme' => (isset(THEMES_POST[recu('theme')]) && recu('theme') !== '') || recu('theme') === 'aucun' ? recu('theme') : '', 'q' => recu('q'), 'du' => ajouter_jours(aujourdhui(), -$periode)];
$tri = in_array(recu('tri'), ['date', 'interactions', 'rapport', 'portee'], true) ? recu('tri') : 'date';
$posts = $marque['id'] !== '' ? posts_lister($marque['id'], $filtres) : [];
if ($tri !== 'date') usort($posts, fn($a, $b) => [(float) ($b[$tri] ?? -1)] <=> [(float) ($a[$tri] ?? -1)]);
$s = posts_synthese($posts);
$tache = recu_entier('tache') ? tache_lire(recu_entier('tache')) : null;
$releve = reglage('metricool_releve_le');
$concerts = [];
foreach (bd_lignes("SELECT c.id, c.titre_en, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id) AS du FROM concerts c ORDER BY du DESC LIMIT 200") as $c) $concerts[(int) $c['id']] = ($c['du'] ? date_fr($c['du']) . ' · ' : '') . couper($c['titre_en'], 50);
$nombre = fn($v) => $v === null ? '<span class="discret">—</span>' : e(number_format((float) $v, 0, ',', ' '));
$adresse = fn(array $p) => url('resultats', array_filter($p + ['reseau' => $filtres['reseau'], 'origine' => $filtres['origine'], 'theme' => $filtres['theme'], 'periode' => $periode === 90 ? '' : $periode, 'tri' => $tri === 'date' ? '' : $tri], 'strlen'));
?>
<div class="titre">
  <h1>Résultats des publications</h1>
  <span class="titre__suite"><?= $marque['id'] !== '' ? 'marque « ' . e($marque['nom'] ?: $marque['id']) . ' »' : 'aucune marque réglée' ?><?= $releve !== '' ? ' · relevé ' . e(il_y_a($releve)) : '' ?></span>
  <div class="titre__actions">
    <?php if (peut('reglages')): ?><a class="bouton" href="<?= e(url('reglages')) ?>#metricool"><?= icone('reglages') ?>Marque et mode</a><?php endif; ?>
    <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="action" value="relever"><input type="hidden" name="jours" value="<?= (int) $periode ?>">
      <button type="submit" class="bouton bouton--plein"<?= metricool_configure() && $marque['id'] !== '' ? '' : ' disabled' ?>><?= icone('actualiser') ?>Relever maintenant</button></form>
  </div>
</div>
<?php if ($mode_test): ?>
  <div class="bandeau"><div class="bandeau__textes"><span class="libelle">Mode test</span><span class="bandeau__titre">Vous regardez les comptes de test<?= $marque['nom'] !== '' ? ' (« ' . e($marque['nom']) . ' »)' : '' ?>, pas ceux de l'artiste.</span></div>
    <?php if (peut('reglages')): ?><a class="bouton bouton--sombre" href="<?= e(url('reglages')) ?>#metricool">Revenir aux vrais comptes</a><?php endif; ?></div>
<?php endif; ?>
<?php if ($tache): ?><div class="carte"><?= suivi_tache($tache) ?></div><?php endif; ?>

<?php if (!metricool_configure() && $posts): ?><p class="message message--alerte">Metricool n'est pas relié sur ce poste : ce sont les mesures du dernier relevé, elles ne peuvent pas être rafraîchies d'ici.</p><?php endif; ?>
<?php if (!metricool_configure() && !$posts): ?>
  <div class="carte"><?= etat_vide('mesures', 'Metricool n\'est pas encore relié', 'Renseignez METRICOOL_JETON et METRICOOL_UTILISATEUR dans pupitre/.env, puis choisissez la marque dans les réglages : les publications et leurs mesures se relèveront ici.', peut('reglages') ? '<a class="bouton bouton--petit" href="' . e(url('reglages')) . '#metricool">Ouvrir les réglages</a>' : '') ?></div>
<?php elseif ($marque['id'] === ''): ?>
  <div class="carte"><?= etat_vide('mesures', 'Aucune marque choisie', 'Choisissez dans les réglages la marque Metricool à lire.', peut('reglages') ? '<a class="bouton bouton--petit" href="' . e(url('reglages')) . '#metricool">Choisir la marque</a>' : '') ?></div>
<?php else: ?>

<p class="intro">Ce qui a été publié sur les réseaux, par Pupitre ou autrement, et ce que chaque publication a donné. Chaque résultat est rapporté à l'habitude de son réseau sur la période : « × 2 » veut dire deux fois plus d'interactions que la médiane.</p>

<div class="indicateurs">
  <?= indicateur('Publications', $s['nombre'], 'sur ' . mb_strtolower($periodes[$periode]), ['icone' => 'social']) ?>
  <?= indicateur('Interactions', number_format($s['interactions'], 0, ',', ' '), 'réactions, commentaires, partages, enregistrements', ['icone' => 'coeur']) ?>
  <?= indicateur('Médiane par publication', $s['mediane'] === null ? '—' : number_format($s['mediane'], 0, ',', ' '), 'interactions : l\'habitude', ['icone' => 'mesures']) ?>
  <?= indicateur('Venues de Pupitre', $s['pupitre']['nombre'], $s['pupitre']['mediane'] !== null && $s['autre']['mediane'] ? 'médiane ' . number_format($s['pupitre']['mediane'], 0, ',', ' ') . ' contre ' . number_format($s['autre']['mediane'], 0, ',', ' ') . ' pour les autres' : 'rattachées à une action de communication', ['icone' => 'communication', 'lien' => $adresse(['origine' => 'pupitre'])]) ?>
</div>

<form method="get" class="filtres">
  <input type="hidden" name="page" value="resultats">
  <?= champ_choix('periode', 'Période', $periodes, $periode) ?>
  <?= champ_choix('reseau', 'Réseau', array_intersect_key(RESEAUX_SOCIAUX, $s['reseaux'] + [$filtres['reseau'] => 1]) ?: RESEAUX_SOCIAUX, $filtres['reseau'], ['vide' => 'Tous']) ?>
  <?= champ_choix('origine', 'Origine', ['pupitre' => 'Venues de Pupitre', 'autre' => 'Publiées autrement'], $filtres['origine'], ['vide' => 'Toutes']) ?>
  <?= champ_choix('theme', 'Thème', array_diff_key(THEMES_POST, ['' => 1]) + ['aucun' => 'Non classées'], $filtres['theme'], ['vide' => 'Tous']) ?>
  <?= champ_choix('tri', 'Trier par', ['date' => 'Date', 'rapport' => 'Résultat face à l\'habitude', 'interactions' => 'Interactions', 'portee' => 'Portée'], $tri) ?>
  <?= champ('q', 'Chercher', $filtres['q'], ['type' => 'search', 'indication' => 'un mot du texte']) ?>
  <button type="submit" class="bouton">Filtrer</button>
</form>

<?php if ($s['reseaux']): ?>
<div class="carte">
  <h2>Par réseau</h2>
  <div class="defile"><table class="tableau tableau--serre">
    <thead><tr><th>Réseau</th><th>Publications</th><th>Interactions</th><th>Médiane par publication</th></tr></thead>
    <tbody><?php foreach ($s['reseaux'] as $reseau => $x): ?>
      <tr><th scope="row"><a href="<?= e($adresse(['reseau' => $reseau])) ?>"><?= e(RESEAUX_SOCIAUX[$reseau] ?? $reseau) ?></a></th><td class="nombre"><?= (int) $x['nombre'] ?></td><td class="nombre"><?= $nombre($x['interactions']) ?></td><td class="nombre"><?= $nombre($x['mediane']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php endif; ?>

<?php if (!$posts): ?>
  <div class="carte"><?= etat_vide('mesures', $releve === '' ? 'Aucun relevé encore' : 'Aucune publication pour ces filtres', $releve === '' ? 'Lancez un premier relevé : les publications des trois derniers mois et leurs mesures arriveront ici.' : 'Élargissez la période, ou retirez un filtre.') ?></div>
<?php else: ?>
<div class="carte">
  <div class="carte__tete"><h2>Publications</h2><span class="carte__compte"><?= count($posts) ?></span></div>
  <div class="defile"><table class="tableau tableau--large">
    <thead><tr><th>Date</th><th>Publication</th><th>Portée</th><th>Interactions</th><th>Détail</th><th>Face à l'habitude</th></tr></thead>
    <tbody>
    <?php foreach (array_slice($posts, 0, 150) as $p): ?>
      <tr id="p-<?= (int) $p['id'] ?>">
        <td class="date"><?= $p['publie_le'] ? '<strong>' . e(date_fr(substr($p['publie_le'], 0, 10), false, false)) . '</strong><br><span class="discret">' . e(substr($p['publie_le'], 11, 5)) . '</span>' : '<span class="discret">—</span>' ?></td>
        <th scope="row">
          <span class="en-ligne"><?= canal_afficher(isset(CANAUX[$p['reseau']]) ? $p['reseau'] : 'autre', false) ?><span class="item__titre"><?= e(RESEAUX_SOCIAUX[$p['reseau']] ?? $p['reseau']) ?></span><?= $p['nature'] !== 'post' ? ' <span class="puce">' . e($p['nature']) . '</span>' : '' ?>
            <?= $p['origine'] === 'pupitre' ? '<a class="puce puce--accent" href="' . e(url('communication', ['id' => $p['communication_id']])) . '">Pupitre</a>' : '' ?>
            <?= $p['theme'] !== '' ? '<span class="puce puce--info">' . e(mb_strtolower(THEMES_POST[$p['theme']] ?? $p['theme'])) . '</span>' : '' ?></span>
          <br><span class="discret"><?= e(couper(preg_replace('/\s+/u', ' ', (string) $p['texte']), 150)) ?: 'sans texte' ?></span>
          <?php if ($p['lien'] !== ''): ?><br><a href="<?= e($p['lien']) ?>" target="_blank" rel="noopener">Voir la publication</a><?php endif; ?>
          <details class="repli"><summary>Classer</summary>
            <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="classer"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="f_reseau" value="<?= e($filtres['reseau']) ?>"><input type="hidden" name="f_origine" value="<?= e($filtres['origine']) ?>"><input type="hidden" name="f_periode" value="<?= $periode === 90 ? '' : (int) $periode ?>">
              <div class="colonnes">
                <?= champ_choix('theme', 'Thème', THEMES_POST, $p['theme'], ['prefixe' => '']) ?>
                <?= champ_choix('concert_id', 'Concert concerné', $concerts, $p['concert_id'], ['vide' => '— aucun —']) ?>
              </div>
              <?= champ('note', 'Note', $p['note'], ['max' => 500, 'indication' => 'ce qu\'on en retient']) ?>
              <button type="submit" class="bouton bouton--petit">Enregistrer</button></form></details>
        </th>
        <td class="nombre"><?= $nombre($p['portee'] ?? $p['impressions']) ?><?= $p['portee'] === null && $p['impressions'] !== null ? '<br><span class="discret">impressions</span>' : '' ?></td>
        <td class="nombre"><strong><?= $nombre($p['interactions']) ?></strong><?= $p['engagement'] !== null ? '<br><span class="discret">' . e(rtrim(rtrim(number_format((float) $p['engagement'], 2, ',', ' '), '0'), ',')) . ' % d\'engagement</span>' : '' ?></td>
        <td class="discret insecable"><?= icone('coeur') ?> <?= $nombre($p['likes']) ?> · <?= icone('commentaire') ?> <?= $nombre($p['commentaires']) ?> · <?= icone('partage') ?> <?= $nombre($p['partages']) ?><?= $p['vues'] !== null ? '<br>' . icone('oeil') . ' ' . $nombre($p['vues']) . ' vues' : '' ?><?= $p['clics'] !== null ? ' · ' . $nombre($p['clics']) . ' clics' : '' ?></td>
        <td><?php if ($p['rapport'] === null): ?><span class="discret">—</span>
          <?php else: ?><span class="puce <?= $p['rapport'] >= 1.5 ? 'puce--ok' : ($p['rapport'] < 0.6 ? 'puce--alerte' : '') ?>"><?= $p['rapport'] >= 1.5 ? icone('tendance') : '' ?>× <?= e(str_replace('.', ',', (string) round($p['rapport'], 1))) ?></span><br><span class="discret">médiane : <?= $nombre($p['mediane']) ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if (count($posts) > 150): ?><p class="aide">Les 150 premières sur <?= count($posts) ?> : resserrez la période ou les filtres.</p><?php endif; ?>
  <p class="aide">« Venues de Pupitre » : la publication correspond à une action de communication de Pupitre (même adresse, ou même réseau, même jour et même début de texte). Les mesures sont celles du dernier relevé : elles continuent de monter pendant des semaines.</p>
</div>
<?php endif; ?>
<?php endif; ?>

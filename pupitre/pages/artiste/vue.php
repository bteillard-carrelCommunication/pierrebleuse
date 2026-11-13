<?php
/**
 * Vue de l'artiste, pensée pour le téléphone (390 px), en lecture : prochain concert, cette
 * semaine, la saison à venir par mois avec le statut de chaque date, date de la dernière mise à
 * jour du site. Une seule action : laisser une note sur un concert, qui arrive dans « À valider ».
 */

defined('PUPITRE') or exit;

$moi = compte();
$titre = 'Ma saison';
$jour = aujourdhui();
$peut_noter = peut('artiste.note');

if (est_post()) {
    exiger_droit('artiste.note');
    $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [recu_entier('concert') ?? 0]);
    $texte = mb_substr(recu('note'), 0, 2000);
    if ($c && $texte !== '') {
        bd_inserer('notes_artiste', ['concert_id' => $c['id'], 'compte_id' => $moi['id'], 'texte' => $texte]);
        journaliser('note', 'concert', (int) $c['id'], $c['titre_fr'] . ', note de l\'artiste', null, ['note' => $texte]);
        message('ok', 'Votre note est transmise à l\'équipe.');
    } else {
        message('alerte', 'Écrivez votre note avant de l\'envoyer.');
    }
    rediriger(url('artiste') . ($c ? '#c' . (int) $c['id'] : ''));
}

$reps = agenda_representations(['du' => $jour]);
$prochaine = null;
foreach ($reps as $r) {
    if ($r['statut'] !== 'annulee' && $r['type'] !== 'disque') { $prochaine = $r; break; }
}
$semaine = array_filter($reps, fn($r) => $r['statut'] !== 'annulee' && $r['date'] <= ajouter_jours($jour, 7) && (!$prochaine || $r['id'] !== $prochaine['id']));
$par_mois = [];
foreach ($reps as $r) $par_mois[substr($r['date'], 0, 7)][] = $r;
$derniere = publication_derniere();
$mes_notes = [];
foreach (bd_lignes('SELECT concert_id, COUNT(*) AS n FROM notes_artiste WHERE compte_id = ? GROUP BY concert_id', [$moi['id']]) as $n) $mes_notes[(int) $n['concert_id']] = (int) $n['n'];

$lieu = fn(array $r) => implode(', ', array_filter([$r['lieu_salle'], $r['lieu_ville'], $r['lieu_pays']], 'strlen'));
$note = function (array $r, string $suffixe) use ($peut_noter, $mes_notes): string {
    if (!$peut_noter) return '';
    $id = 'note-' . (int) $r['id'] . $suffixe;
    $deja = $mes_notes[(int) $r['concert_id']] ?? 0;
    return '<details><summary>Laisser une note' . ($deja ? ' <span class="discret">(' . $deja . ' déjà transmise' . ($deja > 1 ? 's' : '') . ')</span>' : '') . '</summary>'
        . '<form method="post">' . champ_csrf() . '<input type="hidden" name="concert" value="' . (int) $r['concert_id'] . '">'
        . '<label class="sr" for="' . $id . '">Votre note sur ce concert</label>'
        . '<textarea id="' . $id . '" name="note" rows="3" maxlength="2000" required></textarea>'
        . '<button type="submit" class="bouton bouton--plein">Envoyer la note</button></form></details>';
};
?>
<div class="artiste">
  <h1><?= e(reglage('artiste_nom') ?: 'Ma saison') ?></h1>
  <p class="artiste__maj">Site mis à jour <?= $derniere ? 'le ' . e(horodatage_fr($derniere['cree_le'])) : ': pas encore de publication depuis Pupitre' ?>.<?= !$peut_noter ? ' Aperçu de la vue de l\'artiste : lui seul peut y laisser une note.' : '' ?></p>

  <section aria-labelledby="a-prochain">
    <h2 id="a-prochain">Prochain concert</h2>
    <?php if (!$prochaine): ?><p class="rien">Aucun concert à venir dans l'agenda.</p><?php else: $r = $prochaine; ?>
    <div class="prochain" id="c<?= (int) $r['concert_id'] ?>">
      <p class="prochain__quand"><?= e(date_fr($r['date'], true)) ?><?= $r['heure'] ? ' · ' . e(heure_fr($r['heure'])) : '' ?></p>
      <h3><?= e($r['titre_fr']) ?></h3>
      <?php if ($r['ensemble_fr'] !== '' && stripos($r['titre_fr'], $r['ensemble_fr']) === false): ?><p><?= e($r['ensemble_fr']) ?></p><?php endif; ?>
      <p><?= e($lieu($r)) ?></p>
      <p><?= etiquette_statut($r['statut']) ?></p>
    </div>
    <div class="concert"><?= $note($r, '-p') ?></div>
    <?php endif; ?>
  </section>

  <section aria-labelledby="a-semaine">
    <h2 id="a-semaine">Cette semaine</h2>
    <?php if (!$semaine): ?><p class="rien">Rien d'autre dans les sept prochains jours.</p><?php endif; ?>
    <?php foreach ($semaine as $r): ?>
      <div class="concert">
        <p class="concert__quand"><strong><?= e(date_fr($r['date'], true, false)) ?></strong><?= $r['heure'] ? ' ' . e(heure_fr($r['heure'])) : '' ?> <?= etiquette_statut($r['statut']) ?></p>
        <h3><?= e($r['titre_fr']) ?></h3>
        <p class="concert__lieu"><?= e($lieu($r)) ?></p>
      </div>
    <?php endforeach; ?>
  </section>

  <section aria-labelledby="a-saison">
    <h2 id="a-saison">La saison à venir</h2>
    <?php if (!$par_mois): ?><p class="rien">Aucune date à venir.</p><?php endif; ?>
    <?php foreach ($par_mois as $mois => $lignes): ?>
    <div class="mois-artiste">
      <h3><?= e(mois_fr($mois)) ?></h3>
      <?php foreach ($lignes as $r): ?>
        <div class="concert" id="r<?= (int) $r['id'] ?>">
          <p class="concert__quand"><strong><?= e(date_fr($r['date'], true, false)) ?></strong><?= $r['heure'] ? ' ' . e(heure_fr($r['heure'])) : '' ?> <?= etiquette_statut($r['statut']) ?></p>
          <h4><?= e($r['titre_fr']) ?><?= $r['type'] === 'disque' ? ' <span class="puce">disque</span>' : '' ?></h4>
          <?php if ($r['ensemble_fr'] !== '' && stripos($r['titre_fr'], $r['ensemble_fr']) === false): ?><p class="concert__lieu"><?= e($r['ensemble_fr']) ?></p><?php endif; ?>
          <p class="concert__lieu"><?= e($lieu($r)) ?></p>
          <?php if ($r['statut'] === 'annulee' && $r['raison'] !== ''): ?><p class="concert__lieu"><?= e($r['raison']) ?></p><?php endif; ?>
          <?= $note($r, '') ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </section>

  <p class="aide">Connue : pas encore annoncée par l'organisateur. Sous embargo : annonce prévue. Annoncée : sortira sur le site. Publiée : sur le site.</p>
</div>

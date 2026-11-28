<?php
/** Concerts : la collection, date par date, la saison par mois ; filtres par statut, maison et période. */

defined('PUPITRE') or exit;

$titre = 'Concerts';
[$saison_debut, $saison_fin] = saison();
$jour = aujourdhui();
$periodes = ['saison' => 'La saison', 'avenir' => 'À venir', 'passees' => 'Passées', 'tout' => 'Tout', 'dates' => 'Entre deux dates'];
$periode = array_key_exists(recu('periode'), $periodes) ? recu('periode') : 'saison';
$filtres = [
    'statut' => array_key_exists(recu('statut'), STATUTS) ? recu('statut') : '',
    'maison' => recu_entier('maison'),
    'q' => recu('q'),
];
if ($periode === 'saison') { $filtres['du'] = $saison_debut; $filtres['au'] = $saison_fin; }
elseif ($periode === 'avenir') $filtres['du'] = $jour;
elseif ($periode === 'passees') $filtres['au'] = ajouter_jours($jour, -1);
elseif ($periode === 'dates') {
    if (date_valide(recu('du'))) $filtres['du'] = recu('du');
    if (date_valide(recu('au'))) $filtres['au'] = recu('au');
}
$reps = agenda_representations($filtres);
$maisons = [];
foreach (bd_lignes('SELECT * FROM maisons ORDER BY ordre, id') as $m) $maisons[(int) $m['id']] = $m['nom_en'];
$par_mois = [];
foreach ($reps as $r) $par_mois[substr($r['date'], 0, 7)][] = $r;
$decompte = array_count_values(array_column($reps, 'statut'));
?>
<div class="titre">
  <h1>Concerts</h1>
  <span class="titre__suite"><?= e(pluriel(count($reps), 'représentation', 'représentations')) ?><?php foreach ($decompte as $s => $n): ?> · <?= e(mb_strtolower(STATUTS[$s])) ?> : <?= (int) $n ?><?php endforeach; ?></span>
  <div class="titre__actions">
    <?php if (peut('import')): ?><a class="bouton" href="<?= e(url('imports')) ?>"><?= icone('import') ?>Importer l'agenda de l'agence</a><?php endif; ?>
    <a class="bouton bouton--plein" href="<?= e(url('concert', ['id' => 'nouveau'])) ?>"><?= icone('plus') ?>Nouveau concert</a>
  </div>
</div>

<form method="get" class="filtres">
  <input type="hidden" name="page" value="agenda">
  <?= champ_choix('periode', 'Période', $periodes, $periode) ?>
  <?php if ($periode === 'dates'): ?>
    <?= champ('du', 'Du', recu('du'), ['type' => 'date']) ?>
    <?= champ('au', 'Au', recu('au'), ['type' => 'date']) ?>
  <?php endif; ?>
  <?= champ_choix('statut', 'Statut', STATUTS, $filtres['statut'], ['vide' => 'Tous']) ?>
  <?= champ_choix('maison', 'Maison', $maisons, $filtres['maison'], ['vide' => 'Toutes']) ?>
  <?= champ('q', 'Chercher', $filtres['q'], ['type' => 'search', 'indication' => 'titre, ensemble, ville']) ?>
  <button type="submit" class="bouton">Filtrer</button>
</form>

<?php if (!$reps): ?>
  <div class="carte"><?= etat_vide('concerts', 'Aucune représentation pour ces filtres', 'Élargissez la période, ou retirez un filtre.') ?></div>
<?php endif; ?>

<?php foreach ($par_mois as $mois => $lignes): ?>
<section class="mois">
  <h2><?= e(mois_fr($mois)) ?></h2>
  <div class="defile defile--pile-large"><table class="tableau tableau--agenda" role="table">
    <thead role="rowgroup"><tr role="row"><th role="columnheader">Date</th><th role="columnheader">Heure</th><th role="columnheader">Concert</th><th role="columnheader">Lieu</th><th role="columnheader">Maison</th><th role="columnheader">Statut</th></tr></thead>
    <tbody role="rowgroup">
    <?php foreach ($lignes as $r): ?>
      <tr role="row"<?= $r['date'] < $jour ? ' class="passee"' : '' ?>>
        <td role="cell" class="date"><strong><?= e(date_fr($r['date'], false, false)) ?></strong><br><span class="discret"><?= e(JOURS_FR[(int) date('w', strtotime($r['date'] . ' 12:00'))]) ?></span></td>
        <td role="cell" class="nombre"><?= e(heure_fr($r['heure'])) ?: '<span class="discret">—</span>' ?></td>
        <th scope="row" role="rowheader">
          <a href="<?= e(url('concert', ['id' => $r['concert_id']])) ?>"><?= e($r['titre_en']) ?></a>
          <?php if ($r['type'] !== 'concert'): ?> <span class="puce"<?= $r['type'] === 'tournee' ? ' title="Plusieurs dates dans des villes différentes : Pupitre y voit une tournée."' : '' ?>><?= e(TYPES_CONCERT[$r['type']] ?? $r['type']) ?></span><?php endif; ?>
          <?php if (!$r['publie']): ?> <span class="puce puce--alerte">dépublié</span><?php endif; ?>
          <?php if ($r['temps_fort']): ?> <span class="puce puce--accent">à la une</span><?php endif; ?>
          <?php if ($r['ensemble_en'] !== '' && stripos($r['titre_en'], $r['ensemble_en']) === false): ?><br><span class="discret"><?= e($r['ensemble_en']) ?></span><?php endif; ?>
        </th>
        <td role="cell"><?= e(implode(', ', array_filter([$r['lieu_ville'], $r['lieu_pays']], 'strlen'))) ?><?php if ($r['lieu_salle'] !== ''): ?><br><span class="discret"><?= e($r['lieu_salle']) ?></span><?php endif; ?></td>
        <td role="cell" class="discret"><?= e($maisons[(int) $r['maison_id']] ?? '') ?></td>
        <td role="cell"><?= etiquette_statut($r['statut']) ?><?php if ($r['statut'] === 'embargo' && $r['embargo_le']): ?><br><span class="discret">jusqu'au <?= e(date_fr($r['embargo_le'])) ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>

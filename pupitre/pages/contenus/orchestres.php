<?php
/**
 * Orchestres invités : les fiches d'annuaire cochées « dans le parcours », avec leur ville, leurs
 * coordonnées et leur zone. Pupitre propose les orchestres des concerts passés.
 */

defined('PUPITRE') or exit;

$titre = 'Orchestres invités';

if (est_post()) {
    $action = recu('action');
    if ($action === 'ordre') {
        $avant = bd_lignes('SELECT id, nom, parcours_zone FROM annuaire WHERE dans_parcours = 1 ORDER BY parcours_ordre, id');
        $gardes = [];
        foreach (array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) as $ordre => $l) {
            $fid = ctype_digit((string) ($l['id'] ?? '')) ? (int) $l['id'] : 0;
            if (!$fid) continue;
            $zone = array_key_exists($l['parcours_zone'] ?? '', ZONES_PARCOURS) ? $l['parcours_zone'] : '';
            bd_modifier('annuaire', ['parcours_ordre' => $ordre, 'parcours_zone' => $zone], ['id' => $fid, 'dans_parcours' => 1]);
            $gardes[] = $fid;
        }
        foreach ($avant as $f) {
            if (!in_array((int) $f['id'], $gardes, true)) bd_modifier('annuaire', ['dans_parcours' => 0], ['id' => $f['id']]);
        }
        $apres = bd_lignes('SELECT id, nom, parcours_zone FROM annuaire WHERE dans_parcours = 1 ORDER BY parcours_ordre, id');
        journaliser('modification', 'contenu', null, 'Orchestres invités', ['lignes' => $avant], ['lignes' => $apres]);
        message('ok', 'Parcours enregistré.');
    } elseif ($action === 'ajouter' && ($f = fiche_lire(recu_entier('id') ?? 0))) {
        if ($f['latitude'] === null || $f['longitude'] === null) {
            message('alerte', 'Indiquez d\'abord la latitude et la longitude de « ' . $f['nom'] . ' » : sans elles, pas de point sur la carte.');
            rediriger(url('fiche', ['id' => $f['id']]));
        }
        objet_enregistrer('annuaire', (int) $f['id'], ['dans_parcours' => 1, 'parcours_ordre' => 1 + (int) bd_valeur('SELECT MAX(parcours_ordre) FROM annuaire WHERE dans_parcours = 1')], 'fiche', $f['nom']);
        message('ok', '« ' . $f['nom'] . ' » figure maintenant dans le parcours.');
    }
    rediriger(url('orchestres'));
}

$fiches = bd_lignes('SELECT * FROM annuaire WHERE dans_parcours = 1 ORDER BY parcours_ordre, id');
// propositions : les ensembles des concerts passés qui n'y sont pas encore
$propositions = bd_lignes("SELECT a.*, MAX(r.date) AS derniere, COUNT(DISTINCT c.id) AS concerts
    FROM annuaire a JOIN concerts c ON c.ensemble_id = a.id JOIN representations r ON r.concert_id = c.id
    WHERE a.dans_parcours = 0 AND a.categorie IN ('orchestre', 'ensemble') AND r.date < ? AND r.statut <> 'annulee' AND c.type <> 'disque'
    GROUP BY a.id ORDER BY derniere DESC", [aujourdhui()]);
$exports = site_exports();
?>
<div class="titre"><h1>Orchestres invités</h1><span class="titre__suite">data/orchestres.js</span></div>
<p class="intro">La liste du parcours et les points de la carte du site. Ce sont des fiches de l'annuaire : le nom, la ville et les coordonnées se modifient sur la fiche. Ici se règlent l'ordre et la zone.</p>

<form method="post" class="carte">
  <?= champ_csrf() ?><input type="hidden" name="action" value="ordre">
  <div data-liste><div class="lignes" data-lignes>
  <?php foreach ($fiches as $i => $f): $p = 'lignes[' . $i . ']'; ?>
    <div class="ligne ligne--compacte" data-ligne>
      <input type="hidden" name="<?= e($p) ?>[id]" value="<?= (int) $f['id'] ?>">
      <div class="rangee rangee--avec">
        <div class="champ">
          <a href="<?= e(url('fiche', ['id' => $f['id']])) ?>"><strong><?= e($f['nom']) ?></strong></a>
          <?php if ($f['nom_provisoire']): ?> <span class="puce puce--alerte">nom à fournir : le site n'affiche que la ville</span><?php endif; ?>
          <br><span class="discret"><?= e($f['ville_fr']) ?><?= $f['ville_en'] !== '' ? ' / ' . e($f['ville_en']) : '' ?> · <?= e(rtrim(rtrim((string) $f['latitude'], '0'), '.')) ?>, <?= e(rtrim(rtrim((string) $f['longitude'], '0'), '.')) ?></span>
        </div>
        <?= champ_choix('parcours_zone', 'Zone', ZONES_PARCOURS, $f['parcours_zone'], ['prefixe' => $p]) ?>
        <?= boutons_ligne('cet orchestre du parcours') ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div></div>
  <p class="aide">Retirer une ligne ôte l'orchestre du parcours ; sa fiche reste dans l'annuaire.</p>
  <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer l'ordre et les zones</button>
    <a class="bouton" href="<?= e(url('fiche', ['id' => 'nouvelle', 'categorie' => 'orchestre'])) ?>">Nouvel orchestre</a></div>
</form>

<div class="carte">
  <h2>Propositions</h2>
  <p class="intro">Les orchestres et ensembles des concerts passés qui ne sont pas dans le parcours. Rien n'y entre sans vous.</p>
  <?php if (!$propositions): ?><p class="rien">Aucune proposition pour l'instant.</p><?php else: ?>
  <ul class="items">
  <?php foreach ($propositions as $f): ?>
    <li>
      <span class="item__titre"><?= e($f['nom']) ?></span>
      <span class="discret"><?= e(pluriel((int) $f['concerts'], 'concert', 'concerts')) ?>, le dernier le <?= e(date_fr($f['derniere'])) ?><?= $f['latitude'] === null ? ' · coordonnées à renseigner' : '' ?></span>
      <form method="post" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="ajouter"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <button type="submit" class="bouton bouton--petit">Ajouter au parcours</button></form>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<?= apercu_zones(['ORCHESTRES' => ''], $exports) ?>

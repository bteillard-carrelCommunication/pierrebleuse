<?php
/** Annuaire : personnes et structures par catégorie, recherche, doublons probables. */

defined('PUPITRE') or exit;

$titre = 'Annuaire';
$categorie = array_key_exists(recu('categorie'), CATEGORIES) ? recu('categorie') : '';
$q = recu('q');
$ou = ['1 = 1'];
$p = [];
if ($categorie !== '') { $ou[] = 'a.categorie = ?'; $p[] = $categorie; }
if ($q !== '') { $ou[] = '(a.nom LIKE ? OR a.autres_noms LIKE ? OR a.ville_en LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%"); }
$fiches = bd_lignes("SELECT a.*,
        (SELECT COUNT(*) FROM annuaire_comptes x WHERE x.fiche_id = a.id) AS comptes,
        (SELECT COUNT(*) FROM annuaire_comptes x WHERE x.fiche_id = a.id AND x.etat = 'propose') AS proposes,
        (SELECT COUNT(*) FROM parcours_orchestres p WHERE p.fiche_id = a.id) AS dans_parcours
    FROM annuaire a WHERE " . implode(' AND ', $ou) . ' ORDER BY a.nom', $p);
if (est_post() && recu('action') === 'completer') {
    // l'IA cherche le site et les comptes officiels des fiches des concerts à venir qui n'en ont aucun : ils entrent « proposés »
    if (!ia_configuree()) message('erreur', 'L\'IA n\'est pas configurée (ANTHROPIC_API_KEY dans pupitre/.env).');
    else {
        $tache = tache_creer('annuaire_completer', []);
        if (!taches_lancer_en_fond()) message('alerte', 'La recherche attend le prochain passage des tâches de fond (cron).');
        rediriger(url('annuaire', ['tache' => $tache]));
    }
    rediriger(url('annuaire'));
}
$a_completer = fiches_a_completer(12);
$tache = recu_entier('tache') ? tache_lire(recu_entier('tache')) : null;
$decompte = [];
foreach (bd_lignes('SELECT categorie, COUNT(*) AS n FROM annuaire GROUP BY categorie') as $l) $decompte[$l['categorie']] = (int) $l['n'];
$options = [];
foreach (CATEGORIES as $k => $l) $options[$k] = $l . ' (' . ($decompte[$k] ?? 0) . ')';
$doublons = ($categorie === '' && $q === '') ? fiches_doublons() : [];
?>
<div class="titre">
  <h1>Annuaire</h1>
  <span class="titre__suite"><?= e(pluriel(count($fiches), 'fiche', 'fiches')) ?></span>
  <div class="titre__actions"><a class="bouton bouton--plein" href="<?= e(url('fiche', ['id' => 'nouvelle', 'categorie' => $categorie])) ?>">Nouvelle fiche</a></div>
</div>

<?php if ($tache): ?><div class="carte"><?= suivi_tache($tache) ?></div><?php endif; ?>
<?php if ($a_completer && $categorie === '' && $q === ''): ?>
<div class="bandeau">
  <div class="bandeau__textes"><span class="libelle">Comptes à identifier</span>
    <span class="bandeau__titre"><?= e(pluriel(count($a_completer), 'fiche liée à un concert à venir n\'a', 'fiches liées à des concerts à venir n\'ont')) ?> encore aucun compte de réseau social.</span>
    <span><?= e(implode(', ', array_slice(array_column($a_completer, 'nom'), 0, 6))) ?><?= count($a_completer) > 6 ? '…' : '' ?></span></div>
  <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="completer">
    <button type="submit" class="bouton bouton--sombre"<?= ia_configuree() ? '' : ' disabled title="L\'IA n\'est pas configurée"' ?>><?= icone('ia') ?>Les chercher avec l'IA</button></form>
</div>
<?php endif; ?>
<form method="get" class="filtres">
  <input type="hidden" name="page" value="annuaire">
  <?= champ_choix('categorie', 'Catégorie', $options, $categorie, ['vide' => 'Toutes']) ?>
  <?= champ('q', 'Chercher', $q, ['type' => 'search', 'indication' => 'nom, autre nom, ville']) ?>
  <button type="submit" class="bouton">Filtrer</button>
</form>

<?php if ($doublons): ?>
<div class="carte">
  <h2>Doublons probables</h2>
  <ul class="items">
  <?php foreach ($doublons as [$a, $b, $raison]): ?>
    <li><span><a href="<?= e(url('fiche', ['id' => $a['id']])) ?>"><?= e($a['nom']) ?></a> <span class="discret">(<?= e(CATEGORIES[$a['categorie']]) ?>)</span> et
      <a href="<?= e(url('fiche', ['id' => $b['id']])) ?>"><?= e($b['nom']) ?></a> <span class="discret">(<?= e(CATEGORIES[$b['categorie']]) ?>)</span> : <?= e($raison) ?></span></li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if (!$fiches): ?><div class="carte vide"><p>Aucune fiche.</p></div><?php else: ?>
<div class="defile"><table class="tableau">
  <thead><tr><th>Nom</th><th>Catégorie</th><th>Ville, pays</th><th>Comptes sociaux</th><th>Site</th></tr></thead>
  <tbody>
  <?php foreach ($fiches as $f): ?>
    <tr>
      <th scope="row"><a href="<?= e(url('fiche', ['id' => $f['id']])) ?>"><?= e($f['nom']) ?></a>
        <?php if ($f['nom_provisoire']): ?> <span class="puce puce--alerte">nom à fournir</span><?php endif; ?>
        <?php if ($f['dans_parcours']): ?> <span class="puce puce--accent">parcours</span><?php endif; ?>
        <?php if (trim((string) $f['autres_noms']) !== ''): ?><br><span class="discret"><?= e(implode(' · ', lignes($f['autres_noms']))) ?></span><?php endif; ?></th>
      <td><?= e(CATEGORIES[$f['categorie']]) ?></td>
      <td><?= e(implode(', ', array_filter([$f['ville_en'], $f['pays_en']], 'strlen'))) ?></td>
      <td><?= $f['comptes'] ? (int) $f['comptes'] : '<span class="discret">—</span>' ?><?= $f['proposes'] ? ' <span class="puce puce--alerte">' . (int) $f['proposes'] . ' à vérifier</span>' : '' ?></td>
      <td><?php if ($f['site'] !== ''): ?><a href="<?= e($f['site']) ?>" target="_blank" rel="noopener"><?= e(couper(preg_replace('#^https?://(www\.)?#', '', $f['site']), 34)) ?></a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<?php
/** Journal des modifications : qui, quoi, quand, avant, après. Et, pour le pilote, les appels à l'IA. */

defined('PUPITRE') or exit;

$titre = 'Journal';
$type = recu('type');
$qui = recu_entier('compte');
$page = max(1, recu_entier('p') ?? 1);
$par_page = 60;

$ou = ['1 = 1'];
$p = [];
if ($type !== '') { $ou[] = 'objet_type = ?'; $p[] = $type; }
if ($qui) { $ou[] = 'compte_id = ?'; $p[] = $qui; }
$total = (int) bd_valeur('SELECT COUNT(*) FROM journal WHERE ' . implode(' AND ', $ou), $p);
$lignes = bd_lignes('SELECT * FROM journal WHERE ' . implode(' AND ', $ou) . ' ORDER BY id DESC LIMIT ' . $par_page . ' OFFSET ' . (($page - 1) * $par_page), $p);
$types = bd_colonne('SELECT DISTINCT objet_type FROM journal ORDER BY objet_type');
$comptes = [];
foreach (bd_lignes('SELECT id, nom FROM comptes ORDER BY nom') as $c) $comptes[$c['id']] = $c['nom'];
$appels = peut('reglages') ? bd_lignes('SELECT * FROM appels_ia ORDER BY id DESC LIMIT 20') : [];
?>
<div class="titre"><h1>Journal</h1><span class="titre__suite"><?= e(pluriel($total, 'ligne', 'lignes')) ?></span></div>

<form method="get" class="filtres">
  <input type="hidden" name="page" value="journal">
  <?= champ_choix('type', 'Objet', array_combine($types, $types), $type, ['vide' => 'Tous']) ?>
  <?= champ_choix('compte', 'Par', $comptes, $qui, ['vide' => 'Tout le monde']) ?>
  <button type="submit" class="bouton">Filtrer</button>
</form>

<div class="carte"><?= journal_afficher($lignes, true) ?></div>
<p class="actions">
  <?php if ($page > 1): ?><a class="bouton" href="<?= e(url('journal', ['type' => $type, 'compte' => $qui, 'p' => $page - 1])) ?>">Plus récent</a><?php endif; ?>
  <?php if ($page * $par_page < $total): ?><a class="bouton" href="<?= e(url('journal', ['type' => $type, 'compte' => $qui, 'p' => $page + 1])) ?>">Plus ancien</a><?php endif; ?>
</p>

<?php if ($appels): ?>
<h2 style="margin-top:2rem">Appels à l'IA</h2>
<p class="intro">Objet, modèle, jetons, durée, résultat. Ni le document envoyé ni la réponse ne sont conservés ici.</p>
<div class="defile"><table class="tableau tableau--serre">
  <thead><tr><th>Quand</th><th>Objet</th><th>Modèle</th><th>Jetons lus</th><th>Jetons écrits</th><th>Durée</th><th>Résultat</th></tr></thead>
  <tbody><?php foreach ($appels as $a): ?>
    <tr><td class="nombre"><?= e(horodatage_fr($a['cree_le'])) ?></td><td><?= e($a['objet']) ?></td><td class="insecable"><?= e($a['modele']) ?></td>
      <td class="nombre"><?= (int) $a['jetons_entree'] ?></td><td class="nombre"><?= (int) $a['jetons_sortie'] ?></td>
      <td class="nombre"><?= e(str_replace('.', ',', (string) round($a['duree_ms'] / 1000, 1))) ?> s</td>
      <td><span class="puce <?= $a['resultat'] === 'ok' ? 'puce--ok' : 'puce--erreur' ?>"><?= e($a['resultat']) ?></span> <span class="discret"><?= e($a['erreur']) ?></span></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>

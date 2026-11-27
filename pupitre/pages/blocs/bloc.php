<?php
/**
 * Un bloc du site : sa visibilité, ses textes, ses libellés, et les collections ou écrans qu'il
 * affiche. Ce que le bloc donne à régler est décrit dans inclus/blocs.php (blocs_description()).
 */

defined('PUPITRE') or exit;

$cle = recu('cle');
$description = blocs_description()[$cle] ?? null;
$bloc = blocs_tous()[$cle] ?? null;
if (!$description || (!$bloc && $cle !== 'general')) {
    http_response_code(404);
    $titre = 'Bloc introuvable';
    echo '<div class="vide"><h1>Bloc introuvable</h1><p><a class="bouton" href="' . e(url('blocs')) . '">Tous les blocs</a></p></div>';
    return;
}
$nom = $bloc['nom'] ?? $description['nom'];
$titre = $nom;
$textes_du_bloc = $description['textes'] ?? [];
$libelles_du_bloc = $description['libelles'] ?? [];
$reglages_du_bloc = RACINE . '/pages/blocs/reglages/' . $cle . '.php';      // réglages propres à un bloc, s'il en a

if (est_post()) {
    $action = recu('action');
    if ($bloc && in_array($action, ['afficher', 'masquer'], true)) {
        if (bloc_afficher($cle, $action === 'afficher')) message('ok', 'Bloc ' . ($action === 'afficher' ? 'affiché' : 'masqué') . '. Le site suivra à la prochaine publication.');
        rediriger(url('bloc', ['cle' => $cle]));
    }
    if ($action === 'contenus') {
        textes_enregistrer($_POST, $textes_du_bloc);
        libelles_enregistrer(is_array($_POST['lib'] ?? null) ? $_POST['lib'] : [], $libelles_du_bloc);
        message('ok', 'Enregistré. Le site suivra à la prochaine publication.');
        rediriger(url('bloc', ['cle' => $cle]));
    }
}

$textes = textes_lire();
$libelles = libelles_tous();
$reference = langue_reference();
$rang = array_search($cle, array_keys(blocs_tous()), true);
$voisins = array_keys(blocs_tous());
?>
<p class="fil"><a href="<?= e(url('blocs')) ?>">Blocs du site</a></p>
<div class="titre">
  <h1><?= e($nom) ?></h1>
  <?php if ($bloc): ?>
    <span class="titre__suite">
      <?php if (!$bloc['masquable']): ?><span class="puce">toujours affiché</span>
      <?php elseif ($bloc['visible']): ?><span class="puce puce--ok">affiché</span>
      <?php else: ?><span class="puce puce--alerte">masqué</span><?php endif; ?>
    </span>
    <div class="titre__actions">
      <?php if ($bloc['masquable']): ?>
        <form method="post"><?= champ_csrf() ?><button type="submit" name="action" value="<?= $bloc['visible'] ? 'masquer' : 'afficher' ?>" class="bouton"><?= $bloc['visible'] ? 'Masquer ce bloc' : 'Afficher ce bloc' ?></button></form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php if (!empty($description['intro'])): ?><p class="intro"><?= e($description['intro']) ?><?= $bloc && !$bloc['visible'] ? ' Ce bloc est masqué : il n\'est pas sur le site, mais tout ce qui suit est gardé.' : '' ?></p><?php endif; ?>

<?php if (!empty($description['liens'])): ?>
<div class="carte">
  <h2>Ce que ce bloc affiche</h2>
  <ul class="items">
    <?php foreach ($description['liens'] as [$libelle, $page, $params, $ancre]): ?>
      <li><span class="item__titre"><?= e($libelle) ?></span><a class="bouton bouton--petit" href="<?= e(url($page, $params) . $ancre) ?>">Ouvrir</a></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if (is_file($reglages_du_bloc)) require $reglages_du_bloc; ?>

<?php if ($textes_du_bloc || $libelles_du_bloc): ?>
<form method="post" class="carte">
  <?= champ_csrf() ?><input type="hidden" name="action" value="contenus">
  <?php if ($textes_du_bloc): ?>
    <h2>Textes</h2>
    <p class="aide aide--haut">*…* rend l'italique. Les espaces insécables de la typographie sont posées par le site : n'en saisissez pas.</p>
    <?php foreach ($textes_du_bloc as $t): [$libelle, $type, $aide] = TEXTES[$t]; ?>
      <?= champ_bi($t, $libelle, $textes[$t][$reference] ?? '', '', ['texte' => $type === 'bi-texte', 'aide' => $aide, 'objet' => ['textes', $t, 'texte']]) ?>
    <?php endforeach; ?>
  <?php endif; ?>
  <?php if ($libelles_du_bloc): ?>
    <h2>Intitulés et boutons</h2>
    <?php foreach ($libelles_du_bloc as $l): if (!isset($libelles[$l])) continue; ?>
      <?= champ_bi('texte', $l, $libelles[$l]['texte_' . $reference], '', ['prefixe' => 'lib[' . $l . ']', 'objet' => ['libelles', $l, 'texte'], 'texte' => mb_strlen((string) $libelles[$l]['texte_' . $reference]) > 90]) ?>
    <?php endforeach; ?>
  <?php endif; ?>
  <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer</button></div>
</form>
<?php endif; ?>

<?php if (!empty($description['apercu'])) echo apercu_zones($description['apercu']); ?>

<?php if ($bloc && $rang !== false): ?>
<p class="actions">
  <?php if ($rang > 0): ?><a class="bouton bouton--petit" href="<?= e(url('bloc', ['cle' => $voisins[$rang - 1]])) ?>">← <?= e(blocs_tous()[$voisins[$rang - 1]]['nom']) ?></a><?php endif; ?>
  <?php if ($rang < count($voisins) - 1): ?><a class="bouton bouton--petit" href="<?= e(url('bloc', ['cle' => $voisins[$rang + 1]])) ?>"><?= e(blocs_tous()[$voisins[$rang + 1]]['nom']) ?> →</a><?php endif; ?>
</p>
<?php endif; ?>

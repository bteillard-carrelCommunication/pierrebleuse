<?php
/**
 * Blocs du site : les sections de la page d'accueil, dans l'ordre de la page. Chacune s'affiche ou
 * se masque d'un geste ; masquer ne supprime rien. Le détail d'un bloc est sur son écran.
 */

defined('PUPITRE') or exit;

$titre = 'Blocs du site';

if (est_post() && in_array(recu('action'), ['afficher', 'masquer'], true)) {
    $cle = recu('cle');
    $b = blocs_tous()[$cle] ?? null;
    if ($b && bloc_afficher($cle, recu('action') === 'afficher')) {
        message('ok', 'Bloc « ' . $b['nom'] . ' » ' . (recu('action') === 'afficher' ? 'affiché' : 'masqué') . '. Le site suivra à la prochaine publication.');
    }
    rediriger(url('blocs') . '#' . $cle);
}

$blocs = blocs_tous();
$description = blocs_description();
$parents = ['parcours' => 'Parcours professionnel'];
$parent_courant = '';
?>
<div class="titre"><h1>Blocs du site</h1><span class="titre__suite">data/site.js</span></div>
<p class="intro">Les sections de la page d'accueil, dans l'ordre où elles se suivent. Un bloc masqué n'occupe plus de place sur le site et quitte le menu ; ses contenus, ses médias et ses réglages restent en place, et il retrouve tout en étant réaffiché.</p>

<div class="carte">
  <ul class="items items--blocs">
    <li class="bloc-ligne">
      <span class="bloc-ligne__nom"><a href="<?= e(url('bloc', ['cle' => 'general'])) ?>"><?= e($description['general']['nom']) ?></a><span class="discret">tout le site</span></span>
      <span class="bloc-ligne__etat"></span>
      <span class="bloc-ligne__actions"><a class="bouton bouton--petit" href="<?= e(url('bloc', ['cle' => 'general'])) ?>">Régler</a></span>
    </li>
    <?php foreach ($blocs as $cle => $b): ?>
      <?php if ($b['parent'] !== $parent_courant): $parent_courant = $b['parent']; ?>
        <?php if ($parent_courant !== ''): ?><li class="bloc-ligne bloc-ligne--parent"><span class="bloc-ligne__nom"><?= e($parents[$parent_courant] ?? $parent_courant) ?><span class="discret">une section, trois blocs</span></span></li><?php endif; ?>
      <?php endif; ?>
      <li class="bloc-ligne<?= $b['parent'] !== '' ? ' bloc-ligne--enfant' : '' ?><?= $b['visible'] ? '' : ' bloc-ligne--masque' ?>" id="<?= e($cle) ?>">
        <span class="bloc-ligne__nom"><a href="<?= e(url('bloc', ['cle' => $cle])) ?>"><?= e($b['nom']) ?></a><span class="discret"><?= e($description[$cle]['intro'] ?? '') ?></span></span>
        <span class="bloc-ligne__etat">
          <?php if (!$b['masquable']): ?><span class="puce">toujours affiché</span>
          <?php elseif ($b['visible']): ?><span class="puce puce--ok">affiché</span>
          <?php else: ?><span class="puce puce--alerte">masqué</span><?php endif; ?>
        </span>
        <span class="bloc-ligne__actions">
          <?php if ($b['masquable']): ?>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="cle" value="<?= e($cle) ?>">
            <button type="submit" name="action" value="<?= $b['visible'] ? 'masquer' : 'afficher' ?>" class="bouton bouton--petit"><?= $b['visible'] ? 'Masquer' : 'Afficher' ?></button>
          </form>
          <?php endif; ?>
          <a class="bouton bouton--petit" href="<?= e(url('bloc', ['cle' => $cle])) ?>">Régler</a>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<p class="aide">Rien ne change sur le site tant que vous n'avez pas publié (écran « Le site »).</p>

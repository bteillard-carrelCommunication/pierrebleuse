<?php
/** Résultats de la recherche de la barre du haut, en pleine page (touche Entrée, ou navigateur sans script). */

defined('PUPITRE') or exit;

$q = mb_substr(recu('q'), 0, 80);
$titre = 'Recherche';
$groupes = recherche($q, 20);
$total = array_sum(array_map(fn($g) => count($g[1]), $groupes));
?>
<div class="titre">
  <h1>Recherche</h1>
  <span class="titre__suite"><?= $q === '' ? '' : '« ' . e($q) . ' » · ' . e(pluriel($total, 'résultat', 'résultats')) ?></span>
</div>
<?php if (mb_strlen(trim($q)) < 2): ?>
  <div class="carte"><?= etat_vide('recherche', 'Que cherchez-vous ?', 'Un concert, un orchestre, un article, un album, une fiche de l\'annuaire, un écran de Pupitre : deux lettres suffisent pour un écran, trois pour le reste.') ?></div>
<?php elseif (!$groupes): ?>
  <div class="carte"><?= etat_vide('recherche', 'Rien ne correspond', 'Essayez un autre mot, ou une partie du nom.') ?></div>
<?php endif; ?>
<?php foreach ($groupes as [$nom, $resultats]): ?>
<section class="carte">
  <div class="carte__tete"><h2><?= e($nom) ?></h2><span class="carte__compte"><?= count($resultats) ?></span></div>
  <ul class="items">
    <?php foreach ($resultats as [$libelle, $detail, $adresse, $icone]): ?>
      <li><a class="item__titre" href="<?= e($adresse) ?>"><?= icone($icone) ?> <?= e($libelle) ?></a><?php if ($detail !== ''): ?><span class="discret"><?= e($detail) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>

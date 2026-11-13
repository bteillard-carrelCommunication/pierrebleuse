<?php
/** Textes du site : citation, biographie, mention de saison, lettre, pied de page, description. */

defined('PUPITRE') or exit;

$titre = 'Textes';
if (est_post()) {
    textes_enregistrer($_POST);
    message('ok', 'Textes enregistrés. Ils sortiront sur le site à la prochaine publication.');
    rediriger(url('textes'));
}
$t = textes_lire();
$exports = site_exports();
?>
<div class="titre"><h1>Textes</h1><span class="titre__suite">data/textes.js</span></div>
<p class="intro">Les textes éditables du site. *…* rend l'italique. Les espaces insécables de la typographie sont posées par le site : n'en saisissez pas.</p>
<form method="post" class="carte">
  <?= champ_csrf() ?>
  <?php foreach (TEXTES as $cle => [$libelle, $type, $aide]): ?>
    <?= champ_bi($cle, $libelle, $t[$cle]['fr'] ?? '', $t[$cle]['en'] ?? '', ['texte' => $type === 'bi-texte', 'aide' => $aide]) ?>
  <?php endforeach; ?>
  <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer</button></div>
</form>
<?= apercu_zones(['CITATION' => 'Citation', 'BIOGRAPHIE' => 'Biographie', 'SAISON' => 'Mention de saison', 'LETTRE' => 'Lettre', 'CREDIT' => 'Pied de page'], $exports) ?>

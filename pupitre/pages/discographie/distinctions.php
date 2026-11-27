<?php
/**
 * Distinctions et récompenses : la collection, tous albums confondus. Les distinctions d'un album
 * se règlent aussi sur sa fiche. Seulement des distinctions vérifiées.
 */

defined('PUPITRE') or exit;

$titre = 'Distinctions';
$liste = ['table' => 'distinctions', 'ajout' => 'Ajouter une distinction', 'champs' => distinction_champs(true)];
$erreurs = [];
if (est_post()) {
    $erreurs = liste_enregistrer('Distinctions', $liste, is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : []);
    if (!$erreurs) { message('ok', 'Distinctions enregistrées. Le site suivra à la prochaine publication.'); rediriger(url('distinctions')); }
}
$lignes = $erreurs ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : bd_lignes('SELECT * FROM distinctions ORDER BY ordre, id');
?>
<div class="titre"><h1>Distinctions</h1><span class="titre__suite">data/disques.js · <?= e(pluriel(count($lignes), 'distinction', 'distinctions')) ?></span></div>
<p class="intro">Diapason d'or, récompenses de la presse musicale, distinctions internationales, sélections éditoriales, autres prix. Chacune se rattache à un album et paraît dans son panneau détaillé ; « mise en avant », elle paraît dès la vignette. N'inscrivez que des distinctions vérifiées.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
<form method="post" class="carte">
  <?= champ_csrf() ?>
  <?= liste_formulaire($liste, $lignes, 'lignes', (bool) $erreurs) ?>
  <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer les distinctions</button></div>
</form>

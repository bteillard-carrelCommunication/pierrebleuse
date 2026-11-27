<?php
/**
 * Écran d'un contenu en listes : parcours, disques, presse, téléchargements, contacts et réseaux.
 * Les listes et leurs champs sont décrits dans inclus/contenus.php.
 */

defined('PUPITRE') or exit;

$type = recu('type');
// ces collections ont désormais leurs propres écrans
if (isset(['parcours' => 1, 'disques' => 1, 'presse' => 1][$type])) rediriger(url($type));
$contenu = contenus()[$type] ?? null;
if (!$contenu) {
    http_response_code(404);
    $titre = 'Contenu introuvable';
    echo '<div class="vide"><h1>Contenu introuvable</h1></div>';
    return;
}
$titre = $contenu['titre'];
$erreurs = [];
$fautive = '';

if (est_post() && isset($contenu['listes'][recu('liste')])) {
    $fautive = recu('liste');
    $liste = $contenu['listes'][$fautive];
    $recues = is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : [];
    if ($liste['table'] === 'reseaux') {
        foreach ($recues as &$l) if (is_array($l)) $l['cle'] = slug((string) ($l['cle'] ?? ''), 30);
        unset($l);
    }
    $erreurs = liste_enregistrer($contenu['titre'] . ($liste['titre'] !== '' ? ', ' . mb_strtolower($liste['titre']) : ''), $liste, $recues);
    if (!$erreurs) {
        message('ok', 'Enregistré. Le site suivra à la prochaine publication.');
        rediriger(url('contenu', ['type' => $type]));
    }
}
?>
<div class="titre"><h1><?= e($contenu['titre']) ?></h1><span class="titre__suite"><?= e($contenu['fichier']) ?></span></div>
<?php if (!empty($contenu['intro'])): ?><p class="intro"><?= e($contenu['intro']) ?></p><?php endif; ?>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<?php foreach ($contenu['listes'] as $cle => $liste): ?>
<form method="post" class="carte">
  <?= champ_csrf() ?><input type="hidden" name="liste" value="<?= e($cle) ?>">
  <?php if ($liste['titre'] !== ''): ?><h2><?= e($liste['titre']) ?></h2><?php endif; ?>
  <?php
    // après une erreur, on réaffiche ce qui vient d'être saisi
    $lignes = ($erreurs && $fautive === $cle) ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : liste_lire($liste);
    echo liste_formulaire($liste, $lignes, 'lignes', $erreurs && $fautive === $cle);
  ?>
  <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer<?= $liste['titre'] !== '' ? ' : ' . e(mb_strtolower($liste['titre'])) : '' ?></button></div>
</form>
<?php endforeach; ?>

<?= apercu_zones($contenu['apercu']) ?>

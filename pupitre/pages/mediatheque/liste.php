<?php
/** Médiathèque : dépôt d'images, de sons, de vidéos et de documents, avec leurs droits. */

defined('PUPITRE') or exit;

$titre = 'Médiathèque';

if (est_post() && recu('action') === 'deposer') {
    $depot = media_deposer($_FILES['fichier'] ?? [], ['alt_fr' => recu('alt_fr'), 'alt_en' => recu('alt_en'), 'credit' => recu('credit'), 'droits' => recu('droits')]);
    if (is_string($depot)) {
        message('erreur', $depot);
        rediriger(url('medias'));
    }
    message('ok', 'Fichier déposé. Il sera copié dans le site à la publication, s\'il est employé.');
    rediriger(url('media', ['id' => $depot]));
}
$type = array_key_exists(recu('type'), TYPES_MEDIA) ? recu('type') : '';
$medias = $type !== '' ? bd_lignes('SELECT * FROM medias WHERE type = ? ORDER BY id DESC', [$type]) : bd_lignes('SELECT * FROM medias ORDER BY id DESC');
?>
<div class="titre"><h1>Médiathèque</h1><span class="titre__suite"><?= e(pluriel(count($medias), 'média', 'médias')) ?></span></div>

<div class="deux">
  <div>
    <form method="get" class="filtres">
      <input type="hidden" name="page" value="medias">
      <?= champ_choix('type', 'Type', TYPES_MEDIA, $type, ['vide' => 'Tous']) ?>
      <button type="submit" class="bouton">Filtrer</button>
    </form>
    <?php if (!$medias): ?><div class="carte vide"><p>Aucun média.</p></div><?php endif; ?>
    <div class="grille-medias">
    <?php foreach ($medias as $m): $emplois = count(media_emplois((int) $m['id'])); ?>
      <a class="vignette" href="<?= e(url('media', ['id' => $m['id']])) ?>">
        <span class="vignette__image"><?php if ($m['type'] === 'image'): ?><img src="<?= e(media_adresse((int) $m['id'])) ?>" alt="<?= e($m['alt_fr']) ?>" loading="lazy"><?php else: ?><?= e(TYPES_MEDIA[$m['type']]) ?><?php endif; ?></span>
        <span class="vignette__texte"><?= e(couper(media_nom($m), 44)) ?><br><span class="discret"><?= e(taille_lisible((int) $m['taille'])) ?> · <?= $emplois ? e(pluriel($emplois, 'emploi', 'emplois')) : 'pas employé' ?><?= empty($m['chemin_site']) ? ' · pas encore sur le site' : '' ?></span></span>
      </a>
    <?php endforeach; ?>
    </div>
  </div>
  <form method="post" enctype="multipart/form-data" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="deposer">
    <h2>Déposer un fichier</h2>
    <div class="champ"><label for="fichier">Fichier</label><input type="file" id="fichier" name="fichier" required aria-describedby="fichier-aide">
      <p class="aide" id="fichier-aide">Images (JPEG, PNG, WebP, SVG) jusqu'à <?= e(taille_lisible(taille_max('image'))) ?> ; sons (M4A, MP3), vidéos (MP4) et documents (PDF, ZIP, DOCX) jusqu'à <?= e(taille_lisible(taille_max('video'))) ?>.</p></div>
    <?= champ_bi('alt', 'Texte alternatif', '', '', ['aide' => 'Pour une image : ce qu\'elle montre.']) ?>
    <?= champ('credit', 'Crédit', '', ['aide' => 'Photographe, auteur, source.']) ?>
    <?= champ_texte('droits', 'Droits d\'usage', '', ['aide' => 'En texte libre : usages permis, durée, territoires, mention obligatoire.']) ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein">Déposer</button></div>
  </form>
</div>

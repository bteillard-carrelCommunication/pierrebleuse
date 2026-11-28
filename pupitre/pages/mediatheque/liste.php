<?php
/**
 * Médiathèque : une seule pour tout Pupitre. Dépôt d'images, de sons, de vidéos et de documents, ou
 * ajout d'un média par son adresse externe (image, son, vidéo hébergée, lien YouTube), avec crédit et
 * droits. Chaque média dit où il est employé.
 */

defined('PUPITRE') or exit;

$titre = 'Médiathèque';

if (est_post() && recu('action') === 'deposer') {
    $depot = media_deposer($_FILES['fichier'] ?? [], ['alt_en' => recu('alt_en'), 'credit' => recu('credit'), 'droits' => recu('droits')]);
    if (is_string($depot)) {
        message('erreur', $depot);
        rediriger(url('medias'));
    }
    bd_modifier('medias', recu_tr($_POST, 'alt', 500), ['id' => $depot]);       // le texte alternatif, dans la langue de rédaction
    message('ok', 'Fichier déposé. Il sera copié dans le site à la publication, s\'il est employé.');
    rediriger(url('media', ['id' => $depot]));
}
if (est_post() && recu('action') === 'adresse') {
    $ajout = media_creer_externe(recu('adresse'), array_key_exists(recu('type'), ['image' => 1, 'son' => 1, 'video' => 1]) ? recu('type') : '', ['titre' => recu('titre'), 'credit' => recu('credit'), 'droits' => recu('droits')]);
    if (is_string($ajout)) {
        message('erreur', $ajout);
        rediriger(url('medias'));
    }
    message('ok', 'Média ajouté par son adresse. Rien n\'est copié : le site le lira à cette adresse.');
    rediriger(url('media', ['id' => $ajout]));
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
    <?php if (!$medias): ?><div class="carte"><?= etat_vide('medias', 'Aucun média', $type !== '' ? 'Aucun média de ce type.' : 'Déposez un fichier, ou ajoutez un média par son adresse.') ?></div><?php endif; ?>
    <div class="grille-medias">
    <?php foreach ($medias as $m): $emplois = count(media_emplois((int) $m['id'])); ?>
      <a class="vignette" href="<?= e(url('media', ['id' => $m['id']])) ?>">
        <?php $externe = ($m['url_externe'] ?? '') !== ''; $yt = media_youtube($m); ?>
        <span class="vignette__image"><?php if ($m['type'] === 'image'): ?><img src="<?= e(media_adresse((int) $m['id'])) ?>" alt="<?= e($m['alt_en']) ?>" loading="lazy"><?php elseif ($yt !== ''): ?><img src="https://i.ytimg.com/vi/<?= e($yt) ?>/hqdefault.jpg" alt="" loading="lazy"><?php else: ?><?= e(TYPES_MEDIA[$m['type']]) ?><?php endif; ?></span>
        <span class="vignette__texte"><?= e(couper(media_nom($m), 44)) ?><br><span class="discret"><?= $externe ? ($yt !== '' ? 'YouTube' : 'adresse externe') : e(taille_lisible((int) $m['taille'])) ?> · <?= $emplois ? e(pluriel($emplois, 'emploi', 'emplois')) : 'pas employé' ?><?= (!$externe && empty($m['chemin_site'])) ? ' · pas encore sur le site' : '' ?><?= (int) ($m['droits_valides'] ?? 1) === 1 ? '' : ' · droits à confirmer' ?></span></span>
      </a>
    <?php endforeach; ?>
    </div>
  </div>
  <div>
  <form method="post" enctype="multipart/form-data" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="deposer">
    <h2>Déposer un fichier</h2>
    <div class="champ"><label for="fichier">Fichier</label><input type="file" id="fichier" name="fichier" required aria-describedby="fichier-aide">
      <p class="aide" id="fichier-aide">Images (JPEG, PNG, WebP, SVG) jusqu'à <?= e(taille_lisible(taille_max('image'))) ?> ; sons (M4A, MP3), vidéos (MP4) et documents (PDF, ZIP, DOCX) jusqu'à <?= e(taille_lisible(taille_max('video'))) ?>.</p></div>
    <?= champ_tr('alt', 'Texte alternatif', '', ['aide' => 'Pour une image : ce qu\'elle montre.']) ?>
    <?= champ('credit', 'Crédit', '', ['aide' => 'Photographe, auteur, source.']) ?>
    <?= champ_texte('droits', 'Droits d\'usage', '', ['aide' => 'En texte libre : usages permis, durée, territoires, mention obligatoire.']) ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein">Déposer</button></div>
  </form>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="adresse">
    <h2>Ajouter par une adresse</h2>
    <?= champ('adresse', 'Adresse du média', '', ['type' => 'url', 'requis' => true, 'indication' => 'https://…', 'aide' => 'Image, son, vidéo hébergée ou lien YouTube. Rien n\'est téléchargé : le site lit le média là où il est.']) ?>
    <?= champ_choix('type', 'Type', ['image' => 'Image', 'son' => 'Son', 'video' => 'Vidéo'], '', ['vide' => 'Deviner d\'après l\'adresse']) ?>
    <?= champ('titre', 'Nom dans la médiathèque', '', ['max' => 255]) ?>
    <?= champ('credit', 'Crédit', '') ?>
    <?= champ_texte('droits', 'Droits d\'usage', '', ['lignes' => 2]) ?>
    <div class="actions"><button type="submit" class="bouton">Ajouter</button></div>
  </form>
  </div>
</div>

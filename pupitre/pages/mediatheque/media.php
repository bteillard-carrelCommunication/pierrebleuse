<?php
/** Un média : aperçu, texte alternatif, crédit, droits d'usage, et la liste des endroits où il est employé. */

defined('PUPITRE') or exit;

$id = recu_entier('id') ?? 0;
$m = bd_ligne('SELECT * FROM medias WHERE id = ?', [$id]);
if (!$m) {
    http_response_code(404);
    $titre = 'Média introuvable';
    echo '<div class="vide"><h1>Média introuvable</h1><p><a class="bouton" href="' . e(url('medias')) . '">Retour à la médiathèque</a></p></div>';
    return;
}
if (est_post()) {
    if (recu('action') === 'supprimer') {
        $e = media_supprimer($id);
        if ($e) { message('erreur', $e); rediriger(url('media', ['id' => $id])); }
        message('ok', 'Média supprimé de la médiathèque.' . (!empty($m['chemin_site']) ? ' Son fichier reste dans le site.' : ''));
        rediriger(url('medias'));
    }
    objet_enregistrer('medias', $id, recu_bi($_POST, 'alt', 500) + [
        'titre' => mb_substr(recu('titre'), 0, 255),
        'credit' => mb_substr(recu('credit'), 0, 255), 'droits' => recu('droits'),
        'droits_valides' => empty($_POST['droits_valides']) ? 0 : 1,
    ], 'media', media_nom($m));
    $GLOBALS['medias_perimes'] = true;
    message('ok', 'Média enregistré.');
    rediriger(url('media', ['id' => $id]));
}
$titre = media_nom($m);
$emplois = media_emplois($id);
$externe = ($m['url_externe'] ?? '') !== '';
?>
<p class="fil"><a href="<?= e(url('medias')) ?>">Médiathèque</a></p>
<div class="titre"><h1><?= e($titre) ?></h1><span class="titre__suite"><?= e(TYPES_MEDIA[$m['type']]) ?></span></div>

<div class="deux">
  <div>
    <div class="carte">
      <?= media_apercu($m) ?>
    </div>
    <form method="post" class="carte">
      <?= champ_csrf() ?>
      <?= champ('titre', 'Nom dans la médiathèque', $m['titre'] ?? '', ['max' => 255, 'aide' => 'Vide : le nom du fichier.']) ?>
      <?= champ_bi('alt', 'Texte alternatif', $m['alt_fr'], $m['alt_en'], ['aide' => 'Pour une image : ce qu\'elle montre, pour qui ne la voit pas. Chaque emploi peut en donner un autre.', 'objet' => ['medias', $id, 'alt']]) ?>
      <?= champ('credit', 'Crédit', $m['credit'], ['aide' => 'Photographe, auteur, source.']) ?>
      <?= champ_texte('droits', 'Droits d\'usage', $m['droits'], ['aide' => 'En texte libre : usages permis, durée, territoires, mention obligatoire.']) ?>
      <?= champ_case('droits_valides', 'Droits d\'utilisation validés : ce média peut paraître sur le site', (int) ($m['droits_valides'] ?? 1) === 1) ?>
      <p class="aide">Décochée, la case retient le média : il ne sort nulle part sur le site tant que ses droits ne sont pas confirmés.</p>
      <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer</button></div>
    </form>
  </div>
  <div>
    <div class="carte">
      <h2>Le fichier</h2>
      <ul class="items">
        <?php if ($externe): ?>
        <li><span class="discret">Adresse externe</span><span><a href="<?= e($m['url_externe']) ?>" target="_blank" rel="noopener"><?= e(couper($m['url_externe'], 90)) ?></a></span></li>
        <li><span class="discret">Dans le site</span><span>lu à cette adresse : aucun fichier n'est copié</span></li>
        <?php else: ?>
        <li><span class="discret">Type</span><span><?= e($m['mime']) ?></span></li>
        <li><span class="discret">Poids</span><span><?= e(taille_lisible((int) $m['taille'])) ?><?= $m['largeur'] ? ' · ' . (int) $m['largeur'] . ' × ' . (int) $m['hauteur'] . ' px' : '' ?></span></li>
        <li><span class="discret">Dans le site</span><span><?= !empty($m['chemin_site']) ? '<code>' . e($m['chemin_site']) . '</code>' : 'pas encore : il y sera copié à la publication, s\'il est employé' ?></span></li>
        <?php endif; ?>
        <li><span class="discret">Déposé</span><span><?= e(horodatage_fr($m['cree_le'])) ?></span></li>
      </ul>
    </div>
    <div class="carte">
      <h2>Employé ici</h2>
      <?php if (!$emplois): ?><p class="rien">Nulle part pour l'instant.</p><?php else: ?>
      <ul class="items"><?php foreach ($emplois as [$libelle, $adresse]): ?><li><?php if ($adresse !== ''): ?><a href="<?= e($adresse) ?>"><?= e($libelle) ?></a><?php else: ?><?= e($libelle) ?><?php endif; ?></li><?php endforeach; ?></ul>
      <p class="aide">Un même média sert à plusieurs endroits sans être copié. Il ne se supprime pas tant qu'il est employé.</p>
      <?php endif; ?>
    </div>
    <form method="post" class="actions">
      <?= champ_csrf() ?><input type="hidden" name="action" value="supprimer">
      <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Supprimer ce média de la médiathèque ? Cette suppression est définitive."<?= $emplois ? ' disabled title="Ce média est employé"' : '' ?>>Supprimer ce média</button>
    </form>
  </div>
</div>

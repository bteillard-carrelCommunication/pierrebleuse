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
    objet_enregistrer('medias', $id, [
        'alt_fr' => mb_substr(recu('alt_fr'), 0, 500), 'alt_en' => mb_substr(recu('alt_en'), 0, 500),
        'credit' => mb_substr(recu('credit'), 0, 255), 'droits' => recu('droits'),
    ], 'media', media_nom($m));
    message('ok', 'Média enregistré.');
    rediriger(url('media', ['id' => $id]));
}
$titre = media_nom($m);
$emplois = media_emplois($id);
$present = media_fichier($m) !== '';
?>
<p class="fil"><a href="<?= e(url('medias')) ?>">Médiathèque</a></p>
<div class="titre"><h1><?= e($titre) ?></h1><span class="titre__suite"><?= e(TYPES_MEDIA[$m['type']]) ?></span></div>

<div class="deux">
  <div>
    <div class="carte">
      <?php if (!$present): ?><p class="message message--erreur">Le fichier de ce média est introuvable.</p>
      <?php elseif ($m['type'] === 'image'): ?><p><img src="<?= e(media_adresse($id)) ?>" alt="<?= e($m['alt_fr']) ?>" style="max-height:26rem;background:#1b1b1b;border-radius:4px"></p>
      <?php elseif ($m['type'] === 'son'): ?><audio controls preload="metadata" src="<?= e(media_adresse($id)) ?>"></audio>
      <?php elseif ($m['type'] === 'video'): ?><video class="lecteur" controls preload="metadata" src="<?= e(media_adresse($id)) ?>"></video>
      <?php else: ?><p><a class="bouton" href="<?= e(media_adresse($id)) ?>">Télécharger le document</a></p>
      <?php endif; ?>
    </div>
    <form method="post" class="carte">
      <?= champ_csrf() ?>
      <?= champ_bi('alt', 'Texte alternatif', $m['alt_fr'], $m['alt_en'], ['aide' => 'Pour une image : ce qu\'elle montre, pour qui ne la voit pas.']) ?>
      <?= champ('credit', 'Crédit', $m['credit']) ?>
      <?= champ_texte('droits', 'Droits d\'usage', $m['droits'], ['aide' => 'En texte libre : usages permis, durée, territoires, mention obligatoire.']) ?>
      <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer</button></div>
    </form>
  </div>
  <div>
    <div class="carte">
      <h2>Le fichier</h2>
      <ul class="items">
        <li><span class="discret">Type</span><span><?= e($m['mime']) ?></span></li>
        <li><span class="discret">Poids</span><span><?= e(taille_lisible((int) $m['taille'])) ?><?= $m['largeur'] ? ' · ' . (int) $m['largeur'] . ' × ' . (int) $m['hauteur'] . ' px' : '' ?></span></li>
        <li><span class="discret">Dans le site</span><span><?= !empty($m['chemin_site']) ? '<code>' . e($m['chemin_site']) . '</code>' : 'pas encore : il y sera copié à la publication, s\'il est employé' ?></span></li>
        <li><span class="discret">Déposé</span><span><?= e(horodatage_fr($m['cree_le'])) ?></span></li>
      </ul>
    </div>
    <div class="carte">
      <h2>Employé ici</h2>
      <?php if (!$emplois): ?><p class="rien">Nulle part pour l'instant.</p><?php else: ?>
      <ul class="items"><?php foreach ($emplois as [$libelle, $adresse]): ?><li><a href="<?= e($adresse) ?>"><?= e($libelle) ?></a></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <form method="post" class="actions">
      <?= champ_csrf() ?><input type="hidden" name="action" value="supprimer">
      <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Supprimer ce média de la médiathèque ?"<?= $emplois ? ' disabled title="Ce média est employé"' : '' ?>>Supprimer ce média</button>
    </form>
  </div>
</div>

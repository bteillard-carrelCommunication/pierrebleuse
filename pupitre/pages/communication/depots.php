<?php
/**
 * Images des concerts : les zones de dépôt et ce qu'elles ont reçu.
 *
 *   sans paramètre   toutes les zones, celle qui vient de recevoir d'abord, et les concerts à venir
 *                    qui n'ont pas encore de contact « communication » ;
 *   concert=…        les fichiers déposés pour un concert : les voir, les télécharger, les retenir
 *                    (ils rejoignent la médiathèque avec leur crédit), les écarter, les supprimer.
 *
 * La zone d'un concert s'ouvre et se règle sur sa fiche (carte « Images du concert »).
 */

defined('PUPITRE') or exit;

$concert_id = recu_entier('concert');
$concert = $concert_id ? bd_ligne('SELECT id, titre_en, contact_com_nom, contact_com_email FROM concerts WHERE id = ?', [$concert_id]) : null;
$zone = $concert ? depot_de_concert((int) $concert['id']) : null;
$titre = $concert ? 'Images · ' . $concert['titre_en'] : 'Images des concerts';

if (est_post() && $zone) {
    $f = bd_ligne('SELECT * FROM depots_fichiers WHERE id = ? AND depot_id = ?', [recu_entier('fichier') ?? 0, $zone['id']]);
    $action = recu('action');
    if ($f && $action === 'retenir') {
        $r = depot_fichier_retenir((int) $f['id']);
        if (is_int($r)) message('ok', '« ' . $f['nom'] . ' » est dans la médiathèque, avec son crédit. Complétez son texte alternatif avant de l\'employer sur le site.');
        else message('alerte', $r);
    } elseif ($f && in_array($action, ['ecarter', 'recevoir'], true) && !$f['media_id']) {
        bd_modifier('depots_fichiers', ['statut' => $action === 'ecarter' ? 'ecarte' : 'recu'], ['id' => $f['id']]);
        $GLOBALS['donnees_modifiees'] = true;
        message('ok', $action === 'ecarter' ? 'Fichier écarté : il reste dans la zone, sans plus demander d\'attention.' : 'Fichier remis parmi les reçus.');
    } elseif ($f && $action === 'supprimer') {
        depot_fichier_supprimer((int) $f['id']);
        journaliser('suppression', 'depot', (int) $concert['id'], $concert['titre_en'] . ' : fichier déposé « ' . $f['nom'] . ' »', ['fichier' => $f['nom']], null);
        message('ok', 'Fichier supprimé.');
    } elseif ($action === 'tout_ecarter') {
        bd_requete("UPDATE depots_fichiers SET statut = 'ecarte' WHERE depot_id = ? AND statut = 'recu'", [$zone['id']]);
        $GLOBALS['donnees_modifiees'] = true;
        message('ok', 'Les fichiers restés « reçus » sont écartés.');
    }
    rediriger(url('depot', ['concert' => $concert['id']]));
}

// ---------- les fichiers d'un concert ----------
if ($concert):
    $fichiers = $zone ? depot_fichiers((int) $zone['id']) : [];
    $base = chemin_pupitre() . '/depot-fichier.php?id=';
?>
<p class="fil"><a href="<?= e(url('depots')) ?>">Images des concerts</a></p>
<div class="titre">
  <h1><?= e($concert['titre_en']) ?></h1>
  <span class="titre__suite"><?= e(pluriel(count($fichiers), 'fichier déposé', 'fichiers déposés')) ?><?= $zone ? ' · zone ' . (depot_ouvert($zone) ? 'ouverte jusqu\'au ' . e(date_fr(substr((string) $zone['expire_le'], 0, 10))) : 'fermée') : '' ?></span>
  <div class="titre__actions"><a class="bouton" href="<?= e(url('concert', ['id' => $concert['id']])) ?>#depot"><?= icone('concerts') ?>Fiche du concert</a></div>
</div>
<?php if (!$fichiers): ?>
  <div class="carte"><?= etat_vide('depot', 'Rien n\'a encore été déposé', $zone ? 'Les photos et les vidéos déposées par le contact communication apparaîtront ici.' : 'Ce concert n\'a pas encore de zone de dépôt : ouvrez-la depuis sa fiche.') ?></div>
<?php else: ?>
  <p class="intro">Ces fichiers ne sont visibles que d'ici. « Retenir » en met une copie dans la médiathèque, avec le crédit indiqué par le déposant ; « Écarter » le laisse dans la zone, sans plus le signaler.</p>
  <div class="grille-medias grille-medias--depot">
    <?php foreach ($fichiers as $f): $image = str_starts_with($f['type'], 'image/') && in_array($f['type'], ['image/jpeg', 'image/png', 'image/webp'], true); $video = str_starts_with($f['type'], 'video/'); ?>
      <article class="vignette vignette--depot" id="f-<?= (int) $f['id'] ?>">
        <div class="vignette__image">
          <?php if ($image): ?><a href="<?= e($base . (int) $f['id']) ?>" target="_blank" rel="noopener"><img src="<?= e($base . (int) $f['id']) ?>" alt="<?= e($f['nom']) ?>" loading="lazy"></a>
          <?php elseif ($video && $f['type'] !== 'video/quicktime'): ?><video src="<?= e($base . (int) $f['id']) ?>" controls preload="metadata" aria-label="<?= e($f['nom']) ?>"></video>
          <?php else: ?><span><?= e(strtoupper(DEPOT_TYPES[$f['type']][1] ?? 'fichier')) ?> · pas d'aperçu dans le navigateur</span><?php endif; ?>
        </div>
        <div class="vignette__texte">
          <p><strong><?= e(couper($f['nom'], 60)) ?></strong> <span class="puce <?= STATUTS_DEPOT[$f['statut']][1] ?>"><?= e(mb_strtolower(STATUTS_DEPOT[$f['statut']][0])) ?></span></p>
          <p class="discret"><?= e(taille_lisible((int) $f['taille'])) ?> · <?= e(horodatage_fr($f['depose_le'])) ?><?= $f['deposant'] !== '' ? ' · ' . e($f['deposant']) : '' ?></p>
          <?php if ($f['credit'] !== ''): ?><p>Crédit : <?= e($f['credit']) ?></p><?php else: ?><p class="discret">Crédit non indiqué : à demander avant tout emploi.</p><?php endif; ?>
          <?php if ($f['legende'] !== ''): ?><p class="discret">« <?= e($f['legende']) ?> »</p><?php endif; ?>
          <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="fichier" value="<?= (int) $f['id'] ?>">
            <?php if ($f['media_id']): ?><a class="bouton bouton--petit" href="<?= e(url('media', ['id' => $f['media_id']])) ?>"><?= icone('medias') ?>Dans la médiathèque</a>
            <?php else: ?><button type="submit" name="action" value="retenir" class="bouton bouton--petit bouton--doux">Retenir</button>
              <?php if ($f['statut'] === 'recu'): ?><button type="submit" name="action" value="ecarter" class="bouton bouton--petit">Écarter</button>
              <?php else: ?><button type="submit" name="action" value="recevoir" class="bouton bouton--petit">Remettre en « reçu »</button><?php endif; ?>
            <?php endif; ?>
            <a class="bouton bouton--petit" href="<?= e($base . (int) $f['id'] . '&dl=1') ?>"><?= icone('recevoir') ?>Télécharger</a>
            <button type="submit" name="action" value="supprimer" class="bouton bouton--petit bouton--danger" data-confirmer="Supprimer définitivement « <?= e($f['nom']) ?> » ?<?= $f['media_id'] ? ' Sa copie dans la médiathèque reste.' : '' ?>">Supprimer</button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (array_filter($fichiers, fn($f) => $f['statut'] === 'recu')): ?>
  <form method="post" class="actions"><?= champ_csrf() ?><button type="submit" name="action" value="tout_ecarter" class="bouton bouton--petit">Écarter tout ce qui reste « reçu »</button>
    <span class="aide">Quand vous avez retenu ce qui vous intéresse : le concert ne signale plus de nouveaux fichiers.</span></form>
  <?php endif; ?>
<?php endif; ?>
<?php return; endif;

// ---------- toutes les zones ----------
$zones = depots_lister();
$sans_contact = bd_lignes("SELECT c.id, c.titre_en, MIN(r.date) AS premiere FROM concerts c JOIN representations r ON r.concert_id = c.id
    WHERE r.statut <> 'annulee' AND c.type <> 'disque' AND c.contact_com_email = '' GROUP BY c.id, c.titre_en HAVING MIN(r.date) BETWEEN ? AND ? ORDER BY premiere", [aujourdhui(), ajouter_jours(aujourdhui(), 60)]);
[$max, $max_envoi] = depot_limites();
?>
<div class="titre"><h1>Images des concerts</h1><span class="titre__suite"><?= e(pluriel(count($zones), 'zone de dépôt', 'zones de dépôt')) ?></span></div>
<p class="intro">Chaque concert a sa zone de dépôt : une adresse où son contact « communication » dépose photos et courtes vidéos, sans compte. La veille de la première date, Pupitre la lui envoie de lui-même. Le contact se saisit sur la fiche du concert.</p>

<div class="deux">
<div>
  <div class="carte">
    <div class="carte__tete"><h2>Zones de dépôt</h2><span class="carte__compte"><?= count($zones) ?></span></div>
    <?php if (!$zones): ?>
      <?= etat_vide('depot', 'Aucune zone encore', 'Une zone s\'ouvre quand le contact communication d\'un concert est invité, ou à la main depuis la fiche du concert.') ?>
    <?php else: ?>
    <ul class="items">
      <?php foreach ($zones as $z): ?>
        <li>
          <span class="en-ligne"><a class="item__titre" href="<?= e((int) $z['fichiers'] ? url('depot', ['concert' => $z['concert_id']]) : url('concert', ['id' => $z['concert_id']]) . '#depot') ?>"><?= e($z['titre_en']) ?></a>
            <?php if ((int) $z['nouveaux']): ?><span class="puce puce--accent"><?= e(pluriel((int) $z['nouveaux'], 'nouveau fichier', 'nouveaux fichiers')) ?></span><?php endif; ?>
            <?= depot_ouvert($z) ? '' : '<span class="puce">fermée</span>' ?></span>
          <span class="discret"><?= $z['premiere'] ? e(date_fr($z['premiere'])) . ' · ' : '' ?><?= e(pluriel((int) $z['fichiers'], 'fichier', 'fichiers')) ?><?= $z['dernier'] ? ', le dernier ' . e(il_y_a($z['dernier'])) : '' ?>
            · <?= $z['invite_le'] ? 'invitation envoyée le ' . e(date_fr(substr($z['invite_le'], 0, 10))) . ' à ' . e($z['invite_a']) : ($z['contact_com_email'] !== '' ? 'invitation pas encore envoyée' : 'pas de contact communication') ?><?= $z['ouvert_le'] ? ' · zone visitée' : '' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
<div>
  <div class="carte">
    <div class="carte__tete"><h2>Concerts sans contact</h2><span class="carte__compte"><?= count($sans_contact) ?></span></div>
    <?php if (!$sans_contact): ?><p class="rien">Tous les concerts des soixante prochains jours ont leur contact communication.</p><?php else: ?>
    <p class="aide aide--haut">Dans les soixante jours : sans contact, aucune invitation ne partira.</p>
    <ul class="items">
      <?php foreach ($sans_contact as $x): ?><li><a class="item__titre" href="<?= e(url('concert', ['id' => $x['id']])) ?>#contact-com"><?= e($x['titre_en']) ?></a><span class="discret"><?= e(date_fr($x['premiere'])) ?></span></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <div class="carte">
    <h2>Ce que ce serveur accepte</h2>
    <p><?= e(taille_lisible($max)) ?> par fichier<?= $max_envoi !== PHP_INT_MAX ? ', ' . e(taille_lisible($max_envoi)) . ' par envoi' : '' ?>.</p>
    <?php if ($max < 52428800): ?><p class="message message--alerte">C'est peu pour une vidéo. Ces limites viennent de la configuration de PHP (<code>upload_max_filesize</code>, <code>post_max_size</code>) : Pupitre demande 300 Mo par son fichier <code>.user.ini</code>, que tous les hébergements ne lisent pas.</p><?php endif; ?>
    <p class="aide">Photos (JPEG, PNG, HEIC, WebP, TIFF) et vidéos (MP4, MOV, WebM). Les fichiers sont rangés sur le serveur, hors du web : <code>stockage/depots/</code>.</p>
  </div>
</div>
</div>

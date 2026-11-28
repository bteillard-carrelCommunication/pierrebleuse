<?php
/**
 * Zone de dépôt d'un concert : la page où son contact « communication » dépose ses photos et ses
 * courtes vidéos. Hors connexion : l'adresse elle-même (son jeton) ouvre la zone, et rien d'autre.
 *
 *     depot.php?z=<jeton>            la zone, dans la langue du contact (fr ou en ; &lang= pour changer)
 *
 * On ne peut qu'y déposer : la page liste le nom de ce qui a déjà été reçu, sans rien en montrer.
 * Chaque fichier est contrôlé sur son contenu (inclus/depot.php) et rangé hors du web.
 */

require __DIR__ . '/inclus/amorce.php';

$jeton = recu('z');
$d = depot_par_jeton($jeton);
$langue = in_array(recu('lang'), ['fr', 'en'], true) ? recu('lang') : ($d && isset(DEPOT_TEXTES[$d['concert']['contact_com_langue']]) ? $d['concert']['contact_com_langue'] : 'en');
$t = DEPOT_TEXTES[$langue];
$artiste = reglage_sur('artiste_nom') ?: 'Pierre Bleuse';
$moi = chemin_pupitre() . '/depot.php?z=' . $jeton . '&lang=' . $langue;
$erreurs = [];
$recus = 0;

if ($d && $d['ouvert_le'] === null) bd_modifier('depots', ['ouvert_le' => date('Y-m-d H:i:s')], ['id' => $d['id']]);

if ($d && est_post()) {
    // un envoi plus gros que ce que PHP accepte arrive vide : le dire, plutôt que de refuser le formulaire
    if (!$_POST && !$_FILES) {
        $erreurs[] = $langue === 'en' ? 'This upload was too large for the server: please send fewer files at a time.' : 'Cet envoi était trop lourd pour le serveur : envoyez moins de fichiers à la fois.';
    } else {
        verifier_csrf();
        if (!depot_ouvert($d)) {
            $erreurs[] = $t['ferme'];
        } else {
            $f = $_FILES['fichiers'] ?? null;
            $n = is_array($f['name'] ?? null) ? count($f['name']) : 0;
            for ($i = 0; $i < $n; $i++) {
                if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $refus = depot_recevoir($d, ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]], recu('deposant'), recu('credit'), recu('legende'), $langue);
                if ($refus === '') $recus++; else $erreurs[] = $refus;
            }
            if (!$recus && !$erreurs) $erreurs[] = $langue === 'en' ? 'No file was selected.' : 'Aucun fichier n\'a été choisi.';
            if ($recus) {
                $c = $d['concert'];
                journaliser('creation', 'depot', (int) $c['id'], $c['titre_en'] . ' : ' . pluriel($recus, 'fichier déposé', 'fichiers déposés'), null, ['par' => recu('deposant') ?: $c['contact_com_nom'], 'credit' => recu('credit')]);
            }
            if ($recus && !$erreurs) {
                $_SESSION['depot_merci'] = $recus;
                rediriger($moi);
            }
        }
    }
}
$merci = (int) ($_SESSION['depot_merci'] ?? 0);
unset($_SESSION['depot_merci']);
if ($recus && $erreurs) $merci = $recus;

if (!$d) http_response_code(404);
echo page_publique_tete($t['titre'], true, $langue);

if (!$d): ?>
  <h1 class="porte__titre"><?= e($t['inconnu']) ?></h1>
  <p><?= e($t['inconnu_detail']) ?></p>
<?php else:
    $c = $d['concert'];
    [$premiere, $derniere] = depot_dates((int) $c['id']);
    $date = fn(?string $iso) => $iso ? ($langue === 'en' ? date('j F Y', strtotime($iso)) : date_fr($iso)) : '';
    $quand = $premiere && $derniere && $premiere !== $derniere ? $date($premiere) . ' – ' . $date($derniere) : $date($premiere);
    [$max, $max_envoi, $max_nombre] = depot_limites();
    $fichiers = depot_fichiers((int) $d['id']);
?>
  <p class="actions" style="justify-content:flex-end;margin:0 0 .5rem"><a href="<?= e(chemin_pupitre() . '/depot.php?z=' . $jeton . '&lang=' . ($langue === 'en' ? 'fr' : 'en')) ?>" lang="<?= $langue === 'en' ? 'fr' : 'en' ?>" hreflang="<?= $langue === 'en' ? 'fr' : 'en' ?>"><?= $langue === 'en' ? 'Français' : 'English' ?></a></p>
  <h1 class="porte__titre"><?= e($t['titre']) ?></h1>
  <p class="intro"><strong><?= e($c['titre_en']) ?></strong><?= $quand !== '' ? ' · ' . e($quand) : '' ?><?= ($ou = implode(', ', array_filter([$c['salle_en'], $c['ville_en']], 'strlen'))) !== '' ? ' · ' . e($ou) : '' ?></p>
  <?php if ($merci): ?><p class="message message--ok" role="status"><?= e(sprintf($t['merci'], $merci === 1 ? $t['recu1'] : sprintf($t['recus'], $merci))) ?></p><?php endif; ?>
  <?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

  <?php if (!depot_ouvert($d)): ?>
    <p class="message message--alerte"><?= e($t['ferme']) ?> <?= e($t['ferme_detail']) ?></p>
  <?php else: ?>
    <p><?= e(sprintf($t['intro'], $artiste)) ?></p>
    <form method="post" action="<?= e($moi) ?>" enctype="multipart/form-data" data-depot data-depot-max="<?= (int) $max ?>" data-depot-total="<?= $max_envoi === PHP_INT_MAX ? 0 : (int) ($max_envoi * 0.95) ?>"
          data-depot-trop="<?= e($t['trop']) ?>" data-depot-trop-total="<?= e($t['trop_total']) ?>" data-depot-envoi="<?= e($t['envoi']) ?>">
      <?= champ_csrf() ?>
      <div class="colonnes">
        <div class="champ"><label for="deposant"><?= e($t['nom']) ?></label><input type="text" id="deposant" name="deposant" maxlength="190" autocomplete="name" value="<?= e(est_post() ? recu('deposant') : $c['contact_com_nom']) ?>"></div>
        <div class="champ"><label for="credit"><?= e($t['credit']) ?></label><input type="text" id="credit" name="credit" maxlength="255" value="<?= e(recu('credit')) ?>" placeholder="© " aria-describedby="credit-aide">
          <p class="aide" id="credit-aide"><?= e($t['credit_aide']) ?></p></div>
      </div>
      <div class="champ"><label for="legende"><?= e($t['legende']) ?></label><input type="text" id="legende" name="legende" maxlength="500" value="<?= e(recu('legende')) ?>"></div>
      <div class="champ"><label for="fichiers"><?= e($t['fichiers']) ?></label>
        <input type="file" id="fichiers" name="fichiers[]" multiple required accept="image/jpeg,image/png,image/webp,image/heic,image/heif,image/tiff,video/mp4,video/quicktime,video/webm,.heic,.heif,.mov" aria-describedby="fichiers-aide">
        <p class="aide" id="fichiers-aide"><?= e(sprintf($t['accepte'], taille_lisible($max), $max_envoi === PHP_INT_MAX ? taille_lisible($max) : taille_lisible($max_envoi))) ?><?= $max_nombre < 100 ? ' ' . e($langue === 'en' ? 'Up to ' . $max_nombre . ' files at a time.' : 'Jusqu\'à ' . $max_nombre . ' fichiers à la fois.') : '' ?></p>
        <ul class="items items--serre" data-depot-liste aria-live="polite"></ul></div>
      <p class="aide"><?= e(sprintf($t['droits'], $artiste)) ?></p>
      <div class="actions"><button type="submit" class="bouton bouton--plein"><?= e($t['envoyer']) ?></button></div>
    </form>
  <?php endif; ?>

  <?php if ($fichiers): ?>
    <h2><?= e($t['deja']) ?> <span class="carte__compte"><?= count($fichiers) ?></span></h2>
    <ul class="items items--serre">
      <?php foreach ($fichiers as $f): ?><li><span><?= e($f['nom']) ?> <span class="discret">· <?= e(taille_lisible((int) $f['taille'])) ?> · <?= e($langue === 'en' ? date('j M Y, H:i', strtotime($f['depose_le'])) : horodatage_fr($f['depose_le'])) ?></span></span></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif;

echo page_publique_pied($d && $d['expire_le'] && depot_ouvert($d) ? sprintf($t['jusquau'], $langue === 'en' ? date('j F Y', strtotime($d['expire_le'])) : date_fr(substr($d['expire_le'], 0, 10))) : '');

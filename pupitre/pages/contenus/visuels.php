<?php
/**
 * Blocs visuels : ce que montre chaque bloc à médias de la page d'accueil.
 *
 * Un bloc porte une liste d'éléments (inclus/visuels.php). Chaque élément est un média de la
 * médiathèque (fichier déposé ou adresse externe) et la manière de le montrer : image fixe, image
 * animée par un module du registre (inclus/animations.php), vidéo. Le premier élément publié décide
 * de ce que le site montre : une image, ou la suite des vidéos publiées. Les autres restent en
 * réserve, réutilisables, et se déplacent d'un bloc à l'autre.
 */

defined('PUPITRE') or exit;

$titre = 'Blocs visuels';
$erreurs = [];
$images = medias_choix(['image']);
$videos = medias_choix(['video']);
$blocs = blocs_tous();
$nom_bloc = fn(string $cle) => $blocs[$cle]['nom'] ?? $cle;

if (est_post()) {
    $action = recu('action');
    $bloc = recu('bloc');

    // --- ajouter un élément à un bloc : un média de la médiathèque, un fichier déposé, ou une adresse
    if ($action === 'ajouter' && isset(BLOCS_A_MEDIAS[$bloc])) {
        $media = null;
        if (!empty($_FILES['fichier']['name'])) {
            $media = media_deposer($_FILES['fichier'], ['credit' => recu('credit')], ['image', 'video']);
        } elseif (recu('adresse') !== '') {
            $media = media_creer_externe(recu('adresse'), in_array(recu('type'), ['image', 'video'], true) ? recu('type') : '', ['credit' => recu('credit')]);
        } elseif (recu_entier('media_id')) {
            $media = recu_entier('media_id');
        } else {
            $media = 'Choisissez un média, déposez un fichier ou donnez une adresse.';
        }
        $m = is_int($media) ? (medias_par_id()[$media] ?? null) : null;
        if (is_int($media) && (!$m || !in_array($m['type'], ['image', 'video'], true))) $media = 'Un bloc visuel reçoit une image ou une vidéo.';
        if (is_int($media) && BLOCS_A_MEDIAS[$bloc] === 'fond' && $m['type'] !== 'image') $media = 'Ce bloc ne reçoit qu\'une image : c\'est le fond d\'un bloc de texte.';
        if (is_string($media)) {
            message('erreur', $media);
        } else {
            $id = element_creer($bloc, $media);
            message('ok', 'Élément ajouté à la fin du bloc « ' . $nom_bloc($bloc) . ' ». Réglez-le ci-dessous.');
            rediriger(url('visuels') . '#e-' . $id);
        }
        rediriger(url('visuels') . '#' . $bloc);
    }

    $e = element_lire(recu_entier('id') ?? 0);
    if ($e && in_array($action, ['monter', 'descendre'], true)) {
        element_deplacer((int) $e['id'], $action === 'monter' ? -1 : 1);
        rediriger(url('visuels') . '#e-' . $e['id']);
    }
    if ($e && $action === 'retirer') {
        objet_supprimer('medias_usages', (int) $e['id'], 'element', 'Bloc « ' . $nom_bloc($e['objet_id']) . ' » · ' . ($e['media'] ? media_nom($e['media']) : 'élément'));
        message('ok', 'Élément retiré du bloc. Son média reste dans la médiathèque.');
        rediriger(url('visuels') . '#' . $e['objet_id']);
    }
    if ($e && $action === 'analyser') {
        if (($manque = analyse_possible()) !== '') message('alerte', 'Analyse impossible ici : ' . $manque . '.');
        elseif ($e['media'] && ($e['media']['url_externe'] ?? '') !== '') message('alerte', 'L\'analyse demande un fichier déposé : une vidéo lue à une adresse externe ne s\'analyse pas d\'ici.');
        else {
            tache_creer('analyse_son', ['media_id' => (int) $e['media_id'], 'table' => 'medias_usages', 'cle' => (int) $e['id'], 'nom' => slug(pathinfo(media_nom($e['media']), PATHINFO_FILENAME), 40) ?: 'video-' . $e['id']]);
            message(taches_lancer_en_fond() ? 'ok' : 'alerte', 'Analyse de la bande-son demandée. Elle tourne en tâche de fond : rechargez la page dans un instant.');
        }
        rediriger(url('visuels') . '#e-' . $e['id']);
    }
    if ($e && $action === 'enregistrer') {
        $r = is_array($_POST['e'][$e['id']] ?? null) ? $_POST['e'][$e['id']] : [];
        $t = fn(string $k) => trim((string) ($r[$k] ?? ''));
        $pourcent = fn(string $k) => ($t($k) !== '' && ctype_digit($t($k)) && (int) $t($k) <= 100) ? (int) $t($k) : null;
        $bloc = $e['objet_id'];
        $media_id = ctype_digit($t('media_id')) ? (int) $t('media_id') : (int) $e['media_id'];
        $m = medias_par_id()[$media_id] ?? null;
        if (!$m || !in_array($m['type'], ['image', 'video'], true)) $erreurs[] = 'Choisissez une image ou une vidéo de la médiathèque.';
        $nature = $m && $m['type'] === 'video' ? 'video' : ($t('nature') === 'animation' ? 'animation' : 'image');
        if ($m && BLOCS_A_MEDIAS[$bloc] === 'fond' && $nature !== 'image') $erreurs[] = 'Ce bloc ne reçoit qu\'une image fixe : c\'est le fond d\'un bloc de texte.';
        $p = $e['p'];
        foreach (['position' => ['position_x', 'position_y'], 'mobile' => ['mobile_x', 'mobile_y']] as $cle => [$cx, $cy]) {
            $x = $pourcent($cx); $y = $pourcent($cy);
            if (($x === null) !== ($y === null)) $erreurs[] = 'Le point de cadrage demande ses deux valeurs, de 0 à 100.';
            if ($x !== null && $y !== null) $p[$cle] = [$x, $y]; else unset($p[$cle]);
        }
        unset($p['animation']);
        if ($nature === 'animation') {
            $module = $t('module') !== '' ? $t('module') : (string) array_key_first(ANIMATIONS);
            $animation = animation_lire($module, is_array($r['reglages'][$module] ?? null) ? $r['reglages'][$module] : []);
            if (!$animation) $erreurs[] = 'Ce module d\'animation n\'est pas au registre.';
            else $p['animation'] = $animation;
        }
        if ($nature === 'video') {
            $p['son'] = !empty($r['son']);
            if ($media_id !== (int) $e['media_id'] || !$p['son']) unset($p['piste']);     // autre vidéo, ou plus de son : l'analyse ne vaut plus
        } else {
            unset($p['son'], $p['piste']);
        }
        $d = [
            'media_id' => $media_id,
            'nature' => $nature,
            'affiche_id' => ($nature === 'video' && ctype_digit($t('affiche_id')) && isset($images[(int) $t('affiche_id')])) ? (int) $t('affiche_id') : null,
            'credit' => mb_substr($t('credit'), 0, 255),
            'parametres' => json_encode($p ?: new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'publie' => empty($r['publie']) ? 0 : 1,
        ] + recu_tr($r, 'alt', 500) + recu_tr($r, 'titre', 255) + recu_tr($r, 'description', 2000);
        // seule une vidéo porte un titre et une description : ceux d'une image s'effacent, avec leurs traductions
        if ($nature !== 'video') foreach (langues() as $l) { $d['titre_' . $l] = ''; $d['description_' . $l] = ''; }
        $vers = $t('vers');
        if ($vers !== '' && $vers !== $bloc && isset(BLOCS_A_MEDIAS[$vers])) {
            if (BLOCS_A_MEDIAS[$vers] === 'fond' && $nature !== 'image') $erreurs[] = 'Le bloc « ' . $nom_bloc($vers) . ' » ne reçoit qu\'une image fixe.';
            else {
                $d['objet_id'] = $vers;
                $d['ordre'] = (int) bd_valeur("SELECT COALESCE(MAX(ordre), -1) + 1 FROM medias_usages WHERE objet = 'blocs' AND objet_id = ? AND role = 'element'", [$vers]);
            }
        }
        if (!$erreurs) {
            objet_enregistrer('medias_usages', (int) $e['id'], $d, 'element', 'Bloc « ' . $nom_bloc($d['objet_id'] ?? $bloc) . ' » · ' . media_nom($m));
            message('ok', isset($d['objet_id']) ? 'Élément déplacé vers le bloc « ' . $nom_bloc($d['objet_id']) . ' ».' : 'Élément enregistré. Le site suivra à la prochaine publication.');
            rediriger(url('visuels') . '#e-' . $e['id']);
        }
    }
}

$fautif = $erreurs ? (recu_entier('id') ?? 0) : 0;
?>
<div class="titre"><h1>Blocs visuels</h1><span class="titre__suite">data/visuels.js</span></div>
<p class="intro">Chaque bloc porte une liste d'éléments : image fixe, image animée, vidéo. Le premier élément publié décide de ce que le site montre : une image, ou la suite des vidéos publiées, que le visiteur parcourt une à une. Les autres éléments restent en réserve. Sur un bloc visuel, le vol d'étourneaux n'est pas visible.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<?php foreach (BLOCS_A_MEDIAS as $bloc => $role): $elements = elements_lire($bloc); $affiches = array_column(elements_affiches($bloc), 'id'); ?>
<section class="bloc-visuel" id="<?= e($bloc) ?>">
  <div class="titre titre--section">
    <h2><?= e($nom_bloc($bloc)) ?></h2>
    <span class="titre__suite">
      <?php if (bloc_visible($bloc)): ?><span class="puce puce--ok">bloc affiché</span><?php else: ?><span class="puce puce--alerte">bloc masqué</span><?php endif; ?>
      montre <?= e(bloc_visuel_resume($bloc)) ?>
    </span>
    <div class="titre__actions"><a class="bouton bouton--petit" href="<?= e(url('bloc', ['cle' => $bloc])) ?>">Visibilité et textes du bloc</a></div>
  </div>
  <?php if ($role === 'fond'): ?><p class="aide aide--haut">Photographie de fond du bloc : une image fixe, derrière le texte.</p><?php endif; ?>
  <?php if (!$elements): ?><p class="rien">Aucun élément. <?= $role === 'plein' ? 'Un bloc visuel sans élément ne s\'affiche pas sur le site.' : '' ?></p><?php endif; ?>

  <?php foreach ($elements as $i => $el): $m = $el['media']; $p = 'e[' . $el['id'] . ']'; $pp = $el['p'];
      $saisi = ($fautif === (int) $el['id'] && is_array($_POST['e'][$el['id']] ?? null)) ? $_POST['e'][$el['id']] : null;
      $v = fn(string $k, $defaut = '') => $saisi !== null ? (string) ($saisi[$k] ?? '') : (string) $defaut;
      $nature = $saisi !== null && $m && $m['type'] === 'image' ? (($saisi['nature'] ?? '') === 'animation' ? 'animation' : 'image') : $el['nature'];
      $montre = in_array($el['id'], $affiches, false);
      $langues_o = fn(string $champ) => ['objet' => ['medias_usages', $el['id'], $champ]];
  ?>
  <form method="post" class="carte element<?= $el['publie'] ? '' : ' element--reserve' ?>" id="e-<?= (int) $el['id'] ?>">
    <?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $el['id'] ?>">
    <div class="element__tete">
      <h3>Élément <?= $i + 1 ?> · <?= e(NATURES_ELEMENT[$el['nature']] ?? $el['nature']) ?></h3>
      <?php if ($montre): ?><span class="puce puce--ok">sur le site</span>
      <?php elseif (!$el['publie']): ?><span class="puce">non publié</span>
      <?php elseif (!media_publiable($m)): ?><span class="puce puce--alerte">média indisponible ou droits à confirmer</span>
      <?php else: ?><span class="puce">en réserve : le premier élément publié décide</span><?php endif; ?>
      <span class="ligne__outils">
        <button type="submit" name="action" value="monter" class="outil" aria-label="Monter cet élément" title="Monter"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
        <button type="submit" name="action" value="descendre" class="outil" aria-label="Descendre cet élément" title="Descendre"<?= $i === count($elements) - 1 ? ' disabled' : '' ?>>↓</button>
      </span>
    </div>
    <div class="visuel-grille">
      <div>
        <?php if ($m && $m['type'] === 'image'): ?>
          <div class="focal" data-focal="<?= e($p) ?>" title="Cliquez pour poser le point de cadrage">
            <img src="<?= e(media_adresse((int) $m['id'])) ?>" alt="<?= e($el['alt_en']) ?>">
            <span class="focal__point focal__point--large" hidden></span>
            <span class="focal__point focal__point--mobile" hidden></span>
          </div>
          <fieldset class="champ"><legend class="sr">Point de cadrage à régler</legend>
            <div class="en-ligne">
              <label><input type="radio" name="<?= e($p) ?>[cible]" value="large" checked> Écran large</label>
              <label><input type="radio" name="<?= e($p) ?>[cible]" value="mobile"> Téléphone (facultatif)</label>
            </div>
          </fieldset>
          <div class="cadrages" data-cadrages="<?= e($p) ?>" aria-hidden="true">
            <div class="cadrage cadrage--large"><img src="<?= e(media_adresse((int) $m['id'])) ?>" alt=""></div>
            <div class="cadrage cadrage--etroit"><img src="<?= e(media_adresse((int) $m['id'])) ?>" alt=""></div>
          </div>
          <p class="aide">Cliquez sur l'image pour poser le point de cadrage : ce qui reste à l'écran quand elle est rognée. À gauche, le cadrage sur un écran large ; à droite, sur un téléphone.</p>
        <?php else: ?>
          <?= media_apercu($m) ?>
        <?php endif; ?>
        <?php if ($m): ?><p class="aide"><a href="<?= e(url('media', ['id' => $m['id']])) ?>">Fiche du média</a> : crédit, droits, texte alternatif par défaut.<?= ($m['url_externe'] ?? '') !== '' ? ' Adresse externe : rien n\'est copié sur le site.' : '' ?></p><?php endif; ?>
      </div>
      <div>
        <?= champ_choix('media_id', 'Média', ($m && $m['type'] === 'video') ? $videos : $images, $v('media_id', $el['media_id']), ['prefixe' => $p, 'aide' => 'Pour passer d\'une image à une vidéo, ajoutez un nouvel élément au bloc.']) ?>
        <?php if ($m && $m['type'] === 'image' && $role === 'plein'): ?>
          <?= champ_choix('nature', 'Présentation', ['image' => 'Image fixe', 'animation' => 'Image animée'], $nature, ['prefixe' => $p]) ?>
          <fieldset class="element__animation">
            <legend>Animation <span class="discret">(si l'image est animée)</span></legend>
            <?php $actuel = $saisi !== null ? (string) ($saisi['module'] ?? '') : (string) ($pp['animation']['module'] ?? ''); ?>
            <?= champ_choix('module', 'Module', animations_choix(), $actuel !== '' ? $actuel : array_key_first(ANIMATIONS), ['prefixe' => $p, 'aide' => 'Les animations sont du code du site, inscrites au registre : on choisit un module et ses réglages, on n\'en écrit pas ici.']) ?>
            <?php foreach (ANIMATIONS as $module => $a): foreach ($a['reglages'] as $nom => $rg): ?>
              <?= champ_choix($nom, $a['nom'] . ' : ' . mb_strtolower($rg['libelle']), $rg['choix'], $saisi !== null ? ($saisi['reglages'][$module][$nom] ?? $rg['defaut']) : (($pp['animation']['module'] ?? '') === $module ? ($pp['animation'][$nom] ?? $rg['defaut']) : $rg['defaut']), ['prefixe' => $p . '[reglages][' . $module . ']', 'aide' => $rg['aide']]) ?>
            <?php endforeach; endforeach; ?>
          </fieldset>
        <?php endif; ?>
        <?php if ($m && $m['type'] === 'video'): ?>
          <?php if (media_youtube($m) === ''): ?>
            <?= champ_choix('affiche_id', 'Image d\'attente', $images, $v('affiche_id', $el['affiche_id']), ['prefixe' => $p, 'vide' => '— aucune —', 'aide' => 'Montrée avant le départ de la vidéo, et sans JavaScript.']) ?>
            <?= champ_case('son', 'La vidéo joue avec son son, et prend le relais de la musique', $saisi !== null ? !empty($saisi['son']) : !empty($pp['son']), ['prefixe' => $p]) ?>
            <?php if (!empty($pp['son'])): ?><p class="aide">Analyse de la bande-son (ce que suit la nuée) : <?= !empty($pp['piste']) ? '<code>' . e($pp['piste']) . '</code>' : 'à faire' ?>.</p><?php endif; ?>
          <?php else: ?>
            <p class="aide">Vidéo YouTube : rien n'est chargé sur le site avant l'accord du visiteur ; la musique se tait quand il la lance.</p>
          <?php endif; ?>
          <?= champ_tr('titre', 'Titre (facultatif)', $v('titre_en', $el['titre_en']), ['prefixe' => $p] + $langues_o('titre')) ?>
          <?= champ_tr('description', 'Description (facultative)', $v('description_en', $el['description_en']), ['prefixe' => $p, 'texte' => true] + $langues_o('description')) ?>
        <?php endif; ?>
        <?php if (!$m || media_youtube($m) === ''): ?>
        <div class="colonnes">
          <?= champ('position_x', 'Cadrage : horizontal (%)', $v('position_x', $pp['position'][0] ?? ''), ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
          <?= champ('position_y', 'Cadrage : vertical (%)', $v('position_y', $pp['position'][1] ?? ''), ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
        </div>
        <div class="colonnes">
          <?= champ('mobile_x', 'Téléphone : horizontal (%)', $v('mobile_x', $pp['mobile'][0] ?? ''), ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
          <?= champ('mobile_y', 'Téléphone : vertical (%)', $v('mobile_y', $pp['mobile'][1] ?? ''), ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
        </div>
        <?php endif; ?>
        <?= champ_tr('alt', 'Texte alternatif', $v('alt_en', $el['alt_en']), ['prefixe' => $p, 'aide' => 'Ce que montre le média, pour qui ne le voit pas. Vide : celui de la fiche du média.'] + $langues_o('alt')) ?>
        <?= champ('credit', 'Crédit', $v('credit', $el['credit']), ['prefixe' => $p, 'max' => 255, 'aide' => 'Vide : celui de la fiche du média' . ($m && $m['credit'] !== '' ? ' (« ' . $m['credit'] . ' »)' : '') . '.']) ?>
        <div class="colonnes">
          <?= champ_case('publie', 'Publié', $saisi !== null ? !empty($saisi['publie']) : (bool) $el['publie'], ['prefixe' => $p]) ?>
          <?= champ_choix('vers', 'Déplacer vers un autre bloc', array_map($nom_bloc, array_combine(array_keys(BLOCS_A_MEDIAS), array_keys(BLOCS_A_MEDIAS))), '', ['prefixe' => $p, 'vide' => '— laisser dans ce bloc —']) ?>
        </div>
      </div>
    </div>
    <div class="actions">
      <button type="submit" name="action" value="enregistrer" class="bouton bouton--plein">Enregistrer cet élément</button>
      <?php if ($el['nature'] === 'video' && !empty($pp['son']) && $m && media_youtube($m) === ''): ?><button type="submit" name="action" value="analyser" class="bouton">Analyser la bande-son</button><?php endif; ?>
      <button type="submit" name="action" value="retirer" class="bouton bouton--danger" data-confirmer="Retirer cet élément du bloc ? Son média reste dans la médiathèque.">Retirer du bloc</button>
    </div>
  </form>
  <?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="carte carte--ajout">
    <?= champ_csrf() ?><input type="hidden" name="action" value="ajouter"><input type="hidden" name="bloc" value="<?= e($bloc) ?>">
    <h3>Ajouter un élément à ce bloc</h3>
    <p class="aide aide--haut">Une des trois sources, au choix<?= $role === 'fond' ? ' (une image)' : '' ?>.</p>
    <div class="colonnes">
      <div class="champ"><label for="media-<?= e($bloc) ?>">Un média de la médiathèque</label>
        <select id="media-<?= e($bloc) ?>" name="media_id">
          <option value="">— choisir —</option>
          <optgroup label="Images"><?php foreach ($images as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></optgroup>
          <?php if ($role === 'plein'): ?><optgroup label="Vidéos"><?php foreach ($videos as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
        </select>
      </div>
      <div class="champ"><label for="fichier-<?= e($bloc) ?>">ou un fichier à déposer</label>
        <input type="file" id="fichier-<?= e($bloc) ?>" name="fichier" accept="<?= $role === 'plein' ? 'image/*,video/mp4' : 'image/*' ?>">
        <p class="aide">Image jusqu'à <?= e(taille_lisible(taille_max('image'))) ?><?= $role === 'plein' ? ', vidéo MP4 jusqu\'à ' . e(taille_lisible(taille_max('video'))) : '' ?>.</p>
      </div>
      <div class="champ"><label for="adresse-<?= e($bloc) ?>">ou une adresse externe</label>
        <input type="url" id="adresse-<?= e($bloc) ?>" name="adresse" placeholder="https://…">
        <p class="aide"><?= $role === 'plein' ? 'Image, vidéo hébergée (MP4) ou lien YouTube.' : 'Adresse d\'une image.' ?> Rien n'est copié : le site lit le média à cette adresse.</p>
      </div>
    </div>
    <?= champ('credit', 'Crédit (pour un nouveau média)', '', ['max' => 255]) ?>
    <div class="actions"><button type="submit" class="bouton">Ajouter au bloc</button></div>
  </form>
</section>
<?php endforeach; ?>

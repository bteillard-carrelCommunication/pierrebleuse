<?php
/**
 * Visuels : les emplacements de la page d'accueil. Média choisi dans la médiathèque, point focal
 * réglé au clic sur l'image, opacité, place de la nuée. Une nouvelle photo reçoit le préréglage
 * de lumière « generique ».
 */

defined('PUPITRE') or exit;

$titre = 'Visuels';
$erreurs = [];
$images = medias_choix(['image']);
$videos = medias_choix(['video']);
$presets = array_values(array_unique(array_merge(['generique'], array_filter(bd_colonne("SELECT DISTINCT lumiere FROM visuels WHERE lumiere <> ''")))));

if (est_post() && ($v = bd_ligne('SELECT * FROM visuels WHERE cle = ?', [recu('cle')]))) {
    $r = is_array($_POST['v'][$v['cle']] ?? null) ? $_POST['v'][$v['cle']] : [];
    $t = fn(string $k) => trim((string) ($r[$k] ?? ''));
    $pourcent = fn(string $k) => ($t($k) !== '' && ctype_digit($t($k)) && (int) $t($k) <= 100) ? (int) $t($k) : null;
    if (recu('action') === 'analyser') {
        if (($manque = analyse_possible()) !== '') {
            message('alerte', 'Analyse impossible ici : ' . $manque . '.');
        } elseif ($v['media_id']) {
            tache_creer('analyse_son', ['media_id' => (int) $v['media_id'], 'table' => 'visuels', 'cle' => $v['cle'], 'nom' => $v['cle'] . '-bande-son']);
            message(taches_lancer_en_fond() ? 'ok' : 'alerte', 'Analyse de la bande-son demandée. Elle tourne en tâche de fond : rechargez la page dans un instant.');
        }
        rediriger(url('visuels') . '#' . $v['cle']);
    }
    $type = in_array($t('type'), ['image', 'video'], true) ? $t('type') : $v['type'];
    $choix = $type === 'video' ? $videos : $images;
    $media = ctype_digit($t('media_id')) && isset($choix[(int) $t('media_id')]) ? (int) $t('media_id') : null;
    $d = [
        'type' => $type,
        'media_id' => in_array($type, ['image', 'video'], true) ? $media : null,
        'affiche_id' => ($type === 'video' && ctype_digit($t('affiche_id')) && isset($images[(int) $t('affiche_id')])) ? (int) $t('affiche_id') : null,
        'position_x' => $pourcent('position_x'), 'position_y' => $pourcent('position_y'),
        'mobile_x' => $pourcent('mobile_x'), 'mobile_y' => $pourcent('mobile_y'),
        'lumiere' => $type === 'image' ? mb_substr(slug($t('lumiere'), 40), 0, 40) : '',
        'alt_fr' => mb_substr($t('alt_fr'), 0, 500), 'alt_en' => mb_substr($t('alt_en'), 0, 500),
        'opacite' => max(0, min(100, (int) (round(((int) $t('opacite')) / 5) * 5))),
        'nuee' => in_array($t('nuee'), ['arriere-plan', 'premier-plan'], true) ? $t('nuee') : '',
        'son' => ($type === 'video' && !empty($r['son'])) ? 1 : 0,
    ];
    if (in_array($type, ['image', 'video'], true) && !$d['media_id']) $erreurs[] = 'Choisissez un média dans la médiathèque.';
    if (($d['position_x'] === null) !== ($d['position_y'] === null)) $erreurs[] = 'Le point focal demande ses deux valeurs.';
    if (($d['mobile_x'] === null) !== ($d['mobile_y'] === null)) $erreurs[] = 'Le point focal sur téléphone demande ses deux valeurs.';
    if (!$erreurs) {
        $change = (int) $d['media_id'] !== (int) $v['media_id'];
        // une nouvelle photo n'a pas de préréglage à elle : « generique », sauf si l'on a choisi autre chose
        if ($change && $type === 'image' && $v['lumiere'] !== '' && $d['lumiere'] === $v['lumiere'] && $v['lumiere'] !== 'generique') $d['lumiere'] = 'generique';
        // une nouvelle vidéo n'a pas encore d'analyse de sa bande-son
        if ($change || !$d['son']) $d['piste'] = '';
        [$a, $b] = differences(array_intersect_key($v, $d), $d);
        if ($b) {
            bd_modifier('visuels', $b, ['cle' => $v['cle']]);
            journaliser('modification', 'visuel', null, 'Visuel « ' . $v['cle'] . ' »', $a, $b);
        }
        message('ok', 'Visuel enregistré.' . ($change && $type === 'image' && ($d['lumiere'] ?? '') === 'generique' ? ' Nouvelle photo : préréglage de lumière « generique ».' : ''));
        rediriger(url('visuels') . '#' . $v['cle']);
    }
}
$visuels = bd_lignes('SELECT * FROM visuels ORDER BY ordre, cle');
$medias = medias_par_id();
?>
<div class="titre"><h1>Visuels</h1><span class="titre__suite">data/visuels.js</span></div>
<p class="intro">Un visuel par emplacement de la page d'accueil. Cliquez sur l'image pour poser le point focal : c'est ce qui reste à l'écran quand l'image est rognée. Les fichiers se déposent dans la médiathèque.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?= liste_choix('liste-lumieres', $presets) ?>

<?php foreach ($visuels as $v): $p = 'v[' . $v['cle'] . ']'; $m = $medias[(int) $v['media_id']] ?? null; $image = $v['type'] === 'image' && $m; ?>
<form method="post" class="carte" id="<?= e($v['cle']) ?>">
  <?= champ_csrf() ?><input type="hidden" name="cle" value="<?= e($v['cle']) ?>">
  <h2><?= e($v['cle']) ?> <span class="puce"><?= e($v['type']) ?></span></h2>
  <div class="visuel-grille">
    <div>
      <?php if ($image): ?>
        <div class="focal" data-focal="<?= e($p) ?>" title="Cliquez pour poser le point focal">
          <img src="<?= e(media_adresse((int) $m['id'])) ?>" alt="<?= e($v['alt_fr']) ?>">
          <span class="focal__point focal__point--large" hidden></span>
          <span class="focal__point focal__point--mobile" hidden></span>
        </div>
        <fieldset class="champ"><legend class="sr">Point focal à régler</legend>
          <div class="en-ligne">
            <label><input type="radio" name="<?= e($p) ?>[cible]" value="large" checked> Écran large</label>
            <label><input type="radio" name="<?= e($p) ?>[cible]" value="mobile"> Téléphone (facultatif)</label>
          </div>
        </fieldset>
        <div class="cadrages" data-cadrages="<?= e($p) ?>" aria-hidden="true">
          <div class="cadrage cadrage--large"><img src="<?= e(media_adresse((int) $m['id'])) ?>" alt=""></div>
          <div class="cadrage cadrage--etroit"><img src="<?= e(media_adresse((int) $m['id'])) ?>" alt=""></div>
        </div>
        <p class="aide">À gauche, le cadrage sur un écran large ; à droite, sur un téléphone.</p>
      <?php elseif ($v['type'] === 'video' && $m): ?>
        <video class="lecteur" src="<?= e(media_adresse((int) $m['id'])) ?>" controls preload="metadata"<?= $v['affiche_id'] ? ' poster="' . e(media_adresse((int) $v['affiche_id'])) . '"' : '' ?>></video>
      <?php else: ?>
        <p class="rien">Pas de média choisi.</p>
      <?php endif; ?>
    </div>
    <div>
      <?= champ_choix('type', 'Type', ['image' => 'Image', 'video' => 'Vidéo'] + (in_array($v['type'], ['youtube', 'couleur'], true) ? [$v['type'] => ucfirst($v['type'])] : []), $v['type'], ['prefixe' => $p]) ?>
      <?= champ_choix('media_id', 'Média', $v['type'] === 'video' ? $videos : $images, $v['media_id'], ['prefixe' => $p, 'vide' => '— choisir dans la médiathèque —', 'aide' => 'Pour passer d\'une image à une vidéo : changez le type, enregistrez, puis choisissez le média.']) ?>
      <?php if ($v['type'] === 'video'): ?>
        <?= champ_choix('affiche_id', 'Image d\'attente', $images, $v['affiche_id'], ['prefixe' => $p, 'vide' => '— aucune —']) ?>
        <?= champ_case('son', 'La vidéo joue avec son son, et prend le relais de la musique', (bool) $v['son'], ['prefixe' => $p]) ?>
        <?php if ($v['son']): ?>
          <p class="aide">Analyse de la bande-son (ce que suit la nuée) : <?= $v['piste'] !== '' ? '<code>' . e($v['piste']) . '</code>' : 'à faire' ?>.</p>
        <?php endif; ?>
      <?php endif; ?>
      <div class="colonnes">
        <?= champ('position_x', 'Point focal : horizontal (%)', $v['position_x'], ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
        <?= champ('position_y', 'Point focal : vertical (%)', $v['position_y'], ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
      </div>
      <div class="colonnes">
        <?= champ('mobile_x', 'Téléphone : horizontal (%)', $v['mobile_x'], ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
        <?= champ('mobile_y', 'Téléphone : vertical (%)', $v['mobile_y'], ['prefixe' => $p, 'type' => 'number', 'max' => 3]) ?>
      </div>
      <?php if ($v['type'] === 'image'): ?>
        <?= champ('lumiere', 'Préréglage de lumière', $v['lumiere'], ['prefixe' => $p, 'liste' => 'liste-lumieres', 'aide' => 'Vide : l\'image telle quelle. « generique » : pour toute photo. Les autres noms sont réglés sur une photo précise, dans le site.']) ?>
      <?php endif; ?>
      <div class="colonnes">
        <?= champ('opacite', 'Opacité (0 à 100)', $v['opacite'], ['prefixe' => $p, 'type' => 'number', 'max' => 3, 'aide' => 'Par pas de 5. 100 : le visuel plein ; 0 : on ne voit que la nuée.']) ?>
        <?= champ_choix('nuee', 'Place de la nuée', ['arriere-plan' => 'Derrière le visuel', 'premier-plan' => 'Par-dessus le visuel'], $v['nuee'], ['prefixe' => $p, 'vide' => 'Par défaut (derrière)']) ?>
      </div>
      <?= champ_bi('alt', 'Texte alternatif', $v['alt_fr'], $v['alt_en'], ['prefixe' => $p, 'aide' => 'Ce que montre l\'image, pour qui ne la voit pas. Vide si elle est décorative.']) ?>
    </div>
  </div>
  <div class="actions">
    <button type="submit" class="bouton bouton--plein">Enregistrer ce visuel</button>
    <?php if ($v['type'] === 'video' && $v['son'] && $v['media_id']): ?><button type="submit" name="action" value="analyser" class="bouton">Analyser la bande-son</button><?php endif; ?>
  </div>
</form>
<?php endforeach; ?>

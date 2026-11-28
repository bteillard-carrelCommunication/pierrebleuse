<?php
/**
 * Orchestres du monde et carte interactive : deux vues d'un même écran (menu « Carrière »).
 *   page « orchestres »  la collection : tous les orchestres et ensembles connus, leur activité ;
 *   page « carte »       la carte du monde du site : ses lignes, leur position, leur affichage.
 *
 * La carte est une liste administrée : chaque ligne associe un orchestre à une ville et à ses
 * coordonnées. On place un orchestre dans sa ville (le Tokyo Symphony Orchestra à Tokyo, même s'il
 * a été dirigé à Kawasaki), et l'on ajoute les orchestres et les villes que l'on veut. Une ligne a
 * deux statuts : affichée ou non sur la carte, position vérifiée ou à confirmer.
 *
 * Trouver des coordonnées : Pupitre reprend d'abord celles d'une ville qu'il connaît déjà ; à
 * défaut l'IA en propose (à confirmer) ; dans tous les cas le point se place et se corrige à la
 * main, sur la carte du site elle-même. Aucun service de cartographie externe.
 */

defined('PUPITRE') or exit;

$vue = ($page_courante ?? '') === 'carte' ? 'carte' : 'collection';
$titre = $vue === 'carte' ? 'Carte interactive' : 'Orchestres du monde';
// une ligne à régler, un orchestre à placer : c'est l'affaire de la carte (les anciens liens vers « orchestres » y mènent)
if ($vue === 'collection' && !est_post() && (recu('ligne') !== '' || recu('orchestre') !== '' || recu('nouvelle') !== '')) {
    rediriger(url('carte', array_diff_key($_GET, ['page' => 1])) . '#placer');
}
$erreurs = [];
$fiches = [];
foreach (bd_lignes("SELECT * FROM annuaire WHERE categorie IN ('orchestre', 'ensemble') ORDER BY nom") as $f) $fiches[(int) $f['id']] = $f;
$noms = array_map(fn($f) => $f['nom'], $fiches);
$zones = array_diff_key(ZONES_PARCOURS, ['' => 1]);

if (est_post()) {
    $action = recu('action');

    // --- un nouvel orchestre dans la collection
    if ($action === 'creer') {
        $nom = mb_substr(recu('nom'), 0, 190);
        $trouve = $nom !== '' ? orchestre_chercher($nom) : ['id' => null, 'candidats' => []];
        if ($nom === '') $erreurs[] = 'Donnez le nom officiel de l\'orchestre.';
        elseif ($trouve['id']) { message('alerte', '« ' . $fiches[$trouve['id']]['nom'] . ' » est déjà dans la collection : le voici.'); rediriger(url('carte', ['orchestre' => $trouve['id']]) . '#placer'); }
        elseif ($trouve['candidats'] && recu('confirmer') !== '1') $erreurs[] = 'Un orchestre au nom proche existe déjà : ' . implode(', ', array_column(array_slice($trouve['candidats'], 0, 3), 'nom')) . '. Si c\'est bien un autre orchestre, cochez « c\'est un autre orchestre » et recommencez.';
        if (!$erreurs) {
            $id = (int) objet_enregistrer('annuaire', null, ['categorie' => recu('categorie') === 'ensemble' ? 'ensemble' : 'orchestre', 'nom' => $nom,
                'ville_en' => mb_substr(recu('ville_en'), 0, 120), 'pays_en' => mb_substr(recu('pays_en'), 0, 120)], 'fiche', $nom);
            $GLOBALS['annuaire_perime'] = true;
            message('ok', 'Orchestre ajouté à la collection. Placez-le sur la carte : il n\'y paraîtra qu\'une fois affiché.');
            rediriger(url('carte', ['orchestre' => $id]) . '#placer');
        }
    }

    // --- chercher les coordonnées d'une ville : d'abord ce que Pupitre connaît déjà, sinon l'IA, toujours à confirmer
    if ($action === 'chercher') {
        $ville = recu('ville_en');
        $params = array_filter(['ligne' => recu('ligne'), 'orchestre' => recu('fiche_id'), 'nouvelle' => (recu('ligne') === '' && recu('fiche_id') === '') ? '1' : '', 'ville' => $ville, 'pays' => recu('pays')], 'strlen');
        $connues = coordonnees_connues($ville);
        if ($ville === '') message('erreur', 'Donnez d\'abord la ville.');
        elseif ($connues) { $params += ['lat' => $connues[0], 'lon' => $connues[1]]; message('ok', 'Coordonnées reprises de ' . $connues[2] . ' : ' . $ville . '. Vérifiez le point sur la carte.'); }
        elseif (function_exists('ia_geolocaliser') && ia_configuree()) {
            try {
                $r = ia_geolocaliser($fiches[(int) recu('fiche_id')]['nom'] ?? '', $ville, recu('pays'));
                if ($r) { $params += ['lat' => $r['latitude'], 'lon' => $r['longitude'], 'ia' => '1']; message('alerte', 'Coordonnées proposées par l\'IA pour ' . $ville . ($r['remarque'] !== '' ? ' (' . $r['remarque'] . ')' : '') . ' : à vérifier sur la carte, puis à corriger en déplaçant le point.'); }
                else message('alerte', 'L\'IA n\'a pas su situer cette ville. Placez le point à la main sur la carte.');
            } catch (Throwable $e) {
                message('alerte', 'L\'IA n\'a pas répondu (' . $e->getMessage() . '). Placez le point à la main sur la carte.');
            }
        } else message('alerte', 'Cette ville n\'est pas encore connue de Pupitre. Placez le point à la main sur la carte, ou saisissez ses coordonnées.');
        rediriger(url('carte', $params) . '#placer');
    }

    // --- une ligne de la carte : créer, modifier, retirer
    if ($action === 'ligne') {
        $id = recu_entier('ligne');
        $ligne = $id ? bd_ligne('SELECT * FROM parcours_orchestres WHERE id = ?', [$id]) : null;
        $fiche_id = recu_entier('fiche_id') ?? 0;
        $num = fn(string $k) => (($x = str_replace(',', '.', recu($k))) !== '' && is_numeric($x)) ? (float) $x : null;
        $lat = $num('latitude');
        $lon = $num('longitude');
        if (!isset($fiches[$fiche_id])) $erreurs[] = 'Choisissez un orchestre de la collection.';
        if (recu('ville_en') === '') $erreurs[] = 'La ville est obligatoire : c\'est l\'étiquette du point sur la carte.';
        if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180) $erreurs[] = 'Latitude (de -90 à 90) et longitude (de -180 à 180) sont obligatoires, en degrés décimaux : cherchez-les, ou placez le point sur la carte.';
        if (!$erreurs) {
            $d = ['fiche_id' => $fiche_id, 'latitude' => round($lat, 5), 'longitude' => round($lon, 5),
                'zone' => isset($zones[recu('zone')]) ? recu('zone') : '',
                'affiche' => empty($_POST['affiche']) ? 0 : 1, 'verifie' => empty($_POST['verifie']) ? 0 : 1] + recu_tr($_POST, 'ville', 120);
            if ($ligne) {
                objet_enregistrer('parcours_orchestres', $id, $d, 'carte', $fiches[$fiche_id]['nom'] . ' · ' . $d['ville_en']);
            } else {
                $d['ordre'] = 1 + (int) bd_valeur('SELECT COALESCE(MAX(ordre), 0) FROM parcours_orchestres');
                $d['origine'] = recu('origine') === 'ia' ? 'ia' : 'main';
                $id = (int) objet_enregistrer('parcours_orchestres', null, $d, 'carte', $fiches[$fiche_id]['nom'] . ' · ' . $d['ville_en']);
            }
            // « c'est sa ville » : la fiche de l'orchestre reçoit cette ville et ces coordonnées comme référence
            if (!empty($_POST['reference'])) {
                $ref = ['ville_en' => $d['ville_en'], 'latitude' => $d['latitude'], 'longitude' => $d['longitude'], 'verifie' => $d['verifie']];
                if (recu('pays_en') !== '') $ref['pays_en'] = mb_substr(recu('pays_en'), 0, 120);
                objet_enregistrer('annuaire', $fiche_id, $ref, 'fiche', $fiches[$fiche_id]['nom']);
            }
            message('ok', 'Ligne enregistrée' . ($d['affiche'] ? '' : ' (pas encore affichée sur la carte)') . '. Le site suivra à la prochaine publication.');
            rediriger(url('carte') . '#l-' . $id);
        }
    }
    if ($action === 'retirer' && ($ligne = bd_ligne('SELECT p.*, a.nom FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id WHERE p.id = ?', [recu_entier('ligne') ?? 0]))) {
        objet_supprimer('parcours_orchestres', (int) $ligne['id'], 'carte', $ligne['nom'] . ' · ' . $ligne['ville_en']);
        message('ok', 'Ligne retirée de la carte. L\'orchestre reste dans la collection.');
        rediriger(url('carte'));
    }
    if (in_array($action, ['afficher', 'masquer', 'verifier', 'monter', 'descendre'], true) && ($ligne = bd_ligne('SELECT p.*, a.nom FROM parcours_orchestres p JOIN annuaire a ON a.id = p.fiche_id WHERE p.id = ?', [recu_entier('ligne') ?? 0]))) {
        if ($action === 'monter' || $action === 'descendre') {
            $ordre = array_map('intval', bd_colonne('SELECT id FROM parcours_orchestres ORDER BY ordre, id'));
            $i = array_search((int) $ligne['id'], $ordre, true);
            $j = $i + ($action === 'monter' ? -1 : 1);
            if ($i !== false && $j >= 0 && $j < count($ordre)) {
                [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
                foreach ($ordre as $rang => $x) bd_modifier('parcours_orchestres', ['ordre' => $rang], ['id' => $x]);
                journaliser('modification', 'carte', (int) $ligne['id'], $ligne['nom'] . ' · ' . $ligne['ville_en'] . ' : ordre de la liste', ['rang' => $i + 1], ['rang' => $j + 1]);
            }
        } else {
            $d = $action === 'verifier' ? ['verifie' => 1] : ['affiche' => $action === 'afficher' ? 1 : 0];
            objet_enregistrer('parcours_orchestres', (int) $ligne['id'], $d, 'carte', $ligne['nom'] . ' · ' . $ligne['ville_en']);
        }
        rediriger(url('carte') . '#l-' . $ligne['id']);
    }
}

// ---------- ce que l'écran montre ----------
$lignes = carte_lignes();
$carte = carte_monde();
$par_fiche = [];
foreach ($lignes as $l) $par_fiche[(int) $l['fiche_id']][] = $l;

// la ligne que l'on place : une ligne existante (?ligne=), ou une nouvelle pour un orchestre (?orchestre=)
$a_placer = null;
if (recu_entier('ligne')) {
    foreach ($lignes as $l) if ((int) $l['id'] === recu_entier('ligne')) $a_placer = $l;
} elseif (recu_entier('orchestre') && isset($fiches[recu_entier('orchestre')])) {
    $f = $fiches[recu_entier('orchestre')];
    $a_placer = ['id' => null, 'fiche_id' => (int) $f['id'], 'nom' => $f['nom'], 'ville_en' => $f['ville_en'], 'latitude' => $f['latitude'], 'longitude' => $f['longitude'], 'zone' => '', 'affiche' => 0, 'verifie' => (int) $f['verifie']];
    if ($f['latitude'] === null && ($connues = coordonnees_connues($f['ville_en']))) { $a_placer['latitude'] = $connues[0]; $a_placer['longitude'] = $connues[1]; }
} elseif (recu('nouvelle') === '1') {
    $a_placer = ['id' => null, 'fiche_id' => 0, 'nom' => '', 'ville_en' => '', 'latitude' => null, 'longitude' => null, 'zone' => '', 'affiche' => 0, 'verifie' => 0];
}
if ($a_placer) {
    // ce que la recherche de coordonnées vient de proposer, ou ce qui vient d'être saisi
    if (recu('ville') !== '') $a_placer['ville_en'] = recu('ville');
    if (is_numeric(recu('lat')) && is_numeric(recu('lon'))) { $a_placer['latitude'] = (float) recu('lat'); $a_placer['longitude'] = (float) recu('lon'); $a_placer['verifie'] = 0; }
    if ($erreurs && recu('action') === 'ligne') {
        $a_placer = array_merge($a_placer, ['fiche_id' => recu_entier('fiche_id') ?? 0, 'ville_en' => recu('ville_en'), 'latitude' => recu('latitude'), 'longitude' => recu('longitude'), 'zone' => recu('zone'), 'affiche' => empty($_POST['affiche']) ? 0 : 1, 'verifie' => empty($_POST['verifie']) ? 0 : 1]);
    }
}
$point = fn($l) => ($carte && $l['latitude'] !== null && $l['latitude'] !== '' && is_numeric($l['latitude']) && is_numeric($l['longitude'])) ? carte_projeter($carte, (float) $l['latitude'], (float) $l['longitude']) : null;
$activites = [];
foreach ($fiches as $id => $f) $activites[$id] = orchestre_activite($id);
$inconnus = concerts_orchestre_inconnu();
$n_affichees = count(array_filter($lignes, fn($l) => $l['affiche'] && $l['latitude'] !== null));
$n_a_confirmer = count(array_filter($lignes, fn($l) => !$l['verifie']));
?>
<?php if ($vue === 'carte'): ?>
<div class="titre">
  <h1>Carte interactive</h1><span class="titre__suite">data/orchestres.js · <?= e(pluriel($n_affichees, 'ligne affichée', 'lignes affichées')) ?><?= $n_a_confirmer ? ' · <span class="puce puce--alerte">' . $n_a_confirmer . ' à confirmer</span>' : '' ?></span>
  <div class="titre__actions">
    <a class="bouton" href="<?= e(url('orchestres')) ?>"><?= icone('orchestres') ?>Orchestres du monde</a>
    <a class="bouton bouton--plein" href="<?= e(url('carte', ['nouvelle' => '1'])) ?>#placer"><?= icone('plus') ?>Ajouter une ligne</a>
  </div>
</div>
<p class="intro">La carte du monde du site est cette liste : un orchestre, une ville, un point. On place un orchestre dans sa ville, pas dans celle d'un concert donné ailleurs, et l'on ajoute les orchestres et les villes que l'on veut. Une ligne ne paraît sur la carte que si elle est affichée ; « à confirmer » signale une position que Pierre Bleuse n'a pas encore vérifiée.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<?php if ($carte): ?>
<div class="carte">
  <h2>La carte</h2>
  <div class="mappemonde" data-carte data-lon0="<?= e($carte['lon'][0]) ?>" data-lon1="<?= e($carte['lon'][1]) ?>" data-lat0="<?= e($carte['lat'][0]) ?>" data-lat1="<?= e($carte['lat'][1]) ?>" data-l="<?= e($carte['l']) ?>" data-h="<?= e($carte['h']) ?>"<?= $a_placer ? ' data-carte-edition' : '' ?>>
    <svg viewBox="0 0 <?= e($carte['l']) ?> <?= e($carte['h']) ?>" role="img" aria-label="Carte du monde : les lignes de la liste">
      <path class="mappemonde__terre" d="<?= e($carte['trace']) ?>" stroke-width="<?= e(2 * $carte['r']) ?>"/>
      <?php foreach ($lignes as $l): if ($a_placer && (int) $l['id'] === (int) ($a_placer['id'] ?? 0)) continue; $p = $point($l); if (!$p) continue; ?>
        <a href="<?= e(url('carte', ['ligne' => $l['id']])) ?>#placer"><circle class="mappemonde__point<?= $l['affiche'] ? '' : ' mappemonde__point--hors' ?><?= $l['verifie'] ? '' : ' mappemonde__point--a-confirmer' ?>" cx="<?= e($p[0]) ?>" cy="<?= e($p[1]) ?>" r="4"><title><?= e($l['nom'] . ' · ' . $l['ville_en'] . ($l['affiche'] ? '' : ' (pas affichée)') . ($l['verifie'] ? '' : ' (à confirmer)')) ?></title></circle></a>
      <?php endforeach; ?>
      <?php if ($a_placer): $p = $point($a_placer); ?>
        <g class="mappemonde__curseur" data-carte-point<?= $p ? '' : ' hidden' ?> transform="translate(<?= e($p[0] ?? 0) ?> <?= e($p[1] ?? 0) ?>)"><circle r="9" class="mappemonde__halo"/><circle r="4.5" class="mappemonde__actif"/></g>
      <?php endif; ?>
    </svg>
  </div>
  <p class="aide"><span class="legende legende--affiche"></span> affichée et vérifiée · <span class="legende legende--a-confirmer"></span> à confirmer · <span class="legende legende--hors"></span> pas affichée sur le site<?= $a_placer ? ' · <span class="legende legende--actif"></span> la ligne que vous placez : cliquez sur la carte ou faites glisser le point' : ' · cliquez un point pour le régler' ?></p>
</div>
<?php else: ?><p class="message message--alerte">La carte du site (data/cartes.js) est introuvable : les coordonnées se saisissent à la main.</p><?php endif; ?>

<?php if ($a_placer): $p = $point($a_placer); ?>
<form method="post" class="carte" id="placer" data-carte-champs>
  <?= champ_csrf() ?><input type="hidden" name="ligne" value="<?= e($a_placer['id'] ?? '') ?>"><input type="hidden" name="origine" value="<?= recu('ia') === '1' ? 'ia' : 'main' ?>">
  <h2><?= $a_placer['id'] ? 'Régler une ligne de la carte' : 'Ajouter une ligne à la carte' ?></h2>
  <div class="colonnes">
    <?= champ_choix('fiche_id', 'Orchestre', $noms, $a_placer['fiche_id'] ?: '', ['vide' => '— choisir dans la collection —', 'requis' => true, 'aide' => 'Pour un orchestre absent de la liste : ajoutez-le d\'abord dans « Orchestres du monde ».']) ?>
    <?= champ_choix('zone', 'Colonne de la liste du site', $zones, $a_placer['zone'], ['vide' => ZONES_PARCOURS[''], 'aide' => 'À imposer quand la longitude ne suffit pas (Proche-Orient, Russie).']) ?>
  </div>
  <?= champ_tr('ville', 'Ville', $a_placer['ville_en'], ['requis' => true, 'aide' => 'L\'étiquette du point. La ville de l\'orchestre, sauf choix contraire.'] + ($a_placer['id'] ? ['objet' => ['parcours_orchestres', $a_placer['id'], 'ville']] : [])) ?>
  <div class="colonnes">
    <?= champ('pays', 'Pays (pour la recherche)', recu('pays') ?: ($fiches[(int) $a_placer['fiche_id']]['pays_en'] ?? ''), ['max' => 120]) ?>
    <div class="champ"><span class="champ__faux-libelle">Coordonnées</span><button type="submit" name="action" value="chercher" class="bouton" formnovalidate>Chercher les coordonnées de cette ville</button>
      <p class="aide">D'abord dans ce que Pupitre connaît déjà ; sinon l'IA propose, et vous confirmez.</p></div>
  </div>
  <div class="colonnes">
    <?= champ('latitude', 'Latitude', $a_placer['latitude'] === null ? '' : rtrim(rtrim(number_format((float) $a_placer['latitude'], 5, '.', ''), '0'), '.'), ['aide' => 'Degrés décimaux, nord positif.', 'classe' => 'champ--carte-lat']) ?>
    <?= champ('longitude', 'Longitude', $a_placer['longitude'] === null ? '' : rtrim(rtrim(number_format((float) $a_placer['longitude'], 5, '.', ''), '0'), '.'), ['aide' => 'Degrés décimaux, est positif.', 'classe' => 'champ--carte-lon']) ?>
  </div>
  <div class="colonnes">
    <?= champ_case('verifie', 'Position vérifiée', (bool) $a_placer['verifie']) ?>
    <?= champ_case('affiche', 'Affichée sur la carte du site', (bool) $a_placer['affiche']) ?>
    <?= champ_case('reference', 'C\'est la ville de l\'orchestre : la noter aussi sur sa fiche', !$a_placer['id']) ?>
  </div>
  <input type="hidden" name="pays_en" value="<?= e(recu('pays')) ?>">
  <div class="actions">
    <button type="submit" name="action" value="ligne" class="bouton bouton--plein">Enregistrer cette ligne</button>
    <a class="bouton" href="<?= e(url('carte')) ?>">Annuler</a>
    <?php if ($a_placer['id']): ?><button type="submit" name="action" value="retirer" class="bouton bouton--danger" formnovalidate data-confirmer="Retirer cette ligne de la carte ? L'orchestre reste dans la collection.">Retirer de la carte</button><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<div class="carte">
  <h2>Les lignes de la carte</h2>
  <p class="aide aide--haut">L'ordre est celui de la liste du site, colonne par colonne.</p>
  <div class="tableau-defile"><table class="tableau tableau--serre">
    <thead><tr><th>Orchestre</th><th>Ville</th><th>Coordonnées</th><th>Colonne</th><th>Carte</th><th>Position</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $i => $l): ?>
      <tr id="l-<?= (int) $l['id'] ?>">
        <th scope="row"><a href="<?= e(url('carte', ['ligne' => $l['id']])) ?>#placer"><?= e($l['nom']) ?></a><?= $l['nom_provisoire'] ? ' <span class="puce puce--alerte">nom à fournir</span>' : '' ?></th>
        <td><?= e($l['ville_en']) ?></td>
        <td class="discret"><?= $l['latitude'] === null ? '<span class="puce puce--alerte">à placer</span>' : e(rtrim(rtrim($l['latitude'], '0'), '.') . ', ' . rtrim(rtrim($l['longitude'], '0'), '.')) ?></td>
        <td class="discret"><?= e(ZONES_PARCOURS[zone_carte($l['longitude'] === null ? null : (float) $l['longitude'], $l['zone'])] ?? '') ?></td>
        <td><?= $l['affiche'] ? '<span class="puce puce--ok">affichée</span>' : '<span class="puce">pas affichée</span>' ?></td>
        <td><?= $l['verifie'] ? '<span class="puce puce--ok">vérifiée</span>' : '<span class="puce puce--alerte">à confirmer</span>' ?></td>
        <td class="tableau__actions">
          <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="ligne" value="<?= (int) $l['id'] ?>">
            <button type="submit" name="action" value="monter" class="outil" title="Monter" aria-label="Monter cette ligne"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
            <button type="submit" name="action" value="descendre" class="outil" title="Descendre" aria-label="Descendre cette ligne"<?= $i === count($lignes) - 1 ? ' disabled' : '' ?>>↓</button>
            <?php if (!$l['verifie']): ?><button type="submit" name="action" value="verifier" class="bouton bouton--petit">Vérifiée</button><?php endif; ?>
            <button type="submit" name="action" value="<?= $l['affiche'] ? 'masquer' : 'afficher' ?>" class="bouton bouton--petit"><?= $l['affiche'] ? 'Ne plus afficher' : 'Afficher' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?= apercu_zones(['ORCHESTRES' => '']) ?>

<?php else: ?>
<div class="titre">
  <h1>Orchestres du monde</h1><span class="titre__suite"><?= e(pluriel(count($fiches), 'orchestre ou ensemble', 'orchestres et ensembles')) ?> · <?= e(pluriel(count(array_filter($par_fiche)), 'placé sur la carte', 'placés sur la carte')) ?></span>
  <div class="titre__actions"><a class="bouton" href="<?= e(url('carte')) ?>"><?= icone('carte') ?>Carte interactive</a><a class="bouton bouton--plein" href="#ajouter"><?= icone('plus') ?>Ajouter un orchestre</a></div>
</div>
<p class="intro">Les orchestres et les ensembles que Pierre Bleuse a dirigés, dirige ou dirigera : la collection où puisent les concerts, le parcours et la carte du monde. Un orchestre se reconnaît à son nom officiel et à ses autres noms ; sa fiche, dans l'annuaire, porte sa ville de référence et la date de la première collaboration.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<?php if ($inconnus): ?>
<div class="carte carte--alerte">
  <h2>Orchestres de concerts qui ne sont pas référencés</h2>
  <ul class="items">
  <?php foreach ($inconnus as [$c, $r]): ?>
    <li><span class="item__titre"><?= e($c['ensemble_en']) ?></span>
      <span class="discret">concert « <?= e($c['titre_en']) ?> »<?= $r['candidats'] ? ' · proche de : ' . e(implode(', ', array_column(array_slice($r['candidats'], 0, 2), 'nom'))) : '' ?></span>
      <span class="item__actions"><a class="bouton bouton--petit" href="<?= e(url('concert', ['id' => $c['id']])) ?>#orchestre">Voir le concert et décider</a></span></li>
  <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="carte">
  <div class="carte__tete"><h2>La collection</h2><span class="carte__compte"><?= count($fiches) ?></span></div>
  <p class="aide aide--haut">Tous les orchestres et ensembles connus de Pupitre. Être dans la collection n'est pas paraître sur la carte.</p>
  <div class="tableau-defile"><table class="tableau tableau--serre">
    <thead><tr><th>Orchestre</th><th>Ville de référence</th><th>Activité</th><th>Carte</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($fiches as $id => $f): [$etat, $passes, $futurs] = $activites[$id]; $sur = $par_fiche[$id] ?? []; ?>
      <tr>
        <th scope="row"><a href="<?= e(url('fiche', ['id' => $id])) ?>"><?= e($f['nom']) ?></a></th>
        <td><?= e(implode(', ', array_filter([$f['ville_en'], $f['pays_en']], 'strlen'))) ?: '<span class="discret">à préciser</span>' ?><?= ($f['ville_en'] !== '' && !$f['verifie']) ? ' <span class="puce puce--alerte">à confirmer</span>' : '' ?></td>
        <td><?php if ($etat === 'dirige'): ?><span class="puce puce--ok">déjà dirigé</span><?php elseif ($etat === 'a_venir'): ?><span class="puce puce--accent">engagement à venir</span><?php else: ?><span class="discret">aucun concert</span><?php endif; ?>
          <?php if ($passes || $futurs): ?><span class="discret"><?= $passes ? e(pluriel($passes, 'concert passé', 'concerts passés')) : '' ?><?= $passes && $futurs ? ', ' : '' ?><?= $futurs ? $futurs . ' à venir' : '' ?></span><?php endif; ?></td>
        <td><?php if (!$sur): ?><span class="discret">pas sur la carte</span><?php else: ?><?= e(implode(', ', array_map(fn($l) => $l['ville_en'] . ($l['affiche'] ? '' : ' (pas affichée)'), $sur))) ?><?php endif; ?></td>
        <td class="tableau__actions"><a class="bouton bouton--petit" href="<?= e(url('carte', $sur ? ['ligne' => $sur[0]['id']] : ['orchestre' => $id])) ?>#placer"><?= $sur ? 'Régler' : 'Placer sur la carte' ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<form method="post" class="carte" id="ajouter">
  <?= champ_csrf() ?><input type="hidden" name="action" value="creer">
  <h2>Ajouter un orchestre à la collection</h2>
  <p class="aide aide--haut">Indépendamment de tout concert. Pupitre vérifie d'abord qu'il n'y est pas déjà, sous ce nom ou sous un nom voisin.</p>
  <div class="colonnes">
    <?= champ('nom', 'Nom officiel', recu('action') === 'creer' ? recu('nom') : '', ['requis' => true, 'max' => 190]) ?>
    <?= champ_choix('categorie', 'Nature', ['orchestre' => 'Orchestre', 'ensemble' => 'Ensemble'], recu('categorie') ?: 'orchestre') ?>
    <?= champ('ville_en', 'Ville de référence', recu('action') === 'creer' ? recu('ville_en') : '', ['max' => 120]) ?>
    <?= champ('pays_en', 'Pays', recu('action') === 'creer' ? recu('pays_en') : '', ['max' => 120]) ?>
  </div>
  <?php if ($erreurs && recu('action') === 'creer'): ?><?= champ_case('confirmer', 'C\'est un autre orchestre : l\'ajouter quand même', false) ?><?php endif; ?>
  <div class="actions"><button type="submit" class="bouton">Ajouter, puis placer sur la carte</button></div>
  <p class="aide">Autres noms, site, date de première collaboration, notes : sur la fiche de l'orchestre, dans l'annuaire.</p>
</form>

<?php endif; ?>

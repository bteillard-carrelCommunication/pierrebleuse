<?php
/**
 * Un article de la revue de presse : type, média, titre, auteur, date, langue d'origine, chapeau,
 * résumé, informations complémentaires, adresse d'origine, citations (chacune dans sa langue, avec
 * sa traduction à part), visuels, concerts et albums associés, publication, mise en avant.
 *
 * Un article peut être créé à la main, ou prérempli à partir de son adresse ou de son texte collé
 * (pages/presse/import.php : rien n'entre dans la collection sans passer par ce formulaire).
 * Créé depuis la fiche d'un album ou d'un concert (?disque=, ?concert=), il lui est associé d'emblée.
 */

defined('PUPITRE') or exit;

$nouveau = recu('id') === 'nouveau';
$id = $nouveau ? null : recu_entier('id');
$a = $id ? article_lire($id) : null;
if (!$nouveau && !$a) {
    http_response_code(404);
    $titre = 'Article introuvable';
    echo '<div class="vide"><h1>Article introuvable</h1><p><a class="bouton" href="' . e(url('presse')) . '">Retour à la revue de presse</a></p></div>';
    return;
}
$erreurs = [];
$alertes = [];
$medias_presse = [];
foreach (bd_lignes("SELECT id, nom FROM annuaire WHERE categorie = 'media' ORDER BY nom") as $m) $medias_presse[(int) $m['id']] = $m['nom'];

// --- import : l'adresse d'un article, ou son texte collé, préremplit le formulaire (lot IA)
$propose = null;
if ($nouveau && is_file(RACINE . '/pages/presse/import.php')) require RACINE . '/pages/presse/import.php';

if (est_post() && $a && ($r = visuels_traiter('articles', $id, preg_replace('/\*/', '', $a['titre_en']) ?: $a['cle'])) !== null) {
    if ($r !== '') message('erreur', $r); else message('ok', 'Visuels de l\'article enregistrés.');
    rediriger(url('article', ['id' => $id]) . '#visuels');
}

if (est_post() && recu('action') === 'supprimer' && $a) {
    article_supprimer($id);
    message('ok', 'Article supprimé de la revue de presse.');
    rediriger(url('presse'));
}

if (est_post() && recu('action') === 'enregistrer') {
    $t = fn(string $k, int $max = 500) => mb_substr(recu($k), 0, $max);
    // le média : une fiche du référentiel, ou un nouveau média créé au passage
    $media_id = recu_entier('media_id');
    if ($media_id && !isset($medias_presse[$media_id])) $media_id = null;
    $media_neuf = mb_substr(recu('media_neuf'), 0, 190);
    if (!$media_id && $media_neuf !== '') {
        $media_id = fiche_trouver($media_neuf, ['media']) ?: (int) objet_enregistrer('annuaire', null, ['categorie' => 'media', 'nom' => $media_neuf, 'site' => adresse_valide(recu('media_site')) ? recu('media_site') : '',
            'langue' => preg_match('/^[a-z]{2,3}$/', recu('langue')) ? recu('langue') : ''], 'fiche', $media_neuf);
        $GLOBALS['annuaire_perime'] = true;
        $medias_presse[$media_id] = $media_neuf;
    }
    $d = [
        'type' => isset(TYPES_ARTICLE[recu('type')]) ? recu('type') : '',
        'media_id' => $media_id ?: null,
        'auteur' => $t('auteur', 190),
        'date_article' => recu('date_article'),
        'langue' => preg_match('/^[a-z]{2,3}$/', mb_strtolower(recu('langue'))) ? mb_strtolower(recu('langue')) : '',
        'lien' => $t('lien'), 'url_canonique' => $t('url_canonique'),
        'publie' => empty($_POST['publie']) ? 0 : 1, 'une' => empty($_POST['une']) ? 0 : 1,
        'accueil' => empty($_POST['accueil']) ? 0 : 1, 'ordre_accueil' => max(0, min(999, (int) recu('ordre_accueil'))),
        'largeur' => isset(LARGEURS_ARTICLE[(int) recu('largeur')]) ? (int) recu('largeur') : 1,
    ] + recu_tr($_POST, 'titre', 500) + recu_tr($_POST, 'chapeau', 3000) + recu_tr($_POST, 'resume', 5000) + recu_tr($_POST, 'infos', 5000);
    if (!$d['media_id']) $erreurs[] = 'Choisissez un média dans le référentiel, ou nommez-en un nouveau.';
    if ($d['titre_en'] === '') $erreurs[] = 'Le titre de l\'article est obligatoire.';
    if ($d['date_article'] !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])(-(0[1-9]|[12]\d|3[01]))?$/', $d['date_article'])) $erreurs[] = 'Date : AAAA-MM-JJ, ou AAAA-MM si le jour est inconnu.';
    foreach (['lien' => 'L\'adresse de l\'article', 'url_canonique' => 'L\'adresse canonique'] as $k => $nom) if (!adresse_valide($d[$k])) $erreurs[] = $nom . ' doit être complète (https://…).';
    $doublons = article_doublons($d, $id);
    if ($doublons && $nouveau && recu('confirmer_doublon') !== '1') {
        $erreurs[] = 'Cet article ressemble à un article déjà dans la collection (' . implode(' ; ', array_map(fn($x) => ($x[0]['media'] ?? '') . ' « ' . preg_replace('/\*/', '', $x[0]['titre_en']) . ' » : ' . $x[1], array_slice($doublons, 0, 3))) . '). Si c\'est bien un autre article, cochez « ce n\'est pas un doublon » et enregistrez de nouveau.';
    }
    if (!$erreurs) {
        $libelle = preg_replace('/\*/', '', $d['titre_en']);
        $id = bd_transaction(function () use ($id, $d, $libelle, $medias_presse) {
            $creation = $id === null;
            if ($id === null) {
                $d['cle'] = article_cle($medias_presse[$d['media_id']] ?? '', $d['titre_en']);
                $d['ordre'] = (int) bd_valeur('SELECT COALESCE(MIN(ordre), 0) - 1 FROM articles');           // un nouvel article entre en tête de la collection
                $d['origine'] = in_array(recu('origine'), ['url', 'texte'], true) ? recu('origine') : 'main';
                if (recu('texte_source') !== '') $d['texte_source'] = mb_substr(recu('texte_source'), 0, 200000);
                $id = (int) objet_enregistrer('articles', null, $d, 'article', $libelle);
            } else {
                objet_enregistrer('articles', $id, $d, 'article', $libelle);
            }
            citations_enregistrer($id, is_array($_POST['cit'] ?? null) ? $_POST['cit'] : [], recu('principale'));
            // ce que la lecture de l'article proposait dans les autres langues : des propositions de l'IA, à valider sur l'écran « Traductions »
            if ($creation) {
                foreach (is_array($_POST['ia_tr'] ?? null) ? $_POST['ia_tr'] : [] as $champ => $par_langue) {
                    if (!in_array($champ, ['titre', 'chapeau', 'resume', 'infos'], true) || !is_array($par_langue) || trim((string) ($d[$champ . '_' . langue_reference()] ?? '')) === '') continue;
                    foreach ($par_langue as $langue => $t) if (isset(langues_toutes()[$langue]) && is_string($t)) traduction_poser('articles', (string) $id, $champ, (string) $langue, mb_substr($t, 0, 5000), 'ia');
                }
            }
            // l'ancienne colonne « verbatim » suit la citation principale : rien de ce qui la lisait ne casse
            $principale = bd_ligne('SELECT texte FROM articles_citations WHERE article_id = ? ORDER BY principale DESC, ordre, id LIMIT 1', [$id]);
            bd_modifier('articles', ['verbatim' => $principale['texte'] ?? ''], ['id' => $id]);
            article_relations_poser($id, is_array($_POST['concerts'] ?? null) ? $_POST['concerts'] : [], is_array($_POST['disques'] ?? null) ? $_POST['disques'] : []);
            return $id;
        });
        // l'image que la page de l'article proposait : gardée par son adresse (rien n'est téléchargé), droits à confirmer
        if ($nouveau && !empty($_POST['image_garder']) && adresse_valide(recu('image_adresse')) && recu('image_adresse') !== '') {
            $media = media_creer_externe(recu('image_adresse'), 'image', ['credit' => $medias_presse[$d['media_id']] ?? '', 'droits' => 'Image relevée sur la page de l\'article : droits d\'utilisation à confirmer.']);
            if (is_int($media)) {
                bd_modifier('medias', ['droits_valides' => 0], ['id' => $media]);
                $GLOBALS['medias_perimes'] = true;
                objet_enregistrer('medias_usages', null, ['media_id' => $media, 'objet' => 'articles', 'objet_id' => (string) $id, 'role' => 'visuel', 'nature' => 'image', 'publie' => 1, 'ordre' => 0], 'visuel', 'Article « ' . $libelle . ' »');
            }
        }
        message('ok', $nouveau ? 'Article créé dans la revue de presse.' : 'Article enregistré. Le site suivra à la prochaine publication.');
        rediriger(url('article', ['id' => $id]));
    }
}

// --- ce que l'écran montre : la fiche, ce qui vient d'être saisi, ou ce que l'import propose
$saisie = est_post() && (bool) $erreurs && recu('action') === 'enregistrer';
$f = $a ?? ['type' => '', 'media_id' => null, 'titre_en' => '', 'auteur' => '', 'date_article' => '', 'langue' => '', 'chapeau_en' => '', 'resume_en' => '', 'infos_en' => '', 'lien' => '', 'url_canonique' => '',
    'publie' => 1, 'une' => 0, 'accueil' => 0, 'ordre_accueil' => 0, 'largeur' => 1, 'exemple' => 0, 'citations' => [], 'concerts' => array_filter([recu_entier('concert')]), 'disques' => array_filter([recu_entier('disque')])];
if ($propose) $f = array_merge($f, $propose);
if ($saisie) {
    foreach ($_POST as $k => $v) if (is_string($v)) $f[$k] = $v;
    foreach (['publie', 'une', 'accueil'] as $k) $f[$k] = empty($_POST[$k]) ? 0 : 1;
    $f['citations'] = array_values(array_filter($_POST['cit'] ?? [], 'is_array'));
    $f['concerts'] = array_map('intval', is_array($_POST['concerts'] ?? null) ? $_POST['concerts'] : []);
    $f['disques'] = array_map('intval', is_array($_POST['disques'] ?? null) ? $_POST['disques'] : []);
}
$v = fn(string $k) => (string) ($f[$k] ?? '');
$lg = fn(string $champ) => $id ? ['objet' => ['articles', $id, $champ]] : [];
// ce que la lecture de l'article propose dans les autres langues : gardé jusqu'à la création, puis rangé parmi les traductions à valider
$ia_tr = $saisie ? (is_array($_POST['ia_tr'] ?? null) ? $_POST['ia_tr'] : []) : ($propose['traductions'] ?? []);
$titre = $nouveau ? 'Nouvel article' : (preg_replace('/\*/', '', $a['titre_en']) ?: $a['cle']);
$concerts = bd_lignes("SELECT c.id, c.titre_en, c.ensemble_en, c.publie, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id) AS premiere FROM concerts c WHERE c.type <> 'disque' ORDER BY premiere DESC, c.id");
$albums = bd_lignes('SELECT id, titre, annee, publie FROM disques ORDER BY ordre, id');
$doublons = $a ? article_doublons($a, $id) : [];
$texte_source = $saisie ? recu('texte_source') : (string) ($propose['texte_source'] ?? ($a['texte_source'] ?? ''));

/* Une citation : sa formulation d'origine, sa langue, sa traduction anglaise. Ses autres traductions sont sur l'écran « Traductions ». */
$reference = langue_reference();
$ligne_citation = function (array $c, string $i) use ($texte_source, $reference): string {
    $p = 'cit[' . $i . ']';
    $principale = !empty($c['principale']);
    $tr = ctype_digit((string) ($c['id'] ?? '')) ? ['objet' => ['articles_citations', (int) $c['id'], 'traduction'], 'source' => (string) ($c['texte'] ?? ''), 'sauf' => [(string) ($c['langue'] ?? '')]] : [];
    // traductions proposées par la lecture de l'article dans les autres langues : elles suivent la citation jusqu'à sa création
    $proposees = '';
    foreach (is_array($c['ia_tr'] ?? null) ? $c['ia_tr'] : [] as $langue => $t) {
        if (preg_match('/^[a-z]{2,3}$/', (string) $langue) && trim((string) $t) !== '') $proposees .= '<input type="hidden" name="' . e($p) . '[ia_tr][' . e($langue) . ']" value="' . e($t) . '">';
    }
    foreach (array_keys(langues_toutes()) as $langue) {
        if ($langue !== $reference && trim((string) ($c['traduction_' . $langue] ?? '')) !== '') $proposees .= '<input type="hidden" name="' . e($p) . '[ia_tr][' . e($langue) . ']" value="' . e($c['traduction_' . $langue]) . '">';
    }
    $verifiee = array_key_exists('verifiee', $c) ? !empty($c['verifiee']) : true;
    // une citation proposée par l'IA : dit si elle se retrouve mot pour mot dans le texte de l'article
    $controle = '';
    if ($texte_source !== '' && trim((string) ($c['texte'] ?? '')) !== '') {
        $controle = citation_dans_texte((string) $c['texte'], $texte_source)
            ? '<span class="puce puce--ok">retrouvée mot pour mot dans le texte de l\'article</span>'
            : '<span class="puce puce--erreur">introuvable dans le texte de l\'article : à vérifier avant toute publication</span>';
    }
    return '<div class="ligne ligne--compacte" data-ligne><input type="hidden" name="' . e($p) . '[id]" value="' . e($c['id'] ?? '') . '">' . $proposees
        . '<div class="ligne__tete"><label class="en-ligne"><input type="radio" name="principale" value="' . e($i) . '"' . ($principale ? ' checked' : '') . '> Citation principale</label>' . $controle . boutons_ligne('cette citation') . '</div>'
        . '<div class="rangee rangee--citation">'
        . champ_texte('texte', 'Citation, dans sa langue et sa formulation d\'origine', $c['texte'] ?? '', ['prefixe' => $p, 'lignes' => 3, 'aide' => 'Avec ses guillemets. Jamais un résumé, jamais une traduction.'])
        . champ('langue', 'Langue d\'origine', $c['langue'] ?? '', ['prefixe' => $p, 'max' => 3, 'indication' => 'en', 'classe' => 'champ--court'])
        . '</div>'
        . champ_tr('traduction', 'Traduction anglaise (présentée comme telle sur le site)', $c['traduction_en'] ?? '', ['prefixe' => $p, 'texte' => true, 'aide' => 'À laisser vide si la citation est en anglais. Ses traductions dans les autres langues se saisissent sur l\'écran « Traductions ».'] + $tr)
        . champ_case('verifiee', 'Formulation vérifiée dans l\'article', $verifiee, ['prefixe' => $p])
        . '</div>';
};
?>
<p class="fil"><a href="<?= e(url('presse')) ?>">Revue de presse</a></p>
<div class="titre">
  <h1><?= e($titre) ?></h1>
  <?php if ($a): ?><span class="titre__suite"><?= e($a['media'] ?? '') ?> · <?= e($a['cle']) ?>
    <?= $a['publie'] ? '<span class="puce puce--ok">publié</span>' : '<span class="puce puce--alerte">dépublié</span>' ?><?= $a['accueil'] ? ' <span class="puce puce--accent">accueil</span>' : '' ?><?= $a['exemple'] ? ' <span class="puce puce--erreur">exemple de maquette</span>' : '' ?></span><?php endif; ?>
</div>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php foreach ($alertes as $m): ?><p class="message message--alerte"><?= e($m) ?></p><?php endforeach; ?>
<?php if ($a && $a['exemple']): ?><p class="message message--alerte">Cet article est un exemple inventé pour la maquette : il doit être supprimé avant la mise en production (écran Revue de presse).</p><?php endif; ?>
<?php if ($doublons): ?><p class="message message--alerte">Doublon probable : <?php foreach (array_slice($doublons, 0, 3) as $k => [$b, $raison]): ?><?= $k ? ' ; ' : '' ?><a href="<?= e(url('article', ['id' => $b['id']])) ?>"><?= e(($b['media'] ?? '') . ' « ' . preg_replace('/\*/', '', $b['titre_en']) . ' »') ?></a> (<?= e($raison) ?>)<?php endforeach; ?>.</p><?php endif; ?>

<?php if ($nouveau && function_exists('import_article_formulaire')) echo import_article_formulaire($propose); ?>

<div class="deux">
<div>
  <form method="post" class="carte" id="fiche">
    <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
    <input type="hidden" name="origine" value="<?= e($saisie ? recu('origine') : (string) ($propose['origine'] ?? '')) ?>">
    <?php if ($propose): ?><p class="message message--alerte">Formulaire prérempli par la lecture de l'article : rien n'est enregistré. Relisez chaque champ, corrigez, choisissez les citations, puis enregistrez.</p><?php endif; ?>
    <div class="colonnes">
      <?= champ_choix('media_id', 'Média', $medias_presse, $v('media_id'), ['vide' => '— choisir dans le référentiel —']) ?>
      <?= champ('media_neuf', 'ou un nouveau média', $saisie ? recu('media_neuf') : (string) ($propose['media_neuf'] ?? ''), ['max' => 190, 'aide' => 'Il entrera dans le référentiel des médias (annuaire) : logo, site, pays s\'y règlent ensuite.']) ?>
      <?= champ_choix('type', 'Type', TYPES_ARTICLE, $v('type'), ['vide' => '— à préciser —']) ?>
    </div>
    <input type="hidden" name="media_site" value="<?= e((string) ($propose['media_site'] ?? recu('media_site'))) ?>">
    <?= champ_tr('titre', 'Titre de l\'article', $v('titre_en'), ['requis' => true, 'aide' => '*…* pour l\'italique. Dans sa langue d\'origine s\'il ne se traduit pas.'] + $lg('titre')) ?>
    <?php foreach ($ia_tr as $champ => $par_langue): if (!in_array($champ, ['titre', 'chapeau', 'resume', 'infos'], true) || !is_array($par_langue)) continue; foreach ($par_langue as $langue => $t): if (!preg_match('/^[a-z]{2,3}$/', (string) $langue) || trim((string) $t) === '') continue; ?>
      <input type="hidden" name="ia_tr[<?= e($champ) ?>][<?= e($langue) ?>]" value="<?= e($t) ?>">
    <?php endforeach; endforeach; ?>
    <div class="colonnes">
      <?= champ('auteur', 'Auteur', $v('auteur'), ['max' => 190]) ?>
      <?= champ('date_article', 'Date de publication', $v('date_article'), ['max' => 10, 'indication' => '2026-02-13', 'aide' => 'AAAA-MM-JJ, ou AAAA-MM si le jour est inconnu.']) ?>
      <?= champ('langue', 'Langue d\'origine', $v('langue'), ['max' => 3, 'indication' => 'en', 'aide' => 'Code de la langue : fr, en, de…']) ?>
    </div>
    <?= champ_tr('chapeau', 'Chapeau', $v('chapeau_en'), ['texte' => true, 'aide' => 'Une ou deux phrases, montrées sur la vignette de l\'accueil sous le nom du média. Vide : le résumé en tient lieu.'] + $lg('chapeau')) ?>
    <?= champ_tr('resume', 'Résumé', $v('resume_en'), ['texte' => true, 'aide' => 'Montré dans le panneau détaillé. Un résumé n\'est jamais présenté comme une citation.'] + $lg('resume')) ?>
    <?= champ_tr('infos', 'Informations complémentaires', $v('infos_en'), ['texte' => true] + $lg('infos')) ?>
    <div class="colonnes">
      <?= champ('lien', 'Adresse de l\'article d\'origine', $v('lien'), ['type' => 'url', 'indication' => 'https://…', 'aide' => 'Sans adresse, « Lire l\'article » ne s\'affiche pas.']) ?>
      <?= champ('url_canonique', 'Adresse canonique', $v('url_canonique'), ['type' => 'url', 'indication' => 'https://…', 'aide' => 'Si le média en déclare une : sert à repérer les doublons.']) ?>
    </div>

    <h2>Citations</h2>
    <p class="aide aide--haut">Chaque citation est gardée dans sa langue et dans sa formulation d'origine. Sa traduction est à part : le site la présente comme une traduction, jamais comme la citation elle-même. La citation principale est celle de la vignette.</p>
    <div data-liste data-suivant="0"><div class="lignes" data-lignes>
      <?php foreach (array_values($f['citations']) as $i => $c) echo $ligne_citation($c, (string) $i); ?>
    </div><template data-modele><?= $ligne_citation([], '__i__') ?></template>
    <p><button type="button" class="bouton bouton--petit" data-ajouter>Ajouter une citation</button></p></div>

    <h2>Concerts et albums associés</h2>
    <div class="colonnes">
      <fieldset class="champ"><legend>Concerts</legend>
        <ul class="cases cases--defile">
          <?php foreach ($concerts as $c): ?>
            <li><label><input type="checkbox" name="concerts[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], array_map('intval', $f['concerts']), true) ? ' checked' : '' ?>> <?= e($c['titre_en']) ?> <span class="discret"><?= e(date_fr($c['premiere'])) ?><?= $c['publie'] ? '' : ' · dépublié' ?></span></label></li>
          <?php endforeach; ?>
        </ul>
        <p class="aide">Sur le site, le concert passé reçoit le lien « Revue de presse de ce concert », et l'article nomme ses concerts.</p>
      </fieldset>
      <fieldset class="champ"><legend>Albums</legend>
        <ul class="cases cases--defile">
          <?php foreach ($albums as $d): ?>
            <li><label><input type="checkbox" name="disques[]" value="<?= (int) $d['id'] ?>"<?= in_array((int) $d['id'], array_map('intval', $f['disques']), true) ? ' checked' : '' ?>> <?= e(preg_replace('/\*/', '', $d['titre'])) ?> <span class="discret"><?= e((string) $d['annee']) ?><?= $d['publie'] ? '' : ' · dépublié' ?></span></label></li>
          <?php endforeach; ?>
        </ul>
        <p class="aide">L'article entre dans « La critique » du panneau de l'album.</p>
      </fieldset>
    </div>

    <h2>Publication et mise en avant</h2>
    <div class="colonnes">
      <?= champ_case('publie', 'Publié : visible sur le site', !empty($f['publie'])) ?>
      <?= champ_case('une', 'À la une', !empty($f['une'])) ?>
      <?= champ_case('accueil', 'Sélectionné pour la page d\'accueil', !empty($f['accueil'])) ?>
    </div>
    <div class="colonnes">
      <?= champ('ordre_accueil', 'Rang sur l\'accueil', $v('ordre_accueil'), ['type' => 'number', 'aide' => 'Du plus petit au plus grand.']) ?>
      <?= champ_choix('largeur', 'Largeur sur l\'accueil', LARGEURS_ARTICLE, $v('largeur') ?: 1) ?>
    </div>
    <p class="aide">Trois réglages indépendants. Un article dépublié ne paraît nulle part, même sélectionné ou « à la une ».</p>
    <?php if ($nouveau && $erreurs && str_contains(implode(' ', $erreurs), 'doublon')): ?><?= champ_case('confirmer_doublon', 'Ce n\'est pas un doublon : créer l\'article', false) ?><?php endif; ?>
    <?php $image_proposee = $saisie ? recu('image_adresse') : (string) ($propose['image'] ?? ''); if ($nouveau && $image_proposee !== '' && adresse_valide($image_proposee)): ?>
    <h2>Image proposée par la page</h2>
    <input type="hidden" name="image_adresse" value="<?= e($image_proposee) ?>">
    <p><img src="<?= e($image_proposee) ?>" alt="Image proposée par la page de l'article" referrerpolicy="no-referrer" style="max-width:16rem;max-height:10rem;border-radius:4px"></p>
    <?= champ_case('image_garder', 'Garder cette image comme visuel de l\'article (par son adresse : rien n\'est téléchargé)', $saisie && !empty($_POST['image_garder']), ['aide' => 'Elle entre « droits à confirmer » et ne paraît sur le site qu\'une fois ses droits validés sur sa fiche, dans la médiathèque.']) ?>
    <?php endif; ?>
    <textarea name="texte_source" hidden><?= e($texte_source) ?></textarea>
    <div class="actions actions--bas">
      <button type="submit" class="bouton bouton--plein"><?= $nouveau ? 'Créer l\'article' : 'Enregistrer l\'article' ?></button>
      <a class="bouton" href="<?= e(url('presse')) ?>">Retour à la revue de presse</a>
    </div>
  </form>
  <?php if ($a): ?>
  <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer">
    <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Supprimer définitivement cet article, ses citations et ses associations ? Pour le retirer du site sans le perdre, dépubliez-le plutôt.">Supprimer cet article</button></form>
  <?php endif; ?>
</div>

<div>
  <?php if ($a): ?>
  <div class="carte" id="visuels">
    <h2>Visuels</h2>
    <?php $visuels = visuels_de('articles', $id); if (!$visuels): ?><p class="rien">Aucun visuel.</p><?php endif; ?>
    <?php foreach ($visuels as $i => $u): $m = $u['media']; ?>
      <form method="post" class="visuel-ligne">
        <?= champ_csrf() ?><input type="hidden" name="usage" value="<?= (int) $u['id'] ?>">
        <?= media_apercu($m, 'apercu-media apercu-media--petit') ?>
        <?php if ($m && (int) $m['droits_valides'] !== 1): ?><p><span class="puce puce--alerte">droits à confirmer : ne sort pas sur le site</span> <a href="<?= e(url('media', ['id' => $m['id']])) ?>">fiche du média</a></p><?php endif; ?>
        <?= champ_tr('alt', 'Texte alternatif', $u['alt_en'], ['prefixe' => 'v[' . $u['id'] . ']', 'objet' => ['medias_usages', $u['id'], 'alt']]) ?>
        <?= champ('credit', 'Crédit et source', $u['credit'], ['prefixe' => 'v[' . $u['id'] . ']', 'max' => 255, 'aide' => 'Vide : celui de la fiche du média.']) ?>
        <?= champ_case('publie', 'Publié', (bool) $u['publie'], ['prefixe' => 'v[' . $u['id'] . ']']) ?>
        <div class="actions">
          <button type="submit" name="visuel" value="regler" class="bouton bouton--petit">Enregistrer</button>
          <button type="submit" name="visuel" value="monter" class="outil" title="Monter" aria-label="Monter ce visuel"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
          <button type="submit" name="visuel" value="descendre" class="outil" title="Descendre" aria-label="Descendre ce visuel"<?= $i === count($visuels) - 1 ? ' disabled' : '' ?>>↓</button>
          <button type="submit" name="visuel" value="retirer" class="bouton bouton--petit bouton--danger" data-confirmer="Retirer ce visuel de l'article ? Le média reste dans la médiathèque.">Retirer</button>
        </div>
      </form>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="visuel-ajout">
      <?= champ_csrf() ?><input type="hidden" name="visuel" value="ajouter">
      <h3>Ajouter un visuel</h3>
      <?= champ_choix('media_id', 'Une image de la médiathèque', medias_choix(['image']), '', ['vide' => '— choisir —']) ?>
      <div class="champ"><label for="fichier">ou un fichier à déposer</label><input type="file" id="fichier" name="fichier" accept="image/*"></div>
      <?= champ('adresse', 'ou l\'adresse d\'une image', '', ['type' => 'url', 'indication' => 'https://…', 'aide' => 'Rien n\'est téléchargé. Une image venue d\'un site de presse entre « droits à confirmer » : elle ne paraît qu\'une fois ses droits validés sur sa fiche.']) ?>
      <?= champ('visuel_credit', 'Crédit et source', '', ['max' => 255]) ?>
      <?= champ('visuel_droits', 'Droits d\'utilisation', '', ['max' => 500]) ?>
      <button type="submit" class="bouton bouton--petit">Ajouter</button>
    </form>
  </div>
  <div class="carte">
    <h2>Associé à</h2>
    <ul class="items">
      <?php foreach ($a['concerts'] as $cid): $c = bd_ligne('SELECT id, titre_en FROM concerts WHERE id = ?', [$cid]); if (!$c) continue; ?><li><span class="discret">Concert</span><a href="<?= e(url('concert', ['id' => $c['id']])) ?>"><?= e($c['titre_en']) ?></a></li><?php endforeach; ?>
      <?php foreach ($a['disques'] as $did): $d = bd_ligne('SELECT id, titre FROM disques WHERE id = ?', [$did]); if (!$d) continue; ?><li><span class="discret">Album</span><a href="<?= e(url('disque', ['id' => $d['id']])) ?>"><?= e(preg_replace('/\*/', '', $d['titre'])) ?></a></li><?php endforeach; ?>
      <?php if (!$a['concerts'] && !$a['disques']): ?><li class="rien">Aucun concert, aucun album.</li><?php endif; ?>
    </ul>
  </div>
  <?php if ($a['media_id']): ?>
  <div class="carte">
    <h2>Le média</h2>
    <p><a href="<?= e(url('fiche', ['id' => $a['media_id']])) ?>"><?= e($a['media']) ?></a> : logo, site, pays, langue, traitement typographique.</p>
    <?php if ($a['logo_id']): ?><p><img src="<?= e(media_adresse((int) $a['logo_id'])) ?>" alt="Logo de <?= e($a['media']) ?>" style="max-height:3rem;background:#222;padding:.5rem;border-radius:4px"></p><?php else: ?><p class="aide">Sans logo, le site écrit son nom. Avec un logo, le nom n'est pas répété à l'écran : il reste lu par les lecteurs d'écran.</p><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="carte"><h2>Journal</h2><?= journal_afficher(journal_objet('article', $id, 15)) ?></div>
  <?php else: ?>
  <div class="carte"><h2>Visuels, journal</h2><p class="rien">Disponibles une fois l'article créé.</p></div>
  <?php endif; ?>
</div>
</div>

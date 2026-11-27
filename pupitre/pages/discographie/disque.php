<?php
/**
 * Un album : informations générales, contenus éditoriaux (présentation, contexte, notes
 * personnelles de l'artiste, ses citations, informations sur les œuvres), distinctions, liens
 * « Écouter » et « Commander », visuels, critiques (articles de la revue de presse associés),
 * publication. Les notes et les citations de l'artiste ne reçoivent que ce qu'il a fourni ou validé.
 */

defined('PUPITRE') or exit;

$nouveau = recu('id') === 'nouveau';
$id = $nouveau ? null : recu_entier('id');
$d = $id ? disque_lire($id) : null;
if (!$nouveau && !$d) {
    http_response_code(404);
    $titre = 'Album introuvable';
    echo '<div class="vide"><h1>Album introuvable</h1><p><a class="bouton" href="' . e(url('disques')) . '">Retour à la discographie</a></p></div>';
    return;
}
$erreurs = [];
$nom = $d ? preg_replace('/\*/', '', $d['titre']) : '';
$liste_distinctions = ['table' => 'distinctions', 'fixe' => ['disque_id' => $id], 'ajout' => 'Ajouter une distinction', 'champs' => distinction_champs(false)];

if (est_post() && $d && ($r = visuels_traiter('disques', $id, $nom)) !== null) {
    if ($r !== '') message('erreur', $r); else message('ok', 'Visuels de l\'album enregistrés.');
    rediriger(url('disque', ['id' => $id]) . '#visuels');
}
if (est_post() && $d && recu('action') === 'supprimer') {
    disque_supprimer($id);
    message('ok', 'Album supprimé.');
    rediriger(url('disques'));
}
if (est_post() && $d && recu('action') === 'distinctions') {
    $erreurs = liste_enregistrer('Distinctions de « ' . $nom . ' »', $liste_distinctions, is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : []);
    if (!$erreurs) { message('ok', 'Distinctions enregistrées.'); rediriger(url('disque', ['id' => $id]) . '#distinctions'); }
}
if (est_post() && $d && recu('action') === 'articles') {
    // critiques : les articles de la revue de presse associés à cet album
    $voulus = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['articles'] ?? null) ? $_POST['articles'] : []))));
    $avant = array_map('intval', bd_colonne('SELECT article_id FROM articles_disques WHERE disque_id = ?', [$id]));
    sort($voulus); sort($avant);
    if ($voulus !== $avant) {
        bd_requete('DELETE FROM articles_disques WHERE disque_id = ?', [$id]);
        foreach ($voulus as $x) if (bd_valeur('SELECT id FROM articles WHERE id = ?', [$x])) bd_requete('INSERT IGNORE INTO articles_disques (article_id, disque_id) VALUES (?, ?)', [$x, $id]);
        journaliser('modification', 'disque', $id, 'Album « ' . $nom . ' » : critiques associées', ['articles' => $avant], ['articles' => $voulus]);
    }
    message('ok', 'Critiques associées à l\'album.');
    rediriger(url('disque', ['id' => $id]) . '#critiques');
}
if (est_post() && recu('action') === 'enregistrer') {
    $t = fn(string $k, int $max = 255) => mb_substr(recu($k), 0, $max);
    $f = [
        'titre' => $t('titre'),
        'annee' => ctype_digit(recu('annee')) ? (int) recu('annee') : null,
        'date_sortie' => recu('date_sortie'),
        'maison_id' => recu_entier('maison_id'),
        'compositeurs' => implode("\n", lignes(recu('compositeurs'))),
        'ensemble' => $t('ensemble'),
        'ensemble_id' => recu('ensemble') !== '' ? orchestre_chercher(recu('ensemble'))['id'] : null,
        'lien' => $t('lien', 500),
        'publie' => empty($_POST['publie']) ? 0 : 1,
    ] + recu_bi($_POST, 'sous_titre', 255) + recu_bi($_POST, 'descriptif', 3000) + recu_bi($_POST, 'oeuvres', 5000) + recu_bi($_POST, 'interpretes', 5000)
      + recu_bi($_POST, 'presentation', 8000) + recu_bi($_POST, 'contexte', 8000) + recu_bi($_POST, 'mot', 8000) + recu_bi($_POST, 'citations', 5000) + recu_bi($_POST, 'infos', 8000);
    if ($f['titre'] === '') $erreurs[] = 'Le titre est obligatoire.';
    if (!date_souple_valide($f['date_sortie'])) $erreurs[] = 'Date de sortie : AAAA, AAAA-MM ou AAAA-MM-JJ.';
    if (recu('annee') !== '' && $f['annee'] === null) $erreurs[] = 'Année : un nombre (2026).';
    if (!adresse_valide($f['lien'])) $erreurs[] = 'La page de l\'album doit être une adresse complète (https://…).';
    if ($f['annee'] === null && $f['date_sortie'] !== '') $f['annee'] = (int) annee_de($f['date_sortie']);
    if (!$erreurs) {
        $id = bd_transaction(function () use ($id, $f, &$erreurs) {
            if ($id === null) {
                $f['cle'] = cle_unique('disques', $f['titre']);
                $f['ordre'] = (int) bd_valeur('SELECT COALESCE(MIN(ordre), 0) - 1 FROM disques');           // un nouvel album entre en tête : du plus récent au plus ancien
                $id = (int) objet_enregistrer('disques', null, $f, 'disque', preg_replace('/\*/', '', $f['titre']));
            } else {
                objet_enregistrer('disques', $id, $f, 'disque', preg_replace('/\*/', '', $f['titre']));
            }
            foreach (['ecoute', 'commande'] as $nature) {
                $erreurs = array_merge($erreurs, disque_liens_enregistrer($id, $nature, is_array($_POST['liens'][$nature] ?? null) ? $_POST['liens'][$nature] : []));
            }
            return $id;
        });
        if (!$erreurs) {
            message('ok', $nouveau ? 'Album créé.' : 'Album enregistré. Le site suivra à la prochaine publication.');
            rediriger(url('disque', ['id' => $id]));
        }
        $d = disque_lire($id);
        $nouveau = false;
    }
}

$saisie = est_post() && (bool) $erreurs && recu('action') === 'enregistrer';
$f = $d ?? ['titre' => '', 'annee' => '', 'date_sortie' => '', 'maison_id' => null, 'compositeurs' => '', 'ensemble' => '', 'lien' => '', 'publie' => 1, 'liens' => [], 'distinctions' => []];
if ($saisie) foreach ($_POST as $k => $v) if (is_string($v)) $f[$k] = $v;
if ($saisie) $f['publie'] = empty($_POST['publie']) ? 0 : 1;
$v = fn(string $k) => (string) ($f[$k] ?? '');
$lg = fn(string $champ) => ($saisie || !$id) ? ['valeurs' => saisie_bi($_POST, $champ)] : ['objet' => ['disques', $id, $champ]];
$titre = $nouveau ? 'Nouvel album' : $nom;
$maisons = [];
foreach (bd_lignes("SELECT id, nom FROM annuaire WHERE categorie = 'maison_de_disques' ORDER BY nom") as $m) $maisons[(int) $m['id']] = $m['nom'];
$plateformes = array_map(fn($p) => $p[0], PLATEFORMES);

/* Un lien d'écoute ou de commande : plateforme (qui choisit le pictogramme), nom affiché, adresse. */
$ligne_lien = function (string $nature, array $l, string $i) use ($plateformes): string {
    $p = 'liens[' . $nature . '][' . $i . ']';
    return '<div class="ligne ligne--compacte" data-ligne><div class="rangee rangee--lien">'
        . champ_choix('plateforme', $nature === 'ecoute' ? 'Plateforme' : 'Distributeur', $plateformes, $l['plateforme'] ?? '', ['prefixe' => $p, 'vide' => 'Autre (pictogramme simple)'])
        . champ('nom', 'Nom affiché', $l['nom'] ?? '', ['prefixe' => $p, 'max' => 120, 'indication' => 'vide : le nom de la plateforme'])
        . champ('url', 'Adresse', $l['url'] ?? '', ['prefixe' => $p, 'type' => 'url', 'indication' => 'https://…'])
        . boutons_ligne('ce lien') . '</div></div>';
};
$liens_de = function (string $nature) use ($f, $saisie): array {
    if ($saisie) return array_values(array_filter($_POST['liens'][$nature] ?? [], 'is_array'));
    return array_values(array_filter($f['liens'] ?? [], fn($l) => $l['nature'] === $nature));
};
$liste_liens = function (string $nature, string $ajout) use ($ligne_lien, $liens_de): string {
    $s = '<div data-liste data-suivant="0"><div class="lignes" data-lignes>';
    foreach ($liens_de($nature) as $i => $l) $s .= $ligne_lien($nature, $l, (string) $i);
    return $s . '</div><template data-modele>' . $ligne_lien($nature, [], '__i__') . '</template><p><button type="button" class="bouton bouton--petit" data-ajouter>' . e($ajout) . '</button></p></div>';
};
?>
<p class="fil"><a href="<?= e(url('disques')) ?>">Discographie</a></p>
<div class="titre">
  <h1><?= e($titre) ?></h1>
  <?php if ($d): ?><span class="titre__suite"><?= e($d['cle']) ?> <?= $d['publie'] ? '<span class="puce puce--ok">publié</span>' : '<span class="puce puce--alerte">dépublié</span>' ?></span><?php endif; ?>
</div>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?= liste_choix('liste-ensembles', annuaire_noms(['orchestre', 'ensemble'])) ?>

<div class="deux">
<div>
  <form method="post" class="carte" id="fiche">
    <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
    <h2>Informations générales</h2>
    <?= champ('titre', 'Titre', $v('titre'), ['requis' => true, 'aide' => '*…* pour l\'italique.']) ?>
    <?= champ_bi('sous_titre', 'Sous-titre (facultatif)', $v('sous_titre_fr'), '', $lg('sous_titre')) ?>
    <div class="colonnes">
      <?= champ_choix('maison_id', 'Label ou éditeur', $maisons, $v('maison_id'), ['vide' => '— aucun —', 'aide' => 'Fiches « maison de disques » de l\'annuaire.']) ?>
      <?= champ('annee', 'Année', $v('annee'), ['max' => 4, 'classe' => 'champ--court']) ?>
      <?= champ('date_sortie', 'Date de sortie', $v('date_sortie'), ['max' => 10, 'indication' => '2026-02-13', 'aide' => 'AAAA, AAAA-MM ou AAAA-MM-JJ.']) ?>
    </div>
    <?= champ_bi('descriptif', 'Lignes de la vignette', $v('descriptif_fr'), '', ['texte' => true, 'aide' => 'Sous le titre, sur la vignette : une ligne par ligne affichée (orchestre, soliste). Les distinctions ont leur propre rubrique.'] + $lg('descriptif')) ?>
    <div class="colonnes">
      <?= champ('ensemble', 'Orchestre ou ensemble', $v('ensemble'), ['liste' => 'liste-ensembles', 'aide' => 'Appellation officielle.']) ?>
      <?= champ_texte('compositeurs', 'Compositeurs', $v('compositeurs'), ['lignes' => 3, 'aide' => 'Un par ligne.']) ?>
    </div>
    <?= champ_bi('oeuvres', 'Œuvres enregistrées', $v('oeuvres_fr'), '', ['texte' => true, 'aide' => 'Une œuvre par ligne. *…* pour l\'italique.'] + $lg('oeuvres')) ?>
    <?= champ_bi('interpretes', 'Interprètes et collaborateurs', $v('interpretes_fr'), '', ['texte' => true, 'aide' => 'Un par ligne : nom, rôle ou instrument.'] + $lg('interpretes')) ?>

    <h2>Contenus éditoriaux</h2>
    <?= champ_bi('presentation', 'Présentation générale', $v('presentation_fr'), '', ['texte' => true, 'aide' => 'Texte de la rédaction. Un paragraphe par bloc, séparés par une ligne vide.'] + $lg('presentation')) ?>
    <?= champ_bi('contexte', 'Contexte artistique de l\'enregistrement', $v('contexte_fr'), '', ['texte' => true] + $lg('contexte')) ?>
    <?= champ_bi('mot', 'Notes personnelles de Pierre Bleuse', $v('mot_fr'), '', ['texte' => true, 'aide' => 'Le mot de l\'artiste, présenté comme tel, distinct de la présentation. Seulement ce qu\'il a écrit ou validé.'] + $lg('mot')) ?>
    <?= champ_bi('citations', 'Citations de Pierre Bleuse', $v('citations_fr'), '', ['texte' => true, 'aide' => 'Une par ligne, avec ses guillemets. Seulement des propos qu\'il a tenus ou validés.'] + $lg('citations')) ?>
    <?= champ_bi('infos', 'Informations complémentaires sur les œuvres', $v('infos_fr'), '', ['texte' => true] + $lg('infos')) ?>

    <h2>Écouter</h2>
    <p class="aide aide--haut">Une plateforme ne paraît sur le site que si son adresse est renseignée. La plateforme choisit le pictogramme.</p>
    <?= $liste_liens('ecoute', 'Ajouter une plateforme d\'écoute') ?>
    <h2>Commander</h2>
    <p class="aide aide--haut">Sans lien, la zone « Commander » ne s'affiche pas.</p>
    <?= $liste_liens('commande', 'Ajouter un lien de commande') ?>

    <h2>Page de l'album et publication</h2>
    <?= champ('lien', 'Page de l\'album chez l\'éditeur (facultative)', $v('lien'), ['type' => 'url', 'indication' => 'https://…', 'aide' => 'Proposée dans le panneau détaillé.']) ?>
    <?= champ_case('publie', 'Publié : l\'album figure dans la discographie du site', !empty($f['publie'])) ?>
    <div class="actions actions--bas">
      <button type="submit" class="bouton bouton--plein"><?= $nouveau ? 'Créer l\'album' : 'Enregistrer l\'album' ?></button>
      <a class="bouton" href="<?= e(url('disques')) ?>">Retour à la discographie</a>
    </div>
  </form>

  <?php if ($d): ?>
  <form method="post" class="carte" id="distinctions">
    <?= champ_csrf() ?><input type="hidden" name="action" value="distinctions">
    <h2>Distinctions et récompenses</h2>
    <p class="aide aide--haut">Seulement des distinctions vérifiées. « Mise en avant » : la distinction paraît dès la vignette de l'album, sur une ligne ; les autres sont dans le panneau détaillé.</p>
    <?php $lignes_d = ($erreurs && recu('action') === 'distinctions') ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : $d['distinctions']; ?>
    <?= liste_formulaire($liste_distinctions, $lignes_d, 'lignes', $erreurs && recu('action') === 'distinctions') ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer les distinctions</button></div>
  </form>
  <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer">
    <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Supprimer définitivement cet album, ses liens et ses distinctions ? Pour le retirer du site sans le perdre, dépubliez-le plutôt.">Supprimer cet album</button></form>
  <?php endif; ?>
</div>

<div>
  <?php if ($d): ?>
  <div class="carte" id="visuels">
    <h2>Visuels</h2>
    <p class="aide aide--haut">Le premier est le visuel principal. Plusieurs images : un petit diaporama dans le panneau de l'album, de dimensions réduites.</p>
    <?php $visuels = visuels_de('disques', $id); if (!$visuels): ?><p class="rien">Aucun visuel.</p><?php endif; ?>
    <?php foreach ($visuels as $i => $u): $m = $u['media']; ?>
      <form method="post" class="visuel-ligne">
        <?= champ_csrf() ?><input type="hidden" name="usage" value="<?= (int) $u['id'] ?>">
        <?= media_apercu($m, 'apercu-media apercu-media--petit') ?>
        <?php if ($i === 0): ?><p><span class="puce puce--accent">visuel principal</span></p><?php endif; ?>
        <?= champ_bi('alt', 'Texte alternatif', $u['alt_fr'], '', ['prefixe' => 'v[' . $u['id'] . ']', 'objet' => ['medias_usages', $u['id'], 'alt']]) ?>
        <?= champ('credit', 'Crédit', $u['credit'], ['prefixe' => 'v[' . $u['id'] . ']', 'max' => 255, 'aide' => 'Vide : celui de la fiche du média.']) ?>
        <?= champ_case('publie', 'Publié', (bool) $u['publie'], ['prefixe' => 'v[' . $u['id'] . ']']) ?>
        <div class="actions">
          <button type="submit" name="visuel" value="regler" class="bouton bouton--petit">Enregistrer</button>
          <button type="submit" name="visuel" value="monter" class="outil" title="Monter" aria-label="Monter ce visuel"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
          <button type="submit" name="visuel" value="descendre" class="outil" title="Descendre" aria-label="Descendre ce visuel"<?= $i === count($visuels) - 1 ? ' disabled' : '' ?>>↓</button>
          <button type="submit" name="visuel" value="retirer" class="bouton bouton--petit bouton--danger" data-confirmer="Retirer ce visuel de l'album ? Le média reste dans la médiathèque.">Retirer</button>
        </div>
      </form>
    <?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="visuel-ajout">
      <?= champ_csrf() ?><input type="hidden" name="visuel" value="ajouter">
      <h3>Ajouter un visuel</h3>
      <?= champ_choix('media_id', 'Une image de la médiathèque', medias_choix(['image']), '', ['vide' => '— choisir —']) ?>
      <div class="champ"><label for="fichier">ou un fichier à déposer</label><input type="file" id="fichier" name="fichier" accept="image/*"></div>
      <?= champ('adresse', 'ou l\'adresse d\'une image', '', ['type' => 'url', 'indication' => 'https://…', 'aide' => 'Rien n\'est téléchargé : le site lit l\'image à cette adresse.']) ?>
      <?= champ('visuel_credit', 'Crédit', '', ['max' => 255]) ?>
      <button type="submit" class="bouton bouton--petit">Ajouter</button>
    </form>
  </div>

  <form method="post" class="carte" id="critiques">
    <?= champ_csrf() ?><input type="hidden" name="action" value="articles">
    <h2>Critiques</h2>
    <p class="aide aide--haut">Les critiques d'un album sont des articles de la revue de presse, associés à l'album : il n'y a pas de collection de critiques à part.</p>
    <?php $lies = array_map('intval', bd_colonne('SELECT article_id FROM articles_disques WHERE disque_id = ?', [$id])); $tous = articles_lister(); ?>
    <label class="sr" for="chercher-article">Chercher un article</label>
    <input type="search" id="chercher-article" placeholder="chercher : média, titre, auteur" data-filtrer-cases="#articles-choix">
    <ul class="cases cases--defile" id="articles-choix">
      <?php foreach ($tous as $a): ?>
        <li data-texte="<?= e(mb_strtolower(($a['media'] ?? '') . ' ' . $a['titre_fr'] . ' ' . $a['auteur'])) ?>"><label><input type="checkbox" name="articles[]" value="<?= (int) $a['id'] ?>"<?= in_array((int) $a['id'], $lies, true) ? ' checked' : '' ?>> <?= e($a['media'] ?? '—') ?> <span class="discret"><?= e(couper(preg_replace('/\*/', '', $a['titre_fr']), 50)) ?><?= $a['publie'] ? '' : ' · dépublié' ?><?= $a['exemple'] ? ' · exemple' : '' ?></span></label></li>
      <?php endforeach; ?>
    </ul>
    <div class="actions">
      <button type="submit" class="bouton bouton--plein">Enregistrer les critiques associées</button>
      <a class="bouton" href="<?= e(url('article', ['id' => 'nouveau', 'disque' => $id])) ?>">Créer un article pour cet album</a>
    </div>
    <p class="aide">Un article créé d'ici entre dans la revue de presse générale, déjà associé à cet album.</p>
  </form>
  <div class="carte"><h2>Journal</h2><?= journal_afficher(journal_objet('disque', $id, 15)) ?></div>
  <?php else: ?>
  <div class="carte"><h2>Distinctions, visuels, critiques</h2><p class="rien">Disponibles une fois l'album créé.</p></div>
  <?php endif; ?>
</div>
</div>

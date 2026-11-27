<?php
/**
 * Revue de presse : la collection des articles. Recherche, filtres, publication, ordre de la
 * collection, sélection et composition de l'accueil (rang et largeur), suppression des faux
 * articles de maquette. La fiche d'un article est sur son écran (pages/presse/article.php).
 */

defined('PUPITRE') or exit;

$titre = 'Revue de presse';

if (est_post()) {
    $action = recu('action');
    $a = bd_ligne('SELECT * FROM articles WHERE id = ?', [recu_entier('id') ?? 0]);
    if ($action === 'exemples' && peut('site.publier')) {
        $n = articles_exemples_supprimer();
        message('ok', $n ? pluriel($n, 'faux article supprimé', 'faux articles supprimés') . ', avec leurs médias inventés. Le site suivra à la prochaine publication.' : 'Il n\'y avait plus de faux article.');
        rediriger(url('presse'));
    }
    if ($a && in_array($action, ['publier', 'depublier'], true)) {
        objet_enregistrer('articles', (int) $a['id'], ['publie' => $action === 'publier' ? 1 : 0], 'article', preg_replace('/\*/', '', $a['titre_fr']) ?: $a['cle']);
    } elseif ($a && in_array($action, ['monter', 'descendre'], true)) {
        $ordre = array_map('intval', bd_colonne('SELECT id FROM articles ORDER BY ordre, id'));
        $i = array_search((int) $a['id'], $ordre, true);
        $j = $i + ($action === 'monter' ? -1 : 1);
        if ($i !== false && $j >= 0 && $j < count($ordre)) {
            [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
            foreach ($ordre as $rang => $x) bd_modifier('articles', ['ordre' => $rang], ['id' => $x]);
        }
    } elseif ($action === 'accueil') {
        // composition de l'accueil : pour chaque article, sélection, rang et largeur
        $avant = $apres = [];
        foreach (is_array($_POST['art'] ?? null) ? $_POST['art'] : [] as $id => $r) {
            $x = bd_ligne('SELECT id, cle, accueil, ordre_accueil, largeur FROM articles WHERE id = ?', [(int) $id]);
            if (!$x || !is_array($r)) continue;
            $d = ['accueil' => empty($r['accueil']) ? 0 : 1, 'ordre_accueil' => max(0, min(999, (int) ($r['rang'] ?? 0))), 'largeur' => isset(LARGEURS_ARTICLE[(int) ($r['largeur'] ?? 1)]) ? (int) $r['largeur'] : 1];
            if ($d != ['accueil' => (int) $x['accueil'], 'ordre_accueil' => (int) $x['ordre_accueil'], 'largeur' => (int) $x['largeur']]) {
                $avant[$x['cle']] = ['accueil' => (int) $x['accueil'], 'rang' => (int) $x['ordre_accueil'], 'largeur' => (int) $x['largeur']];
                $apres[$x['cle']] = ['accueil' => $d['accueil'], 'rang' => $d['ordre_accueil'], 'largeur' => $d['largeur']];
                bd_modifier('articles', $d, ['id' => $x['id']]);
            }
        }
        if ($apres) journaliser('modification', 'article', null, 'Revue de presse : composition de l\'accueil', $avant, $apres);
        message('ok', $apres ? 'Composition de l\'accueil enregistrée. Le site suivra à la prochaine publication.' : 'Rien n\'a changé.');
        rediriger(url('presse') . '#accueil');
    }
    rediriger(url('presse', array_filter(['q' => recu('q'), 'type' => recu('type'), 'etat' => recu('etat')], 'strlen')) . ($a ? '#a-' . $a['id'] : ''));
}

$filtres = ['q' => recu('q'), 'type' => isset(TYPES_ARTICLE[recu('type')]) ? recu('type') : '', 'etat' => recu('etat')];
$articles = articles_lister($filtres);
$tous = articles_lister();
$accueil = array_values(array_filter($tous, fn($a) => $a['accueil']));
usort($accueil, fn($x, $y) => [(int) $x['ordre_accueil'], (int) $x['id']] <=> [(int) $y['ordre_accueil'], (int) $y['id']]);
$exemples = count(array_filter($tous, fn($a) => $a['exemple']));
$titre_article = fn(array $a) => preg_replace('/\*/', '', $a['titre_fr']) ?: ('(sans titre) ' . $a['cle']);
$colonnes = 0;
?>
<div class="titre">
  <h1>Revue de presse</h1><span class="titre__suite">data/presse.js · <?= e(pluriel(count($tous), 'article', 'articles')) ?>, <?= count(array_filter($tous, fn($a) => $a['publie'])) ?> publiés, <?= count($accueil) ?> sur l'accueil</span>
  <div class="titre__actions">
    <a class="bouton" href="<?= e(url('article', ['id' => 'nouveau', 'import' => '1'])) ?>">Importer depuis une adresse</a>
    <a class="bouton bouton--plein" href="<?= e(url('article', ['id' => 'nouveau'])) ?>">Nouvel article</a>
  </div>
</div>
<p class="intro">Chaque article est une fiche unique : l'accueil, la page Presse, les fiches de concerts et les panneaux d'albums montrent tous le même. Publier, mettre sur l'accueil et « à la une » sont trois réglages indépendants : un article dépublié ne paraît nulle part, même sélectionné.</p>

<?php if ($exemples): ?>
<div class="carte carte--alerte">
  <h2>Faux articles de maquette</h2>
  <p><?= e(pluriel($exemples, 'article est un exemple inventé', 'articles sont des exemples inventés')) ?> (médias, auteurs, citations), gardés pour juger la mise en page. Ils portent la marque <span class="puce puce--erreur">exemple</span> et doivent disparaître avant la mise en production.</p>
  <?php if (peut('site.publier')): ?>
  <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="exemples">
    <button type="submit" class="bouton bouton--danger" data-confirmer="Supprimer définitivement les <?= (int) $exemples ?> faux articles de maquette et leurs médias inventés ?">Supprimer tous les faux articles</button></form>
  <?php else: ?><p class="aide">Leur suppression revient au pilote.</p><?php endif; ?>
</div>
<?php endif; ?>

<form method="post" class="carte" id="accueil">
  <?= champ_csrf() ?><input type="hidden" name="action" value="accueil">
  <h2>Composition de l'accueil</h2>
  <p class="aide aide--haut">Les articles sélectionnés paraissent dans l'ordre de leur rang, sur une grille de quatre colonnes : un article en occupe une, deux ou les quatre. Aperçu de la grille ci-dessous.</p>
  <div class="grille-presse" aria-hidden="true">
    <?php foreach ($accueil as $a): if (!$a['publie']) continue; ?>
      <div class="grille-presse__case grille-presse__case--<?= (int) $a['largeur'] ?><?= $a['exemple'] ? ' grille-presse__case--exemple' : '' ?>"><?= e(couper(($a['media'] ?? '') . ' · ' . preg_replace('/[«»“”"]/u', '', (string) ($a['citation'] ?? $a['titre_fr'])), 70)) ?></div>
    <?php endforeach; ?>
  </div>
  <div class="tableau-defile"><table class="tableau tableau--serre">
    <thead><tr><th>Sur l'accueil</th><th>Rang</th><th>Largeur</th><th>Article</th><th>État</th></tr></thead>
    <tbody>
    <?php foreach ($tous as $a): ?>
      <tr>
        <td><label><input type="checkbox" name="art[<?= (int) $a['id'] ?>][accueil]" value="1"<?= $a['accueil'] ? ' checked' : '' ?>> <span class="sr">Mettre « <?= e($titre_article($a)) ?> » sur l'accueil</span></label></td>
        <td><label class="sr" for="rang-<?= (int) $a['id'] ?>">Rang</label><input type="number" id="rang-<?= (int) $a['id'] ?>" name="art[<?= (int) $a['id'] ?>][rang]" value="<?= (int) $a['ordre_accueil'] ?>" min="0" max="999" class="champ-nombre"></td>
        <td><label class="sr" for="larg-<?= (int) $a['id'] ?>">Largeur</label><select id="larg-<?= (int) $a['id'] ?>" name="art[<?= (int) $a['id'] ?>][largeur]"><?php foreach (LARGEURS_ARTICLE as $n => $l): ?><option value="<?= $n ?>"<?= (int) $a['largeur'] === $n ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
        <th scope="row"><a href="<?= e(url('article', ['id' => $a['id']])) ?>"><?= e($a['media'] ?? '—') ?></a> <span class="discret"><?= e(couper($titre_article($a), 60)) ?></span></th>
        <td><?= $a['publie'] ? '' : '<span class="puce puce--alerte">dépublié : ne paraîtra pas</span>' ?><?= $a['exemple'] ? ' <span class="puce puce--erreur">exemple</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer la composition de l'accueil</button></div>
</form>

<form method="get" class="filtres">
  <input type="hidden" name="page" value="presse">
  <?= champ('q', 'Chercher', $filtres['q'], ['type' => 'search', 'indication' => 'média, titre, auteur, citation']) ?>
  <?= champ_choix('type', 'Type', TYPES_ARTICLE, $filtres['type'], ['vide' => 'Tous']) ?>
  <?= champ_choix('etat', 'État', ['publies' => 'Publiés', 'depublies' => 'Dépubliés', 'accueil' => 'Sur l\'accueil', 'exemples' => 'Exemples de maquette'], $filtres['etat'], ['vide' => 'Tous']) ?>
  <button type="submit" class="bouton">Filtrer</button>
</form>

<?php if (!$articles): ?><div class="carte vide"><p>Aucun article pour ces filtres.</p></div><?php else: ?>
<div class="carte">
  <h2>La collection <span class="discret">dans l'ordre de la page Presse</span></h2>
  <div class="tableau-defile"><table class="tableau tableau--large">
    <thead><tr><th>Média</th><th>Article</th><th>Date</th><th>Liens</th><th>État</th><th><span class="sr">Agir</span></th></tr></thead>
    <tbody>
    <?php foreach ($articles as $i => $a): $nc = (int) bd_valeur('SELECT COUNT(*) FROM articles_concerts WHERE article_id = ?', [$a['id']]); $nd = (int) bd_valeur('SELECT COUNT(*) FROM articles_disques WHERE article_id = ?', [$a['id']]); ?>
      <tr id="a-<?= (int) $a['id'] ?>"<?= $a['publie'] ? '' : ' class="passee"' ?>>
        <td><?= e($a['media'] ?? '—') ?></td>
        <th scope="row"><a href="<?= e(url('article', ['id' => $a['id']])) ?>"><?= e($titre_article($a)) ?></a>
          <?php if ($a['type'] !== ''): ?> <span class="puce"><?= e(TYPES_ARTICLE[$a['type']] ?? $a['type']) ?></span><?php endif; ?>
          <?php if (trim((string) $a['citation']) !== ''): ?><br><span class="discret"><?= e(couper((string) $a['citation'], 110)) ?></span><?php endif; ?>
          <?php if ($a['auteur'] !== ''): ?><br><span class="discret"><?= e($a['auteur']) ?></span><?php endif; ?></th>
        <td class="date"><?= e($a['date_article']) ?: '<span class="discret">—</span>' ?></td>
        <td class="discret"><?= $nc ? e(pluriel($nc, 'concert', 'concerts')) : '' ?><?= $nc && $nd ? ', ' : '' ?><?= $nd ? e(pluriel($nd, 'album', 'albums')) : '' ?><?= $a['lien'] !== '' ? '' : (($nc || $nd) ? '<br>' : '') . 'sans adresse' ?></td>
        <td class="tableau__etats"><?= $a['publie'] ? '<span class="puce puce--ok">publié</span>' : '<span class="puce puce--alerte">dépublié</span>' ?>
          <?= $a['accueil'] ? '<span class="puce puce--accent">accueil</span>' : '' ?><?= $a['une'] ? ' <span class="puce puce--accent">à la une</span>' : '' ?><?= $a['exemple'] ? ' <span class="puce puce--erreur">exemple</span>' : '' ?></td>
        <td class="tableau__actions">
          <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <?php foreach ($filtres as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
            <?php if (!array_filter($filtres)): ?>
            <button type="submit" name="action" value="monter" class="outil" title="Monter" aria-label="Monter cet article"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
            <button type="submit" name="action" value="descendre" class="outil" title="Descendre" aria-label="Descendre cet article"<?= $i === count($articles) - 1 ? ' disabled' : '' ?>>↓</button>
            <?php endif; ?>
            <button type="submit" name="action" value="<?= $a['publie'] ? 'depublier' : 'publier' ?>" class="bouton bouton--petit"><?= $a['publie'] ? 'Dépublier' : 'Publier' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<p class="aide">Les médias de presse (nom, logo, site, pays, langue) sont des fiches de l'<a href="<?= e(url('annuaire', ['categorie' => 'media'])) ?>">annuaire</a> : un média ne se saisit qu'une fois.</p>

<?= apercu_zones(['PRESSE' => 'Accueil']) ?>

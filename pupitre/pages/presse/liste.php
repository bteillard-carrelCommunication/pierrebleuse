<?php
/**
 * Revue de presse : la collection des articles. Recherche, filtres, publication, ordre de la
 * collection, composition de l'accueil, suppression des faux articles de maquette. La fiche d'un
 * article est sur son écran (pages/presse/article.php).
 *
 * La composition de l'accueil se fait sur la grille elle-même : on fait glisser un article pour
 * changer sa place (ou on emploie ses flèches), on choisit sa largeur ; le rang se déduit de la
 * place et la grille se redessine à chaque geste. Tout s'enregistre au fur et à mesure
 * (ajax/presse.php) ; sans script, « Ajouter » et « Retirer » restent de simples formulaires.
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
        objet_enregistrer('articles', (int) $a['id'], ['publie' => $action === 'publier' ? 1 : 0], 'article', preg_replace('/\*/', '', $a['titre_en']) ?: $a['cle']);
    } elseif ($a && in_array($action, ['monter', 'descendre'], true)) {
        $ordre = array_map('intval', bd_colonne('SELECT id FROM articles ORDER BY ordre, id'));
        $i = array_search((int) $a['id'], $ordre, true);
        $j = $i + ($action === 'monter' ? -1 : 1);
        if ($i !== false && $j >= 0 && $j < count($ordre)) {
            [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
            foreach ($ordre as $rang => $x) bd_modifier('articles', ['ordre' => $rang], ['id' => $x]);
        }
    } elseif ($a && in_array($action, ['accueil_ajouter', 'accueil_retirer'], true)) {
        // l'accueil : un article y entre en dernière place, ou le quitte ; les rangs se resserrent d'eux-mêmes
        $ordre = array_map(fn($x) => (int) $x['id'], accueil_articles(articles_lister()));
        $ordre = array_values(array_diff($ordre, [(int) $a['id']]));
        if ($action === 'accueil_ajouter') $ordre[] = (int) $a['id'];
        accueil_composer($ordre);
        message('ok', ($action === 'accueil_ajouter' ? 'Article ajouté à l\'accueil, en dernière place.' : 'Article retiré de l\'accueil.') . ' Le site suivra à la prochaine publication.');
        rediriger(url('presse') . '#accueil');
    }
    rediriger(url('presse', array_filter(['q' => recu('q'), 'type' => recu('type'), 'etat' => recu('etat')], 'strlen')) . ($a ? '#a-' . $a['id'] : ''));
}

$filtres = ['q' => recu('q'), 'type' => isset(TYPES_ARTICLE[recu('type')]) ? recu('type') : '', 'etat' => recu('etat')];
$articles = articles_lister($filtres);
$tous = articles_lister();
$accueil = accueil_articles($tous);
$hors_accueil = array_values(array_filter($tous, fn($a) => !$a['accueil']));
$exemples = count(array_filter($tous, fn($a) => $a['exemple']));
$titre_article = fn(array $a) => preg_replace('/\*/', '', $a['titre_en']) ?: ('(sans titre) ' . $a['cle']);
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

<section class="carte" id="accueil" aria-labelledby="t-accueil" data-composition>
  <div class="carte__tete"><h2 id="t-accueil">Composition de l'accueil</h2><span class="carte__compte" data-composition-compte><?= count($accueil) ?></span>
    <span class="carte__suite discret" data-composition-etat role="status" aria-live="polite"></span></div>
  <p class="aide aide--haut">La grille ci-dessous est celle de l'accueil du site : quatre colonnes, un article en occupe une, deux ou les quatre. Faites glisser un article pour changer sa place (ou employez ses flèches), choisissez sa largeur : le numéro d'ordre suit, la grille se redessine, et tout s'enregistre aussitôt.</p>
  <div class="grille-presse" data-glisser="accueil" data-composition-grille role="list" aria-label="Articles de l'accueil, dans l'ordre">
    <?php foreach ($accueil as $i => $a): $nom_a = ($a['media'] ?? '—') . ' · ' . couper($titre_article($a), 40); ?>
      <div class="grille-presse__case grille-presse__case--<?= (int) $a['largeur'] ?><?= $a['exemple'] ? ' grille-presse__case--exemple' : '' ?><?= $a['publie'] ? '' : ' grille-presse__case--depublie' ?>" role="listitem" data-glisser-element data-id="<?= (int) $a['id'] ?>">
        <div>
          <span class="grille-presse__media"><?= e($a['media'] ?? '—') ?></span>
          <?php if (!$a['publie']): ?> <span class="puce puce--alerte">dépublié : ne paraîtra pas</span><?php endif; ?><?php if ($a['exemple']): ?> <span class="puce puce--erreur">exemple</span><?php endif; ?>
          <span class="grille-presse__texte"><?= e(couper(preg_replace('/[«»“”"]/u', '', (string) ($a['citation'] ?: $titre_article($a))), 120)) ?></span>
        </div>
        <div class="grille-presse__pied">
          <span class="grille-presse__rang" data-rang title="Numéro d'ordre : il suit la place de l'article"><?= $i + 1 ?></span>
          <label class="sr" for="larg-<?= (int) $a['id'] ?>">Largeur de « <?= e($nom_a) ?> »</label>
          <select id="larg-<?= (int) $a['id'] ?>" data-largeur><?php foreach ([1 => '1 col.', 2 => '2 col.', 4 => '4 col.'] as $n => $l): ?><option value="<?= $n ?>"<?= (int) $a['largeur'] === $n ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          <button type="button" class="outil" data-glisser-avant title="Avancer d'une place" aria-label="Avancer « <?= e($nom_a) ?> » d'une place"><?= icone('chevron-gauche') ?></button>
          <button type="button" class="outil" data-glisser-apres title="Reculer d'une place" aria-label="Reculer « <?= e($nom_a) ?> » d'une place"><?= icone('chevron-droite') ?></button>
          <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button type="submit" name="action" value="accueil_retirer" class="outil outil--retirer" title="Retirer de l'accueil" aria-label="Retirer « <?= e($nom_a) ?> » de l'accueil"><?= icone('fermer') ?></button></form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$accueil): ?><p class="grille-presse__vide">Aucun article sur l'accueil : ajoutez-en ci-dessous.</p><?php endif; ?>
  </div>
  <details<?= $accueil ? '' : ' open' ?>>
    <summary>Ajouter un article à l'accueil (<?= count($hors_accueil) ?> disponibles)</summary>
    <?php if (!$hors_accueil): ?><p class="rien">Tous les articles sont sur l'accueil.</p><?php else: ?>
    <ul class="items">
      <?php foreach ($hors_accueil as $a): ?>
        <li><span><a class="item__titre" href="<?= e(url('article', ['id' => $a['id']])) ?>"><?= e($a['media'] ?? '—') ?></a> <span class="discret"><?= e(couper($titre_article($a), 70)) ?></span><?= $a['publie'] ? '' : ' <span class="puce puce--alerte">dépublié</span>' ?><?= $a['exemple'] ? ' <span class="puce puce--erreur">exemple</span>' : '' ?></span>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button type="submit" name="action" value="accueil_ajouter" class="bouton bouton--petit"><?= icone('plus') ?>Ajouter</button></form></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </details>
</section>

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

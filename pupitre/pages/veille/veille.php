<?php
/**
 * Veille : ce qui se publie sur l'artiste. Les signalements arrivent « à lire » (flux d'actualités,
 * recherche de l'IA, ajout à la main) ; chacun se retient (il peut devenir un article de la revue de
 * presse), se partage (une action de communication est créée, qui le cite) ou s'écarte.
 *
 * Réglages, sur l'écran même : les recherches (une par ligne) et les éditions d'actualités.
 */

defined('PUPITRE') or exit;

$titre = 'Veille';
$retour = fn() => url('veille', array_filter(['statut' => recu('f_statut'), 'source' => recu('f_source'), 'mention' => recu('f_mention')], 'strlen'));

if (est_post()) {
    $action = recu('action');
    $v = bd_ligne('SELECT * FROM veille WHERE id = ?', [recu_entier('id') ?? 0]);
    if ($action === 'collecter') {
        $tache = tache_creer('veille', ['jours' => 30, 'ia' => !empty($_POST['ia'])]);
        if (!taches_lancer_en_fond()) message('alerte', 'La collecte attend le prochain passage des tâches de fond (cron).');
        rediriger(url('veille', ['tache' => $tache]));
    } elseif ($action === 'ajouter') {
        [$id, $erreur] = veille_ajouter(recu('adresse'), recu('titre'));
        if ($id) journaliser('creation', 'veille', $id, 'Signalement ajouté à la main', null, ['adresse' => recu('adresse')]);
        message($id ? 'ok' : 'erreur', $id ? 'Signalement ajouté à la veille.' : $erreur);
    } elseif ($action === 'reglages' && peut('reglages')) {
        $avant = ['recherches' => reglage('veille_requetes'), 'editions' => reglage('veille_editions')];
        $apres = ['recherches' => implode("\n", array_slice(lignes(recu('requetes')), 0, 8)), 'editions' => implode(',', array_values(array_intersect(array_keys(EDITIONS_VEILLE), is_array($_POST['editions'] ?? null) ? $_POST['editions'] : [])))];
        reglage_ecrire('veille_requetes', $apres['recherches']);
        reglage_ecrire('veille_editions', $apres['editions']);
        journaliser('modification', 'reglages', null, 'Réglages : veille', $avant, $apres);
        message('ok', 'Réglages de la veille enregistrés.');
        rediriger(url('veille') . '#reglages');
    } elseif ($v && $action === 'article') {
        // retenir : la lecture de l'article est lancée, son formulaire s'ouvre prérempli
        veille_decider((int) $v['id'], 'retenue');
        $tache = tache_creer('article_lecture', ['url' => $v['lien'], 'texte' => '']);
        if (!taches_lancer_en_fond()) message('alerte', 'La lecture attend le prochain passage des tâches de fond (cron).');
        rediriger(url('article', ['id' => 'nouveau', 'lecture' => $tache]) . '#lire');
    } elseif ($v && $action === 'partager' && peut('communication')) {
        rediriger(url('communication', ['id' => 'nouvelle', 'veille' => $v['id']]));
    } elseif ($v && in_array($action, ['retenue', 'ecartee', 'a_lire'], true)) {
        veille_decider((int) $v['id'], $action);
    } elseif ($v && $action === 'mention' && isset(MENTIONS_VEILLE[recu('mention')])) {
        bd_modifier('veille', ['mention' => recu('mention'), 'note' => mb_substr(recu('note'), 0, 500)], ['id' => $v['id']]);
    } elseif ($action === 'tout_ecarter') {
        $n = 0;
        foreach (bd_colonne("SELECT id FROM veille WHERE statut = 'a_lire' AND cree_le < ?", [date('Y-m-d H:i:s', time() - 30 * 86400)]) as $x) { veille_decider((int) $x, 'ecartee'); $n++; }
        message('ok', $n ? pluriel($n, 'signalement écarté', 'signalements écartés') . '.' : 'Aucun signalement de plus de trente jours n\'attendait.');
    }
    rediriger($retour() . ($v ? '#v-' . $v['id'] : ''));
}

$decompte = veille_decompte();
$filtres = ['statut' => isset(STATUTS_VEILLE[recu('statut')]) ? recu('statut') : (recu('statut') === 'tout' ? '' : 'a_lire'), 'source' => isset(SOURCES_VEILLE[recu('source')]) ? recu('source') : '',
    'mention' => isset(MENTIONS_VEILLE[recu('mention')]) ? recu('mention') : '', 'q' => recu('q')];
$signalements = veille_lister($filtres);
$tache = recu_entier('tache') ? tache_lire(recu_entier('tache')) : null;
$derniere = reglage('veille_le');
$adresse = fn(array $p) => url('veille', array_filter($p + ['statut' => $filtres['statut'] === 'a_lire' ? '' : ($filtres['statut'] ?: 'tout'), 'source' => $filtres['source'], 'mention' => $filtres['mention']], 'strlen'));
$cachees = '<input type="hidden" name="f_statut" value="' . e($filtres['statut'] === 'a_lire' ? '' : ($filtres['statut'] ?: 'tout')) . '"><input type="hidden" name="f_source" value="' . e($filtres['source']) . '"><input type="hidden" name="f_mention" value="' . e($filtres['mention']) . '">';
?>
<div class="titre">
  <h1>Veille</h1>
  <span class="titre__suite"><?= e(pluriel($decompte['a_lire'], 'signalement à lire', 'signalements à lire')) ?><?= $derniere !== '' ? ' · dernière collecte ' . e(il_y_a($derniere)) : '' ?></span>
  <div class="titre__actions">
    <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="action" value="collecter">
      <?php if (ia_configuree()): ?><label class="en-ligne" style="font-size:var(--t-sm)"><input type="checkbox" name="ia" value="1" checked> avec la recherche de l'IA</label><?php endif; ?>
      <button type="submit" class="bouton bouton--plein"><?= icone('veille') ?>Collecter maintenant</button></form>
  </div>
</div>
<p class="intro">Ce qui se publie sur <?= e(reglage('artiste_nom') ?: 'l\'artiste') ?> : presse en ligne, sites de salles et d'orchestres, publications visibles des réseaux. Chaque signalement se lit, puis se retient, se partage ou s'écarte ; ce que vous en faites reste tracé.</p>
<?php if ($tache): ?><div class="carte"><?= suivi_tache($tache) ?></div><?php endif; ?>

<nav class="onglets" aria-label="Signalements par état">
  <?php foreach (STATUTS_VEILLE as $s => [$libelle]): ?>
    <a href="<?= e(url('veille', array_filter(['statut' => $s === 'a_lire' ? '' : $s]))) ?>"<?= $filtres['statut'] === $s ? ' aria-current="page"' : '' ?>><?= e($libelle) ?> <span class="carte__compte"><?= (int) $decompte[$s] ?></span></a>
  <?php endforeach; ?>
  <a href="<?= e(url('veille', ['statut' => 'tout'])) ?>"<?= $filtres['statut'] === '' ? ' aria-current="page"' : '' ?>>Tout</a>
</nav>

<div class="deux">
<div>
  <form method="get" class="filtres">
    <input type="hidden" name="page" value="veille"><input type="hidden" name="statut" value="<?= e($filtres['statut'] === 'a_lire' ? '' : ($filtres['statut'] ?: 'tout')) ?>">
    <?= champ_choix('source', 'Source', SOURCES_VEILLE, $filtres['source'], ['vide' => 'Toutes']) ?>
    <?= champ_choix('mention', 'L\'artiste y est', array_map(fn($m) => $m[0], MENTIONS_VEILLE), $filtres['mention'], ['vide' => 'Indifférent']) ?>
    <?= champ('q', 'Chercher', $filtres['q'], ['type' => 'search', 'indication' => 'titre, média, extrait']) ?>
    <button type="submit" class="bouton">Filtrer</button>
  </form>

  <?php if (!$signalements): ?>
    <div class="carte"><?= etat_vide('veille', $derniere === '' ? 'La veille n\'a pas encore tourné' : 'Rien ici', $derniere === '' ? 'Lancez une première collecte : les trente derniers jours seront parcourus.' : 'Aucun signalement pour cet état et ces filtres.') ?></div>
  <?php endif; ?>
  <?php foreach ($signalements as $v): ?>
    <article class="carte signalement" id="v-<?= (int) $v['id'] ?>">
      <p class="en-ligne">
        <span class="puce <?= STATUTS_VEILLE[$v['statut']][1] ?>"><?= e(mb_strtolower(STATUTS_VEILLE[$v['statut']][0])) ?></span>
        <span class="etiquette etiquette--<?= $v['nature'] === 'presse' ? 'presse' : ($v['nature'] === 'social' ? 'social' : 'web') ?>"><?= e(NATURES_VEILLE[$v['nature']] ?? $v['nature']) ?></span>
        <span class="puce <?= MENTIONS_VEILLE[$v['mention']][1] ?>"><?= e(mb_strtolower(MENTIONS_VEILLE[$v['mention']][0])) ?></span>
        <span class="discret"><?= $v['paru_le'] ? e(date_fr($v['paru_le'])) : 'date inconnue' ?> · <?= e(mb_strtolower(SOURCES_VEILLE[$v['source']] ?? $v['source'])) ?><?= $v['langue'] !== '' ? ' · ' . e(strtoupper($v['langue'])) : '' ?></span>
      </p>
      <h2 class="signalement__titre"><a href="<?= e($v['lien']) ?>" target="_blank" rel="noopener noreferrer"><?= e($v['titre']) ?></a></h2>
      <p class="discret"><?= e($v['site'] ?: preg_replace('/^www\./', '', (string) parse_url($v['lien'], PHP_URL_HOST))) ?><?= $v['auteur'] !== '' ? ' · ' . e($v['auteur']) : '' ?></p>
      <?php if (trim((string) $v['extrait']) !== ''): ?><p><?= e($v['extrait']) ?></p><?php endif; ?>
      <?php if ($v['note'] !== ''): ?><p class="discret">Note : <?= e($v['note']) ?></p><?php endif; ?>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><?= $cachees ?>
        <?php if (peut('contenus') && !$v['article_id']): ?><button type="submit" name="action" value="article" class="bouton bouton--petit bouton--doux" title="Retenir ce signalement et ouvrir la fiche d'un article de presse, préremplie"><?= icone('presse') ?>En faire un article</button><?php endif; ?>
        <?php if (peut('communication')): ?><button type="submit" name="action" value="partager" class="bouton bouton--petit"><?= icone('partage') ?>Partager</button><?php endif; ?>
        <?php if ($v['statut'] !== 'retenue'): ?><button type="submit" name="action" value="retenue" class="bouton bouton--petit">Retenir</button><?php endif; ?>
        <?php if ($v['statut'] !== 'ecartee'): ?><button type="submit" name="action" value="ecartee" class="bouton bouton--petit">Écarter</button>
        <?php else: ?><button type="submit" name="action" value="a_lire" class="bouton bouton--petit">Remettre à lire</button><?php endif; ?>
        <?php if ($v['communication_id']): ?><a class="bouton bouton--petit" href="<?= e(url('communication', ['id' => $v['communication_id']])) ?>">Voir le partage</a><?php endif; ?>
      </form>
      <details class="repli"><summary>Identifié ou seulement cité ? Une note ?</summary>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="mention"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><?= $cachees ?>
          <div class="colonnes">
            <?= champ_choix('mention', 'L\'artiste y est', array_map(fn($m) => $m[0], MENTIONS_VEILLE), $v['mention'], ['aide' => '« Identifié » : la page mène à son site ou à l\'un de ses comptes. « Cité » : elle le nomme seulement.']) ?>
            <?= champ('note', 'Note', $v['note'], ['max' => 500]) ?>
          </div>
          <button type="submit" class="bouton bouton--petit">Enregistrer</button></form></details>
    </article>
  <?php endforeach; ?>
  <?php if ($filtres['statut'] === 'a_lire' && count($signalements) > 20): ?>
    <form method="post" class="actions"><?= champ_csrf() ?><button type="submit" name="action" value="tout_ecarter" class="bouton bouton--petit" data-confirmer="Écarter tous les signalements « à lire » arrivés il y a plus de trente jours ?">Écarter ce qui attend depuis plus de trente jours</button></form>
  <?php endif; ?>
</div>

<div>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="ajouter">
    <h2>Ajouter un signalement</h2>
    <?= champ('adresse', 'Adresse de la page ou de la publication', '', ['type' => 'url', 'requis' => true, 'indication' => 'https://…', 'aide' => 'Pupitre lit la page pour en reprendre le titre et le média. Pour une publication d\'un réseau social, donnez-lui un titre.']) ?>
    <?= champ('titre', 'Titre (facultatif)', '', ['max' => 300]) ?>
    <button type="submit" class="bouton"><?= icone('plus') ?>Ajouter</button>
  </form>

  <form method="post" class="carte" id="reglages">
    <?= champ_csrf() ?><input type="hidden" name="action" value="reglages">
    <h2>Ce que la veille cherche</h2>
    <?= champ_texte('requetes', 'Recherches', implode("\n", veille_requetes()), ['lignes' => 3, 'aide' => 'Une par ligne, huit au plus. Entre guillemets, l\'expression exacte : "Pierre Bleuse". On peut préciser : "Pierre Bleuse" Ensemble intercontemporain.']) ?>
    <fieldset class="champ"><legend>Éditions des actualités</legend>
      <ul class="cases colonnes" style="gap:0 1rem">
        <?php foreach (EDITIONS_VEILLE as $code => [$pays, $hl]): ?><li><label><input type="checkbox" name="editions[]" value="<?= e($code) ?>"<?= in_array($code, veille_editions(), true) ? ' checked' : '' ?>> <?= e($pays) ?> <span class="discret"><?= e(strtoupper(substr($hl, 0, 2))) ?></span></label></li><?php endforeach; ?>
      </ul></fieldset>
    <?php if (peut('reglages')): ?><button type="submit" class="bouton">Enregistrer</button><?php else: ?><p class="aide">Ces réglages reviennent au pilote.</p><?php endif; ?>
  </form>

  <div class="carte">
    <h2>D'où viennent les signalements</h2>
    <ul class="items">
      <li><span class="item__titre">Flux d'actualités</span><span class="discret">La presse en ligne, par recherche et par pays. Sans clé ni abonnement ; le lien passe par l'agrégateur.</span></li>
      <li><span class="item__titre">Recherche de l'IA</span><span><?= ia_configuree() ? '<span class="puce puce--ok">disponible</span>' : '<span class="puce puce--alerte">IA non configurée</span>' ?></span><span class="discret">Le web au-delà de la presse : salles, orchestres, blogs. Une première lecture dit si l'artiste est identifié ou seulement cité.</span></li>
      <li><span class="item__titre">Ajout à la main</span><span class="discret">Une publication repérée sur un réseau, un lien reçu par mail.</span></li>
    </ul>
    <p class="aide">La collecte tourne chaque nuit (cron) et à la demande. Elle ne lit que des pages publiques.</p>
  </div>
</div>
</div>

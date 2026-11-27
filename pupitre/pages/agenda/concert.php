<?php
/**
 * Fiche concert : un écran. Publication, représentations et statuts, salle, ville, pays, ensemble et
 * orchestres associés, maison, programme, participants, description, informations complémentaires,
 * lien de billetterie, page de l'organisateur, notes internes, journal. Champs traduisibles dans
 * chaque langue des contenus, côte à côte. Indicateur de ce qui manque pour annoncer.
 * Si l'orchestre du concert n'est pas référencé, l'écran le dit et propose de l'ajouter.
 */

defined('PUPITRE') or exit;

$nouveau = recu('id') === 'nouveau';
$id = $nouveau ? null : recu_entier('id');
$c = $id ? concert_lire($id) : null;
if (!$nouveau && !$c) {
    http_response_code(404);
    $titre = 'Concert introuvable';
    echo '<div class="vide"><h1>Concert introuvable</h1><p><a class="bouton" href="' . e(url('agenda')) . '">Retour à l\'agenda</a></p></div>';
    return;
}
$erreurs = [];

if (est_post()) {
    $action = recu('action');
    if ($action === 'enregistrer') {
        [$nouvel_id, $erreurs] = concert_enregistrer($id, $_POST);
        if (!$erreurs) {
            message('ok', $nouveau ? 'Concert créé. Ses représentations sont « connues » : elles ne sortent pas sur le site tant qu\'elles ne sont pas annoncées.' : 'Fiche enregistrée.');
            rediriger(url('concert', ['id' => $nouvel_id]));
        }
    } elseif ($c && $action === 'statut') {
        $champs = ['lien_source' => recu('lien_source'), 'embargo_le' => recu('embargo_le'), 'raison' => recu('raison')];
        if (recu('rep') === 'toutes') {
            [$faites, $soucis] = statut_changer_tout($id, recu('statut'), $champs);
            if ($faites) message('ok', pluriel($faites, 'représentation passée', 'représentations passées') . ' à « ' . (STATUTS[recu('statut')] ?? '') . ' ».');
            foreach ($soucis as $s) message('erreur', $s);
            if (!$faites && !$soucis) message('alerte', 'Aucune représentation ne pouvait recevoir ce statut.');
        } else {
            $rep = bd_ligne('SELECT id FROM representations WHERE id = ? AND concert_id = ?', [recu_entier('rep') ?? 0, $id]);
            $e = $rep ? statut_changer((int) $rep['id'], recu('statut'), $champs) : 'Représentation introuvable.';
            message($e ? 'erreur' : 'ok', $e ?: 'Statut enregistré.');
        }
        rediriger(url('concert', ['id' => $id]));
    } elseif ($c && in_array($action, ['publier', 'depublier'], true)) {
        if (concert_publier($id, $action === 'publier')) {
            message('ok', $action === 'publier' ? 'Concert publié : ses dates annoncées sortiront sur le site à la prochaine publication.' : 'Concert dépublié : il quittera le site à la prochaine publication. Rien n\'est supprimé, ses dates gardent leur statut.');
        }
        rediriger(url('concert', ['id' => $id]));
    } elseif ($c && $action === 'associer' && ($fiche = fiche_lire(recu_entier('fiche') ?? 0)) && in_array($fiche['categorie'], CATEGORIES_ORCHESTRE, true)) {
        concert_orchestres_poser($id, array_merge(array_column($c['orchestres'], 'id'), [(int) $fiche['id']]), $c['titre_fr']);
        if (!$c['ensemble_id']) bd_modifier('concerts', ['ensemble_id' => (int) $fiche['id']], ['id' => $id]);
        message('ok', '« ' . $fiche['nom'] . ' » est associé à ce concert.');
        rediriger(url('concert', ['id' => $id]));
    } elseif ($c && $action === 'orchestre') {
        // l'orchestre du concert n'est pas référencé : sa fiche est créée, préremplie, et l'on passe à la carte
        $fiche_id = orchestre_creer_depuis_concert($id);
        if ($fiche_id) {
            message('ok', 'Orchestre ajouté à la collection et associé au concert. Vérifiez sa ville, puis placez-le sur la carte : il n\'y paraîtra qu\'une fois affiché.');
            rediriger(url('carte', ['orchestre' => $fiche_id]) . '#placer');
        }
        rediriger(url('concert', ['id' => $id]));
    } elseif ($c && $action === 'fiche' && array_key_exists(recu('categorie'), CATEGORIES)) {
        fiche_assurer(recu('categorie'), recu('nom'), ['ville_fr' => recu('ville_fr'), 'pays_fr' => recu('pays_fr')]);
        concert_relier_tout($id);
        message('ok', 'Fiche créée dans l\'annuaire et reliée au concert.');
        rediriger(url('concert', ['id' => $id]));
    } elseif ($c && $action === 'note') {
        $note = bd_ligne('SELECT * FROM notes_artiste WHERE id = ? AND concert_id = ?', [recu_entier('note') ?? 0, $id]);
        if ($note) {
            bd_modifier('notes_artiste', ['statut' => 'traitee', 'traitee_par' => compte()['id'], 'traitee_le' => date('Y-m-d H:i:s')], ['id' => $note['id']]);
            journaliser('note', 'concert', $id, $c['titre_fr'] . ', note de l\'artiste traitée', ['statut' => 'a_traiter'], ['statut' => 'traitee']);
            message('ok', 'Note marquée comme traitée.');
        }
        rediriger(url('concert', ['id' => $id]));
    } elseif ($c && $action === 'supprimer') {
        concert_supprimer($id);
        message('ok', 'Concert supprimé. S\'il était sur le site, il en sortira à la prochaine publication.');
        rediriger(url('agenda'));
    }
}

// ce qu'on affiche : la fiche en base, ou ce qui vient d'être saisi si l'enregistrement a échoué
$f = $c ?? ['type' => 'concert', 'maison_id' => null, 'temps_fort' => 0, 'representations' => [[]], 'programme' => [], 'participants' => [], 'orchestres' => []];
$saisie = est_post() && (bool) $erreurs;         // on réaffiche ce qui vient d'être saisi
if ($saisie) {
    foreach ($_POST as $k => $v) if (is_string($v)) $f[$k] = $v;
    $f['temps_fort'] = empty($_POST['temps_fort']) ? 0 : 1;
    $f['representations'] = array_values(array_filter($_POST['reps'] ?? [], 'is_array'));
    $f['programme'] = array_values(array_filter($_POST['prog'] ?? [], 'is_array'));
    $f['participants'] = array_values(array_filter($_POST['avec'] ?? [], 'is_array'));
}
$v = fn(string $k) => (string) ($f[$k] ?? '');
// traductions d'un champ : celles de la base (propositions de l'IA comprises), ou ce qui vient d'être saisi
$lg = fn(string $objet, $cle, string $champ, ?array $saisi = null) => ($saisi !== null || !$cle) ? ['valeurs' => saisie_bi($saisi ?? [], $champ)] : ['objet' => [$objet, $cle, $champ]];
$lgc = fn(string $champ) => $lg('concerts', $saisie ? null : $id, $champ, $saisie ? $_POST : null);
$titre = $nouveau ? 'Nouveau concert' : $c['titre_fr'];
$maisons = [];
foreach (bd_lignes('SELECT * FROM maisons ORDER BY ordre, id') as $m) $maisons[(int) $m['id']] = $m['nom_fr'];
$manques = $c ? concert_incomplet($c) : [];
$pour_annoncer = $c ? concert_manques($c) : [];
$jour = aujourdhui();

/* Une ligne de représentation, de programme ou de participant. $i : rang, ou __i__ pour le modèle. */
$ligne_rep = function (array $r, string $i) use ($lg, $saisie): string {
    $p = 'reps[' . $i . ']';
    $t = fn(string $champ) => $lg('representations', $saisie ? null : ($r['id'] ?? null), $champ, $saisie ? $r : null);
    $propre = trim(($r['salle_fr'] ?? '') . ($r['ville_fr'] ?? '') . ($r['pays_fr'] ?? '') . ($r['lien_billetterie'] ?? '')) !== '';
    return '<div class="ligne ligne--compacte" data-ligne><input type="hidden" name="' . $p . '[id]" value="' . e($r['id'] ?? '') . '">'
        . '<div class="rangee rangee--rep">'
        . champ('date', 'Date', $r['date'] ?? '', ['prefixe' => $p, 'type' => 'date', 'requis' => true])
        . champ('heure', 'Heure', isset($r['heure']) ? substr((string) $r['heure'], 0, 5) : '', ['prefixe' => $p, 'indication' => '19:30', 'max' => 5])
        . champ('lien_source', 'Lien source', $r['lien_source'] ?? '', ['prefixe' => $p, 'type' => 'url', 'indication' => 'page où l\'organisateur annonce la date'])
        . boutons_ligne('cette représentation') . '</div>'
        . '<details' . ($propre ? ' open' : '') . '><summary>Lieu propre à cette date (tournée)</summary><div class="colonnes">'
        . champ_bi('salle', 'Salle', $r['salle_fr'] ?? '', $r['salle_en'] ?? '', ['prefixe' => $p, 'liste' => 'liste-salles'] + $t('salle'))
        . champ_bi('ville', 'Ville', $r['ville_fr'] ?? '', $r['ville_en'] ?? '', ['prefixe' => $p] + $t('ville'))
        . champ_bi('pays', 'Pays', $r['pays_fr'] ?? '', $r['pays_en'] ?? '', ['prefixe' => $p] + $t('pays'))
        . champ('lien_billetterie', 'Billetterie de cette date', $r['lien_billetterie'] ?? '', ['prefixe' => $p, 'type' => 'url', 'indication' => 'https://…'])
        . '</div></details></div>';
};
$ligne_prog = function (array $r, string $i) use ($lg, $saisie): string {
    $p = 'prog[' . $i . ']';
    $t = fn(string $champ) => $lg('programme', $saisie ? null : ($r['id'] ?? null), $champ, $saisie ? $r : null);
    return '<div class="ligne ligne--compacte" data-ligne><div class="rangee rangee--prog">'
        . champ_bi('compositeur', 'Compositeur', $r['compositeur_fr'] ?? '', $r['compositeur_en'] ?? '', ['prefixe' => $p, 'liste' => 'liste-compositeurs'] + $t('compositeur'))
        . champ_bi('oeuvre', 'Œuvre', $r['oeuvre_fr'] ?? '', $r['oeuvre_en'] ?? '', ['prefixe' => $p] + $t('oeuvre'))
        . '</div><div class="rangee rangee--prog2">'
        . champ_bi('mention', 'Mention (création, commande…)', $r['mention_fr'] ?? '', $r['mention_en'] ?? '', ['prefixe' => $p] + $t('mention'))
        . boutons_ligne('cette œuvre') . '</div></div>';
};
$ligne_avec = function (array $r, string $i) use ($lg, $saisie): string {
    $p = 'avec[' . $i . ']';
    $t = fn(string $champ) => $lg('participants', $saisie ? null : ($r['id'] ?? null), $champ, $saisie ? $r : null);
    $s = '<div class="ligne ligne--compacte" data-ligne><div class="rangee rangee--avec">'
        . champ('nom', 'Nom', $r['nom'] ?? '', ['prefixe' => $p, 'liste' => 'liste-participants'])
        . champ_bi('role', 'Rôle, instrument, voix', $r['role_fr'] ?? '', $r['role_en'] ?? '', ['prefixe' => $p] + $t('role'))
        . boutons_ligne('ce participant') . '</div>';
    if (!empty($r['id']) && ($r['nom'] ?? '') !== '') {
        $s .= '<p class="aide">' . (!empty($r['fiches'])
            ? 'Annuaire : ' . implode(', ', array_map(fn($x) => '<a href="' . e(url('fiche', ['id' => $x['id']])) . '">' . e($x['nom']) . '</a>', $r['fiches']))
            : 'Hors annuaire.') . '</p>';
    }
    return $s . '</div>';
};
$liste = function (string $nom, array $lignes, callable $rendre, string $ajout): string {
    $s = '<div data-liste data-suivant="0"><div class="lignes" data-lignes>';
    foreach (array_values($lignes) as $i => $l) $s .= $rendre($l, (string) $i);
    return $s . '</div><template data-modele>' . $rendre([], '__i__') . '</template>'
        . '<p><button type="button" class="bouton bouton--petit" data-ajouter>' . e($ajout) . '</button></p></div>';
};
/* Formulaire de changement de statut : un bouton, et le champ que le statut exige. */
$vers = function (string $statut, $rep, array $r = []) use ($id): string {
    $champ = match ($statut) {
        'annoncee' => '<label class="sr" for="src-' . e($rep) . '">Lien source</label><input type="url" id="src-' . e($rep) . '" name="lien_source" value="' . e($r['lien_source'] ?? '') . '" placeholder="lien source : la page de l\'organisateur" required>',
        'embargo' => '<label class="sr" for="emb-' . e($rep) . '">Annonce prévue le</label><input type="date" id="emb-' . e($rep) . '" name="embargo_le" value="' . e($r['embargo_le'] ?? '') . '" required>',
        'annulee' => '<label class="sr" for="rai-' . e($rep) . '">Raison</label><input type="text" id="rai-' . e($rep) . '" name="raison" placeholder="raison de l\'annulation ou du report" required>',
        default => '',
    };
    $libelle = ['connue' => 'Remettre à « connue »', 'embargo' => 'Mettre sous embargo', 'annoncee' => 'Annoncer', 'annulee' => 'Annuler ou reporter'][$statut];
    return '<form method="post" action="' . e(url('concert', ['id' => $id])) . '">' . champ_csrf()
        . '<input type="hidden" name="action" value="statut"><input type="hidden" name="rep" value="' . e($rep) . '"><input type="hidden" name="statut" value="' . e($statut) . '">'
        . $champ . '<button type="submit" class="bouton bouton--petit' . ($statut === 'annulee' ? ' bouton--danger' : '') . '">' . e($libelle) . '</button></form>';
};
/* Un nom saisi qui n'est pas dans l'annuaire : un bouton crée sa fiche. Le formulaire de ce bouton vit
   hors du formulaire de la fiche (un formulaire ne s'imbrique pas dans un autre) : il est rendu plus bas. */
$annexes = [];
$hors_annuaire = function (string $cle, string $nom, ?int $fiche_id, string $categorie, array $plus = []) use ($id, $nouveau, &$annexes): string {
    if ($nouveau || $nom === '') return '';
    if ($fiche_id) return '<p class="aide">Annuaire : <a href="' . e(url('fiche', ['id' => $fiche_id])) . '">' . e($nom) . '</a></p>';
    $f = '<form method="post" id="creer-' . e($cle) . '" action="' . e(url('concert', ['id' => $id])) . '" hidden>' . champ_csrf()
        . '<input type="hidden" name="action" value="fiche"><input type="hidden" name="categorie" value="' . e($categorie) . '"><input type="hidden" name="nom" value="' . e($nom) . '">';
    foreach ($plus as $k => $val) $f .= '<input type="hidden" name="' . e($k) . '" value="' . e($val) . '">';
    $annexes[] = $f . '</form>';
    return '<p class="aide en-ligne">Hors annuaire. <button type="submit" form="creer-' . e($cle) . '" class="bouton bouton--petit">Créer la fiche « ' . e(couper($nom, 40)) . ' »</button></p>';
};
?>
<p class="fil"><a href="<?= e(url('agenda')) ?>">Concerts</a></p>
<div class="titre">
  <h1><?= e($titre) ?></h1>
  <?php if ($c): ?>
    <span class="titre__suite"><?= e(TYPES_CONCERT[$c['type']]) ?> · <?= e($c['cle']) ?>
      <?= $c['publie'] ? '<span class="puce puce--ok">publié</span>' : '<span class="puce puce--alerte">dépublié</span>' ?>
      <?= $c['temps_fort'] ? '<span class="puce puce--accent">à la une</span>' : '' ?></span>
    <div class="titre__actions">
      <form method="post"><?= champ_csrf() ?><button type="submit" name="action" value="<?= $c['publie'] ? 'depublier' : 'publier' ?>" class="bouton"><?= $c['publie'] ? 'Dépublier ce concert' : 'Publier ce concert' ?></button></form>
    </div>
  <?php endif; ?>
</div>
<?php if ($c && !$c['publie']): ?><p class="message message--alerte">Ce concert est dépublié : aucune de ses dates ne sort sur le site, quel que soit leur statut. Il n'est pas supprimé, et retrouvera sa place dès qu'il sera publié de nouveau.</p><?php endif; ?>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<?= liste_choix('liste-ensembles', annuaire_noms(['orchestre', 'ensemble'])) ?>
<?= liste_choix('liste-salles', annuaire_noms(['salle', 'festival'])) ?>
<?= liste_choix('liste-compositeurs', annuaire_noms(['compositeur'])) ?>
<?= liste_choix('liste-participants', annuaire_noms(['soliste', 'ensemble', 'orchestre'])) ?>

<div class="deux">
<div>
  <form method="post" class="carte" id="fiche">
    <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
    <div class="colonnes">
      <?= champ_choix('type', 'Type', TYPES_CONCERT, $v('type') ?: 'concert') ?>
      <?= champ_choix('maison_id', 'Maison', $maisons, $v('maison_id'), ['vide' => '— aucune —']) ?>
      <div class="champ"><span class="sr">Mise en avant</span><?= champ_case('temps_fort', 'À la une', !empty($f['temps_fort'])) ?>
        <p class="aide">Marque éditoriale, indépendante de la publication.</p></div>
    </div>
    <?= champ_bi('titre', 'Titre', $v('titre_fr'), $v('titre_en'), ['requis' => true] + $lgc('titre')) ?>
    <?= champ_bi('ensemble', 'Orchestre ou ensemble', $v('ensemble_fr'), $v('ensemble_en'), ['liste' => 'liste-ensembles', 'aide' => 'Tel qu\'il s\'affiche sur le site. Choisi dans la collection Orchestres, il est associé au concert de lui-même.'] + $lgc('ensemble')) ?>
    <?php
      // orchestres associés : ceux de la base, ou ceux qui viennent d'être envoyés
      $associes = $saisie ? array_map('intval', is_array($_POST['orchestres'] ?? null) ? $_POST['orchestres'] : []) : array_map('intval', array_column($f['orchestres'] ?? [], 'id'));
      $tous_orchestres = [];
      foreach (bd_lignes("SELECT id, nom FROM annuaire WHERE categorie IN ('orchestre', 'ensemble') ORDER BY nom") as $o) $tous_orchestres[(int) $o['id']] = $o['nom'];
    ?>
    <fieldset class="champ">
      <legend>Orchestres associés</legend>
      <?php if ($associes): ?>
        <ul class="cases">
          <?php foreach ($associes as $oid): if (!isset($tous_orchestres[$oid])) continue; ?>
            <li><label><input type="checkbox" name="orchestres[]" value="<?= (int) $oid ?>" checked> <?= e($tous_orchestres[$oid]) ?></label> <a class="discret" href="<?= e(url('fiche', ['id' => $oid])) ?>">fiche</a></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><input type="hidden" name="orchestres[]" value=""><p class="discret">Aucun orchestre associé pour l'instant.</p><?php endif; ?>
      <label class="sr" for="c-orchestre-plus">Associer un autre orchestre</label>
      <select id="c-orchestre-plus" name="orchestres[]">
        <option value="">— associer un autre orchestre de la collection —</option>
        <?php foreach ($tous_orchestres as $oid => $nom): if (in_array($oid, $associes, true)) continue; ?><option value="<?= (int) $oid ?>"><?= e($nom) ?></option><?php endforeach; ?>
      </select>
      <p class="aide">Un concert peut réunir plusieurs orchestres. Décochez pour retirer une association ; l'orchestre reste dans la collection.</p>
    </fieldset>

    <h2>Lieu</h2>
    <?= champ_bi('salle', 'Salle', $v('salle_fr'), $v('salle_en'), ['liste' => 'liste-salles', 'aide' => 'Pour une tournée, laissez vide : chaque date porte son lieu.'] + $lgc('salle')) ?>
    <?= $c ? $hors_annuaire('salle', $c['salle_fr'], $c['salle_id'] ? (int) $c['salle_id'] : null, 'salle', ['ville_fr' => $c['ville_fr'], 'pays_fr' => $c['pays_fr']]) : '' ?>
    <div class="colonnes">
      <?= champ_bi('ville', 'Ville', $v('ville_fr'), $v('ville_en'), $lgc('ville')) ?>
      <?= champ_bi('pays', 'Pays', $v('pays_fr'), $v('pays_en'), $lgc('pays')) ?>
    </div>
    <?= champ_choix('fuseau', 'Fuseau de la salle', array_combine(timezone_identifiers_list(), timezone_identifiers_list()), $v('fuseau'), ['vide' => '— à déduire du pays et de la ville —', 'aide' => 'Ne sert qu\'à calculer l\'heure de l\'artiste sur le tableau de bord. Les heures saisies restent celles de la salle.']) ?>

    <h2>Représentations</h2>
    <p class="aide">Date et heure locales de la salle. Le statut se règle à droite, date par date, une fois la fiche enregistrée.</p>
    <div class="fiche-concert__reps"><?= $liste('reps', $f['representations'], $ligne_rep, 'Ajouter une représentation') ?></div>

    <h2>Programme</h2>
    <?= $liste('prog', $f['programme'], $ligne_prog, 'Ajouter une œuvre') ?>

    <h2>Participants</h2>
    <p class="aide">Choisis dans l'annuaire. Plusieurs personnes peuvent partager une ligne, séparées par des virgules.</p>
    <?= $liste('avec', $f['participants'], $ligne_avec, 'Ajouter un participant') ?>

    <h2>Présentation et liens</h2>
    <?= champ_bi('description', 'Description', $v('description_fr'), '', ['texte' => true, 'aide' => 'Présentation du concert, montrée dans sa fiche sur la page des concerts. Facultative.'] + $lgc('description')) ?>
    <?= champ_bi('mentions', 'Informations complémentaires', $v('mentions_fr'), $v('mentions_en'), ['texte' => true, 'aide' => 'Débuts avec l\'orchestre, enregistrement, partenaire… Une ligne discrète sous la fiche.'] + $lgc('mentions')) ?>
    <div class="colonnes">
      <?= champ('lien_billetterie', 'Adresse externe : billetterie', $v('lien_billetterie'), ['type' => 'url', 'indication' => 'https://…']) ?>
      <?= champ('page_organisateur', 'Adresse externe : page de l\'organisateur', $v('page_organisateur'), ['type' => 'url', 'indication' => 'https://…']) ?>
    </div>
    <p class="aide">Sur le site, « En savoir plus » mène à la billetterie, sinon à la page de l'organisateur. Sans aucune de ces adresses, il mène à la fiche du concert sur la page des concerts : jamais de lien mort.</p>
    <?= champ_texte('notes_internes', 'Notes internes', $v('notes_internes'), ['aide' => 'Ne sortent jamais sur le site.']) ?>
    <div class="actions actions--bas">
      <button type="submit" class="bouton bouton--plein"><?= $nouveau ? 'Créer le concert' : 'Enregistrer la fiche' ?></button>
      <a class="bouton" href="<?= e(url('agenda')) ?>">Retour à l'agenda</a>
    </div>
  </form>
  <?= implode('', $annexes) ?>
  <?php if ($c): ?>
  <form method="post" class="actions">
    <?= champ_csrf() ?><input type="hidden" name="action" value="supprimer">
    <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Supprimer ce concert, ses représentations, son programme et ses notes ?">Supprimer ce concert</button>
  </form>
  <?php endif; ?>
</div>

<?php if ($c): ?>
<div>
  <?php if (peut('communication')): $actions_com = communications_de_concert($id); $annonce = (bool) array_filter($c['representations'], fn($r) => in_array($r['statut'], ['annoncee', 'publiee'], true)); ?>
  <div class="carte" id="communication">
    <div class="carte__tete"><h2>Communication</h2><span class="carte__compte"><?= count($actions_com) ?></span></div>
    <?php if (!$actions_com): ?>
      <p class="rien"><?= $annonce ? 'Aucune action de communication enregistrée pour ce concert.' : 'Ce concert n\'est pas encore annoncé par son organisateur : on ne communique pas avant.' ?></p>
    <?php else: ?>
      <ul class="items">
      <?php foreach ($actions_com as $k): $quand = communication_date($k); ?>
        <li><span class="en-ligne"><?= canal_afficher($k['canal']) ?> <?= communication_puce($k) ?></span>
          <a class="item__titre" href="<?= e(url('communication', ['id' => $k['id']])) ?>"><?= e(communication_libelle($k)) ?></a>
          <span class="discret"><?= $quand ? e(date_fr(substr($quand, 0, 10))) : 'pas de date' ?></span></li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <p class="actions"><a class="bouton bouton--petit<?= (!$actions_com && $annonce) ? ' bouton--doux' : '' ?>" href="<?= e(url('communication', ['id' => 'nouvelle', 'concert' => $id])) ?>"><?= icone('plus') ?>Prévoir une action</a>
      <a class="bouton bouton--petit" href="<?= e(url('editorial')) ?>#concerts">Tableau de bord éditorial</a></p>
  </div>
  <?php endif; ?>
  <?php if ($c['type'] !== 'disque' && trim($c['ensemble_fr']) !== ''): $trouve = $c['orchestres'] ? null : orchestre_chercher($c['ensemble_fr'], (string) traduction_publiee('concerts', $id, 'ensemble', 'en')); ?>
  <div class="carte<?= $c['orchestres'] ? '' : ' carte--alerte' ?>" id="orchestre">
    <h2>Orchestre</h2>
    <?php if ($c['orchestres']): ?>
      <ul class="items">
      <?php foreach ($c['orchestres'] as $o): $sur_carte = (int) bd_valeur('SELECT COUNT(*) FROM parcours_orchestres WHERE fiche_id = ? AND affiche = 1', [$o['id']]); ?>
        <li><span class="item__titre"><?= e($o['nom']) ?></span>
          <span><?= $sur_carte ? '<span class="puce puce--ok">sur la carte du monde</span>' : '<span class="puce">pas sur la carte</span>' ?>
            <a href="<?= e(url('carte', ['orchestre' => $o['id']])) ?>#placer">carte</a> · <a href="<?= e(url('fiche', ['id' => $o['id']])) ?>">fiche</a></span></li>
      <?php endforeach; ?>
      </ul>
    <?php elseif ($trouve && $trouve['candidats']): ?>
      <p>« <?= e($c['ensemble_fr']) ?> » ne correspond exactement à aucun orchestre de la collection. Est-ce l'un de ceux-ci ?</p>
      <ul class="items">
      <?php foreach (array_slice($trouve['candidats'], 0, 4) as $cand): ?>
        <li><span class="item__titre"><?= e($cand['nom']) ?></span>
          <form method="post" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="associer"><input type="hidden" name="fiche" value="<?= (int) $cand['id'] ?>"><button type="submit" class="bouton bouton--petit">Oui, l'associer</button></form></li>
      <?php endforeach; ?>
      </ul>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="orchestre"><button type="submit" class="bouton bouton--petit">Non : l'ajouter comme nouvel orchestre</button></form>
    <?php else: ?>
      <p><strong>Cet orchestre n'est pas encore référencé. Souhaitez-vous l'ajouter à la carte mondiale ?</strong></p>
      <p class="aide">« <?= e($c['ensemble_fr']) ?> » entrera dans la collection Orchestres, avec la ville et le pays de ce concert comme proposition à confirmer. Il ne paraîtra sur la carte qu'une fois sa position vérifiée et son affichage décidé.</p>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="orchestre"><button type="submit" class="bouton bouton--plein">Ajouter cet orchestre</button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="carte">
    <h2>Pour annoncer</h2>
    <?php if (!$pour_annoncer): ?>
      <p><span class="puce puce--ok">rien ne manque</span></p>
    <?php else: ?>
      <p class="manques"><?php foreach ($pour_annoncer as $m): ?><span class="puce puce--alerte"><?= e($m) ?></span><?php endforeach; ?></p>
    <?php endif; ?>
    <?php $en_plus = array_diff($manques, $pour_annoncer); if ($en_plus): ?>
      <p class="aide">Pour une fiche complète, il manque aussi : <?= e(implode(', ', $en_plus)) ?>.</p>
    <?php endif; ?>
  </div>

  <div class="carte">
    <h2>Représentations et statuts</h2>
    <?php if (count($c['representations']) > 1): ?>
    <details>
      <summary>Tout le concert</summary>
      <div class="rep__etat">
        <?= $vers('annoncee', 'toutes') ?><?= $vers('embargo', 'toutes') ?><?= $vers('annulee', 'toutes') ?><?= $vers('connue', 'toutes') ?>
      </div>
    </details>
    <?php endif; ?>
    <ul class="items">
    <?php foreach ($c['representations'] as $r): ?>
      <li<?= $r['date'] < $jour ? ' class="passee"' : '' ?>>
        <span class="item__titre"><?= e(date_fr($r['date'], true)) ?><?= $r['heure'] ? ', ' . e(heure_fr($r['heure'])) : '' ?></span>
        <span><?= etiquette_statut($r['statut']) ?>
          <?php if ($r['statut'] === 'embargo' && $r['embargo_le']): ?> <span class="discret">annonce prévue le <?= e(date_fr($r['embargo_le'])) ?></span><?php endif; ?>
          <?php if ($r['statut'] === 'annulee'): ?> <span class="discret"><?= e($r['raison']) ?></span><?php endif; ?>
        </span>
        <?php if (lieu($r, $c, 'ville') !== '' && $r['ville_fr'] !== ''): ?><span class="discret"><?= e(implode(', ', array_filter([lieu($r, $c, 'ville'), lieu($r, $c, 'salle')], 'strlen'))) ?></span><?php endif; ?>
        <?php if ($r['lien_source'] !== ''): ?><span class="discret">Source : <a href="<?= e($r['lien_source']) ?>" target="_blank" rel="noopener"><?= e(couper(preg_replace('#^https?://#', '', $r['lien_source']), 44)) ?></a></span><?php endif; ?>
        <?php if (($h = heure_artiste($r['date'], $r['heure'], $c['fuseau'])) !== '' && $c['fuseau'] !== reglage('fuseau')): ?><span class="discret">Heure de l'artiste : <?= e($h) ?></span><?php endif; ?>
        <details>
          <summary>Changer le statut</summary>
          <div class="rep__etat"><?php foreach (statuts_possibles($r['statut']) as $s) echo $vers($s, (int) $r['id'], $r); ?></div>
          <p class="aide">« Publiée » est posé par la publication du site, jamais à la main.</p>
        </details>
      </li>
    <?php endforeach; ?>
    </ul>
  </div>

  <?php $notes = bd_lignes('SELECT n.*, c.nom FROM notes_artiste n JOIN comptes c ON c.id = n.compte_id WHERE n.concert_id = ? ORDER BY n.id DESC', [$id]); if ($notes): ?>
  <div class="carte">
    <h2>Notes de l'artiste</h2>
    <ul class="items">
    <?php foreach ($notes as $n): ?>
      <li>
        <span><?= nl2br(e($n['texte'])) ?></span>
        <span class="discret"><?= e($n['nom']) ?>, <?= e(horodatage_fr($n['cree_le'])) ?> · <?= $n['statut'] === 'traitee' ? 'traitée' : 'à traiter' ?></span>
        <?php if ($n['statut'] === 'a_traiter'): ?>
        <form method="post" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="note"><input type="hidden" name="note" value="<?= (int) $n['id'] ?>"><button type="submit" class="bouton bouton--petit">Marquer comme traitée</button></form>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="carte">
    <h2>Journal</h2>
    <?= journal_afficher(journal_objet('concert', $id, 25)) ?>
  </div>
</div>
<?php endif; ?>
</div>

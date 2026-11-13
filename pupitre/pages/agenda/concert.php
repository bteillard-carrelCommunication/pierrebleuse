<?php
/**
 * Fiche concert : un écran. Représentations et statuts, salle, ville, pays, ensemble, maison,
 * programme, participants, mentions, lien de billetterie, page de l'organisateur, notes internes,
 * journal. Champs en français et en anglais côte à côte. Indicateur de ce qui manque pour annoncer.
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
$f = $c ?? ['type' => 'concert', 'maison_id' => null, 'temps_fort' => 0, 'representations' => [[]], 'programme' => [], 'participants' => []];
if (est_post() && $erreurs) {
    foreach ($_POST as $k => $v) if (is_string($v)) $f[$k] = $v;
    $f['temps_fort'] = empty($_POST['temps_fort']) ? 0 : 1;
    $f['representations'] = array_values(array_filter($_POST['reps'] ?? [], 'is_array'));
    $f['programme'] = array_values(array_filter($_POST['prog'] ?? [], 'is_array'));
    $f['participants'] = array_values(array_filter($_POST['avec'] ?? [], 'is_array'));
}
$v = fn(string $k) => (string) ($f[$k] ?? '');
$titre = $nouveau ? 'Nouveau concert' : $c['titre_fr'];
$maisons = [];
foreach (bd_lignes('SELECT * FROM maisons ORDER BY ordre, id') as $m) $maisons[(int) $m['id']] = $m['nom_fr'];
$manques = $c ? concert_incomplet($c) : [];
$pour_annoncer = $c ? concert_manques($c) : [];
$jour = aujourdhui();

/* Une ligne de représentation, de programme ou de participant. $i : rang, ou __i__ pour le modèle. */
$ligne_rep = function (array $r, string $i): string {
    $p = 'reps[' . $i . ']';
    $propre = trim(($r['salle_fr'] ?? '') . ($r['ville_fr'] ?? '') . ($r['pays_fr'] ?? '') . ($r['lien_billetterie'] ?? '')) !== '';
    return '<div class="ligne ligne--compacte" data-ligne><input type="hidden" name="' . $p . '[id]" value="' . e($r['id'] ?? '') . '">'
        . '<div class="rangee rangee--rep">'
        . champ('date', 'Date', $r['date'] ?? '', ['prefixe' => $p, 'type' => 'date', 'requis' => true])
        . champ('heure', 'Heure', isset($r['heure']) ? substr((string) $r['heure'], 0, 5) : '', ['prefixe' => $p, 'indication' => '19:30', 'max' => 5])
        . champ('lien_source', 'Lien source', $r['lien_source'] ?? '', ['prefixe' => $p, 'type' => 'url', 'indication' => 'page où l\'organisateur annonce la date'])
        . boutons_ligne('cette représentation') . '</div>'
        . '<details' . ($propre ? ' open' : '') . '><summary>Lieu propre à cette date (tournée)</summary><div class="colonnes">'
        . champ_bi('salle', 'Salle', $r['salle_fr'] ?? '', $r['salle_en'] ?? '', ['prefixe' => $p, 'liste' => 'liste-salles'])
        . champ_bi('ville', 'Ville', $r['ville_fr'] ?? '', $r['ville_en'] ?? '', ['prefixe' => $p])
        . champ_bi('pays', 'Pays', $r['pays_fr'] ?? '', $r['pays_en'] ?? '', ['prefixe' => $p])
        . champ('lien_billetterie', 'Billetterie de cette date', $r['lien_billetterie'] ?? '', ['prefixe' => $p, 'type' => 'url', 'indication' => 'https://…'])
        . '</div></details></div>';
};
$ligne_prog = function (array $r, string $i): string {
    $p = 'prog[' . $i . ']';
    return '<div class="ligne ligne--compacte" data-ligne><div class="rangee rangee--prog">'
        . champ_bi('compositeur', 'Compositeur', $r['compositeur_fr'] ?? '', $r['compositeur_en'] ?? '', ['prefixe' => $p, 'liste' => 'liste-compositeurs'])
        . champ_bi('oeuvre', 'Œuvre', $r['oeuvre_fr'] ?? '', $r['oeuvre_en'] ?? '', ['prefixe' => $p])
        . '</div><div class="rangee rangee--prog2">'
        . champ_bi('mention', 'Mention (création, commande…)', $r['mention_fr'] ?? '', $r['mention_en'] ?? '', ['prefixe' => $p])
        . boutons_ligne('cette œuvre') . '</div></div>';
};
$ligne_avec = function (array $r, string $i): string {
    $p = 'avec[' . $i . ']';
    $s = '<div class="ligne ligne--compacte" data-ligne><div class="rangee rangee--avec">'
        . champ('nom', 'Nom', $r['nom'] ?? '', ['prefixe' => $p, 'liste' => 'liste-participants'])
        . champ_bi('role', 'Rôle, instrument, voix', $r['role_fr'] ?? '', $r['role_en'] ?? '', ['prefixe' => $p])
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
<p class="fil"><a href="<?= e(url('agenda')) ?>">Agenda</a></p>
<div class="titre">
  <h1><?= e($titre) ?></h1>
  <?php if ($c): ?><span class="titre__suite"><?= e(TYPES_CONCERT[$c['type']]) ?> · <?= e($c['cle']) ?></span><?php endif; ?>
</div>
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
      <div class="champ"><span class="sr">Mise en avant</span><?= champ_case('temps_fort', 'Temps fort de la saison', !empty($f['temps_fort'])) ?></div>
    </div>
    <?= champ_bi('titre', 'Titre', $v('titre_fr'), $v('titre_en'), ['requis' => true]) ?>
    <?= champ_bi('ensemble', 'Ensemble', $v('ensemble_fr'), $v('ensemble_en'), ['liste' => 'liste-ensembles', 'aide' => 'Choisi dans l\'annuaire, ou saisi tel qu\'il doit s\'afficher.']) ?>
    <?= $c ? $hors_annuaire('ensemble', $c['ensemble_fr'], $c['ensemble_id'] ? (int) $c['ensemble_id'] : null, stripos($c['ensemble_fr'], 'ensemble') !== false ? 'ensemble' : 'orchestre') : '' ?>

    <h2>Lieu</h2>
    <?= champ_bi('salle', 'Salle', $v('salle_fr'), $v('salle_en'), ['liste' => 'liste-salles', 'aide' => 'Pour une tournée, laissez vide : chaque date porte son lieu.']) ?>
    <?= $c ? $hors_annuaire('salle', $c['salle_fr'], $c['salle_id'] ? (int) $c['salle_id'] : null, 'salle', ['ville_fr' => $c['ville_fr'], 'pays_fr' => $c['pays_fr']]) : '' ?>
    <div class="colonnes">
      <?= champ_bi('ville', 'Ville', $v('ville_fr'), $v('ville_en')) ?>
      <?= champ_bi('pays', 'Pays', $v('pays_fr'), $v('pays_en')) ?>
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

    <h2>Mentions et liens</h2>
    <?= champ_bi('mentions', 'Mentions', $v('mentions_fr'), $v('mentions_en'), ['texte' => true, 'aide' => 'Débuts avec l\'orchestre, enregistrement, partenaire…']) ?>
    <div class="colonnes">
      <?= champ('lien_billetterie', 'Lien de billetterie', $v('lien_billetterie'), ['type' => 'url', 'indication' => 'https://…']) ?>
      <?= champ('page_organisateur', 'Page de l\'organisateur', $v('page_organisateur'), ['type' => 'url', 'indication' => 'https://…']) ?>
    </div>
    <p class="aide">Sur le site, « En savoir plus » mène à la billetterie, sinon à la page de l'organisateur.</p>
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

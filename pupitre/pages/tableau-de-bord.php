<?php
/**
 * Tableau de bord : cinq blocs. Aujourd'hui, cette semaine, à valider, alertes, le site.
 */

defined('PUPITRE') or exit;

$titre = 'Tableau de bord';
$jour = aujourdhui();

if (est_post()) {
    $action = recu('action');
    if ($action === 'alerte' && ($a = bd_ligne('SELECT * FROM alertes WHERE id = ?', [recu_entier('id') ?? 0]))) {
        if (recu('quoi') === 'reporter') { alerte_reporter((int) $a['id']); message('ok', 'Alerte reportée d\'une semaine.'); }
        elseif (recu('quoi') === 'ignorer') { alerte_ignorer((int) $a['id']); message('ok', 'Alerte ignorée : elle reviendra si son sujet change.'); }
        elseif (recu('quoi') === 'rouvrir') { alerte_rouvrir((int) $a['id']); message('ok', 'Alerte rouverte.'); }
    } elseif ($action === 'compte_social' && in_array(recu('etat'), ['verifie', 'a_revoir'], true)) {
        $c = bd_ligne('SELECT x.*, a.nom FROM annuaire_comptes x JOIN annuaire a ON a.id = x.fiche_id WHERE x.id = ?', [recu_entier('id') ?? 0]);
        if ($c) {
            bd_modifier('annuaire_comptes', ['etat' => recu('etat')], ['id' => $c['id']]);
            journaliser('validation', 'fiche', (int) $c['fiche_id'], $c['nom'] . ', compte ' . $c['reseau'], ['etat' => $c['etat']], ['etat' => recu('etat')]);
            message('ok', recu('etat') === 'verifie' ? 'Compte vérifié.' : 'Compte marqué « à revoir ».');
        }
    } elseif ($action === 'note' && ($n = bd_ligne('SELECT n.*, c.titre_fr FROM notes_artiste n JOIN concerts c ON c.id = n.concert_id WHERE n.id = ?', [recu_entier('id') ?? 0]))) {
        bd_modifier('notes_artiste', ['statut' => 'traitee', 'traitee_par' => compte()['id'], 'traitee_le' => date('Y-m-d H:i:s')], ['id' => $n['id']]);
        journaliser('note', 'concert', (int) $n['concert_id'], $n['titre_fr'] . ', note de l\'artiste traitée', ['statut' => 'a_traiter'], ['statut' => 'traitee']);
        message('ok', 'Note marquée comme traitée.');
    } elseif ($action === 'lancer') {
        $ok = taches_lancer_en_fond();
        message($ok ? 'ok' : 'alerte', $ok ? 'Les tâches de fond sont lancées.' : 'Ce poste ne permet pas de lancer les tâches d\'ici : elles attendront le cron.');
    } elseif ($action === 'recalculer') {
        message('ok', 'Alertes recalculées : ' . pluriel(alertes_recalculer(), 'ouverte', 'ouvertes') . '.');
    }
    rediriger(url('bord'));
}

$reps = agenda_representations(['sauf_annulees' => true, 'du' => $jour, 'au' => ajouter_jours($jour, 7)]);
$aujourdhui = array_filter($reps, fn($r) => $r['date'] === $jour);
$semaine = array_filter($reps, fn($r) => $r['date'] > $jour);
$embargos = bd_lignes("SELECT r.*, c.titre_fr FROM representations r JOIN concerts c ON c.id = r.concert_id
    WHERE r.statut = 'embargo' AND r.embargo_le IS NOT NULL AND r.embargo_le <= ? ORDER BY r.embargo_le", [ajouter_jours($jour, 7)]);
$imports = bd_lignes("SELECT i.*, (SELECT COUNT(*) FROM import_lignes l WHERE l.import_id = i.id AND l.decision = 'en_attente') AS attente
    FROM imports i WHERE i.statut IN ('a_valider', 'depose', 'lecture') ORDER BY i.id DESC");
$comptes_proposes = bd_lignes("SELECT x.*, a.nom FROM annuaire_comptes x JOIN annuaire a ON a.id = x.fiche_id WHERE x.etat = 'propose' ORDER BY x.id DESC LIMIT 30");
$notes = bd_lignes("SELECT n.*, c.titre_fr, k.nom FROM notes_artiste n JOIN concerts c ON c.id = n.concert_id JOIN comptes k ON k.id = n.compte_id WHERE n.statut = 'a_traiter' ORDER BY n.id DESC");
$a_valider = count($imports) + count($comptes_proposes) + count($notes);
$alertes = alertes_lire();
$cachees = (int) bd_valeur("SELECT COUNT(*) FROM alertes WHERE etat <> 'ouverte'");
$derniere = publication_derniere();
try { $ecarts = publication_etat()['ecarts']; } catch (Throwable $e) { $ecarts = null; }
$fuseau_artiste = reglage('fuseau');

$ligne_concert = function (array $r) use ($fuseau_artiste): string {
    $ici = heure_artiste($r['date'], $r['heure'], $r['fuseau']);
    $s = '<li><span class="item__titre">' . e($r['titre_fr']) . '</span>'
        . '<span>' . e(date_fr($r['date'], true, false)) . ($r['heure'] ? ', ' . e(heure_fr($r['heure'])) . ' <span class="discret">heure de la salle</span>' : ' <span class="puce puce--alerte">heure à renseigner</span>');
    if ($r['heure'] && $ici !== '') $s .= ' · ' . e($ici) . ' <span class="discret">heure de l\'artiste</span>';
    elseif ($r['heure'] && $r['fuseau'] === '') $s .= ' <span class="discret">· fuseau de la salle à préciser</span>';
    $s .= '</span><span class="discret">' . e(implode(', ', array_filter([$r['lieu_salle'], $r['lieu_ville'], $r['lieu_pays']], 'strlen'))) . '</span>'
        . '<div class="item__actions">' . etiquette_statut($r['statut']) . ' <a class="bouton bouton--petit" href="' . e(url('concert', ['id' => $r['concert_id']])) . '">Ouvrir la fiche</a></div></li>';
    return $s;
};
?>
<div class="titre"><h1>Tableau de bord</h1><span class="titre__suite"><?= e(date_fr($jour, true)) ?></span></div>

<div class="bord">
  <section class="carte" aria-labelledby="b-jour">
    <div class="bloc__tete"><h2 id="b-jour">Aujourd'hui</h2><span class="bloc__compte"><?= count($aujourdhui) ?></span></div>
    <?php if (!$aujourdhui): ?><p class="rien">Pas de concert aujourd'hui.</p><?php else: ?>
      <ul class="items"><?php foreach ($aujourdhui as $r) echo $ligne_concert($r); ?></ul>
    <?php endif; ?>
  </section>

  <section class="carte" aria-labelledby="b-semaine">
    <div class="bloc__tete"><h2 id="b-semaine">Cette semaine</h2><span class="bloc__compte"><?= count($semaine) + count($embargos) ?></span></div>
    <?php if (!$semaine && !$embargos): ?><p class="rien">Rien dans les sept prochains jours.</p><?php endif; ?>
    <?php if ($semaine): ?><ul class="items"><?php foreach ($semaine as $r) echo $ligne_concert($r); ?></ul><?php endif; ?>
    <?php if ($embargos): ?>
      <h3 style="margin-top:.9rem">Embargos à échéance</h3>
      <ul class="items"><?php foreach ($embargos as $r): ?>
        <li><span class="item__titre"><?= e($r['titre_fr']) ?>, <?= e(date_fr($r['date'])) ?></span>
          <span><?= $r['embargo_le'] <= $jour ? '<span class="puce puce--alerte">embargo levé</span> depuis le ' : 'Annonce prévue le ' ?><?= e(date_fr($r['embargo_le'])) ?></span>
          <div class="item__actions"><a class="bouton bouton--petit" href="<?= e(url('concert', ['id' => $r['concert_id']])) ?>">Ouvrir la fiche</a></div></li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
  </section>

  <section class="carte" aria-labelledby="b-valider">
    <div class="bloc__tete"><h2 id="b-valider">À valider</h2><span class="bloc__compte"><?= $a_valider ?></span></div>
    <?php if (!$a_valider): ?><p class="rien">Rien n'attend de validation.</p><?php endif; ?>
    <ul class="items">
    <?php foreach ($imports as $i): ?>
      <li><span class="item__titre">Extrait de l'agence<?= $i['date_extrait'] ? ' du ' . e(date_fr($i['date_extrait'])) : '' ?></span>
        <span><?= $i['statut'] === 'a_valider' ? e(pluriel((int) $i['attente'], 'changement en attente', 'changements en attente')) : 'lecture en cours' ?></span>
        <div class="item__actions"><a class="bouton bouton--petit bouton--plein" href="<?= e(url('import', ['id' => $i['id']])) ?>"><?= $i['statut'] === 'a_valider' ? 'Valider, corriger, refuser' : 'Suivre' ?></a></div></li>
    <?php endforeach; ?>
    <?php foreach ($comptes_proposes as $c): ?>
      <li><span class="item__titre"><?= e($c['nom']) ?> · <?= e(RESEAUX_CONNUS[$c['reseau']] ?? $c['reseau']) ?></span>
        <span>Compte proposé : <?= e($c['adresse']) ?></span>
        <div class="item__actions">
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="compte_social"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="etat" value="verifie"><button type="submit" class="bouton bouton--petit bouton--plein">Valider</button></form>
          <a class="bouton bouton--petit" href="<?= e(url('fiche', ['id' => $c['fiche_id']])) ?>">Corriger</a>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="compte_social"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="etat" value="a_revoir"><button type="submit" class="bouton bouton--petit">Refuser</button></form>
        </div></li>
    <?php endforeach; ?>
    <?php foreach ($notes as $n): ?>
      <li><span class="item__titre">Note de <?= e($n['nom']) ?> · <?= e($n['titre_fr']) ?></span>
        <span><?= nl2br(e($n['texte'])) ?></span><span class="discret"><?= e(horodatage_fr($n['cree_le'])) ?></span>
        <div class="item__actions">
          <a class="bouton bouton--petit" href="<?= e(url('concert', ['id' => $n['concert_id']])) ?>">Ouvrir la fiche</a>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="note"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><button type="submit" class="bouton bouton--petit bouton--plein">Traitée</button></form>
        </div></li>
    <?php endforeach; ?>
    </ul>
  </section>

  <section class="carte" aria-labelledby="b-site">
    <div class="bloc__tete"><h2 id="b-site">Le site</h2></div>
    <ul class="items">
      <li><span class="discret">Dernière publication</span><span><?= $derniere ? e(horodatage_fr($derniere['cree_le'])) . ' <span class="discret">par ' . e($derniere['compte_nom']) . '</span>' : 'Pupitre n\'a pas encore publié' ?></span></li>
      <li><span class="discret">Écarts en attente</span><span><?= $ecarts === null ? '<span class="puce puce--erreur">site introuvable</span>' : ($ecarts ? '<span class="puce puce--alerte">' . e(pluriel($ecarts, 'écart', 'écarts')) . '</span>' : '<span class="puce puce--ok">aucun</span>') ?></span></li>
    </ul>
    <div class="actions">
      <a class="bouton" href="<?= e(url('site')) ?>">Prévisualiser</a>
      <?php if (peut('site.publier')): ?>
        <form method="post" action="<?= e(url('site')) ?>"><?= champ_csrf() ?><input type="hidden" name="action" value="publier"><button type="submit" class="bouton bouton--plein"<?= $ecarts ? '' : ' disabled' ?> data-confirmer="Publier vers le site ?">Publier</button></form>
        <?php if ($derniere): ?><form method="post" action="<?= e(url('site')) ?>"><?= champ_csrf() ?><input type="hidden" name="action" value="revenir"><button type="submit" class="bouton bouton--danger" data-confirmer="Remettre le site dans son état d'avant la dernière publication ?">Revenir en arrière</button></form><?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="carte bord__large" aria-labelledby="b-alertes">
    <div class="bloc__tete"><h2 id="b-alertes">Alertes</h2><span class="bloc__compte"><?= count($alertes) ?><?= $cachees ? ' · ' . $cachees . ' reportée(s) ou ignorée(s)' : '' ?></span></div>
    <?php if (!$alertes): ?><p class="rien">Aucune alerte ouverte.</p><?php else: ?>
    <ul class="items">
    <?php foreach ($alertes as $a): ?>
      <li><span class="item__titre"><?= e(TYPES_ALERTE[$a['type']] ?? $a['type']) ?></span>
        <span><?= e($a['message']) ?></span>
        <div class="item__actions">
          <a class="bouton bouton--petit bouton--plein" href="<?= e(chemin_pupitre() . '/index.php?' . $a['lien']) ?>">Traiter</a>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="alerte"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="quoi" value="reporter"><button type="submit" class="bouton bouton--petit">Reporter d'une semaine</button></form>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="alerte"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="quoi" value="ignorer"><button type="submit" class="bouton bouton--petit">Ignorer</button></form>
        </div></li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if ($cachees): ?>
    <details style="margin-top:.8rem"><summary>Alertes reportées ou ignorées</summary>
      <ul class="items"><?php foreach (alertes_lire(true) as $a): if ($a['etat'] === 'ouverte') continue; ?>
        <li><span><?= e($a['message']) ?></span><span class="discret"><?= $a['etat'] === 'reportee' ? 'reportée au ' . e(date_fr($a['reportee_au'])) : 'ignorée' ?></span>
          <form method="post" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="alerte"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="quoi" value="rouvrir"><button type="submit" class="bouton bouton--petit">Rouvrir</button></form></li>
      <?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
    <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="recalculer"><button type="submit" class="bouton bouton--petit">Recalculer les alertes</button>
      <span class="aide">Elles le sont après chaque modification, et chaque nuit par le cron.</span></form>
  </section>
</div>
<?php if (taches_en_cours()): ?>
<form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="lancer"><button type="submit" class="bouton bouton--petit">Lancer maintenant</button><span class="aide"><?= e(pluriel(taches_en_cours(), 'tâche de fond attend', 'tâches de fond attendent')) ?>.</span></form>
<?php endif; ?>

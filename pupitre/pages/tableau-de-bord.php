<?php
/**
 * Tableau de bord : l'accueil de Pupitre. Ce qui arrive, ce qui attend, ce qui cloche, et de quoi
 * y aller vite.
 *
 *   quatre tuiles            points de vigilance, actions à réaliser, prochain concert, état du site ;
 *   Prochains concerts       les dates à venir, heure de la salle et heure de l'artiste ;
 *   Actions à réaliser       ce qui attend une validation, puis les alertes de niveau « action » ;
 *   Dernières actualisations du site : publications, contenus modifiés ;
 *   Points de vigilance      les alertes prioritaires, puis les informations ;
 *   Prochaines échéances     embargos, actions de communication datées ;
 *   Dernières publications   ce qui a été publié sur les réseaux ou envoyé par lettre ;
 *   Raccourcis.
 *
 * Tout chiffre est lu dans la base : rien n'est estimé, rien n'est inventé. Un bloc sans donnée le dit.
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
    rediriger(recu('retour') === 'notifications' ? url('notifications') : url('bord'));
}

// ---------- ce que l'écran montre ----------
$fuseau_artiste = reglage('fuseau');
$a_venir = agenda_representations(['sauf_annulees' => true, 'du' => $jour]);
$prochains = array_slice($a_venir, 0, 6);
$prochain = $a_venir[0] ?? null;

// ce qui attend une validation
$imports = bd_lignes("SELECT i.*, (SELECT COUNT(*) FROM import_lignes l WHERE l.import_id = i.id AND l.decision = 'en_attente') AS attente
    FROM imports i WHERE i.statut IN ('a_valider', 'depose', 'lecture') ORDER BY i.id DESC");
$comptes_proposes = bd_lignes("SELECT x.*, a.nom FROM annuaire_comptes x JOIN annuaire a ON a.id = x.fiche_id WHERE x.etat = 'propose' ORDER BY x.id DESC LIMIT 30");
$notes = bd_lignes("SELECT n.*, c.titre_fr, k.nom FROM notes_artiste n JOIN concerts c ON c.id = n.concert_id JOIN comptes k ON k.id = n.compte_id WHERE n.statut = 'a_traiter' ORDER BY n.id DESC");
$a_valider = count($imports) + count($comptes_proposes) + count($notes);

// alertes, par niveau
$alertes = alertes_lire();
$par_niveau = ['prioritaire' => [], 'action' => [], 'info' => []];
foreach ($alertes as $a) $par_niveau[isset($par_niveau[$a['niveau'] ?? '']) ? $a['niveau'] : 'action'][] = $a;
$cachees = (int) bd_valeur("SELECT COUNT(*) FROM alertes WHERE etat <> 'ouverte'");
$n_actions = $a_valider + count($par_niveau['action']);

// le site
$derniere = publication_derniere();
try { $ecarts = publication_etat()['ecarts']; } catch (Throwable $e) { $ecarts = null; }
$publications_site = bd_lignes("SELECT * FROM publications WHERE annulee = 0 AND nature = 'publication' ORDER BY id DESC LIMIT 3");
$actualisations = bd_lignes("SELECT * FROM journal WHERE objet_type NOT IN ('site', 'communication', 'import', 'compte', 'sauvegarde', 'base', 'reprise') AND action IN ('creation', 'modification', 'suppression', 'statut', 'validation')
    ORDER BY id DESC LIMIT 6");

// échéances des trente prochains jours (et celles qui sont passées sans avoir été tenues)
$horizon = ajouter_jours($jour, 30);
$echeances = [];
foreach (bd_lignes("SELECT r.id, r.date, r.embargo_le, r.concert_id, c.titre_fr FROM representations r JOIN concerts c ON c.id = r.concert_id
                    WHERE r.statut = 'embargo' AND r.embargo_le IS NOT NULL AND r.embargo_le <= ? ORDER BY r.embargo_le", [$horizon]) as $r) {
    $echeances[] = ['date' => $r['embargo_le'], 'titre' => 'Fin d\'embargo · ' . $r['titre_fr'], 'detail' => 'concert du ' . date_fr($r['date']) . ($r['embargo_le'] <= $jour ? ' : l\'annonce peut être faite' : ''),
        'lien' => url('concert', ['id' => $r['concert_id']]), 'retard' => $r['embargo_le'] <= $jour, 'icone' => 'embargo'];
}
foreach (communications_lister(['au' => $horizon]) as $k) {
    if ($k['statut'] === 'publiee' || !$k['prevue_le']) continue;
    $echeances[] = ['date' => substr($k['prevue_le'], 0, 10), 'titre' => (CANAUX[$k['canal']][0] ?? '') . ' · ' . communication_libelle($k), 'detail' => mb_strtolower(communication_statut($k)) . ($k['concerts'] ? ' · ' . implode(', ', $k['concerts']) : ''),
        'lien' => url('communication', ['id' => $k['id']]), 'retard' => $k['prevue_le'] < date('Y-m-d H:i:s'), 'icone' => CANAUX[$k['canal']][1] ?? 'social'];
}
usort($echeances, fn($x, $y) => $x['date'] <=> $y['date']);

// communication : ce qui a été publié
$publiees = communications_lister(['statut' => 'publiee', 'recentes' => true, 'limite' => 5]);

$prenom = preg_split('/\s+/u', trim((string) (compte()['nom'] ?? '')))[0] ?? '';
$dans = function (string $date) use ($jour): string {
    $n = (int) round((strtotime($date . ' 12:00') - strtotime($jour . ' 12:00')) / 86400);
    return $n <= 0 ? 'aujourd\'hui' : ($n === 1 ? 'demain' : 'dans ' . $n . ' jours');
};
$libelles_action = ['creation' => 'Création', 'modification' => 'Modification', 'suppression' => 'Suppression', 'statut' => 'Changement de statut', 'validation' => 'Validation'];
$boutons_alerte = function (array $a): string {
    $f = fn(string $quoi, string $libelle) => '<form method="post">' . champ_csrf() . '<input type="hidden" name="action" value="alerte"><input type="hidden" name="id" value="' . (int) $a['id'] . '"><input type="hidden" name="quoi" value="' . $quoi . '">'
        . '<button type="submit" class="bouton bouton--petit">' . $libelle . '</button></form>';
    return '<a class="bouton bouton--petit bouton--plein" href="' . e(chemin_pupitre() . '/index.php?' . $a['lien']) . '">Traiter</a>' . $f('reporter', 'Reporter d\'une semaine') . $f('ignorer', 'Ignorer');
};
?>
<div class="accueil">
  <div>
    <h1><?= $prenom !== '' ? 'Bonjour ' . e($prenom) : 'Tableau de bord' ?></h1>
    <p><?= e(ucfirst(date_fr($jour, true))) ?><?= $prochain ? ' · prochain concert ' . e($dans($prochain['date'])) : '' ?></p>
  </div>
  <div class="titre__actions">
    <?php if (peut('agenda')): ?><a class="bouton" href="<?= e(url('concert', ['id' => 'nouveau'])) ?>"><?= icone('plus') ?>Nouveau concert</a><?php endif; ?>
    <?php if (peut('communication')): ?><a class="bouton bouton--doux" href="<?= e(url('communication', ['id' => 'nouvelle'])) ?>"><?= icone('communication') ?>Prévoir une communication</a><?php endif; ?>
  </div>
</div>

<div class="tuiles">
  <?= tuile('alerte', 'Points de vigilance', count($par_niveau['prioritaire']) ? 'à traiter sans attendre' : 'aucune alerte prioritaire', ['lien' => '#b-vigilance', 'chiffre' => count($par_niveau['prioritaire']), 'classe' => 'tuile--vigilance']) ?>
  <?= tuile('actions', 'Actions à réaliser', $n_actions ? ($a_valider ? pluriel($a_valider, 'validation en attente', 'validations en attente') : 'à traiter') : 'rien n\'attend', ['lien' => '#b-actions', 'chiffre' => $n_actions, 'classe' => 'tuile--actions']) ?>
  <?php if ($prochain): ?>
    <?= tuile('concerts', 'Prochain concert', ucfirst($dans($prochain['date'])) . ' · ' . $prochain['titre_fr'], ['lien' => url('concert', ['id' => $prochain['concert_id']]), 'classe' => 'tuile--calme']) ?>
  <?php else: ?>
    <?= tuile('concerts', 'Prochain concert', 'aucune date à venir', ['lien' => url('agenda'), 'classe' => 'tuile--calme']) ?>
  <?php endif; ?>
  <?php if ($ecarts === null): ?>
    <?= tuile('alerte', 'Site introuvable', 'vérifier sa racine dans les réglages', ['lien' => url('reglages'), 'classe' => 'tuile--plein']) ?>
  <?php elseif ($ecarts): ?>
    <?= tuile('publier', 'Publier le site', pluriel($ecarts, 'modification attend', 'modifications attendent'), ['lien' => url('site'), 'classe' => 'tuile--plein']) ?>
  <?php else: ?>
    <?= tuile('ok', 'Site à jour', $derniere ? 'publié ' . il_y_a($derniere['cree_le']) : 'aucune publication encore', ['lien' => url('site'), 'classe' => 'tuile--plein']) ?>
  <?php endif; ?>
</div>

<div class="bord">
  <div class="bord__colonne">
    <section class="carte" aria-labelledby="b-concerts">
      <div class="bloc__tete"><h2 id="b-concerts">Prochains concerts</h2><span class="bloc__compte"><?= count($a_venir) ?></span><a class="bloc__lien" href="<?= e(url('agenda')) ?>">Tous les concerts</a></div>
      <?php if (!$prochains): ?>
        <?= etat_vide('concerts', 'Aucune date à venir', 'Les prochaines dates apparaîtront ici dès qu\'elles seront dans l\'agenda.', peut('agenda') ? '<a class="bouton bouton--petit" href="' . e(url('concert', ['id' => 'nouveau'])) . '">Nouveau concert</a>' : '') ?>
      <?php else: ?>
      <ul class="liste-dates">
        <?php foreach ($prochains as $r): $ici = heure_artiste($r['date'], $r['heure'], $r['fuseau']); ?>
          <li>
            <?= pastille_date($r['date'], $r['date'] === $jour ? 'pastille-date--jour' : '') ?>
            <div class="liste-dates__corps">
              <a class="liste-dates__titre" href="<?= e(url('concert', ['id' => $r['concert_id']])) ?>"><?= e($r['titre_fr']) ?></a>
              <span class="liste-dates__detail"><?= e(implode(', ', array_filter([$r['lieu_salle'], $r['lieu_ville'], $r['lieu_pays']], 'strlen'))) ?: 'lieu à renseigner' ?></span>
              <span class="liste-dates__detail">
                <?php if ($r['type'] === 'disque'): ?>Parution
                <?php elseif ($r['heure']): ?><?= e(heure_fr($r['heure'])) ?> heure de la salle<?= $ici !== '' ? ' · ' . e($ici) . ' heure de l\'artiste' : ($r['fuseau'] === '' ? ' · fuseau de la salle à préciser' : '') ?>
                <?php else: ?><span class="puce puce--alerte">heure à renseigner</span><?php endif; ?>
              </span>
            </div>
            <div class="liste-dates__fin"><?= etiquette_statut($r['statut']) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <section class="carte" aria-labelledby="b-actions">
      <div class="bloc__tete"><h2 id="b-actions">Actions à réaliser</h2><span class="bloc__compte"><?= $n_actions ?></span><a class="bloc__lien" href="<?= e(url('notifications')) ?>">Centre de notifications</a></div>
      <?php if (!$n_actions): ?><?= etat_vide('ok', 'Rien n\'attend', 'Aucune validation en attente, aucune action à traiter.') ?><?php endif; ?>
      <div id="b-valider">
      <?php if ($a_valider): ?>
        <h3>À valider</h3>
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
      <?php endif; ?>
      </div>
      <?php if ($par_niveau['action']): ?>
        <?php if ($a_valider): ?><h3 style="margin-top:1.2rem">À traiter</h3><?php endif; ?>
        <ul class="items">
        <?php foreach ($par_niveau['action'] as $a): ?>
          <li><span class="item__titre"><?= e(TYPES_ALERTE[$a['type']] ?? $a['type']) ?></span>
            <span><?= e($a['message']) ?></span>
            <div class="item__actions"><?= $boutons_alerte($a) ?></div></li>
        <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="carte" aria-labelledby="b-site">
      <div class="bloc__tete"><h2 id="b-site">Dernières actualisations du site</h2><a class="bloc__lien" href="<?= e(url('site')) ?>">Publication</a></div>
      <?php if ($ecarts): ?><p class="encart"><?= icone('publier') ?><span><strong><?= e(pluriel($ecarts, 'modification attend', 'modifications attendent')) ?> d'être publiée<?= $ecarts > 1 ? 's' : '' ?>.</strong> Le site ne change pas tant que vous n'avez pas publié.</span></p><?php endif; ?>
      <?php if (!$publications_site && !$actualisations): ?>
        <?= etat_vide('site', 'Rien encore', 'Les publications du site et les contenus modifiés apparaîtront ici.') ?>
      <?php endif; ?>
      <?php if ($publications_site): ?>
        <h3<?= $ecarts ? ' style="margin-top:1.2rem"' : '' ?>>Publications</h3>
        <ul class="fil-activite fil-activite--publication">
          <?php foreach ($publications_site as $p): $f = json_decode((string) $p['fichiers'], true) ?: []; $nf = count($f['remplaces'] ?? []) + count($f['crees'] ?? []); ?>
            <li><span class="fil-activite__quoi">Site publié · version <?= e($p['version']) ?></span>
              <span class="fil-activite__qui"><?= e(horodatage_fr($p['cree_le'])) ?> · <?= e($p['compte_nom']) ?><?= $nf ? ' · ' . e(pluriel($nf, 'fichier', 'fichiers')) : '' ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($actualisations): ?>
        <h3 style="margin-top:1.2rem">Contenus modifiés</h3>
        <ul class="fil-activite">
          <?php foreach ($actualisations as $l): ?>
            <li><span class="fil-activite__quoi"><?= e($libelles_action[$l['action']] ?? $l['action']) ?> · <?= e(couper($l['objet_libelle'], 90)) ?></span>
              <span class="fil-activite__qui"><?= e(il_y_a($l['cree_le'])) ?> · <?= e($l['compte_nom'] ?: '—') ?></span></li>
          <?php endforeach; ?>
        </ul>
        <?php if (peut('journal')): ?><p class="actions"><a class="bouton bouton--petit" href="<?= e(url('journal')) ?>">Tout le journal</a></p><?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <div class="bord__colonne">
    <section class="carte" aria-labelledby="b-vigilance">
      <div class="bloc__tete"><h2 id="b-vigilance">Points de vigilance</h2><span class="bloc__compte"><?= count($par_niveau['prioritaire']) ?></span></div>
      <?php if (!$par_niveau['prioritaire']): ?><?= etat_vide('bouclier', 'Aucune alerte prioritaire', 'Pas d\'échéance dépassée, rien qui cloche.') ?><?php else: ?>
      <div>
        <?php foreach ($par_niveau['prioritaire'] as $a): ?>
          <?= notification_afficher(['niveau' => 'prioritaire', 'icone' => 'alerte', 'type' => TYPES_ALERTE[$a['type']] ?? $a['type'], 'message' => $a['message'], 'quand' => null], $boutons_alerte($a)) ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($par_niveau['info']): ?>
        <details style="margin-top:1rem"><summary>Informations (<?= count($par_niveau['info']) ?>) : à savoir, rien ne presse</summary>
          <ul class="items">
          <?php foreach ($par_niveau['info'] as $a): ?>
            <li><span class="item__titre"><?= e(TYPES_ALERTE[$a['type']] ?? $a['type']) ?></span><span><?= e($a['message']) ?></span>
              <div class="item__actions"><?= $boutons_alerte($a) ?></div></li>
          <?php endforeach; ?>
          </ul>
        </details>
      <?php endif; ?>
      <?php if ($cachees): ?>
        <details style="margin-top:.8rem"><summary>Alertes reportées ou ignorées (<?= $cachees ?>)</summary>
          <ul class="items"><?php foreach (alertes_lire(true) as $a): if ($a['etat'] === 'ouverte') continue; ?>
            <li><span><?= e($a['message']) ?></span><span class="discret"><?= $a['etat'] === 'reportee' ? 'reportée au ' . e(date_fr($a['reportee_au'])) : 'ignorée' ?></span>
              <form method="post" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="alerte"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="quoi" value="rouvrir"><button type="submit" class="bouton bouton--petit">Rouvrir</button></form></li>
          <?php endforeach; ?></ul>
        </details>
      <?php endif; ?>
    </section>

    <section class="carte" aria-labelledby="b-echeances">
      <div class="bloc__tete"><h2 id="b-echeances">Prochaines échéances</h2><span class="bloc__compte"><?= count($echeances) ?></span><?php if (peut('communication')): ?><a class="bloc__lien" href="<?= e(url('editorial')) ?>">Calendrier éditorial</a><?php endif; ?></div>
      <?php if (!$echeances): ?>
        <?= etat_vide('horloge', 'Aucune échéance dans les trente jours', 'Fins d\'embargo et actions de communication datées apparaîtront ici.') ?>
      <?php else: ?>
      <ul class="liste-dates">
        <?php foreach (array_slice($echeances, 0, 6) as $x): ?>
          <li>
            <?= pastille_date($x['date'], $x['retard'] ? 'pastille-date--alerte' : '') ?>
            <div class="liste-dates__corps"><a class="liste-dates__titre" href="<?= e($x['lien']) ?>"><?= e($x['titre']) ?></a><span class="liste-dates__detail"><?= e($x['detail']) ?></span></div>
            <div class="liste-dates__fin"><?= $x['retard'] ? '<span class="puce puce--erreur">' . icone('alerte') . 'date passée</span>' : '<span class="discret">' . e($dans($x['date'])) . '</span>' ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>

    <?php if (peut('communication')): ?>
    <section class="carte" aria-labelledby="b-publications">
      <div class="bloc__tete"><h2 id="b-publications">Dernières publications</h2><a class="bloc__lien" href="<?= e(url('social')) ?>">Social Media</a></div>
      <?php if (!$publiees): ?>
        <?= etat_vide('social', 'Aucune publication enregistrée', 'Les publications des réseaux et les lettres envoyées apparaîtront ici une fois notées dans Pupitre.', '<a class="bouton bouton--petit" href="' . e(url('communication', ['id' => 'nouvelle'])) . '">Enregistrer une publication</a>') ?>
      <?php else: ?>
      <ul class="items">
        <?php foreach ($publiees as $k): ?>
          <li><span class="en-ligne"><?= canal_afficher($k['canal']) ?> <span class="discret"><?= e(il_y_a($k['publiee_le'])) ?></span></span>
            <a class="item__titre" href="<?= e(url('communication', ['id' => $k['id']])) ?>"><?= e(communication_libelle($k)) ?></a>
            <?php if ($k['concerts']): ?><span class="discret"><?= e(implode(' · ', $k['concerts'])) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="carte" aria-labelledby="b-raccourcis">
      <div class="bloc__tete"><h2 id="b-raccourcis">Raccourcis</h2></div>
      <div class="raccourcis">
        <?php if (peut('agenda')): ?><a class="raccourci" href="<?= e(url('concert', ['id' => 'nouveau'])) ?>"><?= icone('concerts') ?>Nouveau concert</a><?php endif; ?>
        <?php if (peut('import')): ?><a class="raccourci" href="<?= e(url('imports')) ?>"><?= icone('import') ?>Importer l'agenda de l'agence</a><?php endif; ?>
        <?php if (peut('contenus')): ?><a class="raccourci" href="<?= e(url('article', ['id' => 'nouveau'])) ?>"><?= icone('presse') ?>Nouvel article de presse</a><?php endif; ?>
        <?php if (peut('communication')): ?><a class="raccourci" href="<?= e(url('communication', ['id' => 'nouvelle'])) ?>"><?= icone('communication') ?>Prévoir une communication</a><?php endif; ?>
        <?php if (peut('contenus')): ?><a class="raccourci" href="<?= e(url('blocs')) ?>"><?= icone('textes') ?>Textes du site</a><?php endif; ?>
        <?php if (peut('medias')): ?><a class="raccourci" href="<?= e(url('medias')) ?>"><?= icone('medias') ?>Médiathèque</a><?php endif; ?>
        <?php if (peut('site.voir')): ?><a class="raccourci" href="<?= e(url('site')) ?>"><?= icone('publier') ?>Publier le site</a><?php endif; ?>
        <a class="raccourci" href="<?= e(url('artiste')) ?>"><?= icone('artiste') ?>Vue de l'artiste</a>
      </div>
    </section>
  </div>
</div>

<form method="post" class="actions"><?= champ_csrf() ?>
  <button type="submit" name="action" value="recalculer" class="bouton bouton--petit"><?= icone('actualiser') ?>Recalculer les alertes</button>
  <?php if (taches_en_cours()): ?><button type="submit" name="action" value="lancer" class="bouton bouton--petit">Lancer les tâches de fond</button><?php endif; ?>
  <span class="aide">Les alertes sont recalculées après chaque modification, et chaque nuit.<?= taches_en_cours() ? ' ' . e(pluriel(taches_en_cours(), 'tâche de fond attend', 'tâches de fond attendent')) . '.' : '' ?></span>
</form>

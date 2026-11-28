<?php
/**
 * Une action de communication : une publication sur un réseau, ou une lettre. Son canal, son texte,
 * son statut, ses dates, les concerts qu'elle concerne.
 *
 *   ?id=nouvelle                  une nouvelle publication ;
 *   ?id=nouvelle&famille=lettre   une nouvelle lettre ;
 *   ?id=nouvelle&concert=12       prérattachée à un concert (depuis le tableau de bord éditorial ou la fiche du concert).
 */

defined('PUPITRE') or exit;

$nouvelle = recu('id') === 'nouvelle';
$id = $nouvelle ? null : recu_entier('id');
$a = $id ? communication_lire($id) : null;
if (!$nouvelle && !$a) {
    http_response_code(404);
    $titre = 'Action introuvable';
    echo '<div class="vide"><h1>Action introuvable</h1><p><a class="bouton" href="' . e(url('editorial')) . '">Tableau de bord éditorial</a></p></div>';
    return;
}
$famille = $a ? canal_famille($a['canal']) : (recu('famille') === 'lettre' ? 'lettre' : 'social');
$GLOBALS['communication_famille'] = $famille;        // pour le menu et le fil d'Ariane
$lettre = $famille === 'lettre';
$ecran = $lettre ? 'newsletter' : 'social';
$erreurs = [];

if (est_post() && recu('action') === 'supprimer' && $a) {
    communication_supprimer($id);
    message('ok', $lettre ? 'Lettre supprimée.' : 'Publication supprimée.');
    rediriger(url($ecran));
}
if (est_post() && recu('action') === 'enregistrer') {
    [$nouvel_id, $erreurs] = communication_enregistrer($id, $_POST + ['concerts' => is_array($_POST['concerts'] ?? null) ? $_POST['concerts'] : []]);
    if (!$erreurs && $nouvelle && ($signalement = recu_entier('veille')) && function_exists('veille_decider')) {
        veille_decider($signalement, 'partagee', ['communication_id' => $nouvel_id]);       // le signalement de la veille que cette publication partage
    }
    if (!$erreurs) {
        message('ok', $nouvelle ? ($lettre ? 'Lettre enregistrée.' : 'Publication enregistrée.') : 'Enregistré.');
        rediriger(url('communication', ['id' => $nouvel_id]));
    }
}

// ce que le formulaire montre : la fiche, ou ce qui vient d'être saisi
$saisie = est_post() && $erreurs;
$f = $a ?? ['canal' => $lettre ? 'lettre' : '', 'titre' => '', 'texte' => '', 'statut' => 'a_preparer', 'prevue_le' => null, 'publiee_le' => null, 'lien' => '', 'notes' => '', 'concerts' => array_filter([recu_entier('concert')])];
// une publication qui partage un signalement de la veille : son titre et son adresse sont repris
$partage = ($nouvelle && recu_entier('veille') && bd_colonne_existe('veille', 'empreinte')) ? bd_ligne('SELECT * FROM veille WHERE id = ?', [recu_entier('veille')]) : null;
if ($partage && !$saisie) {
    $f['titre'] = mb_substr('Partage : ' . $partage['titre'], 0, 255);
    $f['texte'] = '« ' . $partage['titre'] . ' »' . ($partage['site'] !== '' ? ' · ' . $partage['site'] : '') . "\n" . $partage['lien'];
    $f['notes'] = 'Signalement de la veille du ' . date_fr(substr($partage['cree_le'], 0, 10)) . ($partage['mention'] === 'cite' ? ' : l\'artiste y est cité sans être identifié.' : '.');
}
$v = fn(string $k) => $saisie ? recu($k) : (string) ($f[$k] ?? '');
$date = fn(string $k, string $champ) => $saisie ? recu($champ) : ($f[$k] ? substr($f[$k], 0, 10) : '');
$heure = fn(string $k, string $champ) => $saisie ? recu($champ) : (($f[$k] && substr($f[$k], 11, 5) !== '00:00') ? substr($f[$k], 11, 5) : '');
$coches = $saisie ? array_map('intval', is_array($_POST['concerts'] ?? null) ? $_POST['concerts'] : []) : array_map('intval', $f['concerts']);
$titre = $nouvelle ? ($lettre ? 'Nouvelle lettre' : 'Nouvelle publication') : communication_libelle($a);
$jour = aujourdhui();
// les concerts à proposer : à venir d'abord, puis les plus récents ; ceux qui sont déjà associés restent toujours visibles
$concerts = bd_lignes("SELECT c.id, c.titre_en, c.publie, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id AND r.date >= ? AND r.statut <> 'annulee') AS prochaine,
    (SELECT MAX(r.date) FROM representations r WHERE r.concert_id = c.id) AS derniere,
    (SELECT COUNT(*) FROM representations r WHERE r.concert_id = c.id AND r.statut IN ('annoncee', 'publiee')) AS annoncees
    FROM concerts c ORDER BY prochaine IS NULL, prochaine, derniere DESC", [$jour]);
$statuts = [];
foreach (STATUTS_COMMUNICATION as $k => $s) $statuts[$k] = $s[$lettre ? 1 : 0];
?>
<p class="fil"><a href="<?= e(url($ecran)) ?>"><?= e(FAMILLES_COMMUNICATION[$famille]) ?></a></p>
<div class="titre">
  <h1><?= e($titre) ?></h1>
  <?php if ($a): ?><span class="titre__suite"><?= canal_afficher($a['canal']) ?> <?= communication_puce($a) ?></span><?php endif; ?>
</div>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="deux">
<div>
  <form method="post" class="carte" id="fiche">
    <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
    <?php if ($partage): ?><input type="hidden" name="veille" value="<?= (int) $partage['id'] ?>">
      <p class="encart" style="margin-bottom:1rem"><?= icone('veille') ?><span><strong>Partage d'un signalement de la veille.</strong> <?= e($partage['titre']) ?><?= $partage['site'] !== '' ? ' · ' . e($partage['site']) : '' ?>. Choisissez le réseau, ajustez le texte : à l'enregistrement, le signalement sera marqué « partagé ».</span></p>
    <?php endif; ?>
    <h2><?= $lettre ? 'La lettre' : 'La publication' ?></h2>
    <?php if ($lettre): ?>
      <input type="hidden" name="canal" value="lettre">
    <?php else: ?>
      <?= champ_choix('canal', 'Réseau', canaux_de('social'), $v('canal'), ['vide' => '— choisir —', 'requis' => true]) ?>
    <?php endif; ?>
    <?= champ('titre', $lettre ? 'Objet de la lettre' : 'Intitulé', $v('titre'), ['max' => 255, 'aide' => $lettre ? 'Ce que le destinataire lit dans sa boîte.' : 'Quelques mots pour la reconnaître dans les listes. Facultatif si le texte est saisi.']) ?>
    <?= champ_texte('texte', $lettre ? 'Texte, ou plan de la lettre' : 'Texte de la publication', $v('texte'), ['lignes' => 7, 'aide' => $lettre ? '' : 'Le texte prévu, ou celui qui a été publié.']) ?>

    <h2>Statut et dates</h2>
    <div class="colonnes">
      <?= champ_choix('statut', 'Statut', $statuts, $v('statut') ?: 'a_preparer', ['aide' => 'Programmée : une date prévue est requise. ' . ($lettre ? 'Envoyée' : 'Publiée') . ' : sa date réelle aussi.']) ?>
      <?= champ('date_prevue', 'Date prévue', $date('prevue_le', 'date_prevue'), ['type' => 'date']) ?>
      <?= champ('heure_prevue', 'Heure prévue', $heure('prevue_le', 'heure_prevue'), ['indication' => '18:30', 'max' => 5, 'classe' => 'champ--court']) ?>
    </div>
    <div class="colonnes">
      <?= champ('date_publiee', $lettre ? 'Date d\'envoi' : 'Date de publication', $date('publiee_le', 'date_publiee'), ['type' => 'date', 'aide' => 'À remplir quand c\'est fait.']) ?>
      <?= champ('heure_publiee', 'Heure', $heure('publiee_le', 'heure_publiee'), ['indication' => '18:30', 'max' => 5, 'classe' => 'champ--court']) ?>
      <?= champ('lien', $lettre ? 'Adresse de la lettre en ligne' : 'Adresse de la publication', $v('lien'), ['type' => 'url', 'indication' => 'https://…', 'max' => 500]) ?>
    </div>

    <h2>Concerts concernés</h2>
    <p class="aide aide--haut">Une action peut concerner plusieurs concerts<?= $lettre ? ' : une lettre annonce souvent ceux du mois' : '' ?>. C'est ce lien qui fait dire au tableau de bord éditorial qu'un concert a sa communication.</p>
    <div class="champ"><label for="chercher-concert">Chercher un concert</label><input type="search" id="chercher-concert" data-filtrer-cases="#liste-concerts" placeholder="titre, année…" autocomplete="off"></div>
    <ul class="cases cases--defile" id="liste-concerts">
      <?php foreach ($concerts as $c): $quand = $c['prochaine'] ?: $c['derniere']; ?>
        <li data-texte="<?= e(mb_strtolower($c['titre_en'] . ' ' . $quand)) ?>"><label><input type="checkbox" name="concerts[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $coches, true) ? ' checked' : '' ?>> <?= e($c['titre_en']) ?></label>
          <span class="discret"><?= e(date_fr($quand)) ?><?= $c['prochaine'] ? '' : ' · passé' ?><?= ($c['prochaine'] && !(int) $c['annoncees']) ? ' · pas encore annoncé' : '' ?><?= $c['publie'] ? '' : ' · dépublié' ?></span></li>
      <?php endforeach; ?>
    </ul>

    <h2>Notes internes</h2>
    <?= champ_texte('notes', 'Notes', $v('notes'), ['lignes' => 3, 'aide' => 'Visuel prévu, personnes à mentionner, validation attendue… Rien de ceci n\'est publié.']) ?>

    <div class="actions actions--bas">
      <button type="submit" class="bouton bouton--plein"><?= $nouvelle ? 'Enregistrer' : 'Enregistrer les modifications' ?></button>
      <a class="bouton" href="<?= e(url($ecran)) ?>">Retour à la liste</a>
    </div>
  </form>
  <?php if ($a): ?>
  <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer">
    <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Supprimer définitivement cette <?= $lettre ? 'lettre' : 'publication' ?> de Pupitre ?"><?= icone('corbeille') ?>Supprimer</button></form>
  <?php endif; ?>
</div>

<div>
  <div class="carte">
    <h2>Avant de communiquer</h2>
    <ul class="items">
      <li><span class="item__titre">Un concert annoncé seulement</span><span class="discret">On ne communique pas sur une date « connue » ou « sous embargo » : l'annonce revient à l'organisateur.</span></li>
      <li><span class="item__titre">Les noms officiels</span><span class="discret">Orchestres, salles et solistes : leurs comptes et leurs mots-dièse sont sur leur fiche, dans l'annuaire.</span></li>
      <li><span class="item__titre">Rien ne part d'ici</span><span class="discret">Pupitre garde la trace : la publication elle-même se fait sur le réseau, ou dans l'outil d'envoi de la lettre.</span></li>
    </ul>
  </div>
  <?php if ($a && !$lettre):
      $parties = parties_prenantes($a['concerts']);
      $reseau = $a['canal'];
      $identifiants = [];
      foreach ($parties as $x) if (($x['comptes'][$reseau]['etat'] ?? '') === 'verifie' && $x['comptes'][$reseau]['identifiant'] !== '') $identifiants[] = $x['comptes'][$reseau]['identifiant'];
      $dieses = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', implode(' ', array_column($parties, 'mots_diese'))), fn($m) => $m !== '')));
      $sans = array_filter($parties, fn($x) => !isset($x['comptes'][$reseau]));
  ?>
  <div class="carte" id="identifier">
    <div class="carte__tete"><h2>Comptes à identifier</h2><span class="carte__compte"><?= count($identifiants) ?></span></div>
    <?php if (!$a['concerts']): ?><p class="rien">Associez un concert à cette publication : ses orchestres, sa salle et ses solistes seront proposés ici, avec leur compte sur ce réseau.</p>
    <?php elseif (!$parties): ?><p class="rien">Les concerts associés n'ont pas encore de fiche reliée dans l'annuaire (orchestre, salle, solistes).</p>
    <?php else: ?>
    <ul class="items">
      <?php foreach ($parties as $x): $c = $x['comptes'][$reseau] ?? null; ?>
        <li><span class="en-ligne"><a class="item__titre" href="<?= e(url('fiche', ['id' => $x['id']])) ?>#comptes"><?= e($x['nom']) ?></a> <span class="discret"><?= e(mb_strtolower(CATEGORIES[$x['categorie']] ?? '')) ?></span></span>
          <?php if ($c && $c['etat'] === 'verifie'): ?><span><code><?= e($c['identifiant'] ?: $c['adresse']) ?></code></span>
          <?php elseif ($c): ?><span><code><?= e($c['identifiant'] ?: $c['adresse']) ?></code> <span class="puce puce--alerte">à vérifier avant de l'employer</span></span>
          <?php else: ?><span class="discret">pas de compte <?= e(CANAUX[$reseau][0] ?? 'sur ce réseau') ?> connu<?= $x['comptes'] ? ' (il en a sur ' . e(implode(', ', array_map(fn($r) => RESEAUX_CONNUS[$r] ?? $r, array_keys($x['comptes'])))) . ')' : '' ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($identifiants || $dieses): ?>
      <div class="champ" style="margin-top:1rem"><label for="identifiants">À coller dans la publication</label><textarea id="identifiants" rows="2" readonly><?= e(trim(implode(' ', $identifiants) . ($dieses ? "\n" . implode(' ', array_map(fn($m) => '#' . ltrim($m, '#'), $dieses)) : ''))) ?></textarea></div>
      <div class="actions"><button type="button" class="bouton bouton--petit" data-copier="#identifiants" data-copie="Copié"><?= icone('copier') ?>Copier</button></div>
    <?php endif; ?>
    <?php if ($sans && peut('annuaire')): ?>
      <form method="post" action="<?= e(url('annuaire')) ?>" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="completer">
        <button type="submit" class="bouton bouton--petit"<?= ia_configuree() ? '' : ' disabled title="L\'IA n\'est pas configurée"' ?>><?= icone('ia') ?>Chercher les comptes manquants avec l'IA</button></form>
    <?php endif; ?>
    <p class="aide">Seuls les comptes vérifiés sont donnés à copier. Un compte proposé par l'IA se vérifie sur la fiche, ou depuis le tableau de bord.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($a): ?>
  <div class="carte">
    <h2>Concerts concernés</h2>
    <?php if (!$a['concerts']): ?><p class="rien">Aucun concert associé.</p><?php else: ?>
    <ul class="items">
      <?php foreach ($a['concerts'] as $cid): $c = bd_ligne('SELECT id, titre_en FROM concerts WHERE id = ?', [$cid]); if (!$c) continue; ?>
        <li><span class="item__titre"><?= e($c['titre_en']) ?></span><a class="bouton bouton--petit" href="<?= e(url('concert', ['id' => $c['id']])) ?>">Ouvrir</a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <div class="carte"><h2>Journal</h2><?= journal_afficher(journal_objet('communication', $id, 12)) ?></div>
  <?php endif; ?>
</div>
</div>

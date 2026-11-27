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
    if (!$erreurs) {
        message('ok', $nouvelle ? ($lettre ? 'Lettre enregistrée.' : 'Publication enregistrée.') : 'Enregistré.');
        rediriger(url('communication', ['id' => $nouvel_id]));
    }
}

// ce que le formulaire montre : la fiche, ou ce qui vient d'être saisi
$saisie = est_post() && $erreurs;
$f = $a ?? ['canal' => $lettre ? 'lettre' : '', 'titre' => '', 'texte' => '', 'statut' => 'a_preparer', 'prevue_le' => null, 'publiee_le' => null, 'lien' => '', 'notes' => '', 'concerts' => array_filter([recu_entier('concert')])];
$v = fn(string $k) => $saisie ? recu($k) : (string) ($f[$k] ?? '');
$date = fn(string $k, string $champ) => $saisie ? recu($champ) : ($f[$k] ? substr($f[$k], 0, 10) : '');
$heure = fn(string $k, string $champ) => $saisie ? recu($champ) : (($f[$k] && substr($f[$k], 11, 5) !== '00:00') ? substr($f[$k], 11, 5) : '');
$coches = $saisie ? array_map('intval', is_array($_POST['concerts'] ?? null) ? $_POST['concerts'] : []) : array_map('intval', $f['concerts']);
$titre = $nouvelle ? ($lettre ? 'Nouvelle lettre' : 'Nouvelle publication') : communication_libelle($a);
$jour = aujourdhui();
// les concerts à proposer : à venir d'abord, puis les plus récents ; ceux qui sont déjà associés restent toujours visibles
$concerts = bd_lignes("SELECT c.id, c.titre_fr, c.publie, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id AND r.date >= ? AND r.statut <> 'annulee') AS prochaine,
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
        <li data-texte="<?= e(mb_strtolower($c['titre_fr'] . ' ' . $quand)) ?>"><label><input type="checkbox" name="concerts[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $coches, true) ? ' checked' : '' ?>> <?= e($c['titre_fr']) ?></label>
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
  <?php if ($a): ?>
  <div class="carte">
    <h2>Concerts concernés</h2>
    <?php if (!$a['concerts']): ?><p class="rien">Aucun concert associé.</p><?php else: ?>
    <ul class="items">
      <?php foreach ($a['concerts'] as $cid): $c = bd_ligne('SELECT id, titre_fr FROM concerts WHERE id = ?', [$cid]); if (!$c) continue; ?>
        <li><span class="item__titre"><?= e($c['titre_fr']) ?></span><a class="bouton bouton--petit" href="<?= e(url('concert', ['id' => $c['id']])) ?>">Ouvrir</a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <div class="carte"><h2>Journal</h2><?= journal_afficher(journal_objet('communication', $id, 12)) ?></div>
  <?php endif; ?>
</div>
</div>

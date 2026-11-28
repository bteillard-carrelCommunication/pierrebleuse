<?php
/**
 * Aperçus et validations : montrer le site à venir avant de le publier, et en faire valider les
 * évolutions.
 *
 *   sans identifiant   la liste des aperçus, et de quoi en créer un (le pilote) ;
 *   id=…               un aperçu : son lien à partager, ce qu'il présente, les demandes de validation
 *                      (un lien personnel par compte, envoyé par mail), les procès-verbaux ;
 *   id=…&pv=…          un procès-verbal seul, à imprimer.
 *
 * Un aperçu est une copie figée : le modifier n'a pas de sens, on en crée un autre. L'éditeur voit
 * tout ; créer, envoyer et retirer reviennent au pilote, comme la publication.
 */

defined('PUPITRE') or exit;

apercus_purger();
$id = recu_entier('id');
$a = $id ? apercu_lire($id) : null;
if ($id && !$a) {
    http_response_code(404);
    $titre = 'Aperçu introuvable';
    echo '<div class="vide"><h1>Aperçu introuvable</h1><p><a class="bouton" href="' . e(url('apercus')) . '">Retour aux aperçus</a></p></div>';
    return;
}
$titre = $a ? 'Aperçu « ' . $a['titre'] . ' »' : 'Aperçus et validations';

if (est_post()) {
    exiger_droit('site.publier');
    $action = recu('action');
    try {
        if ($action === 'creer') {
            $nouveau = apercu_creer(recu('titre'), recu('note'), (int) recu('jours'));
            message('ok', 'Aperçu créé : il est consultable jusqu\'au ' . horodatage_fr($nouveau['expire_le']) . '. Partagez son lien, ou demandez une validation.');
            rediriger(url('apercu', ['id' => $nouveau['id']]));
        } elseif ($a && $action === 'inviter') {
            [$erreur, $lien] = apercu_inviter($a, recu_entier('compte') ?? 0);
            if ($erreur !== '') message('erreur', $erreur);
            else {
                // le lien personnel ne se relit pas : il est montré une fois, ici, au cas où le mail n'arriverait pas
                $_SESSION['apercu_lien'] = ['apercu' => (int) $a['id'], 'compte' => recu_entier('compte'), 'lien' => $lien];
                message('ok', 'Demande de validation envoyée.');
            }
            rediriger(url('apercu', ['id' => $a['id']]) . '#validations');
        } elseif ($a && $action === 'retirer') {
            apercu_retirer((int) $a['id']);
            message('ok', 'Aperçu retiré : son lien ne mène plus à rien. Les validations déjà données restent au dossier.');
            rediriger(url('apercus'));
        }
    } catch (ErreurPublication $e) {
        message('erreur', $e->getMessage());
    }
    rediriger($a ? url('apercu', ['id' => $a['id']]) : url('apercus'));
}

try {
    $etat = publication_etat();
} catch (ErreurPublication $e) {
    $etat = null;
}
$empreinte = $etat ? apercu_empreinte($etat) : '';
$etat_apercu = function (array $x): string {
    if ($x['retire_le'] !== null) return '<span class="puce">retiré le ' . e(date_fr(substr($x['retire_le'], 0, 10))) . '</span>';
    if (apercu_expire($x)) return '<span class="puce puce--alerte">expiré</span>';
    return '<span class="puce puce--ok">en ligne jusqu\'au ' . e(horodatage_fr($x['expire_le'])) . '</span>';
};

// ---------- un procès-verbal seul, à imprimer ----------
if ($a && recu_entier('pv')) {
    $v = null;
    foreach ($a['validations'] as $x) if ((int) $x['id'] === recu_entier('pv') && $x['decision'] !== 'attente') $v = $x;
    if ($v) { ?>
<p class="fil"><a href="<?= e(url('apercu', ['id' => $a['id']])) ?>"><?= e($a['titre']) ?></a></p>
<div class="titre"><h1>Procès-verbal</h1><div class="titre__actions"><button type="button" class="bouton" data-imprimer>Imprimer ou enregistrer en PDF</button></div></div>
<div class="carte">
  <?= proces_verbal_afficher($a, $v) ?>
  <h2 style="margin-top:1.5rem">Ce que présentait l'aperçu</h2>
  <?= apercu_resume_afficher($a) ?>
</div>
<?php   return;
    }
}

// ---------- un aperçu ----------
if ($a):
    $en_ligne = !apercu_expire($a);
    $comptes = bd_lignes('SELECT id, nom, email, role FROM comptes WHERE actif = 1 ORDER BY nom');
    $lien_montre = $_SESSION['apercu_lien'] ?? null;
    unset($_SESSION['apercu_lien']);
    if ($lien_montre && (int) $lien_montre['apercu'] !== (int) $a['id']) $lien_montre = null;
?>
<p class="fil"><a href="<?= e(url('apercus')) ?>">Aperçus et validations</a></p>
<div class="titre">
  <h1><?= e($a['titre']) ?></h1>
  <span class="titre__suite"><?= $etat_apercu($a) ?> créé le <?= e(horodatage_fr($a['cree_le'])) ?> par <?= e($a['cree_par_nom']) ?></span>
</div>

<div class="deux">
<div>
  <div class="carte">
    <h2>Lien à partager</h2>
    <?php if ($en_ligne): ?>
      <div class="champ"><label for="lien-apercu">Adresse de l'aperçu</label><input type="text" id="lien-apercu" value="<?= e(apercu_adresse($a)) ?>" readonly></div>
      <div class="actions">
        <button type="button" class="bouton bouton--plein" data-copier="#lien-apercu" data-copie="Adresse copiée"><?= icone('copier') ?>Copier l'adresse</button>
        <a class="bouton" href="<?= e(apercu_adresse($a)) ?>" target="_blank" rel="noopener"><?= icone('externe') ?>Ouvrir l'aperçu</a>
      </div>
      <p class="aide">Toute personne qui a cette adresse voit le site tel qu'il serait publié, jusqu'au <?= e(horodatage_fr($a['expire_le'])) ?> : c'est le lien à montrer. Il ne permet pas de valider : pour cela, envoyez un lien personnel, plus bas.</p>
    <?php else: ?>
      <p class="rien">Cet aperçu ne se consulte plus : <?= $a['retire_le'] !== null && $a['expire_le'] > $a['retire_le'] ? 'il a été retiré' : 'il a expiré le ' . e(horodatage_fr($a['expire_le'])) ?>. Les validations ci-dessous restent au dossier.</p>
    <?php endif; ?>
  </div>

  <div class="carte" id="validations">
    <div class="carte__tete"><h2>Validations</h2><span class="carte__compte"><?= count($a['validations']) ?></span></div>
    <?php if ($lien_montre): ?>
      <div class="encart" style="margin-bottom:1rem"><?= icone('lien') ?><span><strong>Lien personnel envoyé.</strong> Il n'est montré qu'ici, cette fois : gardez-le si le mail n'arrive pas (sur un poste sans envoi de mail, c'est le seul moyen de le transmettre).<br>
        <input type="text" id="lien-perso" value="<?= e($lien_montre['lien']) ?>" readonly aria-label="Lien personnel de validation" style="margin-top:.5rem"> <button type="button" class="bouton bouton--petit bouton--sombre" data-copier="#lien-perso" data-copie="Copié" style="margin-top:.5rem">Copier</button></span></div>
    <?php endif; ?>
    <?php if (!$a['validations']): ?><p class="rien">Aucune validation demandée pour cet aperçu.</p><?php endif; ?>
    <ul class="items">
      <?php foreach ($a['validations'] as $v): ?>
        <li>
          <span class="en-ligne"><span class="item__titre"><?= e($v['nom']) ?></span> <span class="puce <?= DECISIONS_APERCU[$v['decision']][1] ?>"><?= e(DECISIONS_APERCU[$v['decision']][0]) ?></span></span>
          <span class="discret"><?= e($v['email']) ?> · lien envoyé le <?= e(horodatage_fr($v['envoye_le'])) ?><?= $v['vu_le'] ? ' · ouvert le ' . e(horodatage_fr($v['vu_le'])) : ' · pas encore ouvert' ?><?= $v['decide_le'] ? ' · décision le ' . e(horodatage_fr($v['decide_le'])) : '' ?></span>
          <?php if (trim((string) $v['remarques']) !== ''): ?><span><?= nl2br(e($v['remarques'])) ?></span><?php endif; ?>
          <?php if ($v['decision'] !== 'attente'): ?>
            <details class="repli"><summary>Procès-verbal</summary><?= proces_verbal_afficher($a, $v) ?>
              <p class="actions"><a class="bouton bouton--petit" href="<?= e(url('apercu', ['id' => $a['id'], 'pv' => $v['id']])) ?>"><?= icone('proces-verbal') ?>Ouvrir pour imprimer</a></p></details>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if (peut('site.publier') && $en_ligne): ?>
    <form method="post" class="visuel-ajout">
      <?= champ_csrf() ?><input type="hidden" name="action" value="inviter">
      <h3>Demander une validation</h3>
      <?php $choix = []; foreach ($comptes as $c) $choix[(int) $c['id']] = $c['nom'] . ' · ' . ROLES[$c['role']] . ' · ' . $c['email']; ?>
      <?= champ_choix('compte', 'À qui', $choix, '', ['vide' => '— choisir un compte —', 'aide' => 'Un compte de Pupitre : le lien personnel part à son adresse. La personne à qui vous voulez l\'envoyer n\'y est pas ? Créez d\'abord son compte (Administration › Comptes) ; le rôle « artiste » suffit.']) ?>
      <button type="submit" class="bouton bouton--plein"><?= icone('envoyer') ?>Envoyer le lien personnel</button>
      <p class="aide">Le mail mène à l'aperçu, avec un bouton « Valider ». La personne valide, ou demande des corrections : vous êtes prévenu par mail et par une notification, et le procès-verbal paraît ici. Renvoyer une demande à la même personne remplace son lien.</p>
    </form>
    <?php endif; ?>
  </div>
</div>

<div>
  <div class="carte">
    <h2>Ce que présente cet aperçu</h2>
    <?= apercu_resume_afficher($a) ?>
    <?php if ($empreinte !== ''): ?>
      <?php if ($empreinte === $a['empreinte']): ?>
        <p class="message message--ok" style="margin-top:1rem">Pupitre n'a pas changé depuis : publier maintenant, c'est publier exactement ce que montre cet aperçu.</p>
      <?php else: ?>
        <p class="message message--alerte" style="margin-top:1rem">Pupitre a changé depuis la création de cet aperçu (ou le site a été publié) : ce qu'il montre n'est plus ce qui serait publié. Pour faire valider l'état présent, créez un nouvel aperçu.</p>
      <?php endif; ?>
    <?php endif; ?>
    <p class="aide">Empreinte : <code class="insecable"><?= e(substr($a['empreinte'], 0, 16)) ?>…</code></p>
    <?php if (peut('site.voir')): ?><p class="actions"><a class="bouton" href="<?= e(url('site')) ?>"><?= icone('publier') ?>Écran de publication</a></p><?php endif; ?>
  </div>
  <?php if (peut('site.publier') && $a['retire_le'] === null): ?>
  <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="retirer">
    <button type="submit" class="bouton bouton--danger bouton--petit" data-confirmer="Retirer cet aperçu ? Son lien ne mènera plus à rien. Les validations déjà données restent au dossier.">Retirer cet aperçu maintenant</button></form>
  <?php endif; ?>
  <div class="carte"><h2>Journal</h2><?= journal_afficher(journal_objet('apercu', (int) $a['id'], 15)) ?></div>
</div>
</div>
<?php return; endif;

// ---------- la liste ----------
$apercus = apercus_lister();
$ecarts = $etat['ecarts'] ?? null;
?>
<div class="titre">
  <h1>Aperçus et validations</h1>
  <?php if (peut('site.voir')): ?><div class="titre__actions"><a class="bouton" href="<?= e(url('site')) ?>"><?= icone('publier') ?>Écran de publication</a></div><?php endif; ?>
</div>
<p class="intro">Un aperçu montre le site tel qu'il serait publié maintenant, sans rien publier : une adresse à partager, valable un temps limité. Pour faire valider des évolutions, envoyez à un compte un lien personnel : sa décision fait un procès-verbal, gardé ici.</p>

<div class="deux">
<div>
  <div class="carte">
    <div class="carte__tete"><h2>Aperçus</h2><span class="carte__compte"><?= count($apercus) ?></span></div>
    <?php if (!$apercus): ?>
      <?= etat_vide('oeil', 'Aucun aperçu encore', 'Créez-en un pour montrer les modifications en cours avant de les publier.') ?>
    <?php else: ?>
    <ul class="items">
      <?php foreach ($apercus as $x): $decisions = array_count_values(array_column($x['validations'], 'decision')); ?>
        <li>
          <span class="en-ligne"><a class="item__titre" href="<?= e(url('apercu', ['id' => $x['id']])) ?>"><?= e($x['titre']) ?></a> <?= $etat_apercu($x) ?>
            <?php foreach ($decisions as $decision => $n): ?><span class="puce <?= DECISIONS_APERCU[$decision][1] ?>"><?= e(mb_strtolower(DECISIONS_APERCU[$decision][0])) ?><?= $n > 1 ? ' × ' . $n : '' ?></span><?php endforeach; ?></span>
          <span class="discret">créé le <?= e(horodatage_fr($x['cree_le'])) ?> par <?= e($x['cree_par_nom']) ?><?= ($empreinte !== '' && $empreinte === $x['empreinte']) ? ' · montre l\'état présent de Pupitre' : '' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>

<div>
  <?php if (peut('site.publier')): ?>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="creer">
    <h2>Créer un aperçu</h2>
    <?php if ($etat === null): ?>
      <p class="message message--erreur" role="alert">La racine du site est introuvable : vérifiez-la dans les réglages.</p>
    <?php else: ?>
    <p class="aide aide--haut"><?= $ecarts ? e(pluriel($ecarts, 'modification attend', 'modifications attendent')) . ' d\'être publiée' . ($ecarts > 1 ? 's' : '') . ' : l\'aperçu les montrera.' : 'Rien n\'attend d\'être publié : l\'aperçu montrerait le site tel qu\'il est.' ?></p>
    <?= champ('titre', 'Titre', recu('titre'), ['max' => 190, 'requis' => true, 'indication' => 'Saison 2027 : nouvelles dates et revue de presse']) ?>
    <?= champ_texte('note', 'Ce que vous voulez en dire', recu('note'), ['lignes' => 4, 'aide' => 'Facultatif. Repris dans le mail de demande de validation et au procès-verbal.']) ?>
    <?= champ_choix('jours', 'Valable', APERCU_DUREES, 7, ['aide' => 'Passé ce délai, l\'aperçu disparaît de lui-même.']) ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein"><?= icone('oeil') ?>Créer l'aperçu</button></div>
    <?php endif; ?>
  </form>
  <?php else: ?>
  <div class="carte"><h2>Créer un aperçu</h2><p class="aide">La création d'un aperçu et les demandes de validation reviennent au pilote, comme la publication.</p></div>
  <?php endif; ?>
</div>
</div>

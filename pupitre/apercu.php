<?php
/**
 * Lien personnel d'un aperçu : la porte d'entrée de la personne à qui l'on demande une validation.
 * Hors connexion : le lien lui-même, envoyé par mail à un compte de Pupitre, dit qui elle est.
 *
 *     apercu.php?j=<jeton>          note la visite, puis mène à l'aperçu du site (le bouton « Valider » y paraît)
 *     apercu.php?j=<jeton>&pv=1     le procès-verbal : valider, ou demander des corrections
 *
 * Une décision prise ne se reprend pas : le même lien montre ensuite le procès-verbal, et rien d'autre.
 * La page ne dit jamais rien d'un jeton inconnu, sinon qu'il n'est pas valable.
 */

require __DIR__ . '/inclus/amorce.php';

apercus_purger();
$jeton = recu('j');
$v = validation_par_jeton($jeton);
$a = $v['apercu'] ?? null;
$erreur = '';

if ($v && $v['vu_le'] === null) {
    bd_modifier('apercus_validations', ['vu_le' => date('Y-m-d H:i:s')], ['id' => $v['id']]);
    $v['vu_le'] = date('Y-m-d H:i:s');
}

if ($v && est_post() && $v['decision'] === 'attente') {
    verifier_csrf();
    $decision = recu('decision');
    $remarques = recu('remarques');
    if (apercu_expire($a)) {
        $erreur = 'Cet aperçu a expiré : il ne peut plus être validé. Demandez-en un nouveau.';
    } elseif ($decision === 'validee' && empty($_POST['accord'])) {
        $erreur = 'Pour valider, cochez la case qui confirme que vous avez parcouru l\'aperçu.';
    } elseif ($decision === 'corrections' && trim($remarques) === '') {
        $erreur = 'Pour demander des corrections, dites lesquelles dans les remarques.';
    } elseif (in_array($decision, ['validee', 'corrections'], true)) {
        validation_decider($v, $decision, $remarques);
        rediriger(chemin_pupitre() . '/apercu.php?pv=1&j=' . $jeton);
    }
}

// le lien seul : vers l'aperçu, tant qu'il se consulte et que rien n'est décidé
if ($v && recu('pv') === '' && $v['decision'] === 'attente' && !apercu_expire($a)) {
    rediriger(apercu_adresse($a, 'index.html') . '?v=' . $jeton);
}

if (!$v) http_response_code(404);
echo page_publique_tete($v ? 'Validation de l\'aperçu' : 'Lien non valable');

if (!$v): ?>
  <h1 class="porte__titre">Ce lien n'est pas valable</h1>
  <p>Il est incomplet, ou il a été remplacé par un lien plus récent. Reprenez le lien du dernier mail reçu, ou demandez-en un nouveau à la personne qui vous l'a envoyé.</p>
<?php elseif ($v['decision'] !== 'attente'): ?>
  <h1 class="porte__titre"><?= $v['decision'] === 'validee' ? 'Merci : votre validation est enregistrée' : 'Votre demande de corrections est enregistrée' ?></h1>
  <p><?= e($a['cree_par_nom']) ?> en est prévenu<?= $v['decision'] === 'validee' ? ' et peut maintenant publier ces évolutions' : ' : rien n\'est publié en l\'état' ?>. Vous pouvez fermer cette page.</p>
  <?= proces_verbal_afficher($a, $v) ?>
  <p class="actions"><button type="button" class="bouton" data-imprimer>Imprimer ou enregistrer en PDF</button></p>
<?php elseif (apercu_expire($a)): ?>
  <h1 class="porte__titre">Cet aperçu a expiré</h1>
  <p>L'aperçu « <?= e($a['titre']) ?> » était consultable jusqu'au <?= e(horodatage_fr($a['expire_le'])) ?>. Il ne peut plus être ni consulté, ni validé : demandez-en un nouveau à <?= e($a['cree_par_nom']) ?>.</p>
<?php else: ?>
  <h1 class="porte__titre">Validation de l'aperçu</h1>
  <p class="intro">« <?= e($a['titre']) ?> » · proposé par <?= e($a['cree_par_nom']) ?> le <?= e(horodatage_fr($a['cree_le'])) ?> · consultable jusqu'au <?= e(horodatage_fr($a['expire_le'])) ?>.</p>
  <?php if ($erreur !== ''): ?><p class="message message--erreur" role="alert"><?= e($erreur) ?></p><?php endif; ?>
  <p class="actions"><a class="bouton" href="<?= e(apercu_adresse($a, 'index.html') . '?v=' . $jeton) ?>">Revoir l'aperçu du site</a></p>

  <h2>Ce que présente cet aperçu</h2>
  <?= apercu_resume_afficher($a) ?>

  <h2>Votre décision</h2>
  <form method="post" action="<?= e(chemin_pupitre()) ?>/apercu.php?pv=1&amp;j=<?= e($jeton) ?>">
    <?= champ_csrf() ?>
    <p class="encart"><span><?= e(validation_declaration($v)) ?></span></p>
    <div class="champ champ--case" style="margin-top:1rem"><input type="checkbox" id="accord" name="accord" value="1"><label for="accord">J'ai parcouru l'aperçu et je valide ces évolutions en vue de leur publication.</label></div>
    <div class="champ"><label for="remarques">Remarques</label><textarea id="remarques" name="remarques" rows="4" aria-describedby="remarques-aide"><?= e(recu('remarques')) ?></textarea>
      <p class="aide" id="remarques-aide">Facultatives pour valider. Pour demander des corrections, dites ici lesquelles.</p></div>
    <div class="actions">
      <button type="submit" name="decision" value="validee" class="bouton bouton--plein">Signer et valider</button>
      <button type="submit" name="decision" value="corrections" class="bouton">Demander des corrections</button>
    </div>
    <p class="aide">Votre décision est enregistrée avec la date, votre adresse IP et votre navigateur : elle fait le procès-verbal de validation, transmis à <?= e($a['cree_par_nom']) ?>.</p>
  </form>
<?php endif;

echo page_publique_pied('Pupitre · ' . ($a ? 'aperçu établi pour ' . (reglage_sur('artiste_nom') ?: 'le site') : 'validation d\'un aperçu'));

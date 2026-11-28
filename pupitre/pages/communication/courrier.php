<?php
/**
 * Courrier : les mails que Pupitre envoie (invitations à déposer des images, demandes de validation
 * d'un aperçu, liens de connexion) et ce qui lui revient dans la boîte de l'expéditeur : réponses,
 * messages d'absence, avis de non-remise. Chaque retour est rattaché à l'envoi auquel il répond.
 */

defined('PUPITRE') or exit;

$titre = 'Courrier';

if (est_post()) {
    $action = recu('action');
    if ($action === 'relever') {
        $tache = tache_creer('courrier', []);
        if (!taches_lancer_en_fond()) message('alerte', 'Le relevé attend le prochain passage des tâches de fond (cron).');
        rediriger(url('courrier', ['tache' => $tache]));
    } elseif (in_array($action, ['lu', 'a_lire'], true) && ($c = bd_ligne("SELECT * FROM courriers WHERE id = ? AND sens = 'recu'", [recu_entier('id') ?? 0]))) {
        bd_modifier('courriers', ['statut' => $action], ['id' => $c['id']]);
        $GLOBALS['donnees_modifiees'] = true;
    } elseif ($action === 'tout_lu') {
        bd_requete("UPDATE courriers SET statut = 'lu' WHERE sens = 'recu' AND statut = 'a_lire'");
        $GLOBALS['donnees_modifiees'] = true;
        message('ok', 'Tous les retours sont marqués comme lus.');
    }
    rediriger(url('courrier', array_filter(['vue' => recu('vue')], 'strlen')));
}

$vue = recu('vue') === 'envois' ? 'envois' : 'retours';
$retours = courrier_retours();
$envois = courrier_envois();
$a_lire = count(array_filter($retours, fn($r) => $r['statut'] === 'a_lire'));
$tache = recu_entier('tache') ? tache_lire(recu_entier('tache')) : null;
$releve = reglage('courrier_le');
?>
<div class="titre">
  <h1>Courrier</h1>
  <span class="titre__suite"><?= e(pluriel($a_lire, 'retour à lire', 'retours à lire')) ?><?= $releve !== '' ? ' · boîte relevée ' . e(il_y_a($releve)) : '' ?></span>
  <div class="titre__actions">
    <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="action" value="relever">
      <button type="submit" class="bouton bouton--plein"<?= imap_configure() ? '' : ' disabled' ?>><?= icone('courrier') ?>Relever la boîte</button></form>
  </div>
</div>
<p class="intro">Ce que Pupitre a envoyé, et ce qui lui est revenu dans la boîte <?= e(env('IMAP_UTILISATEUR') ?: env('MAIL_EXPEDITEUR') ?: 'de l\'expéditeur') ?>. Pupitre ne garde que les retours de ses propres envois : le reste de la boîte ne le regarde pas.</p>
<?php if (!imap_configure()): ?>
  <p class="message message--alerte">La boîte aux lettres n'est pas reliée : renseignez <code>IMAP_HOTE</code> (<code>ssl0.ovh.net</code> pour une boîte OVH), <code>IMAP_UTILISATEUR</code> (l'adresse complète) et <code>IMAP_MOT_DE_PASSE</code> dans <code>pupitre/.env</code>. Les envois sont déjà notés ci-dessous ; leurs retours le seront dès que la boîte sera reliée.</p>
<?php endif; ?>
<?php if ($tache): ?><div class="carte"><?= suivi_tache($tache) ?></div><?php endif; ?>

<nav class="onglets" aria-label="Courrier">
  <a href="<?= e(url('courrier')) ?>"<?= $vue === 'retours' ? ' aria-current="page"' : '' ?>>Retours <span class="carte__compte"><?= count($retours) ?></span></a>
  <a href="<?= e(url('courrier', ['vue' => 'envois'])) ?>"<?= $vue === 'envois' ? ' aria-current="page"' : '' ?>>Envois <span class="carte__compte"><?= count($envois) ?></span></a>
</nav>

<?php if ($vue === 'retours'): ?>
  <?php if (!$retours): ?>
    <div class="carte"><?= etat_vide('courrier', 'Aucun retour', imap_configure() ? 'Les réponses, messages d\'absence et avis de non-remise apparaîtront ici, rattachés à leur envoi.' : 'Reliez la boîte aux lettres pour suivre les retours.') ?></div>
  <?php endif; ?>
  <?php foreach ($retours as $r): $lien = courrier_lien($r); ?>
    <article class="carte signalement<?= $r['statut'] === 'lu' ? ' passee' : '' ?>" id="c-<?= (int) $r['id'] ?>">
      <p class="en-ligne"><span class="puce <?= TYPES_RETOUR[$r['type_retour']][1] ?? '' ?>"><?= e(mb_strtolower(TYPES_RETOUR[$r['type_retour']][0] ?? $r['type_retour'])) ?></span>
        <?= $r['statut'] === 'a_lire' ? '<span class="puce puce--accent">à lire</span>' : '' ?>
        <span class="discret"><?= e(horodatage_fr($r['date_message'])) ?> · <?= e(NATURES_COURRIER[$r['nature']] ?? $r['nature']) ?></span></p>
      <h2 class="signalement__titre"><?= e($r['sujet'] ?: '(sans sujet)') ?></h2>
      <p class="discret"><?= e($r['correspondant_nom'] !== '' ? $r['correspondant_nom'] . ' · ' : '') ?><?= e($r['correspondant_email']) ?></p>
      <?php if (trim((string) $r['texte']) !== ''): ?><details class="repli"<?= $r['statut'] === 'a_lire' ? ' open' : '' ?>><summary>Le message</summary><p style="white-space:pre-wrap"><?= e(mb_substr((string) $r['texte'], 0, 2500)) ?><?= mb_strlen((string) $r['texte']) > 2500 ? ' …' : '' ?></p></details><?php endif; ?>
      <?php if ($r['reponse_a']): ?><p class="discret">En réponse à « <?= e($r['envoi_sujet']) ?> », envoyé le <?= e(horodatage_fr($r['envoi_date'])) ?> à <?= e($r['envoi_a']) ?>.</p><?php endif; ?>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <?php if ($lien !== ''): ?><a class="bouton bouton--petit" href="<?= e($lien) ?>"><?= $r['objet_table'] === 'concerts' ? 'Ouvrir le concert' : 'Ouvrir l\'aperçu' ?></a><?php endif; ?>
        <?php if ($r['correspondant_email'] !== '' && $r['type_retour'] !== 'rejet'): ?><a class="bouton bouton--petit" href="mailto:<?= e($r['correspondant_email']) ?>?subject=<?= e(rawurlencode('Re: ' . sujet_nu($r['sujet']))) ?>">Répondre</a><?php endif; ?>
        <button type="submit" name="action" value="<?= $r['statut'] === 'a_lire' ? 'lu' : 'a_lire' ?>" class="bouton bouton--petit"><?= $r['statut'] === 'a_lire' ? 'Marquer comme lu' : 'Remettre à lire' ?></button>
      </form>
    </article>
  <?php endforeach; ?>
  <?php if ($a_lire > 1): ?><form method="post" class="actions"><?= champ_csrf() ?><button type="submit" name="action" value="tout_lu" class="bouton bouton--petit">Tout marquer comme lu</button></form><?php endif; ?>
<?php else: ?>
  <div class="carte">
    <?php if (!$envois): ?><?= etat_vide('envoyer', 'Aucun envoi encore', 'Les mails envoyés par Pupitre seront listés ici, avec ce qu\'ils sont devenus.') ?><?php else: ?>
    <div class="defile"><table class="tableau tableau--large">
      <thead><tr><th>Date</th><th>Destinataire</th><th>Message</th><th>État</th><th>Retours</th></tr></thead>
      <tbody>
      <?php foreach ($envois as $x): $lien = courrier_lien($x); ?>
        <tr>
          <td class="date"><strong><?= e(date_fr(substr((string) $x['date_message'], 0, 10), false, false)) ?></strong><br><span class="discret"><?= e(substr((string) $x['date_message'], 11, 5)) ?></span></td>
          <td><?= e($x['correspondant_nom'] ?: $x['correspondant_email']) ?><?= $x['correspondant_nom'] !== '' ? '<br><span class="discret insecable">' . e($x['correspondant_email']) . '</span>' : '' ?></td>
          <th scope="row"><?= $lien !== '' ? '<a href="' . e($lien) . '">' . e($x['sujet']) . '</a>' : e($x['sujet']) ?><br><span class="discret"><?= e(NATURES_COURRIER[$x['nature']] ?? $x['nature']) ?></span></th>
          <td><span class="puce <?= STATUTS_ENVOI[$x['statut']][1] ?? '' ?>"><?= e(mb_strtolower(STATUTS_ENVOI[$x['statut']][0] ?? $x['statut'])) ?></span></td>
          <td class="nombre"><?= (int) $x['retours'] ?: '<span class="discret">—</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="aide">« Non envoyé » : le poste n'a pas d'envoi de mails configuré, ou l'adresse est une adresse d'essai. « Remis », « ouvert », « rejeté » viennent du routeur (Mailjet), relu à chaque relevé. Le texte d'un mail qui porte un lien personnel (connexion, validation) n'est pas conservé.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

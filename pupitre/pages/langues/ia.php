<?php
/**
 * Assistance à la traduction, dans l'onglet « Traductions » (inclus par pages/langues/langues.php,
 * qui traite les actions « proposer » et « proposer_un »).
 *
 * L'IA propose ; une personne relit et valide. Une proposition ne paraît pas sur le site tant
 * qu'elle n'est pas validée, et une traduction validée n'est jamais remplacée. Les paroles de
 * l'artiste (sa citation, son mot sur un album) et les citations de presse ne sont pas traduites
 * d'office : elles se demandent une à une, ou se traduisent à la main.
 */

defined('PUPITRE') or exit;

$tache_tr = recu_entier('tache') ? tache_lire((int) recu_entier('tache')) : null;
if ($tache_tr && $tache_tr['type'] !== 'traduction') $tache_tr = null;
$a_proposer = 0;
foreach ($inventaire as $x) if ($x['statut'] === 'manquante' && !traduction_reservee($x['objet'], $x['id'], $x['champ'])) $a_proposer++;
?>
<div class="carte">
  <h2>Assistance à la traduction</h2>
  <?php if ($tache_tr && in_array($tache_tr['statut'], ['en_attente', 'en_cours'], true)): ?>
    <?= suivi_tache($tache_tr) ?>
    <p class="aide">Les propositions arrivent par lots. La page se rechargera d'elle-même à la fin.</p>
  <?php else: ?>
    <?php if ($tache_tr): ?><p class="message message--<?= $tache_tr['statut'] === 'echec' ? 'erreur' : 'ok' ?>"><?= e($tache_tr['message']) ?><?php if ($tache_tr['statut'] !== 'echec' && $decompte['proposee']): ?> <a href="<?= e($filtre(['statut' => 'proposee', 'p' => ''])) ?>">Voir les propositions à valider</a><?php endif; ?></p><?php endif; ?>
    <?php if (!ia_configuree()): ?>
      <p class="rien">L'IA n'est pas configurée (écran Réglages) : les traductions se saisissent à la main, ici ou sur chaque fiche.</p>
    <?php else: ?>
    <form method="post" class="ia-carte">
      <?= champ_csrf() ?><input type="hidden" name="action" value="proposer">
      <input type="hidden" name="langue" value="<?= e($langue) ?>"><input type="hidden" name="objet" value="<?= e($objet) ?>"><input type="hidden" name="statut" value="<?= e($statut) ?>">
      <p><?= $a_proposer ? e(pluriel($a_proposer, 'texte sans traduction peut', 'textes sans traduction peuvent')) . ' recevoir une proposition de l\'IA' . ($objet !== '' ? ' dans « ' . e(TRADUISIBLES[$objet][0]) . ' »' : '') . '.' : 'Aucun texte sans traduction' . ($objet !== '' ? ' dans « ' . e(TRADUISIBLES[$objet][0]) . ' »' : '') . ' à proposer à l\'IA.' ?>
        <span class="discret">Beaucoup de noms propres et de titres d'œuvres s'écrivent de la même façon dans les deux langues : l'IA les laisse alors sans traduction, et le site garde le texte d'origine.</span></p>
      <?php if ($a_proposer): ?><button type="submit" class="bouton" data-confirmer="Demander à l'IA une proposition pour <?= (int) min($a_proposer, 400) ?> texte(s) en <?= e(mb_strtolower(langue_nom($langue))) ?> ? Rien ne paraîtra sur le site sans votre validation.">Proposer les traductions manquantes</button><?php endif; ?>
    </form>
    <p class="aide">Les propositions prennent l'état « proposée par l'IA, à valider ». Les paroles de l'artiste et les citations de presse ne sont jamais traduites d'office : demandez-les une à une (bouton sous chaque texte), ou traduisez-les à la main.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

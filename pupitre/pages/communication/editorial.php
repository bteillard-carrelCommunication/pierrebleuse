<?php
/**
 * Tableau de bord éditorial : où en est la communication, concert par concert et jour par jour.
 *
 *   indicateurs   concerts à venir, concerts annoncés sans action, actions programmées, à préparer ;
 *   concerts      chaque concert à venir avec ses actions de communication ; ceux pour lesquels
 *                 rien n'est prévu sont signalés ; ceux qui ne sont pas encore annoncés par leur
 *                 organisateur sont à part : on ne communique pas avant l'annonce ;
 *   calendrier    le mois : concerts, publications, lettres, fins d'embargo.
 *
 * Tout vient de ce qui est saisi dans Pupitre. Aucune plateforme n'est connectée : ce que les
 * réseaux ont réellement publié n'est connu de Pupitre que si on l'y note.
 */

defined('PUPITRE') or exit;

$titre = 'Tableau de bord éditorial';
$jour = aujourdhui();
$concerts = concerts_a_communiquer();
$etats = ['sans_action' => 'Sans action', 'couvert' => 'Avec action', 'en_attente' => 'Pas encore annoncés'];
$filtre = isset($etats[recu('etat')]) ? recu('etat') : '';
$n = ['sans_action' => 0, 'couvert' => 0, 'en_attente' => 0];
foreach ($concerts as $c) $n[$c['etat']]++;
$montres = $filtre === '' ? $concerts : array_values(array_filter($concerts, fn($c) => $c['etat'] === $filtre));
$decompte = communications_decompte();
$lettres = communications_decompte('lettre');

// calendrier du mois demandé
$mois = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', recu('mois')) ? recu('mois') : substr($jour, 0, 7);
$premier = strtotime($mois . '-01 12:00');
$evenements = calendrier_editorial($mois);
$decalage = ((int) date('N', $premier)) - 1;                      // lundi en première colonne
$jours_du_mois = (int) date('t', $premier);
$cases = (int) (ceil(($decalage + $jours_du_mois) / 7) * 7);
$mois_precedent = date('Y-m', strtotime('-1 month', $premier));
$mois_suivant = date('Y-m', strtotime('+1 month', $premier));
$adresse = fn(array $p) => url('editorial', array_filter($p + ['etat' => $filtre, 'mois' => $mois === substr($jour, 0, 7) ? '' : $mois], 'strlen'));
?>
<div class="titre">
  <h1>Tableau de bord éditorial</h1>
  <span class="titre__suite"><?= e(ucfirst(date_fr($jour, true))) ?></span>
  <div class="titre__actions"><a class="bouton bouton--plein" href="<?= e(url('communication', ['id' => 'nouvelle'])) ?>"><?= icone('plus') ?>Prévoir une action</a></div>
</div>
<p class="intro">La communication autour des concerts, d'un seul regard : ce qui est prévu, ce qui est fait, et les concerts pour lesquels rien n'est encore prévu. Une action est une publication sur un réseau ou une lettre ; elle se rattache aux concerts qu'elle concerne.</p>

<div class="indicateurs">
  <?= indicateur('Concerts à venir', count($concerts), pluriel($n['en_attente'], 'pas encore annoncé', 'pas encore annoncés'), ['icone' => 'concerts', 'lien' => url('agenda')]) ?>
  <?= indicateur('Sans action', $n['sans_action'], $n['sans_action'] ? 'concerts annoncés, sans communication' : 'chaque concert annoncé a une action', ['icone' => 'communication', 'lien' => $adresse(['etat' => 'sans_action']) . '#concerts', 'classe' => $n['sans_action'] ? 'indicateur--alerte' : 'indicateur--ok']) ?>
  <?= indicateur('À préparer', $decompte['a_preparer'], 'actions prévues, pas encore prêtes', ['icone' => 'brouillon', 'lien' => url('social', ['statut' => 'a_preparer'])]) ?>
  <?= indicateur('Programmées', $decompte['programmee'], 'prêtes, à une date fixée', ['icone' => 'programmee', 'lien' => url('social', ['statut' => 'programmee'])]) ?>
  <?= indicateur('Lettres à venir', $lettres['a_preparer'] + $lettres['programmee'], pluriel($lettres['publiee'], 'lettre envoyée', 'lettres envoyées'), ['icone' => 'newsletter', 'lien' => url('newsletter')]) ?>
</div>

<section class="carte" id="concerts" aria-labelledby="t-concerts">
  <div class="carte__tete"><h2 id="t-concerts">Concerts à venir et leur communication</h2><span class="carte__compte"><?= count($montres) ?></span></div>
  <nav class="onglets" aria-label="Filtrer les concerts">
    <a href="<?= e($adresse(['etat' => '']) . '#concerts') ?>"<?= $filtre === '' ? ' aria-current="page"' : '' ?>>Tous <span class="carte__compte"><?= count($concerts) ?></span></a>
    <?php foreach ($etats as $k => $libelle): ?>
      <a href="<?= e($adresse(['etat' => $k]) . '#concerts') ?>"<?= $filtre === $k ? ' aria-current="page"' : '' ?>><?= e($libelle) ?> <span class="carte__compte"><?= (int) $n[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$concerts): ?>
    <?= etat_vide('concerts', 'Aucun concert à venir', 'Les concerts à venir de l\'agenda apparaîtront ici, avec leurs actions de communication.') ?>
  <?php elseif (!$montres): ?>
    <?= etat_vide('ok', 'Aucun concert dans ce cas') ?>
  <?php else: ?>
  <div class="defile defile--pile"><table class="tableau tableau--editorial" role="table">
    <thead role="rowgroup"><tr role="row"><th role="columnheader">Date</th><th role="columnheader">Concert</th><th role="columnheader">Annonce</th><th role="columnheader">Communication</th><th role="columnheader"><span class="sr">Agir</span></th></tr></thead>
    <tbody role="rowgroup">
    <?php foreach ($montres as $c): ?>
      <tr role="row">
        <td role="cell" class="date"><strong><?= e(date_fr($c['prochaine'], false, false)) ?></strong><br><span class="discret"><?= $c['dans'] <= 0 ? 'aujourd\'hui' : 'dans ' . e(pluriel($c['dans'], 'jour', 'jours')) ?></span><?php if (count($c['dates']) > 1): ?><br><span class="discret"><?= count($c['dates']) ?> dates</span><?php endif; ?></td>
        <th scope="row" role="rowheader"><a href="<?= e(url('concert', ['id' => $c['id']])) ?>"><?= e($c['titre']) ?></a><?php if ($c['type'] !== 'concert'): ?> <span class="puce"><?= e(TYPES_CONCERT[$c['type']] ?? $c['type']) ?></span><?php endif; ?>
          <br><span class="discret"><?= e(implode(', ', array_filter([$c['salle'], $c['ville']], 'strlen'))) ?></span></th>
        <td role="cell">
          <?php if ($c['annonce']): ?><span class="puce puce--ok"><?= icone('coche') ?>annoncé</span>
          <?php elseif (isset($c['statuts']['embargo'])): ?><span class="puce puce--alerte"><?= icone('embargo') ?>sous embargo</span><?php if ($c['embargo_le']): ?><br><span class="discret">jusqu'au <?= e(date_fr($c['embargo_le'])) ?></span><?php endif; ?>
          <?php else: ?><span class="puce"><?= icone('cadenas') ?>pas encore annoncé</span><?php endif; ?>
        </td>
        <td role="cell">
          <?php if ($c['actions']): ?>
            <div class="actions-concert">
              <?php foreach ($c['actions'] as $a): ?>
                <a class="puce <?= e(STATUTS_COMMUNICATION[$a['statut']][2]) ?>" href="<?= e(url('communication', ['id' => $a['id']])) ?>" title="<?= e(communication_libelle($a)) ?>"><?= icone(CANAUX[$a['canal']][1] ?? 'social') ?><?= e(CANAUX[$a['canal']][0] ?? '') ?> · <?= e(mb_strtolower(communication_statut($a))) ?></a>
              <?php endforeach; ?>
            </div>
          <?php elseif ($c['etat'] === 'sans_action'): ?>
            <span class="puce puce--erreur"><?= icone('alerte') ?>aucune action enregistrée</span>
          <?php else: ?>
            <span class="discret">On ne communique pas avant l'annonce de l'organisateur.</span>
          <?php endif; ?>
        </td>
        <td role="cell" class="tableau__actions"><a class="bouton bouton--petit<?= $c['etat'] === 'sans_action' ? ' bouton--doux' : '' ?>" href="<?= e(url('communication', ['id' => 'nouvelle', 'concert' => $c['id']])) ?>"><?= icone('plus') ?>Prévoir</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="carte" id="calendrier" aria-labelledby="t-calendrier">
  <div class="calendrier__tete">
    <h2 id="t-calendrier">Calendrier éditorial · <?= e(mois_fr($mois)) ?></h2>
    <a class="bouton bouton--petit" href="<?= e(url('editorial', array_filter(['etat' => $filtre, 'mois' => $mois_precedent], 'strlen')) . '#calendrier') ?>"><?= icone('chevron-gauche') ?><span class="sr">Mois précédent : </span><?= e(mois_fr($mois_precedent)) ?></a>
    <?php if ($mois !== substr($jour, 0, 7)): ?><a class="bouton bouton--petit" href="<?= e(url('editorial', array_filter(['etat' => $filtre], 'strlen')) . '#calendrier') ?>">Ce mois-ci</a><?php endif; ?>
    <a class="bouton bouton--petit" href="<?= e(url('editorial', array_filter(['etat' => $filtre, 'mois' => $mois_suivant], 'strlen')) . '#calendrier') ?>"><span class="sr">Mois suivant : </span><?= e(mois_fr($mois_suivant)) ?><?= icone('chevron-droite') ?></a>
  </div>
  <?php if (!$evenements): ?><p class="aide aide--haut">Rien n'est daté ce mois-ci : ni concert, ni action de communication, ni fin d'embargo.</p><?php endif; ?>
  <div class="calendrier__grille">
    <?php foreach (['lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.'] as $nom): ?><div class="calendrier__jour-nom" aria-hidden="true"><?= $nom ?></div><?php endforeach; ?>
    <?php for ($i = 0; $i < $cases; $i++):
        $date = date('Y-m-d', strtotime(($i - $decalage) . ' days', $premier));
        $hors = substr($date, 0, 7) !== $mois;
        $du_jour = $hors ? [] : ($evenements[$date] ?? []); ?>
      <div class="calendrier__case<?= $hors ? ' calendrier__case--hors' : '' ?><?= $date === $jour ? ' calendrier__case--jour' : '' ?><?= $du_jour ? '' : ' calendrier__case--vide' ?>">
        <span class="calendrier__numero"><span class="sr"><?= e(JOURS_FR[(int) date('w', strtotime($date . ' 12:00'))]) ?> </span><?= (int) substr($date, 8, 2) ?><span class="calendrier__mois-court"> <?= e(MOIS_COURTS[(int) substr($date, 5, 2) - 1]) ?></span></span>
        <?php foreach ($du_jour as $x): ?>
          <a class="evenement evenement--<?= e($x['nature']) ?><?= $x['nature'] === 'action' && $x['statut'] === 'a_preparer' ? ' evenement--a_preparer' : '' ?>" href="<?= e($x['lien']) ?>" title="<?= e($x['libelle'] . ($x['detail'] !== '' ? ' · ' . $x['detail'] : '')) ?>"><?= icone($x['icone']) ?><span><?= e($x['libelle']) ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endfor; ?>
  </div>
  <p class="calendrier__legende">
    <span class="evenement"><?= icone('concerts') ?><span>concert</span></span>
    <span class="evenement evenement--action"><?= icone('social') ?><span>action programmée ou publiée</span></span>
    <span class="evenement evenement--action evenement--a_preparer"><?= icone('brouillon') ?><span>action à préparer</span></span>
    <span class="evenement evenement--embargo"><?= icone('embargo') ?><span>fin d'embargo</span></span>
  </p>
</section>

<p class="encart"><?= icone('info') ?><span><strong>Aucune plateforme n'est connectée.</strong> Les actions se saisissent à la main : Pupitre ne publie rien sur les réseaux et n'envoie aucune lettre, et il ne connaît d'une publication que ce que l'on y note. La connexion aux réseaux sociaux et à l'outil d'envoi de la lettre fera l'objet d'une seconde phase.</span></p>

<?php
/**
 * Social Media et Newsletter : la liste des actions de communication d'une famille de canaux.
 *   page « social »      les publications sur les réseaux ;
 *   page « newsletter »  les lettres d'information.
 * Filtres : statut (à préparer, programmée, publiée) et, pour les réseaux, canal.
 */

defined('PUPITRE') or exit;

$famille = ($page_courante ?? '') === 'newsletter' ? 'lettre' : 'social';
$lettre = $famille === 'lettre';
$ecran = $lettre ? 'newsletter' : 'social';
$titre = FAMILLES_COMMUNICATION[$famille];
$canaux = canaux_de($famille);
$statut = isset(STATUTS_COMMUNICATION[recu('statut')]) ? recu('statut') : '';
$canal = isset($canaux[recu('canal')]) ? recu('canal') : '';
$decompte = communications_decompte($famille);
$total = array_sum($decompte);
$actions = communications_lister(['famille' => $famille, 'statut' => $statut, 'canal' => $canal, 'recentes' => $statut === 'publiee']);
$libelle_statut = fn(string $s) => STATUTS_COMMUNICATION[$s][$lettre ? 1 : 0];
$adresse = fn(array $p) => url($ecran, array_filter($p + ['statut' => $statut, 'canal' => $canal], 'strlen'));
$comptes = comptes_artiste();
$inscrits = bd_table_existe('lettre_inscrits') ? (int) bd_valeur('SELECT COUNT(*) FROM lettre_inscrits') : 0;
?>
<div class="titre">
  <h1><?= e($titre) ?></h1>
  <span class="titre__suite"><?= e(pluriel($total, $lettre ? 'lettre' : 'publication', $lettre ? 'lettres' : 'publications')) ?></span>
  <div class="titre__actions">
    <a class="bouton" href="<?= e(url('editorial')) ?>"><?= icone('editorial') ?>Calendrier éditorial</a>
    <a class="bouton bouton--plein" href="<?= e(url('communication', ['id' => 'nouvelle'] + ($lettre ? ['famille' => 'lettre'] : []))) ?>"><?= icone('plus') ?><?= $lettre ? 'Nouvelle lettre' : 'Nouvelle publication' ?></a>
  </div>
</div>
<p class="intro"><?= $lettre
    ? 'Les lettres d\'information : celles qui sont à préparer, celles qui sont programmées, celles qui ont été envoyées. Chaque lettre se rattache aux concerts qu\'elle annonce.'
    : 'Les publications sur les réseaux : celles qui sont à préparer, celles qui sont programmées, celles qui ont été publiées. Chaque publication se rattache aux concerts qu\'elle concerne.' ?></p>

<div class="indicateurs">
  <?= indicateur($libelle_statut('a_preparer'), $decompte['a_preparer'], 'prévues, pas encore prêtes', ['icone' => 'brouillon', 'lien' => $adresse(['statut' => 'a_preparer'])]) ?>
  <?= indicateur($lettre ? 'Programmées' : 'Programmées', $decompte['programmee'], 'prêtes, à une date fixée', ['icone' => 'programmee', 'lien' => $adresse(['statut' => 'programmee'])]) ?>
  <?= indicateur($lettre ? 'Envoyées' : 'Publiées', $decompte['publiee'], $lettre ? 'lettres parties' : 'en ligne sur les réseaux', ['icone' => 'ok', 'lien' => $adresse(['statut' => 'publiee'])]) ?>
</div>

<div class="deux">
<div>
  <nav class="onglets" aria-label="Filtrer par statut">
    <a href="<?= e($adresse(['statut' => ''])) ?>"<?= $statut === '' ? ' aria-current="page"' : '' ?>>Toutes <span class="carte__compte"><?= (int) $total ?></span></a>
    <?php foreach (array_keys(STATUTS_COMMUNICATION) as $s): ?>
      <a href="<?= e($adresse(['statut' => $s])) ?>"<?= $statut === $s ? ' aria-current="page"' : '' ?>><?= e($s === 'publiee' ? ($lettre ? 'Envoyées' : 'Publiées') : ($s === 'programmee' ? 'Programmées' : 'À préparer')) ?> <span class="carte__compte"><?= (int) $decompte[$s] ?></span></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$lettre && $total): ?>
  <form method="get" class="filtres">
    <input type="hidden" name="page" value="social"><?php if ($statut !== ''): ?><input type="hidden" name="statut" value="<?= e($statut) ?>"><?php endif; ?>
    <?= champ_choix('canal', 'Réseau', $canaux, $canal, ['vide' => 'Tous les réseaux']) ?>
    <button type="submit" class="bouton">Filtrer</button>
  </form>
  <?php endif; ?>

  <?php if (!$actions): ?>
    <div class="carte">
      <?php if (!$total): ?>
        <?= etat_vide($lettre ? 'newsletter' : 'social', $lettre ? 'Aucune lettre enregistrée' : 'Aucune publication enregistrée',
            $lettre ? 'Notez ici les lettres à préparer, programmées ou déjà envoyées : le tableau de bord éditorial les rattachera aux concerts.' : 'Notez ici les publications à préparer, programmées ou déjà en ligne : le tableau de bord éditorial les rattachera aux concerts.',
            '<a class="bouton bouton--plein" href="' . e(url('communication', ['id' => 'nouvelle'] + ($lettre ? ['famille' => 'lettre'] : []))) . '">' . icone('plus') . ($lettre ? 'Nouvelle lettre' : 'Nouvelle publication') . '</a>') ?>
      <?php else: ?>
        <?= etat_vide('filtre', 'Rien avec ce filtre', '', '<a class="bouton bouton--petit" href="' . e(url($ecran)) . '">Tout montrer</a>') ?>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <div class="defile defile--pile"><table class="tableau tableau--actions" role="table">
    <thead role="rowgroup"><tr role="row"><th role="columnheader"><?= $lettre ? 'Lettre' : 'Publication' ?></th><th role="columnheader">Concerts</th><th role="columnheader">Date</th><th role="columnheader">Statut</th></tr></thead>
    <tbody role="rowgroup">
    <?php foreach ($actions as $a): $quand = communication_date($a); $retard = $a['statut'] !== 'publiee' && $a['prevue_le'] && $a['prevue_le'] < date('Y-m-d H:i:s'); ?>
      <tr role="row">
        <th scope="row" role="rowheader">
          <?php if (!$lettre): ?><?= canal_afficher($a['canal']) ?><br><?php endif; ?>
          <a href="<?= e(url('communication', ['id' => $a['id']])) ?>"><?= e(communication_libelle($a)) ?></a>
          <?php if (trim((string) $a['titre']) !== '' && trim((string) $a['texte']) !== ''): ?><br><span class="discret"><?= e(couper(preg_replace('/\s+/u', ' ', (string) $a['texte']), 110)) ?></span><?php endif; ?>
        </th>
        <td role="cell"><?php if ($a['concerts']): ?><?php foreach ($a['concerts'] as $cid => $ct): ?><a href="<?= e(url('concert', ['id' => $cid])) ?>"><?= e($ct) ?></a><br><?php endforeach; ?><?php else: ?><span class="discret">aucun concert associé</span><?php endif; ?></td>
        <td role="cell" class="date"><?php if ($quand): ?><strong><?= e(date_fr(substr($quand, 0, 10))) ?></strong><?php if (substr($quand, 11, 5) !== '00:00'): ?><br><span class="discret"><?= e(heure_fr(substr($quand, 11, 5))) ?></span><?php endif; ?><?php else: ?><span class="discret">pas de date</span><?php endif; ?>
          <?php if ($retard): ?><br><span class="puce puce--erreur"><?= icone('alerte') ?>date passée</span><?php endif; ?></td>
        <td role="cell"><?= communication_puce($a) ?><?php if ($a['lien'] !== ''): ?><br><a class="discret" href="<?= e($a['lien']) ?>" target="_blank" rel="noopener">voir en ligne</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div>
  <?php if ($lettre): ?>
  <div class="carte">
    <h2>Inscriptions</h2>
    <p><?= $inscrits ? e(pluriel($inscrits, 'adresse inscrite', 'adresses inscrites')) . '.' : 'Aucune adresse inscrite.' ?></p>
    <p class="aide">Le formulaire d'inscription du site est inactif : il ne collecte et n'envoie rien. L'inscription, la liste des abonnés et l'envoi des lettres seront ouverts dans une seconde phase, avec l'outil d'envoi retenu.</p>
    <p class="actions"><a class="bouton bouton--petit" href="<?= e(url('bloc', ['cle' => 'suivre'])) ?>">Textes du bloc « Restons en contact »</a></p>
  </div>
  <?php else: ?>
  <div class="carte">
    <h2>Comptes de l'artiste</h2>
    <?php if (!$comptes): ?><p class="rien">Aucun réseau renseigné.</p><?php else: ?>
    <ul class="items">
      <?php foreach ($comptes as $cle => $c): ?>
        <li><?= isset(CANAUX[$cle]) ? canal_afficher($cle) : '<span class="canal"><span class="canal__bulle">' . icone('social') . '</span>' . e($c['nom']) . '</span>' ?>
          <a class="bouton bouton--petit" href="<?= e($c['url']) ?>" target="_blank" rel="noopener"><?= icone('externe') ?>Ouvrir</a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="actions"><a class="bouton bouton--petit" href="<?= e(url('contenu', ['type' => 'contacts'])) ?>">Régler les réseaux du site</a></p>
  </div>
  <?php endif; ?>
  <p class="encart"><?= icone('info') ?><span><strong>Saisie à la main.</strong> <?= $lettre
      ? 'Pupitre n\'envoie aucune lettre : il garde la trace de ce qui est prévu et de ce qui est parti.'
      : 'Pupitre ne publie rien sur les réseaux et n\'y lit rien : il garde la trace de ce qui est prévu et de ce qui est en ligne.' ?> La connexion aux plateformes viendra dans une seconde phase.</span></p>
</div>
</div>

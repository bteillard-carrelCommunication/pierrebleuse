<?php
/**
 * Le site : dernière publication, écarts entre Pupitre et le site fichier par fichier,
 * prévisualisation, publication, retour à la publication précédente.
 * L'éditeur voit et prévisualise ; seul le pilote publie.
 */

defined('PUPITRE') or exit;

$titre = 'Le site';

if (est_post()) {
    exiger_droit('site.publier');
    try {
        if (recu('action') === 'publier') {
            $r = publier();
            message('ok', 'Publié : ' . pluriel($r['fichiers'], 'fichier écrit', 'fichiers écrits')
                . ($r['medias'] ? ', ' . pluriel($r['medias'], 'média copié', 'médias copiés') : '')
                . ($r['representations'] ? ', ' . pluriel($r['representations'], 'représentation passée à « publiée »', 'représentations passées à « publiée »') : '')
                . '. Version des scripts : ' . $r['version'] . '. La mise en ligne (envoi vers le serveur) reste à faire à la main.');
        } elseif (recu('action') === 'version') {
            $r = publier(true);
            message('ok', 'Version des scripts et des feuilles de style renouvelée : ' . $r['version'] . '. Les navigateurs rechargeront ces fichiers. La mise en ligne reste à faire à la main.');
        } elseif (recu('action') === 'revenir') {
            $r = publication_revenir();
            message('ok', 'Le site est revenu à son état d\'avant la publication du ' . $r['publication_defaite'] . ' : ' . pluriel($r['fichiers'], 'fichier remis', 'fichiers remis') . ' en place'
                . ($r['representations'] ? ', ' . pluriel($r['representations'], 'représentation repassée', 'représentations repassées') . ' à « annoncée »' : '') . '. Les contenus de Pupitre n\'ont pas changé.');
        }
    } catch (ErreurPublication $e) {
        message('erreur', $e->getMessage());
    }
    rediriger(url('site'));
}

try {
    $etat = publication_etat();
    $souci = '';
} catch (ErreurPublication $e) {
    $etat = null;
    $souci = $e->getMessage();
}
$derniere = publication_derniere();
$historique = bd_lignes('SELECT * FROM publications ORDER BY id DESC LIMIT 12');
$entrent = publication_representations()['entrent'];
$sortent = bd_lignes("SELECT r.date, c.titre_fr, r.statut FROM representations r JOIN concerts c ON c.id = r.concert_id
    WHERE r.statut = 'annulee' AND r.modifie_le > COALESCE((SELECT MAX(cree_le) FROM publications WHERE nature = 'publication' AND annulee = 0), '1970-01-01') ORDER BY r.date");
$changes = $etat ? array_filter($etat['fichiers'], fn($f) => in_array($f['etat'], ['modifie', 'nouveau'], true)) : [];
$reecrits = $etat ? array_filter($etat['fichiers'], fn($f) => $f['etat'] === 'equivalent') : [];
$a_publier = $etat && ($changes || $reecrits || $etat['medias']);
$libelles = ['modifie' => ['modifié', 'puce--alerte'], 'nouveau' => ['nouveau', 'puce--accent'], 'equivalent' => ['même contenu, autre écriture', '']];
?>
<div class="titre">
  <h1>Le site</h1>
  <?php if (site_adresse() !== ''): ?><div class="titre__actions"><a class="bouton" href="<?= e(site_adresse()) ?>" target="_blank" rel="noopener">Voir le site</a></div><?php endif; ?>
</div>
<?php if ($souci !== ''): ?><p class="message message--erreur" role="alert"><?= e($souci) ?></p><?php endif; ?>

<div class="deux">
<div>
  <div class="carte">
    <h2>Écarts entre Pupitre et le site</h2>
    <?php if ($etat && !$changes && !$etat['medias']): ?>
      <p><span class="puce puce--ok">aucun écart</span> Le site dit ce que dit Pupitre.</p>
    <?php elseif ($etat): ?>
      <p class="intro"><?= e(pluriel($etat['ecarts'], 'écart', 'écarts')) ?>. Ouvrez un fichier pour voir ce qui change : en rouge ce qui part, en vert ce qui arrive.</p>
      <?php foreach ($changes as $chemin => $f): ?>
        <details class="ecart">
          <summary><code><?= e($chemin) ?></code> <span class="puce <?= $libelles[$f['etat']][1] ?>"><?= $libelles[$f['etat']][0] ?></span></summary>
          <div class="diff"><?php foreach (differences_lignes($f['avant'], $f['apres']) as [$signe, $ligne]): ?><div class="<?= $signe === '-' ? 'moins' : ($signe === '+' ? 'plus' : ($signe === '…' ? 'coupe' : '')) ?>"><?= $signe === '…' ? '⋯' : e($signe . ' ' . (mb_strlen($ligne) > 360 ? mb_substr($ligne, 0, 360) . ' …' : $ligne)) ?></div><?php endforeach; ?></div>
        </details>
      <?php endforeach; ?>
      <?php foreach ($etat['medias'] as $source => $cible): ?>
        <p class="ecart"><code><?= e($cible) ?></code> <span class="puce puce--accent">média à copier</span></p>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($reecrits): ?>
      <details class="ecart"><summary><?= e(pluriel(count($reecrits), 'fichier sera réécrit', 'fichiers seront réécrits')) ?> sans changer de contenu</summary>
        <p class="aide">Mêmes données, écrites autrement (retraits, ordre des clés) : <?= e(implode(', ', array_keys($reecrits))) ?>. Ce n'est pas un écart.</p></details>
    <?php endif; ?>
    <p class="aide">À chaque publication, le numéro de version des scripts change dans toutes les pages : les navigateurs rechargent les fichiers.</p>
  </div>

  <?php if ($entrent || $sortent): ?>
  <div class="carte">
    <h2>Agenda</h2>
    <?php if ($entrent): ?>
      <h3>Passeront à « publiée »</h3>
      <ul class="items"><?php foreach ($entrent as $r): ?><li><span><?= e(date_fr($r['date'])) ?> · <?= e($r['titre_fr']) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($sortent): ?>
      <h3 style="margin-top:.8rem">Annulées ou reportées depuis la dernière publication</h3>
      <ul class="items"><?php foreach ($sortent as $r): ?><li><span><?= e(date_fr($r['date'])) ?> · <?= e($r['titre_fr']) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <p class="aide">Le site ne reçoit que les représentations annoncées par leur organisateur : les dates connues, sous embargo ou annulées n'y sortent jamais.</p>
  </div>
  <?php endif; ?>
</div>

<div>
  <div class="carte">
    <h2>Dernière publication</h2>
    <?php if ($derniere): ?>
      <p><strong><?= e(horodatage_fr($derniere['cree_le'])) ?></strong><br><span class="discret">par <?= e($derniere['compte_nom']) ?> · version <?= e($derniere['version']) ?></span></p>
    <?php else: ?>
      <p class="rien">Pupitre n'a pas encore publié.</p>
    <?php endif; ?>
    <?php if (peut('site.publier')): ?>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="publier">
        <button type="submit" class="bouton bouton--plein"<?= $a_publier ? '' : ' disabled' ?> data-confirmer="Publier vers le site ? Les fichiers remplacés sont sauvegardés : on peut revenir en arrière.">Publier</button></form>
      <?php if (!$a_publier): ?>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="version">
        <button type="submit" class="bouton bouton--petit" data-confirmer="Donner un nouveau numéro de version aux scripts et aux feuilles de style du site ?">Renouveler la version des fichiers</button>
        <span class="aide">À faire après une modification à la main d'une feuille de style ou d'un script du site, quand aucun contenu n'a changé : les navigateurs rechargent alors ces fichiers.</span></form>
      <?php endif; ?>
      <?php if ($derniere): ?>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="revenir">
        <button type="submit" class="bouton bouton--danger" data-confirmer="Remettre le site dans son état d'avant la publication du <?= e(horodatage_fr($derniere['cree_le'])) ?> ?">Revenir à la publication précédente</button></form>
      <?php endif; ?>
    <?php else: ?>
      <p class="aide">La publication revient au pilote.</p>
    <?php endif; ?>
    <p class="aide">Publier écrit les fichiers du site sur ce poste. La mise en ligne reste manuelle.</p>
  </div>
  <div class="carte">
    <h2>Historique</h2>
    <?php if (!$historique): ?><p class="rien">Rien pour l'instant.</p><?php else: ?>
    <ul class="items"><?php foreach ($historique as $p): $m = json_decode((string) $p['fichiers'], true) ?: []; ?>
      <li><span class="item__titre"><?= $p['nature'] === 'retour' ? 'Retour en arrière' : 'Publication' ?><?= $p['nature'] === 'publication' && $p['annulee'] ? ' <span class="puce">défaite</span>' : '' ?></span>
        <span class="discret"><?= e(horodatage_fr($p['cree_le'])) ?> · <?= e($p['compte_nom']) ?> · <?= e(pluriel(count($m['remplaces'] ?? []) + count($m['crees'] ?? []), 'fichier', 'fichiers')) ?></span></li>
    <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</div>
</div>

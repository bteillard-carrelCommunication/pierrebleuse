<?php
/**
 * Base de données (pilote) : où en sont les migrations, les sauvegardes, la mise à niveau.
 * La restauration se fait en ligne de commande : php pupitre/sql/restaurer.php <fichier> --oui
 */

defined('PUPITRE') or exit;

$titre = 'Base de données';

if (est_post()) {
    $action = recu('action');
    try {
        if ($action === 'sauvegarder') {
            $f = base_sauvegarder('manuelle');
            journaliser('creation', 'sauvegarde', null, 'Sauvegarde de la base', null, ['fichier' => basename($f)]);
            message('ok', 'Sauvegarde écrite : stockage/sauvegardes/' . basename($f) . ' (' . taille_lisible((int) filesize($f)) . ').');
        } elseif ($action === 'migrer') {
            $notes = [];
            $faites = migrer(function (string $s) use (&$notes) { $notes[] = $s; });
            if ($faites) journaliser('modification', 'base', null, 'Mise à niveau de la base', null, ['migrations' => $faites]);
            message('ok', $faites ? pluriel(count($faites), 'migration passée', 'migrations passées') . '. ' . ($notes[0] ?? '') : 'La base était déjà à jour.');
        }
    } catch (Throwable $e) {
        journal_fichier('erreurs', 'base : ' . $e->getMessage());
        message('erreur', 'L\'opération a échoué : ' . $e->getMessage() . ' La sauvegarde faite juste avant est dans stockage/sauvegardes/.');
    }
    rediriger(url('base'));
}

$passees = [];
try {
    foreach (bd_lignes('SELECT * FROM migrations ORDER BY nom') as $m) $passees[$m['nom']] = $m;
} catch (Throwable $e) {
    // table pas encore créée
}
$disponibles = migrations_disponibles();
$attente = array_diff_key($disponibles, $passees);
$sauvegardes = sauvegardes_liste();
?>
<div class="titre"><h1>Base de données</h1></div>
<?php if ($attente): ?>
  <p class="message message--alerte" role="alert">La base doit être mise à niveau : <?= e(pluriel(count($attente), 'migration attend', 'migrations attendent')) ?>. Les autres écrans de Pupitre sont fermés d'ici là. Une sauvegarde complète est faite avant la mise à niveau.</p>
<?php endif; ?>

<div class="deux">
  <div>
    <div class="carte">
      <h2>Migrations</h2>
      <p class="aide aide--haut">Chaque évolution de la base est une étape numérotée, rejouable et réversible (pupitre/sql/migrations/).</p>
      <ul class="items">
        <?php foreach ($disponibles as $nom => $chemin): $m = require $chemin; ?>
          <li><span class="item__titre"><?= e($nom) ?></span><span><?= e($m['titre'] ?? '') ?><br>
            <?php if (isset($passees[$nom])): ?><span class="puce puce--ok">passée</span> <span class="discret"><?= e(horodatage_fr($passees[$nom]['passee_le'])) ?></span>
            <?php else: ?><span class="puce puce--alerte">en attente</span><?php endif; ?></span></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($attente): ?>
        <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="migrer"><button type="submit" class="bouton bouton--plein">Sauvegarder, puis mettre la base à niveau</button></form>
      <?php else: ?>
        <p><span class="puce puce--ok">à jour</span> Aucune migration en attente.</p>
      <?php endif; ?>
    </div>
  </div>
  <div>
    <div class="carte">
      <h2>Sauvegardes</h2>
      <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="sauvegarder"><button type="submit" class="bouton">Sauvegarder la base maintenant</button></form>
      <?php if (!$sauvegardes): ?><p class="rien">Aucune sauvegarde pour l'instant.</p><?php else: ?>
      <ul class="items">
        <?php foreach (array_slice($sauvegardes, 0, 12) as [$nom, $taille, $date]): ?>
          <li><span class="item__titre"><?= e(date('d/m/Y H:i', $date)) ?></span><span><code><?= e($nom) ?></code> <span class="discret"><?= e(taille_lisible($taille)) ?></span></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <p class="aide">Les sauvegardes sont dans <code>pupitre/stockage/sauvegardes/</code>, hors du web et hors de git : elles contiennent les comptes. Pour restaurer : <code>php pupitre/sql/restaurer.php &lt;fichier&gt; --oui</code> (la base du moment est sauvegardée avant). Les médias déposés (<code>stockage/medias/</code>) se copient à part.</p>
    </div>
  </div>
</div>

<?php
/** Extrait de l'agence : dépôt d'un PDF, d'un tableur ou d'un texte collé, et liste des imports. */

defined('PUPITRE') or exit;

$titre = 'Extrait de l\'agence';

if (est_post()) {
    if (recu('action') === 'lancer') {
        $lancees = taches_lancer_en_fond();
        message($lancees ? 'ok' : 'alerte', $lancees ? 'Les tâches sont lancées.' : 'Ce poste ne permet pas de lancer les tâches d\'ici : elles attendront le cron.');
        rediriger(url('imports'));
    }
    $depot = import_deposer($_FILES['fichier'] ?? null, (string) ($_POST['texte'] ?? ''));
    if (is_string($depot)) {
        message('erreur', $depot);
        rediriger(url('imports'));
    }
    if (!taches_lancer_en_fond()) message('alerte', 'La lecture attend le cron : ce poste ne permet pas de la lancer d\'ici.');
    rediriger(url('import', ['id' => $depot]));
}
$imports = bd_lignes('SELECT i.*, c.nom AS par FROM imports i LEFT JOIN comptes c ON c.id = i.cree_par ORDER BY i.id DESC LIMIT 40');
$statuts = ['depose' => ['déposé', ''], 'lecture' => ['lecture en cours', 'puce--accent'], 'a_valider' => ['à valider', 'puce--alerte'], 'termine' => ['terminé', 'puce--ok'], 'echec' => ['échec', 'puce--erreur']];
?>
<div class="titre"><h1>Extrait de l'agence</h1></div>
<p class="intro">Déposez l'extrait que tient l'agence artistique. L'IA le lit et propose des engagements ; le programme les compare à l'agenda ; vous validez, ligne par ligne ou en bloc. Rien n'entre dans l'agenda avant. Une date validée y entre « connue » : elle ne sort pas sur le site.</p>
<?php if (!ia_configuree()): ?><p class="message message--alerte">La clé de l'API n'est pas renseignée (ANTHROPIC_API_KEY dans pupitre/.env) : la lecture échouera tant qu'elle manque.</p><?php endif; ?>

<div class="deux">
  <div>
    <?php if (!$imports): ?><div class="carte vide"><p>Aucun extrait déposé pour l'instant.</p></div><?php else: ?>
    <div class="defile"><table class="tableau">
      <thead><tr><th>Déposé</th><th>Document</th><th>Extrait du</th><th>État</th><th>Lu</th></tr></thead>
      <tbody>
      <?php foreach ($imports as $i): $b = json_decode((string) $i['bilan'], true) ?: []; ?>
        <tr>
          <td class="nombre"><a href="<?= e(url('import', ['id' => $i['id']])) ?>"><?= e(horodatage_fr($i['cree_le'])) ?></a><br><span class="discret"><?= e($i['par'] ?? '') ?></span></td>
          <td><?= e($i['nom_origine']) ?> <span class="discret"><?= e($i['nature']) ?></span></td>
          <td><?= $i['date_extrait'] ? e(date_fr($i['date_extrait'])) : '<span class="discret">—</span>' ?></td>
          <td><span class="puce <?= $statuts[$i['statut']][1] ?>"><?= $statuts[$i['statut']][0] ?></span><?= $i['statut'] === 'echec' ? '<br><span class="discret">' . e(couper($i['erreur'], 90)) . '</span>' : '' ?></td>
          <td><?= $b ? e(pluriel((int) $b['representations'], 'représentation', 'représentations')) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
    <?php if (taches_en_cours()): ?>
    <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="lancer">
      <button type="submit" class="bouton">Lancer maintenant</button><span class="aide">Des tâches attendent. Sur un serveur, le cron s'en charge.</span></form>
    <?php endif; ?>
  </div>
  <form method="post" enctype="multipart/form-data" class="carte">
    <?= champ_csrf() ?>
    <h2>Déposer un extrait</h2>
    <div class="champ"><label for="fichier">PDF, tableur (.xlsx, .csv) ou texte (.txt)</label><input type="file" id="fichier" name="fichier" accept=".pdf,.xlsx,.csv,.tsv,.txt"></div>
    <?= champ_texte('texte', 'Ou collez le texte de l\'extrait', '', ['lignes' => 8]) ?>
    <p class="aide">Le document est confidentiel : il reste dans pupitre/stockage/imports/, hors du dépôt git, et son contenu n'est écrit dans aucun journal. Il est envoyé à l'API d'Anthropic pour être lu.</p>
    <div class="actions"><button type="submit" class="bouton bouton--plein">Déposer et lire</button></div>
  </form>
</div>

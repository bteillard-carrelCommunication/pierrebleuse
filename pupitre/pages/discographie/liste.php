<?php
/**
 * Discographie : la collection des albums. Ordre d'affichage, publication. La fiche d'un album est
 * sur son écran (pages/discographie/disque.php).
 */

defined('PUPITRE') or exit;

$titre = 'Discographie';

if (est_post() && ($d = bd_ligne('SELECT * FROM disques WHERE id = ?', [recu_entier('id') ?? 0]))) {
    $action = recu('action');
    if (in_array($action, ['publier', 'depublier'], true)) {
        objet_enregistrer('disques', (int) $d['id'], ['publie' => $action === 'publier' ? 1 : 0], 'disque', preg_replace('/\*/', '', $d['titre']));
    } elseif (in_array($action, ['monter', 'descendre'], true)) {
        $ordre = array_map('intval', bd_colonne('SELECT id FROM disques ORDER BY ordre, id'));
        $i = array_search((int) $d['id'], $ordre, true);
        $j = $i + ($action === 'monter' ? -1 : 1);
        if ($i !== false && $j >= 0 && $j < count($ordre)) {
            [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
            foreach ($ordre as $rang => $x) bd_modifier('disques', ['ordre' => $rang], ['id' => $x]);
            journaliser('modification', 'disque', (int) $d['id'], preg_replace('/\*/', '', $d['titre']) . ' : ordre de la discographie', ['rang' => $i + 1], ['rang' => $j + 1]);
        }
    }
    rediriger(url('disques') . '#d-' . $d['id']);
}
$disques = bd_lignes('SELECT d.*, a.nom AS maison FROM disques d LEFT JOIN annuaire a ON a.id = d.maison_id ORDER BY d.ordre, d.id');
?>
<div class="titre">
  <h1>Discographie</h1><span class="titre__suite">data/disques.js · <?= e(pluriel(count($disques), 'album', 'albums')) ?></span>
  <div class="titre__actions"><a class="bouton bouton--plein" href="<?= e(url('disque', ['id' => 'nouveau'])) ?>">Nouvel album</a></div>
</div>
<p class="intro">L'ordre de la liste est celui du site. Chaque album a sa vignette, sobre, et un panneau « En savoir plus » qui montre tout ce qui est renseigné : présentation, notes de l'artiste, distinctions, critiques, visuels, liens d'écoute et de commande.</p>

<?php if (!$disques): ?><div class="carte vide"><p>Aucun album.</p></div><?php else: ?>
<div class="carte">
  <div class="tableau-defile"><table class="tableau">
    <thead><tr><th>Album</th><th>Année, label</th><th>Renseigné</th><th>État</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($disques as $i => $d): $id = (int) $d['id'];
        $n = ['distinctions' => (int) bd_valeur('SELECT COUNT(*) FROM distinctions WHERE disque_id = ?', [$id]), 'critiques' => (int) bd_valeur('SELECT COUNT(*) FROM articles_disques WHERE disque_id = ?', [$id]),
              'visuels' => (int) bd_valeur("SELECT COUNT(*) FROM medias_usages WHERE objet = 'disques' AND objet_id = ?", [(string) $id]), 'liens' => (int) bd_valeur('SELECT COUNT(*) FROM disques_liens WHERE disque_id = ?', [$id])]; ?>
      <tr id="d-<?= $id ?>"<?= $d['publie'] ? '' : ' class="passee"' ?>>
        <th scope="row"><a href="<?= e(url('disque', ['id' => $id])) ?>"><?= e(preg_replace('/\*/', '', $d['titre'])) ?></a></th>
        <td><?= e(implode(' · ', array_filter([(string) $d['annee'], (string) $d['maison']], 'strlen'))) ?></td>
        <td class="discret"><?= e(implode(', ', array_filter([
            trim((string) $d['presentation_fr']) !== '' ? 'présentation' : '', trim((string) $d['mot_fr']) !== '' ? 'mot de l\'artiste' : '',
            $n['distinctions'] ? pluriel($n['distinctions'], 'distinction', 'distinctions') : '', $n['critiques'] ? pluriel($n['critiques'], 'critique', 'critiques') : '',
            $n['visuels'] ? pluriel($n['visuels'], 'visuel', 'visuels') : '', $n['liens'] ? pluriel($n['liens'], 'lien', 'liens') : '']))) ?: '—' ?></td>
        <td><?= $d['publie'] ? '<span class="puce puce--ok">publié</span>' : '<span class="puce puce--alerte">dépublié</span>' ?></td>
        <td class="tableau__actions">
          <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" name="action" value="monter" class="outil" title="Monter" aria-label="Monter cet album"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
            <button type="submit" name="action" value="descendre" class="outil" title="Descendre" aria-label="Descendre cet album"<?= $i === count($disques) - 1 ? ' disabled' : '' ?>>↓</button>
            <button type="submit" name="action" value="<?= $d['publie'] ? 'depublier' : 'publier' ?>" class="bouton bouton--petit"><?= $d['publie'] ? 'Dépublier' : 'Publier' ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?= apercu_zones(['DISQUES' => 'Vignettes']) ?>

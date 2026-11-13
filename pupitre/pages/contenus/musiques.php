<?php
/**
 * Musiques : les titres du lecteur du site. Dépôt du fichier, mention, ordre.
 * Pupitre lance outils/analyse-bande-son.py du site si python3 et ffmpeg sont présents ; sinon il
 * affiche la commande à lancer.
 */

defined('PUPITRE') or exit;

$titre = 'Musiques';
$liste = ['table' => 'musiques', 'cle' => 'titre_fr', 'ajout' => 'Ajouter un titre', 'champs' => [
    ['titre', 'bi', 'Titre', 'requis' => true, 'aide' => 'Libellé de la liste du lecteur. *…* pour l\'italique.'],
    ['mention', 'bi', 'Mention', 'aide' => 'Seconde ligne, plus discrète : interprètes, source, droits.'],
    ['media_id', 'media', 'Fichier son', 'types' => ['son'], 'requis' => true],
]];
$erreurs = [];
$manque = analyse_possible();

/** Demande l'analyse des titres qui n'en ont pas. Rend le nombre de tâches déposées. */
$analyser = function (?int $seul = null): int {
    $n = 0;
    foreach (bd_lignes("SELECT * FROM musiques WHERE media_id IS NOT NULL AND (piste = '' OR id = ?)", [$seul ?? 0]) as $t) {
        if ($seul !== null && (int) $t['id'] !== $seul) continue;
        tache_creer('analyse_son', ['media_id' => (int) $t['media_id'], 'table' => 'musiques', 'cle' => (int) $t['id'], 'nom' => $t['cle']]);
        $n++;
    }
    return $n;
};

if (est_post()) {
    $action = recu('action');
    if ($action === 'deposer') {
        $depot = media_deposer($_FILES['fichier'] ?? [], ['credit' => recu('credit'), 'droits' => recu('droits')], ['son']);
        if (is_string($depot)) message('erreur', $depot);
        else message('ok', 'Fichier déposé dans la médiathèque. Ajoutez un titre et choisissez-le comme fichier son.');
        rediriger(url('musiques'));
    } elseif ($action === 'analyser') {
        if ($manque !== '') message('alerte', 'Analyse impossible ici : ' . $manque . '. La commande à lancer est indiquée sous chaque titre.');
        else {
            $n = $analyser(recu_entier('id'));
            message(taches_lancer_en_fond() ? 'ok' : 'alerte', pluriel($n, 'analyse demandée', 'analyses demandées') . '. Elles tournent en tâche de fond : rechargez la page dans un instant.');
        }
        rediriger(url('musiques'));
    } else {
        $avant = [];
        foreach (bd_lignes('SELECT id, media_id FROM musiques') as $t) $avant[(int) $t['id']] = (int) $t['media_id'];
        $erreurs = liste_enregistrer('Musiques', $liste, is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : []);
        if (!$erreurs) {
            // un titre dont le fichier a changé n'a plus d'analyse valable
            foreach (bd_lignes('SELECT id, media_id FROM musiques') as $t) {
                if (isset($avant[(int) $t['id']]) && $avant[(int) $t['id']] !== (int) $t['media_id']) bd_modifier('musiques', ['piste' => ''], ['id' => $t['id']]);
            }
            $n = $manque === '' ? $analyser() : 0;
            if ($n) taches_lancer_en_fond();
            message('ok', 'Musiques enregistrées.' . ($n ? ' ' . pluriel($n, 'analyse de bande-son lancée', 'analyses de bande-son lancées') . ' en tâche de fond.' : ''));
            rediriger(url('musiques'));
        }
    }
}
$titres = ($erreurs) ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : bd_lignes('SELECT * FROM musiques ORDER BY ordre, id');
$etat = bd_lignes('SELECT * FROM musiques ORDER BY ordre, id');
$medias = medias_par_id();
$racine = site_racine();
?>
<div class="titre"><h1>Musiques</h1><span class="titre__suite">data/playlist.js</span></div>
<p class="intro">Le premier titre est celui de l'ouverture du site. À la fin d'un titre, le suivant s'enchaîne. Chaque titre a besoin de l'analyse de sa bande-son : c'est elle que suit la nuée.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="deux">
  <form method="post" class="carte">
    <?= champ_csrf() ?>
    <?= liste_formulaire($liste, $titres) ?>
    <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer les titres</button></div>
  </form>
  <div>
    <form method="post" enctype="multipart/form-data" class="carte">
      <?= champ_csrf() ?><input type="hidden" name="action" value="deposer">
      <h2>Déposer un fichier son</h2>
      <div class="champ"><label for="fichier">Fichier (.m4a, .mp3), <?= e(taille_lisible(taille_max('son'))) ?> au plus</label><input type="file" id="fichier" name="fichier" accept=".m4a,.mp3,.aac,audio/*" required></div>
      <?= champ('credit', 'Crédit', '', ['aide' => 'Interprètes, source.']) ?>
      <?= champ_texte('droits', 'Droits d\'usage', '', ['lignes' => 2]) ?>
      <button type="submit" class="bouton">Déposer</button>
    </form>
    <div class="carte">
      <h2>Analyses des bandes-son</h2>
      <?php if ($manque !== ''): ?><p class="message message--alerte">Analyse impossible ici : <?= e($manque) ?>. Lancez la commande indiquée, depuis la racine du site.</p><?php endif; ?>
      <ul class="items">
      <?php foreach ($etat as $t): $m = $medias[(int) $t['media_id']] ?? null; ?>
        <li>
          <span class="item__titre"><?= e(preg_replace('/\*/', '', $t['titre_fr'])) ?></span>
          <?php if ($m): ?><audio controls preload="none" src="<?= e(media_adresse((int) $m['id'])) ?>"></audio><?php endif; ?>
          <?php if ($t['piste'] !== ''): ?>
            <span><span class="puce puce--ok">analysée</span> <code><?= e($t['piste']) ?></code></span>
          <?php else: ?>
            <span><span class="puce puce--alerte">analyse à faire</span></span>
            <?php if ($manque !== '' && $m): ?><code class="discret">python3 outils/analyse-bande-son.py <?= e(media_chemin_site($m)) ?> data/<?= e($t['cle']) ?>.json</code><?php endif; ?>
          <?php endif; ?>
          <?php if ($m && $manque === ''): ?>
          <form method="post" class="item__actions"><?= champ_csrf() ?><input type="hidden" name="action" value="analyser"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <button type="submit" class="bouton bouton--petit"><?= $t['piste'] !== '' ? 'Refaire l\'analyse' : 'Analyser' ?></button></form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      </ul>
      <?php if (taches_en_cours()): ?><p class="aide">Des tâches sont en cours. Rechargez la page pour voir leur résultat.</p><?php endif; ?>
    </div>
  </div>
</div>

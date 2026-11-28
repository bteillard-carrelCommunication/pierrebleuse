<?php
/**
 * Playlist : les titres du lecteur du site. Pour chacun : titre et mention (ce que le lecteur
 * affiche), compositeur, œuvre, mouvement, orchestre, interprètes, nature de l'enregistrement
 * (studio, live, autre), fichier de la médiathèque ou adresse externe, crédits, publication.
 * L'ordre de la liste est celui du lecteur ; le morceau d'ouverture se choisit à part.
 * Pupitre lance outils/analyse-bande-son.py du site si python3 et ffmpeg sont présents ; sinon il
 * affiche la commande à lancer.
 */

defined('PUPITRE') or exit;

$titre = 'Playlist';
const NATURES_ENREGISTREMENT = ['studio' => 'Studio', 'live' => 'Live (concert)', 'autre' => 'Autre'];
$orchestres = bd_colonne("SELECT nom FROM annuaire WHERE categorie IN ('orchestre', 'ensemble') ORDER BY nom");
$liste = ['table' => 'musiques', 'cle' => 'titre_en', 'ajout' => 'Ajouter un titre',
    'controle' => fn(array $d) => (empty($d['media_id']) && ($d['url'] ?? '') === '') ? 'Donnez un fichier son de la médiathèque, ou l\'adresse du fichier.' : '',
    'champs' => [
    ['titre', 'bi', 'Titre', 'requis' => true, 'aide' => 'Première ligne de la liste du lecteur. *…* pour l\'italique.'],
    ['mention', 'bi', 'Mention', 'aide' => 'Seconde ligne, plus discrète, telle que le lecteur l\'affiche.'],
    ['compositeur', 'bi', 'Compositeur', 'rang' => 'oeuvre'],
    ['oeuvre', 'bi', 'Œuvre', 'rang' => 'oeuvre'],
    ['mouvement', 'bi', 'Mouvement (s\'il y a lieu)'],
    ['orchestre', 'ligne', 'Orchestre', 'max' => 255, 'suggestions' => $orchestres, 'aide' => 'Appellation officielle, non traduite.', 'rang' => 'qui'],
    ['nature', 'choix', 'Type d\'enregistrement', 'options' => NATURES_ENREGISTREMENT, 'vide' => '— à préciser —', 'defaut' => '', 'aide' => 'Studio, live ou autre : Pierre Bleuse privilégie le studio pour un extrait diffusé hors d\'une vidéo.', 'rang' => 'qui'],
    ['interpretes', 'bi', 'Interprètes'],
    ['media_id', 'media', 'Fichier son (médiathèque)', 'types' => ['son'], 'rang' => 'source'],
    ['url', 'adresse', 'ou adresse du fichier son', 'aide' => 'Un fichier audio hébergé ailleurs. L\'adresse l\'emporte sur le fichier.', 'rang' => 'source'],
    ['credit', 'ligne', 'Crédits', 'max' => 500, 'aide' => 'Source, label, droits.'],
    ['publie', 'case', 'Publié : le titre figure dans le lecteur du site', 'defaut' => 1],
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
    } elseif ($action === 'ouverture') {
        // le morceau d'ouverture : un seul, choisi parmi les titres, quel que soit son rang
        $id = recu_entier('ouverture');
        $t = $id ? bd_ligne('SELECT * FROM musiques WHERE id = ?', [$id]) : null;
        if ($t) {
            $avant = bd_ligne('SELECT id, titre_en FROM musiques WHERE ouverture = 1');
            if (!$avant || (int) $avant['id'] !== $id) {
                bd_requete('UPDATE musiques SET ouverture = (id = ?)', [$id]);
                journaliser('modification', 'contenu', $id, 'Morceau d\'ouverture du site', ['ouverture' => $avant['titre_en'] ?? ''], ['ouverture' => $t['titre_en']]);
            }
            message('ok', 'Morceau d\'ouverture : « ' . preg_replace('/\*/', '', $t['titre_en']) . ' ». Le site suivra à la prochaine publication.');
        }
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
        $erreurs = liste_enregistrer('Playlist', $liste, is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : []);
        if (!$erreurs) {
            // un titre dont le fichier a changé n'a plus d'analyse valable
            foreach (bd_lignes('SELECT id, media_id FROM musiques') as $t) {
                if (isset($avant[(int) $t['id']]) && $avant[(int) $t['id']] !== (int) $t['media_id']) bd_modifier('musiques', ['piste' => ''], ['id' => $t['id']]);
            }
            // il y a toujours un morceau d'ouverture : si celui qui l'était a été retiré, le premier de la liste
            if (!bd_valeur('SELECT COUNT(*) FROM musiques WHERE ouverture = 1') && ($premier = bd_valeur('SELECT id FROM musiques ORDER BY ordre, id LIMIT 1'))) {
                bd_requete('UPDATE musiques SET ouverture = 1 WHERE id = ?', [$premier]);
            }
            $n = $manque === '' ? $analyser() : 0;
            if ($n) taches_lancer_en_fond();
            message('ok', 'Playlist enregistrée.' . ($n ? ' ' . pluriel($n, 'analyse de bande-son lancée', 'analyses de bande-son lancées') . ' en tâche de fond.' : ''));
            rediriger(url('musiques'));
        }
    }
}
$titres = ($erreurs) ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : bd_lignes('SELECT * FROM musiques ORDER BY ordre, id');
$etat = bd_lignes('SELECT * FROM musiques ORDER BY ordre, id');
$medias = medias_par_id();
$racine = site_racine();
?>
<div class="titre"><h1>Playlist</h1><span class="titre__suite">data/playlist.js</span></div>
<p class="intro">Les titres du lecteur, dans l'ordre de sa liste. À la fin d'un titre, le suivant s'enchaîne. Le site s'ouvre sur le morceau d'ouverture, quel que soit son rang. Chaque titre a besoin de l'analyse de sa bande-son : c'est elle que suit la nuée.</p>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="deux">
  <form method="post" class="carte">
    <?= champ_csrf() ?>
    <?= liste_formulaire($liste, $titres, 'lignes', (bool) $erreurs) ?>
    <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer les titres</button></div>
  </form>
  <div>
    <form method="post" class="carte">
      <?= champ_csrf() ?><input type="hidden" name="action" value="ouverture">
      <h2>Morceau d'ouverture</h2>
      <?php $choix = []; $ouverture = ''; foreach ($etat as $t) { $choix[(int) $t['id']] = preg_replace('/\*/', '', $t['titre_en']) . ($t['publie'] ? '' : ' (non publié)'); if ($t['ouverture']) $ouverture = (int) $t['id']; } ?>
      <?= champ_choix('ouverture', 'Le site s\'ouvre sur', $choix, $ouverture, ['aide' => 'Indépendant de l\'ordre de la liste. Si ce titre n\'est pas publié, le site s\'ouvre sur le premier de la liste.']) ?>
      <div class="actions"><button type="submit" class="bouton">Choisir ce morceau</button></div>
    </form>
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
          <span class="item__titre"><?= e(preg_replace('/\*/', '', $t['titre_en'])) ?><?= $t['ouverture'] ? ' <span class="puce puce--accent">ouverture</span>' : '' ?><?= $t['publie'] ? '' : ' <span class="puce">non publié</span>' ?></span>
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

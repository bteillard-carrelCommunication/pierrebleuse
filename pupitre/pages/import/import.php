<?php
/**
 * Un import : suivi de la lecture, puis validation ligne par ligne ou en bloc, avec correction
 * possible. Rien n'est écrit dans l'agenda avant la validation.
 */

defined('PUPITRE') or exit;

$id = recu_entier('id') ?? 0;
$imp = bd_ligne('SELECT * FROM imports WHERE id = ?', [$id]);
if (!$imp) {
    http_response_code(404);
    $titre = 'Import introuvable';
    echo '<div class="vide"><h1>Import introuvable</h1><p><a class="bouton" href="' . e(url('imports')) . '">Retour</a></p></div>';
    return;
}
$titre = 'Extrait' . ($imp['date_extrait'] ? ' du ' . date_fr($imp['date_extrait']) : ' déposé le ' . date('d/m/Y', strtotime($imp['cree_le'])));

if (est_post()) {
    $action = recu('action');
    $une = recu('une');
    if ($action === 'lancer') {
        message(taches_lancer_en_fond() ? 'ok' : 'alerte', 'Lecture lancée.');
    } elseif ($action === 'relire' && in_array($imp['statut'], ['echec', 'depose'], true)) {
        $tache = tache_creer('import_lecture', ['import_id' => $id]);
        bd_modifier('imports', ['tache_id' => $tache, 'statut' => 'depose', 'erreur' => ''], ['id' => $id]);
        taches_lancer_en_fond();
        message('ok', 'La lecture est relancée.');
    } elseif ($une !== '' && preg_match('/^(valider|refuser|corriger):(\d+)$/', $une, $m)) {
        $ligne = bd_ligne('SELECT id FROM import_lignes WHERE id = ? AND import_id = ?', [(int) $m[2], $id]);
        $e = 'Ligne introuvable.';
        if ($ligne) {
            $lid = (int) $ligne['id'];
            if ($m[1] === 'corriger') {
                $e = import_ligne_corriger($lid, is_array($_POST['corr'][$lid] ?? null) ? $_POST['corr'][$lid] : []);
                if (!$e) message('ok', 'Ligne corrigée. Elle reste à valider.');
            } elseif ($m[1] === 'valider') {
                $e = import_ligne_valider($lid, (string) ($_POST['choix'][$lid] ?? ''));
                if (!$e) message('ok', 'Validé : l\'agenda est à jour.');
            } else {
                $e = import_ligne_refuser($lid);
                if (!$e) message('ok', 'Refusé : l\'agenda ne change pas.');
            }
        }
        if ($e) message('erreur', $e);
    } elseif (in_array(recu('bloc'), ['valider', 'refuser'], true)) {
        $faites = 0;
        $soucis = [];
        foreach (array_filter((array) ($_POST['lignes'] ?? []), 'ctype_digit') as $lid) {
            $ligne = bd_ligne('SELECT id, nature FROM import_lignes WHERE id = ? AND import_id = ?', [(int) $lid, $id]);
            if (!$ligne) continue;
            $e = recu('bloc') === 'valider' ? import_ligne_valider((int) $lid, (string) ($_POST['choix'][$lid] ?? '')) : import_ligne_refuser((int) $lid);
            if ($e) $soucis[] = $e; else $faites++;
        }
        if ($faites) message('ok', pluriel($faites, 'ligne', 'lignes') . (recu('bloc') === 'valider' ? ' validée(s).' : ' refusée(s).'));
        foreach (array_unique($soucis) as $s) message('erreur', $s);
        if (!$faites && !$soucis) message('alerte', 'Cochez d\'abord des lignes.');
    }
    rediriger(url('import', ['id' => $id]));
}

$tache = $imp['tache_id'] ? tache_lire((int) $imp['tache_id']) : null;
$lignes = in_array($imp['statut'], ['a_valider', 'termine'], true) ? import_lignes($id) : [];
$bilan = json_decode((string) $imp['bilan'], true) ?: [];
$en_attente = array_filter($lignes, fn($l) => $l['decision'] === 'en_attente');
$inchangees = array_filter($lignes, fn($l) => $l['nature'] === 'inchangee');
$par_nature = [];
foreach ($lignes as $l) if ($l['nature'] !== 'inchangee') $par_nature[$l['nature']][] = $l;
$ordre_natures = ['douteuse', 'nouvelle', 'modifiee', 'programme', 'disparue'];
$intertitres = ['douteuse' => 'Cas douteux', 'nouvelle' => 'Nouvelles dates', 'modifiee' => 'Dates modifiées', 'programme' => 'Programmes changés', 'disparue' => 'Dates disparues'];
$decisions = ['validee' => ['validée', 'puce--ok'], 'refusee' => ['refusée', '']];
$champs_fr = ['heure' => 'heure', 'salle' => 'salle', 'ville' => 'ville', 'pays' => 'pays', 'date' => 'date'];
$programme = fn(array $p) => implode(' · ', array_map(fn($x) => trim(($x['compositeur'] ?? $x['compositeur_fr'] ?? '') . ', ' . ($x['oeuvre'] ?? $x['oeuvre_fr'] ?? ''), ', '), $p));
?>
<p class="fil"><a href="<?= e(url('imports')) ?>">Extrait de l'agence</a></p>
<div class="titre"><h1><?= e($titre) ?></h1><span class="titre__suite"><?= e($imp['nom_origine']) ?></span></div>

<?php if (in_array($imp['statut'], ['depose', 'lecture'], true)): ?>
  <div class="carte">
    <h2>Lecture en cours</h2>
    <p class="intro">L'IA lit le document, puis le programme compare ce qu'elle rend à l'agenda. Cela prend d'une à quelques minutes ; cette page se met à jour seule.</p>
    <?= $tache ? suivi_tache($tache) : '' ?>
    <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="lancer">
      <button type="submit" class="bouton bouton--petit">Lancer maintenant</button><span class="aide">Si rien n'avance : sur un poste local, ce bouton démarre la tâche ; sur un serveur, le cron s'en charge.</span></form>
  </div>
<?php elseif ($imp['statut'] === 'echec'): ?>
  <div class="carte">
    <h2>La lecture a échoué</h2>
    <p class="message message--erreur" role="alert"><?= e($imp['erreur']) ?></p>
    <p>Rien n'a été retenu de ce document, et l'agenda n'a pas changé.</p>
    <form method="post" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="relire"><button type="submit" class="bouton bouton--plein">Relancer la lecture</button></form>
  </div>
<?php else: ?>

<div class="carte">
  <h2>Ce que dit l'extrait</h2>
  <p><?= e(pluriel((int) ($bilan['engagements'] ?? 0), 'engagement', 'engagements')) ?>, <?= e(pluriel((int) ($bilan['representations'] ?? 0), 'représentation', 'représentations')) ?>.
    <?php foreach ($ordre_natures as $n): if (!empty($bilan['natures'][$n])): ?> <span class="puce <?= $n === 'douteuse' ? 'puce--alerte' : 'puce--accent' ?>"><?= (int) $bilan['natures'][$n] ?> · <?= e(mb_strtolower(NATURES_IMPORT[$n])) ?></span><?php endif; endforeach; ?>
    <?php if (!empty($bilan['natures']['inchangee'])): ?> <span class="puce"><?= (int) $bilan['natures']['inchangee'] ?> · inchangée(s)</span><?php endif; ?></p>
  <h3 style="margin-top:.8rem">Contrôle</h3>
  <?php if (empty($bilan['controles'])): ?><p><span class="puce puce--ok">rien à signaler</span></p><?php else: ?>
  <p class="manques"><?php foreach (CONTROLES_IMPORT as $k => $libelle): if (!empty($bilan['controles'][$k])): ?><span class="puce puce--alerte"><?= (int) $bilan['controles'][$k] ?> · <?= e($libelle) ?></span><?php endif; endforeach; ?></p>
  <?php endif; ?>
  <p class="aide">Le contrôle et la comparaison sont faits par le programme, pas par l'IA. Les dates sont celles du document : une date qui semble fausse est signalée, pas corrigée.</p>
</div>

<?php if (!$par_nature): ?><div class="carte"><p><span class="puce puce--ok">rien à valider</span> L'extrait dit ce que dit l'agenda.</p></div><?php endif; ?>

<form method="post" id="bloc">
<?= champ_csrf() ?>
<?php /* la touche Entrée dans un champ déclenche le premier bouton du formulaire : celui-ci ne fait rien */ ?>
<button type="submit" name="bloc" value="" class="sr" tabindex="-1" aria-hidden="true">Ne rien faire</button>
<?php foreach ($ordre_natures as $nature): if (empty($par_nature[$nature])) continue; ?>
<section class="mois mois--import">
  <h2><?= e($intertitres[$nature]) ?> <span class="discret">(<?= count($par_nature[$nature]) ?>)</span></h2>
  <div class="defile"><table class="tableau">
    <thead><tr><th><span class="sr">Cocher</span></th><th>Date</th><th>Engagement</th><th>Lieu</th><th>Contrôle</th><th>Décision</th></tr></thead>
    <tbody>
    <?php foreach ($par_nature[$nature] as $l): $p = $l['propose']; $attente = $l['decision'] === 'en_attente'; $lid = (int) $l['id']; ?>
      <tr<?= $attente ? '' : ' class="passee"' ?>>
        <td><?php if ($attente): ?><input type="checkbox" name="lignes[]" value="<?= $lid ?>" id="l-<?= $lid ?>" aria-label="Cocher la ligne du <?= e(date_fr($p['date'] ?? '')) ?>"><?php endif; ?></td>
        <td class="date"><strong><?= e(date_fr($p['date'] ?? '')) ?></strong><?= !empty($p['heure']) ? '<br>' . e(heure_fr($p['heure'])) : '' ?></td>
        <th scope="row">
          <?= e(($p['titre'] ?? '') !== '' ? $p['titre'] : ($p['ensemble'] ?? '')) ?>
          <?php if (($p['titre'] ?? '') !== '' && ($p['ensemble'] ?? '') !== ''): ?><br><span class="discret"><?= e($p['ensemble']) ?></span><?php endif; ?>
          <?php if ($nature === 'nouvelle' && !empty($p['programme'])): ?><br><span class="discret"><?= e(couper($programme($p['programme']), 160)) ?></span><?php endif; ?>
          <?php if ($nature === 'programme'): ?>
            <div class="journal__diff"><div><span class="avant"><?= e(couper($programme($l['avant']['programme'] ?? []), 300)) ?: '(aucun programme)' ?></span></div><div><span class="apres"><?= e(couper($programme($p['programme'] ?? []), 300)) ?></span></div></div>
          <?php endif; ?>
          <?php if ($l['concert_id']): ?><br><a class="discret" href="<?= e(url('concert', ['id' => $l['concert_id']])) ?>">Ouvrir la fiche</a><?php endif; ?>
        </th>
        <td>
          <?php if ($nature === 'modifiee'): ?>
            <div class="journal__diff"><?php foreach ($p['champs'] ?? [] as $k): ?><div><span class="discret"><?= e($champs_fr[$k] ?? $k) ?> :</span> <span class="avant"><?= e(($l['avant'][$k] ?? '') !== '' && $l['avant'][$k] !== null ? ($k === 'heure' ? heure_fr($l['avant'][$k]) : $l['avant'][$k]) : '(vide)') ?></span> <span class="apres"><?= e($k === 'heure' ? heure_fr($p[$k]) : $p[$k]) ?></span></div><?php endforeach; ?></div>
          <?php elseif ($nature === 'disparue'): ?>
            <span class="discret">Dans l'agenda (<?= e(mb_strtolower(STATUTS[$l['avant']['statut'] ?? 'connue'] ?? '')) ?>), absente de l'extrait. Valider l'annule.</span>
          <?php else: ?>
            <?= e(implode(', ', array_filter([$p['ville'] ?? '', $p['pays'] ?? ''], 'strlen'))) ?><?= ($p['salle'] ?? '') !== '' ? '<br><span class="discret">' . e($p['salle']) . '</span>' : '' ?>
          <?php endif; ?>
          <?php if ($nature === 'douteuse' && $attente): ?>
            <div class="champ" style="margin-top:.4rem"><label for="choix-<?= $lid ?>">C'est…</label>
              <select id="choix-<?= $lid ?>" name="choix[<?= $lid ?>]"><option value="">— à trancher —</option><option value="nouvelle">une nouvelle date</option>
              <?php foreach ($l['candidats'] as $cid): $r = bd_ligne('SELECT r.id, r.date, r.heure, c.titre_fr FROM representations r JOIN concerts c ON c.id = r.concert_id WHERE r.id = ?', [$cid]); if (!$r) continue; ?>
                <option value="<?= (int) $r['id'] ?>">la date du <?= e(date_fr($r['date'])) ?> de « <?= e(couper($r['titre_fr'], 50)) ?> », à mettre à jour</option>
              <?php endforeach; ?></select></div>
          <?php endif; ?>
        </td>
        <td><div class="manques"><?php foreach ($l['controles'] as $k): ?><span class="puce puce--alerte"><?= e(CONTROLES_IMPORT[$k] ?? $k) ?></span><?php endforeach; ?></div></td>
        <td>
          <?php if ($attente): ?>
            <div class="en-ligne">
              <button type="submit" name="une" value="valider:<?= $lid ?>" class="bouton bouton--petit bouton--plein">Valider</button>
              <button type="submit" name="une" value="refuser:<?= $lid ?>" class="bouton bouton--petit">Refuser</button>
            </div>
            <?php if (in_array($nature, ['nouvelle', 'modifiee', 'douteuse'], true)): ?>
            <details style="margin-top:.4rem"><summary>Corriger</summary>
              <?php $c = 'corr[' . $lid . ']'; ?>
              <?= champ('date', 'Date', $p['date'] ?? '', ['prefixe' => $c, 'type' => 'date']) ?>
              <?= champ('heure', 'Heure', $p['heure'] ?? '', ['prefixe' => $c, 'indication' => '19:30']) ?>
              <?= champ('salle', 'Salle', $p['salle'] ?? '', ['prefixe' => $c]) ?>
              <?= champ('ville', 'Ville', $p['ville'] ?? '', ['prefixe' => $c]) ?>
              <?= champ('pays', 'Pays', $p['pays'] ?? '', ['prefixe' => $c]) ?>
              <?php if ($nature !== 'modifiee'): ?>
                <?= champ('titre', 'Titre', $p['titre'] ?? '', ['prefixe' => $c]) ?>
                <?= champ('ensemble', 'Ensemble', $p['ensemble'] ?? '', ['prefixe' => $c]) ?>
              <?php endif; ?>
              <button type="submit" name="une" value="corriger:<?= $lid ?>" class="bouton bouton--petit">Enregistrer la correction</button>
            </details>
            <?php endif; ?>
          <?php else: ?>
            <span class="puce <?= $decisions[$l['decision']][1] ?? '' ?>"><?= e($decisions[$l['decision']][0] ?? $l['decision']) ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>

<?php if ($en_attente): ?>
<div class="carte actions actions--bas" style="margin:0 0 1.2rem">
  <button type="submit" name="bloc" value="valider" class="bouton bouton--plein">Valider les lignes cochées</button>
  <button type="submit" name="bloc" value="refuser" class="bouton">Refuser les lignes cochées</button>
  <span class="aide"><?= e(pluriel(count($en_attente), 'ligne attend', 'lignes attendent')) ?> une décision. Un cas douteux se tranche avant d'être validé.</span>
</div>
<?php endif; ?>
</form>

<?php if ($inchangees): ?>
<details class="carte"><summary><?= e(pluriel(count($inchangees), 'date inchangée', 'dates inchangées')) ?> : l'extrait et l'agenda sont d'accord</summary>
  <ul class="items"><?php foreach ($inchangees as $l): ?><li><span><?= e(date_fr($l['propose']['date'])) ?> · <?= e(($l['propose']['titre'] ?? '') ?: ($l['propose']['ensemble'] ?? '')) ?></span></li><?php endforeach; ?></ul>
</details>
<?php endif; ?>
<?php endif; ?>

<?php
/**
 * Langues et traductions.
 *
 *   Langues       les langues du site, celle de la première visite, l'ajout d'une langue (pilote) ;
 *   Traductions   tous les textes traduisibles, langue par langue : ce qui manque, ce que l'IA a
 *                 proposé et qui attend une relecture, ce qui est à revoir, ce qui est validé ;
 *   Libellés      les intitulés et boutons de l'interface du site.
 *
 * Le modèle est décrit dans inclus/langues.php.
 */

defined('PUPITRE') or exit;

$titre = 'Langues et traductions';
$onglet = in_array(recu('onglet'), ['langues', 'traductions', 'libelles'], true) ? recu('onglet') : 'langues';
$reference = langue_reference();
$erreurs = [];
$PAR_PAGE = 40;

// ---------- Actions ----------
if (est_post() && $onglet === 'langues') {
    exiger_droit('reglages');
    $action = recu('action');
    $toutes = langues_toutes();
    if ($action === 'ajouter') {
        $code = mb_strtolower(recu('code'));
        if (!preg_match('/^[a-z]{2,3}$/', $code)) $erreurs[] = 'Le code d\'une langue tient en deux ou trois lettres : de, es, it…';
        elseif (isset($toutes[$code])) $erreurs[] = 'Cette langue existe déjà.';
        if (recu('nom') === '') $erreurs[] = 'Donnez le nom de la langue tel qu\'il s\'écrit dans cette langue (Deutsch, Español…).';
        if (!$erreurs) {
            bd_inserer('langues', ['code' => $code, 'nom' => mb_substr(recu('nom'), 0, 60), 'nom_fr' => mb_substr(recu('nom_fr') ?: recu('nom'), 0, 60),
                'locale' => preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', recu('locale')) ? recu('locale') : $code,
                'active' => 0, 'reference' => 0, 'defaut' => 0, 'ordre' => count($toutes)]);
            journaliser('creation', 'langue', null, 'Langue « ' . $code . ' »', null, ['code' => $code, 'nom' => recu('nom')]);
            $GLOBALS['langues_perimees'] = true;
            message('ok', 'Langue ajoutée, encore inactive : traduisez les contenus (onglet Traductions), puis activez-la pour qu\'elle paraisse sur le site.');
            rediriger(url('langues'));
        }
    } elseif ($action === 'enregistrer') {
        $actives = is_array($_POST['active'] ?? null) ? array_keys($_POST['active']) : [];
        $defaut = recu('defaut');
        if (!isset($toutes[$defaut]) || (!in_array($defaut, $actives, true) && $defaut !== $reference)) $erreurs[] = 'La langue de la première visite doit être une langue active.';
        if (!$erreurs) {
            $avant = $apres = [];
            foreach ($toutes as $code => $l) {
                $active = ($code === $reference || in_array($code, $actives, true)) ? 1 : 0;
                $avant[$code] = ['active' => (int) $l['active'], 'defaut' => (int) $l['defaut']];
                $apres[$code] = ['active' => $active, 'defaut' => $code === $defaut ? 1 : 0];
                bd_modifier('langues', $apres[$code], ['code' => $code]);
            }
            journaliser('modification', 'langues', null, 'Langues du site', $avant, $apres);
            $GLOBALS['langues_perimees'] = true;
            message('ok', 'Langues enregistrées. Le site suivra à la prochaine publication.');
            rediriger(url('langues'));
        }
    } elseif ($action === 'supprimer') {
        $code = recu('code');
        $l = $toutes[$code] ?? null;
        if ($l && !$l['reference'] && !$l['defaut']) {
            $n = (int) bd_valeur('SELECT COUNT(*) FROM traductions WHERE langue = ?', [$code]);
            bd_requete('DELETE FROM traductions WHERE langue = ?', [$code]);
            bd_supprimer('langues', ['code' => $code]);
            journaliser('suppression', 'langue', null, 'Langue « ' . $code . ' »', ['code' => $code, 'traductions' => $n], null);
            $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = true;
            message('ok', 'Langue supprimée, avec ses ' . pluriel($n, 'traduction', 'traductions') . '.');
        } else {
            message('erreur', 'La langue de référence et la langue de la première visite ne se suppriment pas.');
        }
        rediriger(url('langues'));
    }
}

if (est_post() && $onglet === 'libelles') {
    $recus = is_array($_POST['lib'] ?? null) ? $_POST['lib'] : [];
    $n = libelles_enregistrer($recus, array_keys($recus));
    message('ok', $n ? pluriel($n, 'libellé enregistré', 'libellés enregistrés') . '. Le site suivra à la prochaine publication.' : 'Rien n\'a changé.');
    rediriger(url('langues', ['onglet' => 'libelles']));
}

if (est_post() && $onglet === 'traductions') {
    $langue = recu('langue');
    $retour = array_filter(['onglet' => 'traductions', 'langue' => $langue, 'statut' => recu('statut'), 'objet' => recu('objet'), 'p' => recu('p')], 'strlen');
    $langue_ok = in_array($langue, array_keys(langues_toutes()), true) && $langue !== $reference;
    // --- propositions de l'IA : toutes les traductions manquantes (tâche de fond), ou un seul texte (tout de suite)
    if ($langue_ok && recu('action') === 'proposer') {
        if (!ia_configuree()) message('erreur', 'L\'IA n\'est pas configurée (Réglages) : les traductions se saisissent à la main.');
        else {
            $tache = tache_creer('traduction', ['langue' => $langue, 'objet' => isset(TRADUISIBLES[recu('objet')]) ? recu('objet') : '']);
            if (!taches_lancer_en_fond()) message('alerte', 'Les propositions attendent le prochain passage des tâches de fond (cron).');
            $retour['tache'] = (string) $tache;
        }
        rediriger(url('langues', $retour));
    }
    if ($langue_ok && recu('proposer_un') !== '') {
        $p = explode('|', recu('proposer_un'));
        if (count($p) === 3 && isset(TRADUISIBLES[$p[0]][3][$p[2]]) && ia_configuree()) {
            $texte_ref = trim(reference_lire($p[0], $p[1], $p[2]));
            $deja = traduction($p[0], $p[1], $p[2], $langue);
            if ($texte_ref === '') message('erreur', 'Ce texte est vide dans la langue de référence.');
            elseif ($deja && $deja['statut'] === 'validee' && trim((string) $deja['texte']) !== '') message('alerte', 'Cette traduction est validée : l\'IA ne la remplace pas. Videz-la d\'abord si vous voulez une nouvelle proposition.');
            else {
                try {
                    $t = ia_traduire([['un', $texte_ref, TRADUISIBLES[$p[0]][0] . ' · ' . TRADUISIBLES[$p[0]][3][$p[2]]]], $langue)['un'] ?? '';
                    if ($t !== '' && $t === $texte_ref) message('ok', 'Ce texte s\'écrit de la même façon en ' . mb_strtolower(langue_nom($langue)) . ' : rien à traduire, le site garde le texte d\'origine.');
                    elseif ($t !== '' && traduction_poser($p[0], $p[1], $p[2], $langue, $t, 'ia')) {
                        journaliser('creation', 'traduction', null, 'Proposition de l\'IA · ' . TRADUISIBLES[$p[0]][0] . ' · ' . TRADUISIBLES[$p[0]][3][$p[2]] . ' · ' . strtoupper($langue), null, ['texte' => $t]);
                        message('ok', 'Traduction proposée : relisez-la, corrigez-la au besoin, puis validez-la.');
                    } else message('alerte', 'L\'IA n\'a rien proposé de neuf pour ce texte.');
                } catch (Throwable $e) {
                    message('erreur', $e instanceof ErreurLisible ? $e->getMessage() : 'La proposition n\'a pas abouti.');
                }
            }
        }
        rediriger(url('langues', $retour));
    }
    if ($langue_ok) {
        $n = 0;
        foreach (is_array($_POST['tr'] ?? null) ? $_POST['tr'] : [] as $r) {
            if (!is_array($r)) continue;
            $p = explode('|', (string) ($r['jeton'] ?? ''));
            if (count($p) !== 3 || !isset(TRADUISIBLES[$p[0]][3][$p[2]])) continue;
            $avant = (string) (traduction($p[0], $p[1], $p[2], $langue)['texte'] ?? '');
            $texte = trim(str_replace("\r\n", "\n", (string) ($r['texte'] ?? '')));
            if (traduction_poser($p[0], $p[1], $p[2], $langue, $texte)) {
                journaliser('modification', 'traduction', null, TRADUISIBLES[$p[0]][0] . ' · ' . TRADUISIBLES[$p[0]][3][$p[2]] . ' · ' . strtoupper($langue), ['texte' => $avant], ['texte' => $texte]);
                $n++;
            }
        }
        $valides = is_array($_POST['tr_ok'] ?? null) ? count($_POST['tr_ok']) : 0;
        message('ok', ($n || $valides) ? trim(($n ? pluriel($n, 'traduction enregistrée', 'traductions enregistrées') . '. ' : '') . ($valides ? pluriel($valides, 'traduction validée', 'traductions validées') . '. ' : '')) . ' Le site suivra à la prochaine publication.' : 'Rien n\'a changé.');
    }
    rediriger(url('langues', $retour));
}

// ---------- Lecture ----------
$toutes = langues_toutes();
$autres = array_values(array_diff(array_keys($toutes), [$reference]));
$onglets = ['langues' => 'Langues', 'traductions' => 'Traductions', 'libelles' => 'Libellés de l\'interface'];
?>
<div class="titre"><h1>Langues et traductions</h1></div>
<nav class="onglets" aria-label="Parties de l'écran">
  <?php foreach ($onglets as $k => $l): ?><a href="<?= e(url('langues', ['onglet' => $k])) ?>"<?= $k === $onglet ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
</nav>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<?php if ($onglet === 'langues'): ?>
  <p class="intro">Les contenus sont rédigés dans la langue de référence (<?= e(mb_strtolower(langue_nom($reference))) ?>). Chaque autre langue se traduit champ par champ ; là où une traduction manque, le site affiche le texte de la langue de référence. Le site s'ouvre dans la langue de la première visite, puis retient le choix du visiteur ; la langue du navigateur n'est pas consultée.</p>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
    <h2>Langues du site</h2>
    <div class="tableau-defile"><table class="tableau">
      <thead><tr><th>Langue</th><th>Code</th><th>Dates</th><th>Sur le site</th><th>Première visite</th><th>Traductions</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($toutes as $code => $l): $n = $code === $reference ? null : traductions_decompte(traductions_inventaire($code)); ?>
        <tr>
          <td><strong><?= e($l['nom_fr']) ?></strong> <span class="discret"><?= e($l['nom']) ?></span><?= $l['reference'] ? ' <span class="puce puce--accent">langue de référence</span>' : '' ?></td>
          <td><code><?= e($code) ?></code></td>
          <td><code><?= e($l['locale']) ?></code></td>
          <td><label><input type="checkbox" name="active[<?= e($code) ?>]" value="1"<?= ($l['active'] || $l['reference']) ? ' checked' : '' ?><?= ($l['reference'] || !peut('reglages')) ? ' disabled' : '' ?>> <span class="sr">Proposer <?= e($l['nom_fr']) ?> sur le site</span>active</label><?php if ($l['reference']): ?><input type="hidden" name="active[<?= e($code) ?>]" value="1"><?php endif; ?></td>
          <td><label><input type="radio" name="defaut" value="<?= e($code) ?>"<?= $l['defaut'] ? ' checked' : '' ?><?= peut('reglages') ? '' : ' disabled' ?>> <span class="sr"><?= e($l['nom_fr']) ?> : </span>par défaut</label></td>
          <td>
            <?php if ($n === null): ?><span class="discret">textes d'origine</span>
            <?php else: ?>
              <a href="<?= e(url('langues', ['onglet' => 'traductions', 'langue' => $code])) ?>"><?= (int) $n['validee'] ?> validées</a>
              <?php if ($n['proposee']): ?> · <a href="<?= e(url('langues', ['onglet' => 'traductions', 'langue' => $code, 'statut' => 'proposee'])) ?>"><span class="puce puce--alerte"><?= (int) $n['proposee'] ?> à valider</span></a><?php endif; ?>
              <?php if ($n['a_revoir']): ?> · <a href="<?= e(url('langues', ['onglet' => 'traductions', 'langue' => $code, 'statut' => 'a_revoir'])) ?>"><span class="puce puce--alerte"><?= (int) $n['a_revoir'] ?> à revoir</span></a><?php endif; ?>
              <?php if ($n['manquante']): ?> · <a href="<?= e(url('langues', ['onglet' => 'traductions', 'langue' => $code, 'statut' => 'manquante'])) ?>"><?= (int) $n['manquante'] ?> sans traduction</a><?php endif; ?>
            <?php endif; ?>
          </td>
          <td><?php if (peut('reglages') && !$l['reference'] && !$l['defaut']): ?><button type="submit" form="supprimer-<?= e($code) ?>" class="bouton bouton--petit bouton--danger" data-confirmer="Supprimer la langue « <?= e($l['nom_fr']) ?> » et toutes ses traductions ? Cette suppression est définitive.">Supprimer</button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="aide">Une langue inactive se prépare dans Pupitre sans paraître sur le site. « Sans traduction » ne signale pas forcément un manque : un nom propre ou un titre d'œuvre s'écrit souvent de la même façon dans toutes les langues.</p>
    <?php if (peut('reglages')): ?><div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer</button></div><?php endif; ?>
  </form>
  <?php foreach ($toutes as $code => $l): if (!$l['reference'] && !$l['defaut'] && peut('reglages')): ?>
    <form method="post" id="supprimer-<?= e($code) ?>" hidden><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="code" value="<?= e($code) ?>"></form>
  <?php endif; endforeach; ?>

  <?php if (peut('reglages')): ?>
  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="ajouter">
    <h2>Ajouter une langue</h2>
    <div class="colonnes">
      <?= champ('code', 'Code', recu('code'), ['requis' => true, 'max' => 3, 'indication' => 'de', 'aide' => 'Deux ou trois lettres, en minuscules (norme ISO 639) : de, es, it, ja…']) ?>
      <?= champ('nom', 'Nom, dans cette langue', recu('nom'), ['requis' => true, 'max' => 60, 'indication' => 'Deutsch']) ?>
      <?= champ('nom_fr', 'Nom, en français', recu('nom_fr'), ['max' => 60, 'indication' => 'Allemand']) ?>
      <?= champ('locale', 'Format des dates', recu('locale'), ['max' => 20, 'indication' => 'de-DE', 'aide' => 'Langue et pays, pour l\'écriture des dates sur le site. Vide : le code.']) ?>
    </div>
    <div class="actions"><button type="submit" class="bouton">Ajouter cette langue</button></div>
    <p class="aide">La marche complète (ajout, traduction, activation, publication) est décrite dans pupitre/LISEZ-MOI.md, « Ajouter une langue ».</p>
  </form>
  <?php endif; ?>

<?php elseif ($onglet === 'libelles'): ?>
  <?php
    $libelles = libelles_tous();
    $groupes = [];
    foreach ($libelles as $cle => $l) $groupes[$l['groupe']][$cle] = $l;
    $noms = ['meta' => 'Titre du site', 'nav' => 'Menu', 'ouverture' => 'Hero', 'lecteur' => 'Lecteur de musique', 'agenda' => 'Agenda', 'concerts' => 'Page des concerts',
        'disques' => 'Discographie', 'parcours' => 'Parcours', 'commun' => 'Communs', 'presse' => 'Presse', 'tel' => 'Téléchargements', 'suivre' => 'Restons en contact',
        'lettre' => 'Lettre', 'contact' => 'Contact', 'pied' => 'Pied de page', 'page' => 'Pages secondaires', 'legal' => 'Pages légales', 'yt' => 'Vidéos YouTube', 'video' => 'Lecteur vidéo'];
  ?>
  <p class="intro">Tous les intitulés et boutons de l'interface du site. Les mêmes libellés se règlent aussi bloc par bloc (« Blocs du site »). <code>{n}</code> et <code>{date}</code> sont remplacés par le site : gardez-les tels quels. *…* rend l'italique.</p>
  <form method="post" class="carte">
    <?= champ_csrf() ?>
    <?php foreach ($groupes as $g => $liste): ?>
      <h2><?= e($noms[$g] ?? ucfirst($g)) ?></h2>
      <?php foreach ($liste as $cle => $l): ?>
        <?= champ_bi('texte', $cle, $l['texte_' . $reference], '', ['prefixe' => 'lib[' . $cle . ']', 'objet' => ['libelles', $cle, 'texte'], 'texte' => mb_strlen((string) $l['texte_' . $reference]) > 90]) ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer les libellés</button></div>
  </form>

<?php else: ?>
  <?php
    $langue = in_array(recu('langue'), $autres, true) ? recu('langue') : ($autres[0] ?? '');
    $statut = isset(STATUTS_TRADUCTION[recu('statut')]) ? recu('statut') : '';
    $objet = isset(TRADUISIBLES[recu('objet')]) ? recu('objet') : '';
    $inventaire = $langue !== '' ? traductions_inventaire($langue, $objet ?: null) : [];
    $decompte = traductions_decompte($inventaire);
    $liste = $statut !== '' ? array_values(array_filter($inventaire, fn($x) => $x['statut'] === $statut)) : $inventaire;
    $page_n = max(1, (int) recu('p'));
    $pages = max(1, (int) ceil(count($liste) / $PAR_PAGE));
    $page_n = min($page_n, $pages);
    $visibles = array_slice($liste, ($page_n - 1) * $PAR_PAGE, $PAR_PAGE);
    $filtre = fn(array $plus) => url('langues', array_filter(array_merge(['onglet' => 'traductions', 'langue' => $langue, 'statut' => $statut, 'objet' => $objet], $plus), 'strlen'));
    $puces = ['validee' => 'puce--ok', 'proposee' => 'puce--alerte', 'a_revoir' => 'puce--alerte', 'manquante' => ''];
  ?>
  <?php if ($langue === ''): ?>
    <p class="rien">Aucune autre langue que la langue de référence. Ajoutez-en une dans l'onglet « Langues ».</p>
  <?php else: ?>
  <p class="intro">Chaque texte traduisible, avec son texte de référence. Une traduction saisie ici est validée ; une proposition de l'IA attend que vous la validiez, et ne paraît pas sur le site d'ici là. Une traduction validée n'est jamais remplacée par l'IA.</p>
  <form method="get" class="filtres">
    <input type="hidden" name="page" value="langues"><input type="hidden" name="onglet" value="traductions">
      <?= champ_choix('langue', 'Langue', array_combine($autres, array_map('langue_nom', $autres)), $langue) ?>
      <?= champ_choix('statut', 'État', ['manquante' => 'Sans traduction (' . $decompte['manquante'] . ')', 'proposee' => 'Proposées par l\'IA, à valider (' . $decompte['proposee'] . ')', 'a_revoir' => 'À revoir (' . $decompte['a_revoir'] . ')', 'validee' => 'Validées (' . $decompte['validee'] . ')'], $statut, ['vide' => 'Tous (' . count($inventaire) . ')']) ?>
      <?= champ_choix('objet', 'Partie', array_map(fn($x) => $x[0], TRADUISIBLES), $objet, ['vide' => 'Tout le site']) ?>
    <button type="submit" class="bouton">Filtrer</button>
  </form>

  <?php if (is_file(RACINE . '/pages/langues/ia.php')) require RACINE . '/pages/langues/ia.php'; ?>

  <?php if (!$visibles): ?>
    <p class="rien">Rien à montrer avec ce filtre.</p>
  <?php else: ?>
  <form method="post" class="carte">
    <?= champ_csrf() ?>
    <input type="hidden" name="langue" value="<?= e($langue) ?>"><input type="hidden" name="statut" value="<?= e($statut) ?>"><input type="hidden" name="objet" value="<?= e($objet) ?>"><input type="hidden" name="p" value="<?= (int) $page_n ?>">
    <h2><?= e(langue_nom($langue)) ?> · <?= e(pluriel(count($liste), 'texte', 'textes')) ?><?= $pages > 1 ? ' · page ' . $page_n . ' sur ' . $pages : '' ?></h2>
    <?php foreach ($visibles as $i => $x): $jeton = $x['objet'] . '|' . $x['id'] . '|' . $x['champ']; $long = mb_strlen($x['reference']) > 80 || str_contains($x['reference'], "\n"); ?>
      <div class="tr">
        <p class="tr__ou"><span class="discret"><?= e(TRADUISIBLES[$x['objet']][0]) ?></span> · <?= e(couper($x['nom'], 60)) ?> · <?= e($x['libelle']) ?>
          <span class="puce <?= $puces[$x['statut']] ?>"><?= e(STATUTS_TRADUCTION[$x['statut']]) ?></span></p>
        <div class="tr__paire">
          <div class="tr__ref" lang="<?= e($reference) ?>"><?= nl2br(e($x['reference'])) ?></div>
          <div>
            <label class="sr" for="tr-<?= $i ?>">Traduction en <?= e(mb_strtolower(langue_nom($langue))) ?></label>
            <input type="hidden" name="tr[<?= $i ?>][jeton]" value="<?= e($jeton) ?>">
            <?php if ($long): ?>
              <textarea id="tr-<?= $i ?>" name="tr[<?= $i ?>][texte]" rows="<?= max(2, min(14, substr_count($x['reference'], "\n") + 2 + (int) (mb_strlen($x['reference']) / 70))) ?>" lang="<?= e($langue) ?>"><?= e($x['texte']) ?></textarea>
            <?php else: ?>
              <input type="text" id="tr-<?= $i ?>" name="tr[<?= $i ?>][texte]" value="<?= e($x['texte']) ?>" lang="<?= e($langue) ?>" placeholder="comme en <?= e(mb_strtolower(langue_nom($reference))) ?>">
            <?php endif; ?>
            <?php if (in_array($x['statut'], ['proposee', 'a_revoir'], true)): ?>
              <label class="bi__valider"><input type="checkbox" name="tr_ok[]" value="<?= e($jeton . '|' . $langue) ?>"> <?= $x['statut'] === 'proposee' ? 'valider cette proposition' : 'toujours bonne' ?></label>
            <?php endif; ?>
            <?php if (ia_configuree() && in_array($x['statut'], ['manquante', 'proposee'], true)): ?>
              <p class="tr__proposer"><button type="submit" name="proposer_un" value="<?= e($jeton) ?>" class="bouton bouton--petit" formnovalidate><?= $x['statut'] === 'proposee' ? 'Demander une autre proposition' : 'Proposer une traduction (IA)' ?></button>
                <?php if (function_exists('traduction_reservee') && traduction_reservee($x['objet'], $x['id'], $x['champ'])): ?><span class="discret">parole de l'artiste : jamais traduite d'office</span><?php endif; ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="actions actions--bas">
      <button type="submit" class="bouton bouton--plein">Enregistrer cette page</button>
      <?php if ($statut === 'proposee' || $statut === 'a_revoir'): ?><button type="button" class="bouton" data-cocher="tr_ok[]">Tout cocher</button><?php endif; ?>
    </div>
  </form>
  <?php if ($pages > 1): ?>
    <p class="actions">
      <?php if ($page_n > 1): ?><a class="bouton bouton--petit" href="<?= e($filtre(['p' => (string) ($page_n - 1)])) ?>">← Page précédente</a><?php endif; ?>
      <?php if ($page_n < $pages): ?><a class="bouton bouton--petit" href="<?= e($filtre(['p' => (string) ($page_n + 1)])) ?>">Page suivante →</a><?php endif; ?>
    </p>
  <?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

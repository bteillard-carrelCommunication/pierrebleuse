<?php
/**
 * Traductions : tous les textes du site, et leur traduction dans chaque langue.
 *
 * Les textes se rédigent en anglais, sur les écrans de saisie. Ils se traduisent ici, et ici
 * seulement : une langue à la fois, section par section. D'abord les textes des pages (bloc par
 * bloc), puis une section par collection de données (concerts, presse, discographie…). Chaque texte
 * a sa ligne, à laquelle mène le lien posé sous son champ de saisie (?cible=table|clé|champ).
 *
 *   une traduction saisie ici est validée : elle sortira sur le site ;
 *   une proposition de l'IA attend d'être relue, corrigée au besoin, puis validée (case à cocher) ;
 *   une traduction dont le texte anglais a changé depuis est « à revoir » ;
 *   une ligne laissée vide : le site montre le texte anglais.
 *
 * Le modèle est décrit dans inclus/langues.php.
 */

defined('PUPITRE') or exit;

$titre = 'Traductions';
$PAR_PAGE = 60;
$reference = langue_reference();
$cibles = langues_a_traduire();
$toutes = langues_toutes();

// ---------- où l'on est : langue, section, filtre, ligne visée ----------
$langue = in_array(recu('langue'), $cibles, true) ? recu('langue') : ($cibles[0] ?? '');
$section = isset(SECTIONS_TRADUCTION[recu('section')]) ? recu('section') : '';
$statut = isset(STATUTS_TRADUCTION[recu('statut')]) || recu('statut') === 'a_faire' ? recu('statut') : '';
$q = mb_substr(recu('q'), 0, 120);
$cible = null;
if (preg_match('/^([a-z_]+)\|([^|]{1,80})\|([a-z_]+)$/', recu('cible'), $m) && isset(TRADUISIBLES[$m[1]][3][$m[3]])) {
    $cible = [$m[1], $m[2], $m[3]];
    $section = traduction_section($m[1]) ?: $section;
    $statut = '';
    $q = '';
}
$section_voulue = $section;
if ($section === '') $section = array_key_first(SECTIONS_TRADUCTION);
$ici = fn(array $plus = []) => url('traductions', array_filter(array_merge(['langue' => $langue, 'section' => $section, 'statut' => $statut, 'q' => $q], $plus), 'strlen'));

// ---------- actions ----------
if (est_post() && $langue !== '') {
    $retour = array_filter(['p' => recu('p')], 'strlen');
    // propositions de l'IA : toutes les traductions manquantes de la section (tâche de fond), ou un seul texte (tout de suite)
    if (recu('action') === 'proposer') {
        if (!ia_configuree()) message('erreur', 'L\'IA n\'est pas configurée (Réglages) : les traductions se saisissent à la main.');
        else {
            $tache = tache_creer('traduction', ['langue' => $langue, 'objets' => recu('portee') === 'tout' ? [] : SECTIONS_TRADUCTION[$section][1]]);
            if (!taches_lancer_en_fond()) message('alerte', 'Les propositions attendent le prochain passage des tâches de fond (cron).');
            $retour['tache'] = (string) $tache;
        }
        rediriger($ici($retour));
    }
    // d'abord ce qui a été saisi sur la page, puis la demande d'une proposition : une saisie en cours n'est jamais perdue
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
    $ancre = '';
    if (recu('proposer_un') !== '') {
        $p = explode('|', recu('proposer_un'));
        if (count($p) === 3 && isset(TRADUISIBLES[$p[0]][3][$p[2]])) {
            $ancre = '#' . traduction_ancre($p[0], $p[1], $p[2]);
            $source = trim(reference_lire($p[0], $p[1], $p[2]));
            $deja = traduction($p[0], $p[1], $p[2], $langue);
            if (!ia_configuree()) message('erreur', 'L\'IA n\'est pas configurée (Réglages).');
            elseif ($source === '') message('erreur', 'Ce texte est vide en anglais.');
            elseif ($deja && $deja['statut'] === 'validee' && trim((string) $deja['texte']) !== '') message('alerte', 'Cette traduction est validée : l\'IA ne la remplace pas. Videz-la et enregistrez si vous voulez une nouvelle proposition.');
            else {
                try {
                    $t = ia_traduire([['un', $source, TRADUISIBLES[$p[0]][0] . ' · ' . TRADUISIBLES[$p[0]][3][$p[2]]]], $langue)['un'] ?? '';
                    if ($t !== '' && $t === $source) message('ok', 'Ce texte s\'écrit de la même façon en ' . mb_strtolower(langue_nom($langue)) . ' : rien à traduire, le site garde le texte anglais.');
                    elseif ($t !== '' && traduction_poser($p[0], $p[1], $p[2], $langue, $t, 'ia')) {
                        journaliser('creation', 'traduction', null, 'Proposition de l\'IA · ' . TRADUISIBLES[$p[0]][0] . ' · ' . TRADUISIBLES[$p[0]][3][$p[2]] . ' · ' . strtoupper($langue), null, ['texte' => $t]);
                        message('ok', 'Traduction proposée : relisez-la, corrigez-la au besoin, puis validez-la.');
                    } else message('alerte', 'L\'IA n\'a rien proposé de neuf pour ce texte.');
                } catch (Throwable $e) {
                    message('erreur', $e instanceof ErreurLisible ? $e->getMessage() : 'La proposition n\'a pas abouti.');
                }
            }
        }
    } else {
        message('ok', ($n || $valides) ? trim(($n ? pluriel($n, 'traduction enregistrée', 'traductions enregistrées') . '. ' : '') . ($valides ? pluriel($valides, 'traduction validée', 'traductions validées') . '. ' : '')) . ' Le site suivra à la prochaine publication.' : 'Rien n\'a changé.');
    }
    rediriger($ici($retour) . $ancre);
}

// ---------- lecture ----------
$puces = ['validee' => 'puce--ok', 'proposee' => 'puce--alerte', 'a_revoir' => 'puce--alerte', 'manquante' => ''];
$inventaire = $langue !== '' ? traductions_inventaire($langue, null, true) : [];
$inventaire = array_values(array_filter($inventaire, fn($x) => $x['section'] !== ''));
// décompte par section : ce qui reste à faire (à traduire, à valider, à revoir)
$par_section = [];
foreach (array_keys(SECTIONS_TRADUCTION) as $s) $par_section[$s] = ['validee' => 0, 'proposee' => 0, 'a_revoir' => 0, 'manquante' => 0];
foreach ($inventaire as $x) $par_section[$x['section']][$x['statut']]++;
$total = traductions_decompte($inventaire);
// venu d'une alerte (« n traductions à valider ») sans section : on ouvre la première section qui en porte
if ($section_voulue === '' && $statut !== '' && !est_post()) {
    foreach ($par_section as $s => $n) {
        if (($statut === 'a_faire' ? $n['manquante'] + $n['proposee'] + $n['a_revoir'] : ($n[$statut] ?? 0)) > 0) { $section = $s; break; }
    }
}
$lignes = array_values(array_filter($inventaire, fn($x) => $x['section'] === $section));
$decompte = traductions_decompte($lignes);
if ($statut === 'a_faire') $lignes = array_values(array_filter($lignes, fn($x) => $x['statut'] !== 'validee'));
elseif ($statut !== '') $lignes = array_values(array_filter($lignes, fn($x) => $x['statut'] === $statut));
if ($q !== '') {
    $n = normaliser($q);
    $lignes = array_values(array_filter($lignes, fn($x) => $n === '' || str_contains(normaliser($x['groupe'] . ' ' . $x['reference'] . ' ' . $x['texte'] . ' ' . $x['precision']), $n)));
}
$pages = max(1, (int) ceil(count($lignes) / $PAR_PAGE));
$page_n = max(1, min($pages, (int) recu('p') ?: 1));
if ($cible) {
    // la page où se trouve la ligne visée
    foreach ($lignes as $i => $x) if ($x['objet'] === $cible[0] && $x['id'] === (string) $cible[1] && $x['champ'] === $cible[2]) { $page_n = intdiv($i, $PAR_PAGE) + 1; break; }
}
$visibles = array_slice($lignes, ($page_n - 1) * $PAR_PAGE, $PAR_PAGE);
$tache = recu_entier('tache') ? tache_lire((int) recu_entier('tache')) : null;
if ($tache && $tache['type'] !== 'traduction') $tache = null;
$a_proposer = 0;
foreach ($inventaire as $x) if ($x['section'] === $section && $x['statut'] === 'manquante' && !traduction_reservee($x['objet'], $x['id'], $x['champ'])) $a_proposer++;
$reste = fn(array $n) => $n['manquante'] + $n['proposee'] + $n['a_revoir'];
?>
<div class="titre">
  <h1>Traductions</h1>
  <?php if ($langue !== ''): ?><span class="titre__suite"><?= e(pluriel(count($inventaire), 'texte', 'textes')) ?> · <?= (int) $total['validee'] ?> traduits en <?= e(mb_strtolower(langue_nom($langue))) ?></span><?php endif; ?>
  <div class="titre__actions"><a class="bouton" href="<?= e(url('langues')) ?>"><?= icone('langues') ?>Langues du site</a></div>
</div>
<p class="intro">Les textes se rédigent en <?= e(mb_strtolower(langue_nom($reference))) ?>, sur leurs écrans de saisie. Ils se traduisent ici : chaque texte a sa ligne. Une traduction saisie est validée ; une proposition de l'IA attend votre validation et ne paraît pas sur le site d'ici là ; une ligne laissée vide laisse le site afficher le texte <?= e(mb_strtolower(langue_nom($reference))) ?>.</p>

<?php if ($langue === ''): ?>
  <div class="carte"><?= etat_vide('langues', 'Aucune langue à traduire', 'Le site n\'a pour l\'instant que sa langue de rédaction. Ajoutez une langue pour commencer à traduire.', '<a class="bouton bouton--plein" href="' . e(url('langues')) . '">Ajouter une langue</a>') ?></div>
<?php else: ?>

<?php if (count($cibles) > 1): ?>
<nav class="onglets" aria-label="Langue de traduction">
  <?php foreach ($cibles as $code): ?>
    <a href="<?= e(url('traductions', ['langue' => $code, 'section' => $section])) ?>"<?= $code === $langue ? ' aria-current="page"' : '' ?>><?= e(langue_nom($code)) ?><?= $toutes[$code]['active'] ? '' : ' <span class="discret">en préparation</span>' ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>

<div class="tr-cadre">
  <nav class="tr-sections" aria-label="Sections">
    <ul>
      <?php foreach (SECTIONS_TRADUCTION as $cle => [$nom]): $n = $par_section[$cle]; if (!array_sum($n) && $cle !== $section) continue; ?>
        <li><a href="<?= e(url('traductions', ['langue' => $langue, 'section' => $cle])) ?>"<?= $cle === $section ? ' aria-current="page"' : '' ?>><span><?= e($nom) ?></span>
          <?php if ($reste($n)): ?><span class="tr-sections__reste" title="<?= e(pluriel($reste($n), 'texte à traduire, à valider ou à revoir', 'textes à traduire, à valider ou à revoir')) ?>"><?= (int) $reste($n) ?><span class="sr"> à traiter</span></span>
          <?php else: ?><span class="tr-sections__fait"><?= icone('ok') ?><span class="sr">tout est traduit</span></span><?php endif; ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <div class="tr-corps">
    <form method="get" class="filtres">
      <input type="hidden" name="page" value="traductions"><input type="hidden" name="langue" value="<?= e($langue) ?>"><input type="hidden" name="section" value="<?= e($section) ?>">
      <?= champ_choix('statut', 'État', ['a_faire' => 'À traiter (' . $reste($decompte) . ')', 'manquante' => 'À traduire (' . $decompte['manquante'] . ')', 'proposee' => 'Proposées par l\'IA, à valider (' . $decompte['proposee'] . ')', 'a_revoir' => 'À revoir (' . $decompte['a_revoir'] . ')', 'validee' => 'Validées (' . $decompte['validee'] . ')'], $statut, ['vide' => 'Tous les textes (' . array_sum($decompte) . ')']) ?>
      <?= champ('q', 'Chercher', $q, ['type' => 'search', 'indication' => 'un mot du texte, un titre…']) ?>
      <button type="submit" class="bouton">Filtrer</button>
    </form>

    <div class="carte">
      <div class="carte__tete"><h2>Assistance à la traduction</h2></div>
      <?php if ($tache && in_array($tache['statut'], ['en_attente', 'en_cours'], true)): ?>
        <?= suivi_tache($tache) ?>
        <p class="aide">Les propositions arrivent par lots. La page se rechargera d'elle-même à la fin.</p>
      <?php else: ?>
        <?php if ($tache): ?><p class="message message--<?= $tache['statut'] === 'echec' ? 'erreur' : 'ok' ?>"><?= e($tache['message']) ?><?php if ($tache['statut'] !== 'echec' && $decompte['proposee']): ?> <a href="<?= e($ici(['statut' => 'proposee', 'p' => ''])) ?>">Voir les propositions à valider</a><?php endif; ?></p><?php endif; ?>
        <?php if (!ia_configuree()): ?>
          <p class="rien">L'IA n'est pas configurée (écran Réglages) : les traductions se saisissent à la main.</p>
        <?php else: ?>
        <form method="post" class="ia-carte" action="<?= e($ici()) ?>">
          <?= champ_csrf() ?><input type="hidden" name="action" value="proposer">
          <p><?= $a_proposer ? e(pluriel($a_proposer, 'texte de cette section n\'a pas de traduction', 'textes de cette section n\'ont pas de traduction')) . ' : l\'IA peut en proposer une.' : 'Aucun texte sans traduction dans cette section.' ?>
            <span class="discret">Un nom propre ou un titre d'œuvre s'écrit souvent de la même façon dans les deux langues : l'IA le laisse alors sans traduction, et le site garde le texte anglais.</span></p>
          <?php if ($a_proposer): ?><button type="submit" class="bouton" data-confirmer="Demander à l'IA une proposition pour <?= (int) min($a_proposer, 400) ?> texte(s) en <?= e(mb_strtolower(langue_nom($langue))) ?> ? Rien ne paraîtra sur le site sans votre validation.">Proposer les traductions manquantes</button><?php endif; ?>
          <?php if ($reste($total) > $reste($decompte) && $total['manquante']): ?><button type="submit" name="portee" value="tout" class="bouton bouton--petit" data-confirmer="Demander à l'IA une proposition pour tous les textes sans traduction, dans toutes les sections ?">Toutes les sections</button><?php endif; ?>
        </form>
        <p class="aide">Une proposition prend l'état « proposée par l'IA, à valider » : relisez-la, corrigez-la au besoin, cochez « valider ». Une traduction validée n'est jamais remplacée. Les paroles de l'artiste et les citations de presse ne sont pas traduites par lots : demandez-les une à une, ou traduisez-les à la main.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if (!$visibles): ?>
      <div class="carte"><?= etat_vide('filtre', 'Rien à montrer', $statut !== '' || $q !== '' ? 'Aucun texte de cette section ne correspond à ce filtre.' : 'Cette section n\'a pas encore de texte.', ($statut !== '' || $q !== '') ? '<a class="bouton bouton--petit" href="' . e(url('traductions', ['langue' => $langue, 'section' => $section])) . '">Tout montrer</a>' : '') ?></div>
    <?php else: ?>
    <form method="post" action="<?= e($ici()) ?>" class="tr-forme">
      <?= champ_csrf() ?><input type="hidden" name="p" value="<?= (int) $page_n ?>">
      <?php $groupe = null; foreach ($visibles as $i => $x):
          $jeton = $x['objet'] . '|' . $x['id'] . '|' . $x['champ'];
          $ancre = traduction_ancre($x['objet'], $x['id'], $x['champ']);
          $long = mb_strlen($x['reference']) > 70 || str_contains($x['reference'], "\n");
          $visee = $cible && $x['objet'] === $cible[0] && $x['id'] === (string) $cible[1] && $x['champ'] === $cible[2];
          $reservee = traduction_reservee($x['objet'], $x['id'], $x['champ']);
          if ($x['groupe'] !== $groupe):
              if ($groupe !== null) echo '</div>';
              $groupe = $x['groupe']; ?>
        <div class="carte tr-groupe">
          <div class="carte__tete"><h2><?= e($groupe) ?></h2><?php if ($x['lien'] !== ''): ?><a class="carte__suite" href="<?= e($x['lien']) ?>"><?= icone('crayon') ?>Modifier le texte anglais</a><?php endif; ?></div>
      <?php endif; ?>
          <div class="tr<?= $visee ? ' tr--cible' : '' ?>" id="<?= e($ancre) ?>">
            <p class="tr__ou"><span class="tr__champ"><?= e($x['libelle']) ?></span><?php if ($x['precision'] !== ''): ?> <span class="discret"><?= e($x['precision']) ?></span><?php endif; ?>
              <span class="puce <?= $puces[$x['statut']] ?>"><?= e(ETATS_TRADUCTION[$x['statut']]) ?></span></p>
            <div class="tr__paire">
              <div class="tr__ref" lang="<?= e($x['objet'] === 'articles_citations' ? 'und' : $reference) ?>"><?= nl2br(e($x['reference'])) ?></div>
              <div>
                <label class="sr" for="tr-<?= $i ?>">Traduction en <?= e(mb_strtolower(langue_nom($langue))) ?> : <?= e($x['libelle']) ?>, <?= e(couper($x['groupe'], 60)) ?></label>
                <input type="hidden" name="tr[<?= $i ?>][jeton]" value="<?= e($jeton) ?>">
                <?php if ($long): ?>
                  <textarea id="tr-<?= $i ?>" name="tr[<?= $i ?>][texte]" rows="<?= max(2, min(14, substr_count($x['reference'], "\n") + 2 + (int) (mb_strlen($x['reference']) / 70))) ?>" lang="<?= e($langue) ?>"><?= e($x['texte']) ?></textarea>
                <?php else: ?>
                  <input type="text" id="tr-<?= $i ?>" name="tr[<?= $i ?>][texte]" value="<?= e($x['texte']) ?>" lang="<?= e($langue) ?>" placeholder="comme en <?= e(mb_strtolower(langue_nom($reference))) ?>">
                <?php endif; ?>
                <?php if (in_array($x['statut'], ['proposee', 'a_revoir'], true) || (ia_configuree() && in_array($x['statut'], ['manquante', 'proposee'], true))): ?>
                <p class="tr__suite">
                  <?php if (in_array($x['statut'], ['proposee', 'a_revoir'], true)): ?>
                    <label class="bi__valider"><input type="checkbox" name="tr_ok[]" value="<?= e($jeton . '|' . $langue) ?>"> <?= $x['statut'] === 'proposee' ? 'valider cette proposition' : 'toujours bonne : le texte anglais a changé, pas son sens' ?></label>
                  <?php endif; ?>
                  <?php if (ia_configuree() && in_array($x['statut'], ['manquante', 'proposee'], true)): ?>
                    <button type="submit" name="proposer_un" value="<?= e($jeton) ?>" class="bouton bouton--petit" formnovalidate><?= $x['statut'] === 'proposee' ? 'Demander une autre proposition' : 'Proposer (IA)' ?></button>
                    <?php if ($reservee): ?><span class="discret">parole de l'artiste ou citation : jamais traduite par lots</span><?php endif; ?>
                  <?php endif; ?>
                </p>
                <?php endif; ?>
              </div>
            </div>
          </div>
      <?php endforeach; if ($groupe !== null) echo '</div>'; ?>
      <div class="actions actions--bas actions--page">
        <button type="submit" class="bouton bouton--plein">Enregistrer cette page</button>
        <?php if (array_filter($visibles, fn($x) => in_array($x['statut'], ['proposee', 'a_revoir'], true))): ?><button type="button" class="bouton" data-cocher="tr_ok[]">Tout cocher</button><?php endif; ?>
        <span class="aide"><?= e(pluriel(count($lignes), 'texte', 'textes')) ?><?= $pages > 1 ? ' · page ' . $page_n . ' sur ' . $pages : '' ?></span>
      </div>
    </form>
    <?php if ($pages > 1): ?>
      <p class="actions">
        <?php if ($page_n > 1): ?><a class="bouton bouton--petit" href="<?= e($ici(['p' => (string) ($page_n - 1)])) ?>"><?= icone('chevron-gauche') ?>Page précédente</a><?php endif; ?>
        <?php if ($page_n < $pages): ?><a class="bouton bouton--petit" href="<?= e($ici(['p' => (string) ($page_n + 1)])) ?>">Page suivante<?= icone('chevron-droite') ?></a><?php endif; ?>
      </p>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

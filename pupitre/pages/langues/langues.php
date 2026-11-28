<?php
/**
 * Langues du site.
 *
 *   Langues             les langues du site, leur ordre, l'ajout et le retrait d'une langue (pilote) ;
 *   Tous les intitulés  les intitulés et boutons de l'interface du site, en anglais : ceux d'un bloc
 *                       se règlent aussi sur l'écran de ce bloc, les autres (pages Concerts et
 *                       Presse, pages légales…) ici seulement.
 *
 * L'anglais est la langue de rédaction : toujours la première langue du site, et celle de la
 * première visite. Les autres langues se traduisent sur l'écran « Traductions ». Le sélecteur de
 * langue du site suit cette liste, dans cet ordre, à la publication suivante.
 *
 * Le modèle est décrit dans inclus/langues.php.
 */

defined('PUPITRE') or exit;

$titre = 'Langues du site';
$onglet = recu('onglet') === 'libelles' ? 'libelles' : 'langues';
$reference = langue_reference();
$erreurs = [];
$propositions = json_decode(reglage('migration_011_propositions'), true) ?: [];

// ---------- Actions ----------
if (est_post() && $onglet === 'langues') {
    $action = recu('action');
    $toutes = langues_toutes();
    if (in_array($action, ['adopter', 'ecarter'], true)) {
        // une proposition d'anglais de l'IA, restée en attente au passage à l'anglais de rédaction
        $i = recu_entier('proposition');
        $p = $i !== null ? ($propositions[$i] ?? null) : null;
        if ($p && isset(TRADUISIBLES[$p['objet']][3][$p['champ']])) {
            if ($action === 'adopter') {
                $avant = reference_lire($p['objet'], (string) $p['id'], $p['champ']);
                bd_modifier($p['objet'], [colonne_reference($p['objet'], $p['champ']) => $p['texte']], [cle_primaire($p['objet']) => $p['id']]);
                // les traductions déjà là disent la même chose que le nouveau texte anglais : elles ne sont pas « à revoir »
                bd_requete('UPDATE traductions SET empreinte = ? WHERE objet = ? AND objet_id = ? AND champ = ?', [md5($p['texte']), $p['objet'], (string) $p['id'], $p['champ']]);
                $GLOBALS['traductions_perimees'] = true;
                journaliser('validation', 'traduction', null, 'Texte anglais adopté · ' . TRADUISIBLES[$p['objet']][0] . ' · ' . TRADUISIBLES[$p['objet']][3][$p['champ']], ['texte' => $avant], ['texte' => $p['texte']]);
                message('ok', 'Texte anglais adopté. Le site suivra à la prochaine publication.');
            } else {
                message('ok', 'Proposition écartée : le texte actuel est gardé.');
            }
            unset($propositions[$i]);
            reglage_ecrire('migration_011_propositions', json_encode(array_values($propositions), JSON_UNESCAPED_UNICODE));
        }
        rediriger(url('langues') . '#propositions');
    }
    exiger_droit('reglages');
    if ($action === 'ajouter') {
        $code = mb_strtolower(recu('code'));
        if (!preg_match('/^[a-z]{2,3}$/', $code)) $erreurs[] = 'Le code d\'une langue tient en deux ou trois lettres : de, es, it…';
        elseif (isset($toutes[$code])) $erreurs[] = 'Cette langue existe déjà.';
        if (recu('nom') === '') $erreurs[] = 'Donnez le nom de la langue tel qu\'il s\'écrit dans cette langue (Deutsch, Español…).';
        if (!$erreurs) {
            bd_inserer('langues', ['code' => $code, 'nom' => mb_substr(recu('nom'), 0, 60), 'nom_fr' => mb_substr(recu('nom_fr') ?: recu('nom'), 0, 60),
                'locale' => preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', recu('locale')) ? recu('locale') : $code,
                'active' => 0, 'reference' => 0, 'defaut' => 0, 'ordre' => 1 + (int) bd_valeur('SELECT COALESCE(MAX(ordre), 0) FROM langues')]);
            journaliser('creation', 'langue', null, 'Langue « ' . $code . ' »', null, ['code' => $code, 'nom' => recu('nom')]);
            $GLOBALS['langues_perimees'] = true;
            message('ok', 'Langue ajoutée, encore en préparation : traduisez les textes ci-dessous, puis cochez « sur le site » (écran « Langues ») pour qu\'elle paraisse dans le sélecteur de langue.');
            rediriger(url('traductions', ['langue' => $code]));
        }
    } elseif ($action === 'enregistrer') {
        $actives = is_array($_POST['active'] ?? null) ? array_keys($_POST['active']) : [];
        $avant = $apres = [];
        foreach ($toutes as $code => $l) {
            $active = ($code === $reference || in_array($code, $actives, true)) ? 1 : 0;
            $avant[$code] = ['active' => (int) $l['active']];
            $apres[$code] = ['active' => $active];
            if ((int) $l['active'] !== $active) bd_modifier('langues', ['active' => $active], ['code' => $code]);
        }
        journaliser('modification', 'langues', null, 'Langues du site', $avant, $apres);
        $GLOBALS['langues_perimees'] = true;
        message('ok', 'Langues enregistrées. Le sélecteur de langue du site suivra à la prochaine publication.');
        rediriger(url('langues'));
    } elseif (in_array($action, ['monter', 'descendre'], true)) {
        // l'ordre des langues de traduction, dans le sélecteur du site : l'anglais reste en tête
        $ordre = array_values(array_diff(array_keys($toutes), [$reference]));
        $depart = $ordre;
        $i = array_search(recu('code'), $ordre, true);
        $j = $i === false ? false : $i + ($action === 'monter' ? -1 : 1);
        if ($i !== false && $j >= 0 && $j < count($ordre)) {
            [$ordre[$i], $ordre[$j]] = [$ordre[$j], $ordre[$i]];
            foreach ($ordre as $rang => $code) bd_modifier('langues', ['ordre' => $rang + 1], ['code' => $code]);
            journaliser('modification', 'langues', null, 'Ordre des langues du site', ['ordre' => $depart], ['ordre' => $ordre]);
            $GLOBALS['langues_perimees'] = true;
        }
        rediriger(url('langues'));
    } elseif ($action === 'supprimer') {
        $code = recu('code');
        $l = $toutes[$code] ?? null;
        if ($l && !$l['reference']) {
            $n = (int) bd_valeur('SELECT COUNT(*) FROM traductions WHERE langue = ?', [$code]);
            bd_requete('DELETE FROM traductions WHERE langue = ?', [$code]);
            bd_supprimer('langues', ['code' => $code]);
            journaliser('suppression', 'langue', null, 'Langue « ' . $code . ' »', ['code' => $code, 'traductions' => $n], null);
            $GLOBALS['langues_perimees'] = $GLOBALS['traductions_perimees'] = true;
            message('ok', 'Langue supprimée, avec ses ' . pluriel($n, 'traduction', 'traductions') . '. Elle quittera le site à la prochaine publication.');
        } else {
            message('erreur', 'La langue de rédaction ne se supprime pas.');
        }
        rediriger(url('langues'));
    }
}

if (est_post() && $onglet === 'libelles') {
    $recus = is_array($_POST['lib'] ?? null) ? $_POST['lib'] : [];
    $n = libelles_enregistrer($recus, array_keys($recus));
    message('ok', $n ? pluriel($n, 'intitulé enregistré', 'intitulés enregistrés') . '. Le site suivra à la prochaine publication.' : 'Rien n\'a changé.');
    rediriger(url('langues', ['onglet' => 'libelles']));
}

// ---------- Lecture ----------
$toutes = langues_toutes();
$autres = langues_a_traduire();
$onglets = ['langues' => 'Langues', 'libelles' => 'Tous les intitulés'];
?>
<div class="titre">
  <h1>Langues du site</h1>
  <div class="titre__actions"><a class="bouton bouton--plein" href="<?= e(url('traductions')) ?>"><?= icone('langues') ?>Traductions</a></div>
</div>
<nav class="onglets" aria-label="Parties de l'écran">
  <?php foreach ($onglets as $k => $l): ?><a href="<?= e(url('langues', $k === 'langues' ? [] : ['onglet' => $k])) ?>"<?= $k === $onglet ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
</nav>
<?php if ($erreurs): ?><ul class="erreurs" role="alert"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<?php if ($onglet === 'langues'): ?>
  <p class="intro">Les contenus sont rédigés en <?= e(mb_strtolower(langue_nom($reference))) ?>, sur les écrans de saisie. Chaque autre langue se traduit texte par texte, sur l'écran « Traductions » ; là où une traduction manque, le site affiche le texte <?= e(mb_strtolower(langue_nom($reference))) ?>. Le sélecteur de langue du site reprend cette liste, dans cet ordre : <?= e(mb_strtolower(langue_nom($reference))) ?> en premier, qui est aussi la langue de la première visite.</p>

  <form method="post" class="carte">
    <?= champ_csrf() ?><input type="hidden" name="action" value="enregistrer">
    <h2>Langues</h2>
    <div class="defile"><table class="tableau tableau--large">
      <thead><tr><th>Langue</th><th>Code</th><th>Dates</th><th>Sur le site</th><th>Traductions</th><th>Ordre</th><th><span class="sr">Retirer</span></th></tr></thead>
      <tbody>
      <?php $rang = 0; foreach ($toutes as $code => $l): $n = $code === $reference ? null : traductions_decompte(array_filter(traductions_inventaire($code, null, true), fn($x) => $x['section'] !== '')); ?>
        <tr>
          <th scope="row"><?= e($l['nom_fr']) ?> <span class="discret" lang="<?= e($code) ?>"><?= e($l['nom']) ?></span><?= $l['reference'] ? ' <span class="puce puce--accent">langue de rédaction</span>' : '' ?></th>
          <td><code><?= e($code) ?></code></td>
          <td><code><?= e($l['locale']) ?></code></td>
          <td><?php if ($l['reference']): ?><span class="puce puce--ok">toujours, en premier</span>
            <?php else: ?><label><input type="checkbox" name="active[<?= e($code) ?>]" value="1"<?= $l['active'] ? ' checked' : '' ?><?= peut('reglages') ? '' : ' disabled' ?>> sur le site<span class="sr"> : <?= e($l['nom_fr']) ?></span></label><?php endif; ?></td>
          <td>
            <?php if ($n === null): ?><span class="discret">textes d'origine</span>
            <?php else: ?>
              <a href="<?= e(url('traductions', ['langue' => $code])) ?>"><?= (int) $n['validee'] ?> validées</a>
              <?php if ($n['proposee']): ?> · <span class="puce puce--alerte"><?= (int) $n['proposee'] ?> à valider</span><?php endif; ?>
              <?php if ($n['a_revoir']): ?> · <span class="puce puce--alerte"><?= (int) $n['a_revoir'] ?> à revoir</span><?php endif; ?>
              <?php if ($n['manquante']): ?> · <span class="discret"><?= (int) $n['manquante'] ?> sans traduction</span><?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="tableau__actions"><?php if (!$l['reference'] && peut('reglages') && count($autres) > 1): ?>
            <button type="submit" form="ordre-<?= e($code) ?>" name="action" value="monter" class="outil" title="Monter" aria-label="Monter <?= e($l['nom_fr']) ?> dans le sélecteur de langue"<?= $rang === 0 ? ' disabled' : '' ?>><?= icone('monter') ?></button>
            <button type="submit" form="ordre-<?= e($code) ?>" name="action" value="descendre" class="outil" title="Descendre" aria-label="Descendre <?= e($l['nom_fr']) ?> dans le sélecteur de langue"<?= $rang === count($autres) - 1 ? ' disabled' : '' ?>><?= icone('descendre') ?></button>
          <?php elseif ($l['reference']): ?><span class="discret">1<sup>re</sup></span><?php endif; ?></td>
          <td class="tableau__actions"><?php if (peut('reglages') && !$l['reference']): ?><button type="submit" form="supprimer-<?= e($code) ?>" class="bouton bouton--petit bouton--danger" data-confirmer="Supprimer la langue « <?= e($l['nom_fr']) ?> » et toutes ses traductions ? Cette suppression est définitive. Pour la retirer du site sans rien perdre, décochez plutôt « sur le site ».">Supprimer</button><?php endif; ?></td>
        </tr>
      <?php if (!$l['reference']) $rang++; endforeach; ?>
      </tbody>
    </table></div>
    <p class="aide">Une langue décochée se prépare dans Pupitre sans paraître sur le site : ses traductions restent. « Sans traduction » ne signale pas forcément un manque : un nom propre ou un titre d'œuvre s'écrit souvent de la même façon dans toutes les langues.</p>
    <?php if (peut('reglages')): ?><div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer</button></div><?php endif; ?>
  </form>
  <?php foreach ($toutes as $code => $l): if (!$l['reference'] && peut('reglages')): ?>
    <form method="post" id="supprimer-<?= e($code) ?>" hidden><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="code" value="<?= e($code) ?>"></form>
    <form method="post" id="ordre-<?= e($code) ?>" hidden><?= champ_csrf() ?><input type="hidden" name="code" value="<?= e($code) ?>"></form>
  <?php endif; endforeach; ?>

  <?php if ($propositions): ?>
  <div class="carte carte--alerte" id="propositions">
    <h2>Textes anglais à confirmer</h2>
    <p class="aide aide--haut">Ces textes n'avaient pas d'anglais validé quand l'anglais est devenu la langue de rédaction : leur champ anglais porte encore le texte français. L'IA en avait proposé une version anglaise, jamais validée. Adoptez-la, ou écartez-la et rédigez l'anglais sur la fiche.</p>
    <ul class="items">
      <?php foreach ($propositions as $i => $p): if (!isset(TRADUISIBLES[$p['objet']][3][$p['champ']])) continue; ?>
        <li><span class="discret"><?= e(TRADUISIBLES[$p['objet']][0]) ?> · <?= e(TRADUISIBLES[$p['objet']][3][$p['champ']]) ?></span>
          <span>Aujourd'hui : <strong><?= e(reference_lire($p['objet'], (string) $p['id'], $p['champ'])) ?></strong></span>
          <span>Proposition : <strong lang="en"><?= e($p['texte']) ?></strong></span>
          <div class="item__actions">
            <form method="post"><?= champ_csrf() ?><input type="hidden" name="proposition" value="<?= (int) $i ?>"><button type="submit" name="action" value="adopter" class="bouton bouton--petit bouton--plein">Adopter comme texte anglais</button>
              <button type="submit" name="action" value="ecarter" class="bouton bouton--petit">Écarter</button></form>
          </div></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

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
    <p class="aide">Une langue ajoutée est d'abord « en préparation » : elle se traduit sur l'écran « Traductions » (l'IA peut proposer les traductions), puis se coche « sur le site ». La marche complète est décrite dans pupitre/LISEZ-MOI.md, « Ajouter une langue ».</p>
  </form>
  <?php endif; ?>

<?php else: ?>
  <?php
    $libelles = libelles_tous();
    $groupes = [];
    foreach ($libelles as $cle => $l) $groupes[$l['groupe']][$cle] = $l;
  ?>
  <p class="intro">Tous les intitulés et boutons de l'interface du site, en <?= e(mb_strtolower(langue_nom($reference))) ?>. Ceux d'un bloc de l'accueil se règlent aussi sur l'écran de ce bloc (« Textes du site »). <code>{n}</code> et <code>{date}</code> sont remplacés par le site : gardez-les tels quels. *…* rend l'italique. Leurs traductions sont sur l'écran « Traductions ».</p>
  <form method="post" class="carte">
    <?= champ_csrf() ?>
    <?php foreach ($groupes as $g => $liste): ?>
      <h2><?= e(FAMILLES_LIBELLES[$g] ?? ucfirst($g)) ?></h2>
      <?php foreach ($liste as $cle => $l): ?>
        <div id="lib-<?= e(preg_replace('/[^a-z0-9]+/i', '-', $cle)) ?>" class="ancre">
          <?= champ_tr('texte', $cle, $l['texte_' . $reference], ['prefixe' => 'lib[' . $cle . ']', 'objet' => ['libelles', $cle, 'texte'], 'texte' => mb_strlen((string) $l['texte_' . $reference]) > 90]) ?>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <div class="actions actions--bas"><button type="submit" class="bouton bouton--plein">Enregistrer les intitulés</button></div>
  </form>
<?php endif; ?>

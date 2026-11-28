<?php
/**
 * Réglages (pilote) : nom et sous-titre, fuseau, saison, racine du site, maisons. Les langues ont
 * leurs écrans (Langues, Traductions), la couleur de fond aussi (Apparence).
 * Rien de propre à l'artiste n'est écrit dans le code : tout est ici.
 */

defined('PUPITRE') or exit;

$titre = 'Réglages';
$maisons_liste = [
    'table' => 'maisons', 'ajout' => 'Ajouter une maison',
    'retenir' => fn(array $l) => bd_valeur('SELECT COUNT(*) FROM concerts WHERE maison_id = ?', [$l['id']])
        ? 'La maison « ' . $l['nom_en'] . ' » est employée par des concerts : elle ne peut pas être retirée.' : '',
    'champs' => [
        ['cle', 'ligne', 'Clé', 'requis' => true, 'max' => 40, 'aide' => 'Identifiant court, sans espace : il figure dans les fichiers du site.'],
        ['nom', 'bi', 'Nom', 'requis' => true],
    ],
];
$erreurs = [];

if (est_post() && recu('action') === 'reglages') {
    $nouveaux = [
        'artiste_nom' => recu('artiste_nom'),
        'sous_titre' => recu('sous_titre'),
        'fuseau' => recu('fuseau'),
        'site_racine' => recu('site_racine'),
        'site_adresse' => recu('site_adresse'),
        'saison_debut' => recu('saison_debut'),
        'saison_fin' => recu('saison_fin'),
    ];
    if ($nouveaux['artiste_nom'] === '') $erreurs[] = 'Le nom de l\'artiste est obligatoire.';
    if (!in_array($nouveaux['fuseau'], timezone_identifiers_list(), true)) $erreurs[] = 'Fuseau inconnu.';
    if (!adresse_valide($nouveaux['site_adresse'])) $erreurs[] = 'L\'adresse du site doit être complète (https://…).';
    foreach (['saison_debut', 'saison_fin'] as $k) {
        if ($nouveaux[$k] !== '' && !date_valide($nouveaux[$k])) $erreurs[] = 'Les dates de la saison s\'écrivent AAAA-MM-JJ.';
    }
    if (($nouveaux['saison_debut'] === '') !== ($nouveaux['saison_fin'] === '')) $erreurs[] = 'Donnez les deux dates de la saison, ou aucune.';
    $racine = $nouveaux['site_racine'] ?: '..';
    $chemin = realpath(($racine[0] === '/') ? $racine : RACINE . '/' . $racine);
    if (!$chemin || !is_dir($chemin . '/data')) $erreurs[] = 'La racine du site est introuvable : on y attend un dossier data/.';
    if (!$erreurs) {
        $avant = array_intersect_key(reglages_tous(), $nouveaux);
        foreach ($nouveaux as $k => $v) reglage_ecrire($k, $v);
        journaliser('modification', 'reglages', null, 'Réglages', $avant, $nouveaux);
        message('ok', 'Réglages enregistrés.');
        rediriger(url('reglages'));
    }
}
if (est_post() && recu('action') === 'metricool') {
    // les deux marques (vrais comptes, comptes de test) et le mode actif
    $avant = ['reelle' => reglage('metricool_marque_reelle'), 'test' => reglage('metricool_marque_test'), 'mode' => metricool_mode()];
    $noms = [];
    foreach (is_array($_POST['noms'] ?? null) ? $_POST['noms'] : [] as $id => $nom) $noms[preg_replace('/\D/', '', (string) $id)] = mb_substr((string) $nom, 0, 120);
    $apres = ['reelle' => preg_replace('/\D/', '', recu('marque_reelle')), 'test' => preg_replace('/\D/', '', recu('marque_test')), 'mode' => recu('mode') === 'test' ? 'test' : 'reel'];
    if ($apres['mode'] === 'test' && $apres['test'] === '') $erreurs[] = 'Pour passer en mode test, choisissez d\'abord la marque des comptes de test.';
    if ($apres['reelle'] !== '' && $apres['reelle'] === $apres['test']) $erreurs[] = 'La marque de test doit être une autre marque que celle des vrais comptes.';
    if (!$erreurs) {
        reglage_ecrire('metricool_marque_reelle', $apres['reelle']);
        reglage_ecrire('metricool_marque_reelle_nom', $noms[$apres['reelle']] ?? (recu('marque_reelle') === $avant['reelle'] ? reglage('metricool_marque_reelle_nom') : ''));
        reglage_ecrire('metricool_marque_test', $apres['test']);
        reglage_ecrire('metricool_marque_test_nom', $noms[$apres['test']] ?? (recu('marque_test') === $avant['test'] ? reglage('metricool_marque_test_nom') : ''));
        reglage_ecrire('metricool_mode', $apres['mode']);
        journaliser('modification', 'reglages', null, 'Réglages : Metricool', $avant, $apres);
        message('ok', $apres['mode'] !== $avant['mode']
            ? ($apres['mode'] === 'test' ? 'Mode test : Pupitre lit désormais les comptes de test, pas ceux de l\'artiste.' : 'Retour aux vrais comptes de l\'artiste.')
            : 'Réglages de Metricool enregistrés.');
        rediriger(url('reglages') . '#metricool');
    }
}
if (est_post() && recu('action') === 'maisons') {
    $recues = is_array($_POST['lignes'] ?? null) ? $_POST['lignes'] : [];
    foreach ($recues as &$l) if (is_array($l)) $l['cle'] = slug((string) ($l['cle'] ?? ''), 40);
    unset($l);
    $erreurs = liste_enregistrer('Maisons', $maisons_liste, $recues);
    if (!$erreurs) {
        message('ok', 'Maisons enregistrées.');
        rediriger(url('reglages'));
    }
}

$r = reglages_tous();
[$saison_debut, $saison_fin] = saison();
$analyse = analyse_possible();
?>
<div class="titre"><h1>Réglages</h1></div>
<?php if ($erreurs): ?><ul class="erreurs"><?php foreach ($erreurs as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul><?php endif; ?>

<div class="deux">
  <div>
    <form method="post" class="carte">
      <?= champ_csrf() ?><input type="hidden" name="action" value="reglages">
      <h2>L'artiste et son site</h2>
      <div class="colonnes">
        <?= champ('artiste_nom', 'Nom de l\'artiste', recu('artiste_nom') ?: $r['artiste_nom'], ['requis' => true]) ?>
        <?= champ('sous_titre', 'Sous-titre de Pupitre', est_post() ? recu('sous_titre') : $r['sous_titre'], ['aide' => 'Affiché sous « Pupitre ». Vide : le nom de l\'artiste.']) ?>
      </div>
      <div class="colonnes">
        <?= champ_choix('fuseau', 'Fuseau de l\'artiste', array_combine(timezone_identifiers_list(), timezone_identifiers_list()), recu('fuseau') ?: $r['fuseau'], ['aide' => 'Sert à l\'heure de l\'artiste du tableau de bord et à dater le journal.']) ?>
      </div>
      <div class="colonnes">
        <?= champ('saison_debut', 'Saison en cours : début', est_post() ? recu('saison_debut') : $r['saison_debut'], ['type' => 'date', 'aide' => 'Vide : du ' . date_fr($saison_debut) . ' au ' . date_fr($saison_fin) . '.']) ?>
        <?= champ('saison_fin', 'Saison en cours : fin', est_post() ? recu('saison_fin') : $r['saison_fin'], ['type' => 'date', 'aide' => 'Une date hors saison est signalée à l\'import.']) ?>
      </div>
      <?= champ('site_racine', 'Racine du site à alimenter', recu('site_racine') ?: $r['site_racine'], ['aide' => 'Dossier du site, relatif à pupitre/ ou absolu. Aujourd\'hui : ' . (site_racine() ?: 'introuvable')]) ?>
      <?= champ('site_adresse', 'Adresse du site', est_post() ? recu('site_adresse') : $r['site_adresse'], ['type' => 'url', 'indication' => 'https://…', 'aide' => 'Pour les liens « voir sur le site ». Vide : ' . site_adresse()]) ?>
      <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer</button></div>
    </form>

    <form method="post" class="carte">
      <?= champ_csrf() ?><input type="hidden" name="action" value="maisons">
      <h2>Maisons</h2>
      <p class="intro">Les maisons de l'artiste (ensembles, festivals, « chef invité ») : elles classent les concerts de l'agenda.</p>
      <?= liste_formulaire($maisons_liste, ($erreurs && recu('action') === 'maisons') ? array_values(array_filter($_POST['lignes'] ?? [], 'is_array')) : bd_lignes('SELECT * FROM maisons ORDER BY ordre, id'), 'lignes', $erreurs && recu('action') === 'maisons') ?>
      <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer les maisons</button></div>
    </form>
  </div>

  <div>
    <div class="carte">
      <h2>Réseaux de l'artiste</h2>
      <ul class="items">
        <?php foreach (bd_lignes('SELECT * FROM reseaux ORDER BY ordre, id') as $x): ?>
          <li><span class="item__titre"><?= e($x['nom']) ?></span><span class="discret"><?= $x['url'] !== '' ? e($x['url']) : 'adresse à fournir' ?></span></li>
        <?php endforeach; ?>
      </ul>
      <p class="actions"><a class="bouton bouton--petit" href="<?= e(url('contenu', ['type' => 'contacts'])) ?>">Modifier les réseaux</a></p>
    </div>
    <?php
      $mc_mode = metricool_mode();
      [$mc_marques, $mc_erreur] = metricool_configure() ? metricool_marques() : [[], ''];
      $mc_choix = [];
      foreach ($mc_marques as $m) $mc_choix[$m['id']] = $m['nom'] . ($m['reseaux'] ? ' · ' . implode(', ', array_map(fn($x) => RESEAUX_SOCIAUX[$x] ?? $x, $m['reseaux'])) : ' · aucun réseau connecté');
      // une marque déjà réglée reste proposée même si la liste n'a pas pu être lue
      foreach (['reelle', 'test'] as $k) { $id = reglage('metricool_marque_' . $k); if ($id !== '' && !isset($mc_choix[$id])) $mc_choix[$id] = (reglage('metricool_marque_' . $k . '_nom') ?: 'Marque') . ' · n° ' . $id; }
    ?>
    <form method="post" class="carte" id="metricool">
      <?= champ_csrf() ?><input type="hidden" name="action" value="metricool">
      <div class="carte__tete"><h2>Réseaux sociaux : Metricool</h2><?= $mc_mode === 'test' ? '<span class="puce puce--alerte">mode test</span>' : '<span class="puce puce--ok">vrais comptes</span>' ?></div>
      <?php if (!metricool_configure()): ?>
        <p class="message message--alerte">Metricool n'est pas relié : renseignez <code>METRICOOL_JETON</code> et <code>METRICOOL_UTILISATEUR</code> dans <code>pupitre/.env</code> (Metricool › Paramètres du compte › API). Les marques se choisiront ensuite dans une liste ; d'ici là, leur numéro peut être saisi.</p>
      <?php elseif ($mc_erreur !== ''): ?>
        <p class="message message--erreur" role="alert"><?= e($mc_erreur) ?></p>
      <?php endif; ?>
      <?php foreach ($mc_marques as $m): ?><input type="hidden" name="noms[<?= e($m['id']) ?>]" value="<?= e($m['nom']) ?>"><?php endforeach; ?>
      <?php if ($mc_choix): ?>
        <?= champ_choix('marque_reelle', 'Marque des vrais comptes de l\'artiste', $mc_choix, reglage('metricool_marque_reelle'), ['vide' => '— choisir —']) ?>
        <?= champ_choix('marque_test', 'Marque des comptes de test', $mc_choix, reglage('metricool_marque_test'), ['vide' => '— aucune —', 'aide' => 'Une marque dont les comptes ne servent qu\'aux essais : rien de ce qui s\'y fait ne touche l\'artiste.']) ?>
      <?php else: ?>
        <div class="colonnes">
          <?= champ('marque_reelle', 'Marque des vrais comptes (numéro)', reglage('metricool_marque_reelle'), ['max' => 20, 'indication' => '1234567']) ?>
          <?= champ('marque_test', 'Marque des comptes de test (numéro)', reglage('metricool_marque_test'), ['max' => 20]) ?>
        </div>
      <?php endif; ?>
      <fieldset class="champ"><legend>Mode actif</legend>
        <div class="champ--case"><input type="radio" id="mc-reel" name="mode" value="reel"<?= $mc_mode === 'reel' ? ' checked' : '' ?>><label for="mc-reel">Vrais comptes : Pupitre lit (et, demain, publie sur) les comptes de l'artiste</label></div>
        <div class="champ--case"><input type="radio" id="mc-test" name="mode" value="test"<?= $mc_mode === 'test' ? ' checked' : '' ?>><label for="mc-test">Comptes de test : tout passe par la marque de test, le temps d'un essai</label></div>
      </fieldset>
      <p class="aide">Basculer ne supprime rien : les publications relevées restent rangées avec leur marque, et l'écran « Résultats des publications » ne montre que celles du mode actif. Un bandeau rappelle le mode test tant qu'il est actif.</p>
      <div class="actions"><button type="submit" class="bouton bouton--plein">Enregistrer</button>
        <?php if (peut('communication')): ?><a class="bouton" href="<?= e(url('resultats')) ?>"><?= icone('mesures') ?>Résultats des publications</a><?php endif; ?></div>
    </form>
    <div class="carte">
      <h2>Ce poste</h2>
      <ul class="items">
        <li><span class="item__titre">IA</span><span><?= ia_configuree() ? '<span class="puce puce--ok">clé renseignée</span> modèle ' . e(ia_modele()) : '<span class="puce puce--alerte">clé absente</span> ANTHROPIC_API_KEY dans pupitre/.env' ?></span></li>
        <li><span class="item__titre">Boîte aux lettres</span><span><?= imap_configure() ? '<span class="puce puce--ok">reliée</span> ' . e(env('IMAP_UTILISATEUR')) : '<span class="puce puce--alerte">non reliée</span> IMAP_HOTE, IMAP_UTILISATEUR, IMAP_MOT_DE_PASSE dans pupitre/.env : les retours de mail ne sont pas suivis' ?></span></li>
        <li><span class="item__titre">Metricool</span><span><?= metricool_configure() ? '<span class="puce puce--ok">jeton renseigné</span>' : '<span class="puce puce--alerte">non relié</span> METRICOOL_JETON et METRICOOL_UTILISATEUR dans pupitre/.env' ?></span></li>
        <li><span class="item__titre">Mails</span><span><?= env('MAILJET_CLE') !== '' && env('MAILJET_SECRET') !== '' && env('MAIL_EXPEDITEUR') !== '' ? '<span class="puce puce--ok">Mailjet configuré</span>' : '<span class="puce puce--alerte">Mailjet non configuré</span> les messages sont écrits dans stockage/journaux/mails.log' ?></span></li>
        <li><span class="item__titre">Analyse des bandes-son</span><span><?= $analyse === '' ? '<span class="puce puce--ok">possible</span> python3 et ffmpeg trouvés' : '<span class="puce puce--alerte">impossible ici</span> ' . e($analyse) ?></span></li>
        <li><span class="item__titre">Tâches de fond</span><span><?= php_cli() !== '' ? '<span class="puce puce--ok">lancement possible</span>' : '<span class="puce puce--alerte">PHP en ligne de commande introuvable</span> renseignez PHP_CLI' ?><br><span class="discret">Cron : toutes les cinq minutes, <code><?= e((php_cli() ?: 'php') . ' ' . RACINE . '/cron/taches.php') ?></code></span></span></li>
      </ul>
      <form method="post" action="<?= e(url('bord')) ?>" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="lancer"><button type="submit" class="bouton bouton--petit">Lancer les tâches maintenant</button></form>
    </div>
  </div>
</div>

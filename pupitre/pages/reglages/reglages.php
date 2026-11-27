<?php
/**
 * Réglages (pilote) : nom et sous-titre, fuseau, saison, racine du site, maisons. Les langues ont
 * leur écran (Langues et traductions), la couleur de fond aussi (Apparence).
 * Rien de propre à l'artiste n'est écrit dans le code : tout est ici.
 */

defined('PUPITRE') or exit;

$titre = 'Réglages';
$maisons_liste = [
    'table' => 'maisons', 'ajout' => 'Ajouter une maison',
    'retenir' => fn(array $l) => bd_valeur('SELECT COUNT(*) FROM concerts WHERE maison_id = ?', [$l['id']])
        ? 'La maison « ' . $l['nom_fr'] . ' » est employée par des concerts : elle ne peut pas être retirée.' : '',
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
    <div class="carte">
      <h2>Ce poste</h2>
      <ul class="items">
        <li><span class="item__titre">IA</span><span><?= ia_configuree() ? '<span class="puce puce--ok">clé renseignée</span> modèle ' . e(ia_modele()) : '<span class="puce puce--alerte">clé absente</span> ANTHROPIC_API_KEY dans pupitre/.env' ?></span></li>
        <li><span class="item__titre">Mails</span><span><?= env('MAILJET_CLE') !== '' && env('MAILJET_SECRET') !== '' && env('MAIL_EXPEDITEUR') !== '' ? '<span class="puce puce--ok">Mailjet configuré</span>' : '<span class="puce puce--alerte">Mailjet non configuré</span> les messages sont écrits dans stockage/journaux/mails.log' ?></span></li>
        <li><span class="item__titre">Analyse des bandes-son</span><span><?= $analyse === '' ? '<span class="puce puce--ok">possible</span> python3 et ffmpeg trouvés' : '<span class="puce puce--alerte">impossible ici</span> ' . e($analyse) ?></span></li>
        <li><span class="item__titre">Tâches de fond</span><span><?= php_cli() !== '' ? '<span class="puce puce--ok">lancement possible</span>' : '<span class="puce puce--alerte">PHP en ligne de commande introuvable</span> renseignez PHP_CLI' ?><br><span class="discret">Cron : toutes les cinq minutes, <code><?= e((php_cli() ?: 'php') . ' ' . RACINE . '/cron/taches.php') ?></code></span></span></li>
      </ul>
      <form method="post" action="<?= e(url('bord')) ?>" class="actions"><?= champ_csrf() ?><input type="hidden" name="action" value="lancer"><button type="submit" class="bouton bouton--petit">Lancer les tâches maintenant</button></form>
    </div>
  </div>
</div>

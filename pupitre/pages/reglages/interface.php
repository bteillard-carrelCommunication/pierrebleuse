<?php
/**
 * Bibliothèque d'interface : le design system de Pupitre, montré sur pièces. Les jetons (couleurs,
 * typographie, espacements), puis chaque composant avec son nom, son usage et son balisage.
 *
 * Écrire un nouvel écran : composer avec ce qui est ici. Un besoin que rien ne couvre : ajouter le
 * composant à ressources/css/pupitre.css, sa fonction à gabarits/composants.php s'il en faut une,
 * puis son exemple ici. Aucune couleur en dur dans une page : toujours un jeton.
 */

defined('PUPITRE') or exit;

$titre = 'Bibliothèque d\'interface';
$palette = [
    'Indigo : la couleur de l\'interface' => ['indigo-50', 'indigo-100', 'indigo-200', 'indigo-300', 'indigo-400', 'indigo-500', 'indigo-600', 'indigo-700', 'indigo-800', 'indigo-900'],
    'Gris : fonds, traits, textes' => ['gris-0', 'gris-25', 'gris-50', 'gris-100', 'gris-200', 'gris-300', 'gris-400', 'gris-500', 'gris-600', 'gris-700', 'gris-800', 'gris-900'],
    'États : réservés à ce qu\'ils disent' => ['vert-100', 'vert-500', 'vert-700', 'ambre-100', 'ambre-500', 'ambre-700', 'rouge-100', 'rouge-500', 'rouge-700', 'bleu-100', 'bleu-500', 'bleu-700'],
];
$roles = [
    '--fond' => 'fond de la page', '--surface' => 'cartes, champs', '--surface-douce' => 'en-têtes de tableau, lignes', '--surface-creuse' => 'pastilles, pistes',
    '--texte' => 'texte', '--texte-moyen' => 'libellés', '--texte-doux' => 'aides, précisions', '--trait' => 'séparations', '--trait-fort' => 'bords des champs',
    '--primaire' => 'action principale, lien', '--primaire-doux' => 'élément actif', '--ok' => 'fait, publié', '--alerte' => 'à surveiller', '--erreur' => 'erreur, urgence', '--info' => 'information',
];
$code = fn(string $html) => '<details class="repli"><summary>Balisage</summary><pre class="diff"><div>' . e($html) . '</div></pre></details>';
?>
<div class="titre">
  <h1>Bibliothèque d'interface</h1>
  <span class="titre__suite">ressources/css/pupitre.css · gabarits/composants.php · inclus/icones.php</span>
</div>
<p class="intro">Le design system de Pupitre : ce dont tout écran est fait. Trois étages de jetons (la palette, les rôles, les réglages d'un composant), puis les composants. Un écran se compose avec ces pièces ; une couleur ne s'écrit jamais en dur dans une page.</p>

<section class="carte" aria-labelledby="ds-couleurs">
  <h2 id="ds-couleurs">Couleurs</h2>
  <?php foreach ($palette as $nom => $nuances): ?>
    <h3><?= e($nom) ?></h3>
    <div class="nuancier" style="margin-bottom:1.2rem">
      <?php foreach ($nuances as $n): ?><div class="nuance"><div class="nuance__couleur" style="background: var(--<?= e($n) ?>)"></div><code>--<?= e($n) ?></code></div><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <h3>Rôles : ce qu'un composant emploie</h3>
  <div class="nuancier">
    <?php foreach ($roles as $jeton => $usage): ?><div class="nuance"><div class="nuance__couleur" style="background: var(<?= e($jeton) ?>)"></div><code><?= e($jeton) ?></code><span><?= e($usage) ?></span></div><?php endforeach; ?>
  </div>
</section>

<section class="carte" aria-labelledby="ds-typo">
  <h2 id="ds-typo">Typographie, espacements, rayons</h2>
  <div class="deux">
    <div>
      <p style="font: 600 var(--t-2xl)/1.2 var(--police)">Titre de page · 28 px, 600</p>
      <p style="font: 600 var(--t-lg)/1.3 var(--police); margin-top:.6rem">Titre de section · 18 px, 600</p>
      <p style="font: 600 var(--t-md)/1.35 var(--police); margin-top:.6rem">Titre de carte · 16 px, 600</p>
      <p style="margin-top:.6rem">Texte courant · 14 px, 400. Poppins, servie par Pupitre : aucune requête vers un service tiers.</p>
      <p class="discret" style="margin-top:.4rem">Précision, aide · 13 px</p>
      <p style="font: 500 var(--t-xs) var(--police); letter-spacing:.05em; text-transform:uppercase; color: var(--texte-doux); margin-top:.4rem">En-tête de tableau · 12 px, capitales</p>
    </div>
    <div>
      <p class="discret">Espacements : <code>--e-1</code> 4 px · <code>--e-2</code> 8 · <code>--e-3</code> 12 · <code>--e-4</code> 16 · <code>--e-5</code> 20 · <code>--e-6</code> 24 · <code>--e-8</code> 32.</p>
      <p class="discret" style="margin-top:.5rem">Rayons : <code>--r-sm</code> 6 px (petites pièces) · <code>--r-md</code> 10 (boutons, champs) · <code>--r-lg</code> 14 (cartes) · <code>--r-xl</code> 20 (menu, connexion).</p>
      <p class="discret" style="margin-top:.5rem">Ombres : <code>--ombre-1</code> (cartes) · <code>--ombre-2</code> (menu, survol) · <code>--ombre-3</code> (fenêtres flottantes).</p>
    </div>
  </div>
</section>

<section class="carte" aria-labelledby="ds-boutons">
  <h2 id="ds-boutons">Boutons</h2>
  <p class="aide aide--haut">Un seul bouton plein par zone : l'action principale. Le bouton doux accompagne, le bouton neutre fait le reste, le bouton de danger détruit et demande confirmation.</p>
  <div class="exemple">
    <button type="button" class="bouton bouton--plein"><?= icone('plus') ?>Action principale</button>
    <button type="button" class="bouton bouton--doux">Action d'appui</button>
    <button type="button" class="bouton">Action neutre</button>
    <button type="button" class="bouton bouton--danger"><?= icone('corbeille') ?>Supprimer</button>
    <button type="button" class="bouton" disabled>Indisponible</button>
    <button type="button" class="bouton bouton--petit">Petit</button>
    <button type="button" class="bouton bouton--petit bouton--plein">Petit, plein</button>
    <button type="button" class="outil" aria-label="Monter (exemple)"><?= icone('monter') ?></button>
  </div>
  <?= $code('<a class="bouton bouton--plein" href="…"><?= icone(\'plus\') ?>Action principale</a>' . "\n" . '<button type="submit" class="bouton bouton--danger" data-confirmer="Supprimer… ?">Supprimer</button>') ?>
</section>

<section class="carte" aria-labelledby="ds-puces">
  <h2 id="ds-puces">Étiquettes et statuts</h2>
  <p class="aide aide--haut">Une couleur d'état ne dit jamais rien seule : le mot le dit, la couleur le souligne.</p>
  <div class="exemple">
    <span class="puce">neutre</span><span class="puce puce--ok"><?= icone('coche') ?>fait</span><span class="puce puce--alerte">à surveiller</span><span class="puce puce--erreur"><?= icone('alerte') ?>urgent</span><span class="puce puce--accent">mis en avant</span><span class="puce puce--info">information</span>
  </div>
  <div class="exemple">
    <?php foreach (array_keys(STATUTS) as $s) echo etiquette_statut($s) . ' '; ?>
    <span class="niveau niveau--prioritaire">prioritaire</span><span class="niveau niveau--action">à traiter</span><span class="niveau niveau--info">information</span>
  </div>
  <?= $code('<span class="puce puce--ok">fait</span>   <?= etiquette_statut(\'annoncee\') ?>') ?>
</section>

<section class="carte" aria-labelledby="ds-messages">
  <h2 id="ds-messages">Messages</h2>
  <div class="message message--compose message--ok"><?= icone('ok') ?><p class="message__texte">Enregistré. Le site suivra à la prochaine publication.</p></div>
  <div class="message message--compose message--alerte"><?= icone('alerte') ?><p class="message__texte">À vérifier : une position proposée par l'IA n'est pas encore confirmée.</p></div>
  <div class="message message--compose message--erreur"><?= icone('erreur') ?><p class="message__texte">La date est illisible : AAAA-MM-JJ attendu.</p></div>
  <p class="encart"><?= icone('info') ?><span><strong>Encart.</strong> Une précision durable, qui ne résulte pas d'une action : une règle, une limite, un état du produit.</span></p>
  <?= $code('message(\'ok\', \'Enregistré.\');          // affiché en tête de la page suivante' . "\n" . '<p class="encart"><?= icone(\'info\') ?><span>…</span></p>') ?>
</section>

<section class="carte" aria-labelledby="ds-champs">
  <h2 id="ds-champs">Champs de formulaire</h2>
  <p class="aide aide--haut">Toujours par les fonctions de <code>gabarits/composants.php</code> : libellé relié au champ, aide reliée par <code>aria-describedby</code>, langues côte à côte.</p>
  <div class="exemple exemple--colonne">
    <div class="colonnes">
      <?= champ('ex_texte', 'Champ sur une ligne', 'Valeur', ['aide' => 'Une aide, sous le champ.', 'requis' => true]) ?>
      <?= champ_choix('ex_choix', 'Liste de choix', ['a' => 'Premier choix', 'b' => 'Second choix'], 'a') ?>
      <?= champ('ex_date', 'Date', '2026-11-27', ['type' => 'date']) ?>
    </div>
    <?= champ_bi('ex_bi', 'Champ traduisible', 'Texte de référence', 'Reference text', ['valeurs' => ['en' => 'Reference text']]) ?>
    <?= champ_texte('ex_long', 'Zone de texte', "Plusieurs lignes.\nLa hauteur suit le contenu.") ?>
    <?= champ_case('ex_case', 'Case à cocher', true, ['aide' => 'Avec son aide.']) ?>
  </div>
  <?= $code('<?= champ(\'titre\', \'Titre\', $valeur, [\'requis\' => true, \'aide\' => \'…\']) ?>' . "\n" . '<?= champ_bi(\'titre\', \'Titre\', $fr, \'\', [\'objet\' => [\'concerts\', $id, \'titre\']]) ?>') ?>
</section>

<section class="carte" aria-labelledby="ds-tuiles">
  <h2 id="ds-tuiles">Tuiles, indicateurs, jauges</h2>
  <p class="aide aide--haut">Une tuile mène quelque part. Un indicateur donne un chiffre lu dans la base, jamais une estimation. Une jauge montre une part d'un tout.</p>
  <div class="tuiles">
    <?= tuile('alerte', 'Points de vigilance', 'à traiter sans attendre', ['chiffre' => 3, 'classe' => 'tuile--vigilance']) ?>
    <?= tuile('actions', 'Actions à réaliser', 'validations en attente', ['chiffre' => 5, 'classe' => 'tuile--actions']) ?>
    <?= tuile('concerts', 'Prochain concert', 'Dans 6 jours · exemple', ['classe' => 'tuile--calme']) ?>
    <?= tuile('publier', 'Publier le site', '2 modifications attendent', ['classe' => 'tuile--plein']) ?>
  </div>
  <div class="indicateurs">
    <?= indicateur('Libellé de l\'indicateur', 18, 'une précision', ['icone' => 'concerts']) ?>
    <?= indicateur('En alerte', 3, 'ce qui manque', ['icone' => 'communication', 'classe' => 'indicateur--alerte']) ?>
    <?= indicateur('En ordre', 0, 'rien à signaler', ['icone' => 'ok', 'classe' => 'indicateur--ok']) ?>
  </div>
  <div class="exemple exemple--colonne" style="max-width:26rem">
    <div class="jauge"><div class="jauge__tete"><span>Part traduite (exemple)</span><span class="nombre">62 %</span></div><div class="jauge__piste"><span style="width:62%"></span></div></div>
  </div>
  <?= $code('<?= tuile(\'publier\', \'Publier le site\', \'2 modifications attendent\', [\'lien\' => url(\'site\'), \'classe\' => \'tuile--plein\']) ?>' . "\n" . '<?= indicateur(\'Concerts à venir\', $n, \'dont 2 pas encore annoncés\', [\'icone\' => \'concerts\', \'lien\' => url(\'agenda\')]) ?>') ?>
</section>

<section class="carte" aria-labelledby="ds-listes">
  <h2 id="ds-listes">Tableaux, listes, onglets, états vides</h2>
  <nav class="onglets" aria-label="Exemple d'onglets"><a href="#ds-listes" aria-current="page">Onglet actif <span class="carte__compte">4</span></a><a href="#ds-listes">Autre onglet</a><a href="#ds-listes">Troisième</a></nav>
  <div class="defile"><table class="tableau">
    <thead><tr><th>Date</th><th>Objet</th><th>Lieu</th><th>Statut</th></tr></thead>
    <tbody>
      <tr><td class="date"><strong>12 mars</strong><br><span class="discret">vendredi</span></td><th scope="row"><a href="#ds-listes">Le titre mène à la fiche</a><br><span class="discret">une précision</span></th><td>Ville, pays</td><td><?= etiquette_statut('annoncee') ?></td></tr>
      <tr><td class="date"><strong>14 mars</strong><br><span class="discret">dimanche</span></td><th scope="row"><a href="#ds-listes">Autre ligne</a></th><td>Ville, pays</td><td><?= etiquette_statut('connue') ?></td></tr>
    </tbody>
  </table></div>
  <p class="aide">Un tableau se pose dans un cadre <code>defile</code> : à l'étroit, il défile dans son cadre plutôt que d'écraser ses colonnes (<code>tableau--large</code> à partir de six colonnes). <code>insecable</code> garde d'un seul tenant une adresse ou un identifiant ; <code>tableau__etats</code> laisse à une cellule la place de deux étiquettes de front.</p>
  <p class="aide">Les listes du quotidien (agenda, tableau de bord éditorial, publications et lettres) ne défilent pas de côté : leur cadre porte <code>defile--pile</code> (<code>defile--pile-large</code> pour six colonnes), et chaque ligne devient une fiche quand le cadre n'a plus la largeur des colonnes. C'est le cadre qui est mesuré, pas la fenêtre. Le balisage porte les rôles du tableau (<code>role="table"</code>, <code>row</code>, <code>columnheader</code>, <code>rowheader</code>, <code>cell</code>), pour qu'il reste lu comme un tableau. Le même tableau que l'agenda, dans un cadre étroit :</p>
  <div class="defile defile--pile-large" style="max-width:34rem"><table class="tableau tableau--agenda" role="table">
    <thead role="rowgroup"><tr role="row"><th role="columnheader">Date</th><th role="columnheader">Heure</th><th role="columnheader">Concert</th><th role="columnheader">Lieu</th><th role="columnheader">Maison</th><th role="columnheader">Statut</th></tr></thead>
    <tbody role="rowgroup">
      <tr role="row"><td role="cell" class="date"><strong>12 mars</strong><br><span class="discret">vendredi</span></td><td role="cell" class="nombre">20 h</td><th scope="row" role="rowheader"><a href="#ds-listes">Le titre mène à la fiche</a></th><td role="cell">Ville, pays<br><span class="discret">Salle</span></td><td role="cell" class="discret">Maison</td><td role="cell"><?= etiquette_statut('annoncee') ?></td></tr>
      <tr role="row"><td role="cell" class="date"><strong>14 mars</strong><br><span class="discret">dimanche</span></td><td role="cell" class="nombre">11 h 30</td><th scope="row" role="rowheader"><a href="#ds-listes">Autre ligne</a></th><td role="cell">Ville, pays</td><td role="cell" class="discret">Maison</td><td role="cell"><?= etiquette_statut('connue') ?></td></tr>
    </tbody>
  </table></div>
  <?= $code('<div class="defile defile--pile"><table class="tableau tableau--actions" role="table">' . "\n" . '  <thead role="rowgroup"><tr role="row"><th role="columnheader">…</th></tr></thead>' . "\n" . '  <tbody role="rowgroup"><tr role="row"><th scope="row" role="rowheader">…</th><td role="cell">…</td></tr></tbody>' . "\n" . '</table></div>') ?>
  <div class="deux" style="margin-top:1.2rem">
    <ul class="liste-dates">
      <li><?= pastille_date('2026-12-03') ?><div class="liste-dates__corps"><span class="liste-dates__titre">Liste à pastille de date</span><span class="liste-dates__detail">concerts, échéances</span></div><div class="liste-dates__fin"><span class="discret">dans 6 jours</span></div></li>
      <li><?= pastille_date(aujourdhui(), 'pastille-date--jour') ?><div class="liste-dates__corps"><span class="liste-dates__titre">Aujourd'hui</span><span class="liste-dates__detail">la pastille pleine</span></div><div class="liste-dates__fin"></div></li>
    </ul>
    <?= etat_vide('boite', 'État vide', 'Dire pourquoi il n\'y a rien, et quoi faire.', '<a class="bouton bouton--petit" href="#ds-listes">Une action</a>') ?>
  </div>
  <?= $code('<?= etat_vide(\'concerts\', \'Aucune date à venir\', \'Les prochaines dates apparaîtront ici.\', $bouton) ?>' . "\n" . '<?= pastille_date($r[\'date\']) ?>') ?>
</section>

<section class="carte" aria-labelledby="ds-notifs">
  <h2 id="ds-notifs">Notifications</h2>
  <p class="aide aide--haut">Trois niveaux, posés au calcul de l'alerte (<code>inclus/alertes.php</code>) : prioritaire, action, information.</p>
  <?php foreach ([['prioritaire', 'alerte', 'Anomalie ou échéance dépassée', 'À traiter sans attendre.'], ['action', 'actions', 'Action à traiter', 'Demande une intervention, sans urgence.'], ['info', 'info', 'Information', 'À savoir : rien ne presse.']] as [$niveau, $icone, $type, $message]): ?>
    <?= notification_afficher(['niveau' => $niveau, 'icone' => $icone, 'type' => $type, 'message' => $message, 'quand' => null]) ?>
  <?php endforeach; ?>
</section>

<section class="carte" aria-labelledby="ds-icones">
  <h2 id="ds-icones">Pictogrammes</h2>
  <p class="aide aide--haut"><code>icone('nom')</code>. Un pictogramme accompagne un texte ; seul dans un bouton, celui-ci porte un <code>aria-label</code>. Tracés de Lucide (licence ISC), recopiés dans <code>inclus/icones.php</code>.</p>
  <ul class="pictogrammes">
    <?php foreach (array_keys(ICONES) as $nom): ?><li><?= icone($nom) ?><?= e($nom) ?></li><?php endforeach; ?>
  </ul>
</section>

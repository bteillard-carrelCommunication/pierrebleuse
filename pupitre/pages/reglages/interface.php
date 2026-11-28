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
// la palette de la charte (ressources/charteGraphique, version 1.0) : nom, jeton, valeur, usage
$palette = [
    'Les couleurs de la charte' => [
        ['Marine', 'pp-marine', '#12263F', 'textes, navigation, fonds sombres'],
        ['Lin', 'pp-lin', '#FAF6F0', 'fond des pages'],
        ['Blanc', 'pp-blanc', '#FFFFFF', 'cartes, champs'],
        ['Mandarine', 'pp-mandarine', '#FF8B3D', 'bouton principal, entrée active, repères'],
        ['Cuivre', 'pp-cuivre', '#B84E0C', 'liens, accents'],
        ['Abricot', 'pp-abricot', '#FFE4CC', 'aplats, survols, bandeaux'],
        ['Ardoise', 'pp-ardoise', '#586273', 'textes secondaires'],
        ['Trait', 'pp-trait', '#EAE3D9', 'bordures, séparateurs'],
        ['Brume', 'pp-brume', '#B9C4D4', 'texte secondaire sur marine'],
    ],
    'Leurs mélanges, pour une interface dense' => [
        ['Lin clair', 'pp-lin-clair', '#FCFAF8', 'survol d\'une ligne, barre d\'actions'],
        ['Lin creux', 'pp-lin-creux', '#F2ECE4', 'compteurs, étiquettes neutres'],
        ['Abricot clair', 'pp-abricot-clair', '#FFF3E8', 'repère discret'],
        ['Trait fort', 'pp-trait-fort', '#D3CCC2', 'pointillés, séparateurs appuyés'],
        ['Trait de champ', 'pp-trait-champ', '#868A92', 'bord d\'un champ (3:1)'],
        ['Marine survol', 'pp-marine-survol', '#1E3552', 'survol dans la navigation'],
    ],
    'États (proposition de la charte, à valider)' => [
        ['Succès', 'pp-succes-fond', '#DDF2E6', 'texte #17603D'],
        ['Alerte', 'pp-alerte-fond', '#FFEFC2', 'texte #7A4F00'],
        ['Erreur', 'pp-erreur-fond', '#FBE3E1', 'texte #8C1D18'],
        ['Info', 'pp-info-fond', '#E3EAF3', 'texte #12263F'],
    ],
];
$roles = [
    '--fond' => 'fond de la page', '--surface' => 'cartes, champs', '--surface-douce' => 'en-têtes de tableau, survols', '--surface-creuse' => 'compteurs, pistes',
    '--texte' => 'texte', '--texte-doux' => 'aides, précisions', '--accent' => 'liens (un texte)', '--trait' => 'séparations', '--trait-champ' => 'bords des champs',
    '--primaire' => 'action principale (un fond)', '--primaire-doux' => 'élément actif, bandeau', '--sombre-fond' => 'navigation, bouton sombre',
    '--ok' => 'fait, publié', '--alerte-doux' => 'à surveiller', '--erreur' => 'erreur, urgence', '--info-doux' => 'information',
];
$code = fn(string $html) => '<details class="repli"><summary>Balisage</summary><pre class="diff"><div>' . e($html) . '</div></pre></details>';
?>
<div class="titre">
  <h1>Bibliothèque d'interface</h1>
  <span class="titre__suite">ressources/css/pupitre.css · gabarits/composants.php · inclus/icones.php</span>
</div>
<p class="intro">Le design system de Pupitre : ce dont tout écran est fait, selon la charte graphique (<code>ressources/charteGraphique</code>, version 1.0). Trois étages de jetons (la palette, les rôles, les réglages d'un composant), puis les composants. Un écran se compose avec ces pièces ; une couleur ne s'écrit jamais en dur dans une page.</p>

<section class="carte" aria-labelledby="ds-regles">
  <h2 id="ds-regles">Les trois règles</h2>
  <ol class="items">
    <li><span class="item__titre">Jamais de texte blanc sur mandarine.</span><span class="discret">Sur mandarine, le texte est marine (contraste 6,6:1 ; le blanc n'atteint que 2,3:1).</span></li>
    <li><span class="item__titre">La mandarine sert aux actions et aux repères.</span><span class="discret">Bouton principal, entrée de navigation active, pastille. Pas de texte courant, pas de grand aplat : un lien est cuivre, un aplat est abricot.</span></li>
    <li><span class="item__titre">Outfit pour les titres, Manrope pour tout le reste.</span><span class="discret">Aplats, pas de dégradé, pas d'ombre portée, coins très arrondis, pictogrammes au trait.</span></li>
  </ol>
</section>

<section class="carte" aria-labelledby="ds-marque">
  <h2 id="ds-marque">Logotype et symbole</h2>
  <p class="aide aide--haut">Le logotype ne se recompose jamais en texte : il vient de <code>ressources/marque/</code>, par <code>marque_logo()</code>. Jamais moins de 24 px de haut ; en dessous de 70 px de large, le symbole seul (<code>marque_symbole()</code>).</p>
  <div class="deux">
    <div class="exemple"><?= marque_logo('principal', 44, '') ?><?= marque_symbole('mandarine', 34, '') ?><?= marque_symbole('marine', 34, '') ?><?= marque_symbole('cuivre', 34, '') ?></div>
    <div class="exemple exemple--sombre"><?= marque_logo('inverse', 44, '') ?><?= marque_symbole('blanc', 34, '') ?></div>
  </div>
  <?= $code('<?= marque_logo(\'inverse\', 34) ?>      // menu (fond marine)' . "\n" . '<?= marque_logo(\'principal\', 44) ?>    // connexion, pages publiques (fond clair)' . "\n" . '<?= marque_tete() ?>                  // dans le <head> : icônes, manifeste, polices, feuille de style') ?>
</section>

<section class="carte" aria-labelledby="ds-couleurs">
  <h2 id="ds-couleurs">Couleurs</h2>
  <?php foreach ($palette as $nom => $nuances): ?>
    <h3><?= e($nom) ?></h3>
    <div class="nuancier" style="margin-bottom:1.5rem">
      <?php foreach ($nuances as [$libelle, $jeton, $valeur, $usage]): ?><div class="nuance"><div class="nuance__couleur" style="background: var(--<?= e($jeton) ?>)"></div><strong><?= e($libelle) ?></strong><code><?= e($valeur) ?></code><span><?= e($usage) ?></span></div><?php endforeach; ?>
    </div>
  <?php endforeach; ?>
  <h3>Rôles : ce qu'un composant emploie</h3>
  <p class="aide aide--haut"><code>--primaire</code> est un fond (le texte y est marine, <code>--sur-primaire</code>) ; <code>--accent</code> est une couleur de texte, sur lin ou sur blanc.</p>
  <div class="nuancier">
    <?php foreach ($roles as $jeton => $usage): ?><div class="nuance"><div class="nuance__couleur" style="background: var(<?= e($jeton) ?>)"></div><code><?= e($jeton) ?></code><span><?= e($usage) ?></span></div><?php endforeach; ?>
  </div>
</section>

<section class="carte" aria-labelledby="ds-typo">
  <h2 id="ds-typo">Typographie, espacements, rayons</h2>
  <div class="deux">
    <div>
      <p class="specimen-titre" style="font-size: var(--t-2xl); line-height:1.05">Titre de page · 44 px</p>
      <p class="specimen-titre" style="font-size: var(--t-xl); line-height:1.15; margin-top:.8rem">Titre de section · 28 px</p>
      <p class="specimen-titre" style="font-size: var(--t-lg); line-height:1.25; margin-top:.8rem">Titre de carte · 20 px</p>
      <p class="discret" style="margin-top:.3rem">Outfit, 600, approche −0,02 em.</p>
      <p style="font-size: var(--t-md); margin-top:.9rem">Texte de lecture · Manrope 16 px, 400.</p>
      <p style="margin-top:.4rem">Texte d'interface · Manrope 15 px. Les deux polices sont servies par Pupitre : aucune requête vers un service tiers.</p>
      <p class="discret" style="margin-top:.4rem">Précision, aide · 13 px</p>
      <p class="libelle" style="margin-top:.5rem">Libellé · 13 px, 600, capitales espacées</p>
    </div>
    <div>
      <p class="discret">Espacements : <code>--e-1</code> 4 px · <code>--e-2</code> 8 · <code>--e-3</code> 12 · <code>--e-4</code> 16 · <code>--e-5</code> 20 · <code>--e-6</code> 24 · <code>--e-8</code> 32.</p>
      <p class="discret" style="margin-top:.5rem">Rayons : <code>--r-sm</code> 8 px (petites pièces) · <code>--r-md</code> 12 (champs, encarts) · <code>--r-lg</code> 20 (cartes) · <code>--r-xl</code> 24 (connexion) · <code>--r-nav</code> 22 (entrées de menu) · <code>--r-rond</code> (boutons, étiquettes).</p>
      <p class="discret" style="margin-top:.5rem">Hauteur des contrôles : 44 px (<code>--hauteur-controle</code>) pour un bouton, un champ, une entrée de menu ; 36 px pour un petit bouton.</p>
      <p class="discret" style="margin-top:.5rem">Pas d'ombre : une carte se détache par son fond blanc et son trait ; une fenêtre flottante, par un trait plus fort.</p>
    </div>
  </div>
</section>

<section class="carte" aria-labelledby="ds-boutons">
  <h2 id="ds-boutons">Boutons</h2>
  <p class="aide aide--haut">Une pilule de 44 px. Un seul bouton plein (mandarine, texte marine) par zone : l'action principale. Le bouton secondaire (bordure marine) fait le reste, le bouton doux accompagne, le bouton sombre se pose sur un aplat abricot, le bouton de danger détruit et demande confirmation.</p>
  <div class="exemple">
    <button type="button" class="bouton bouton--plein"><?= icone('plus') ?>Action principale</button>
    <button type="button" class="bouton">Action secondaire</button>
    <button type="button" class="bouton bouton--doux">Action d'appui</button>
    <button type="button" class="bouton bouton--sombre">Sur un aplat</button>
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
  <p class="aide aide--haut">Toujours par les fonctions de <code>gabarits/composants.php</code> : libellé relié au champ, aide reliée par <code>aria-describedby</code>. Un champ traduisible se saisit en anglais ; chaque autre langue est à un lien de là, sur l'écran « Traductions », à la ligne du texte.</p>
  <div class="exemple exemple--colonne">
    <div class="colonnes">
      <?= champ('ex_texte', 'Champ sur une ligne', 'Valeur', ['aide' => 'Une aide, sous le champ.', 'requis' => true]) ?>
      <?= champ_choix('ex_choix', 'Liste de choix', ['a' => 'Premier choix', 'b' => 'Second choix'], 'a') ?>
      <?= champ('ex_date', 'Date', '2026-11-27', ['type' => 'date']) ?>
    </div>
    <?= champ_tr('ex_tr', 'Champ traduisible', 'Reference text, written in English') ?>
    <?= champ_texte('ex_long', 'Zone de texte', "Plusieurs lignes.\nLa hauteur suit le contenu.") ?>
    <?= champ_case('ex_case', 'Case à cocher', true, ['aide' => 'Avec son aide.']) ?>
  </div>
  <?= $code('<?= champ(\'titre\', \'Titre\', $valeur, [\'requis\' => true, \'aide\' => \'…\']) ?>' . "\n" . '<?= champ_tr(\'titre\', \'Titre\', $c[\'titre_en\'], [\'objet\' => [\'concerts\', $id, \'titre\']]) ?>') ?>
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

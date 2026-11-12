# Site Pierre Bleuse · LISEZ-MOI

Code principal du site depuis le 12 novembre 2026. La phase des hypothèses est close : la page
validée, `hypotheses/h4-2-nuit/` (la nuée d'étourneaux sur fond noir), est devenue ce dossier.
`site/index.html` est la page d'accueil, en français et en anglais, avec les contenus réels et un
agenda calculé.

Ouvrir : `http://localhost:8888/pierrebleuse/site/` (MAMP).

Le site n'est pas en ligne. `<meta name="robots" content="noindex">` reste en place jusqu'au
lancement. `index.php` et la page du bac à sable à la racine ne pointent pas encore vers `site/`.

## Fichiers

| Fichier | Rôle |
|---|---|
| `index.html` | la page : dix temps, textes français en dur (repli sans JavaScript), liste simple de l'agenda entre les repères `AGENDA-STATIQUE` |
| `css/site.css` | tout ce qui est propre au site : navigation, ouverture, agenda, sections noires, pied de page |
| `js/contenu.js` | textes dans les deux langues (une seule source), choix de la langue, typographie, navigation (menu, ancres, bascule FR / EN) |
| `js/agenda.js` | règles de l'agenda (sans DOM, réutilisables) puis rendu de l'accueil |
| `data/agenda.json` | **source** de l'agenda |
| `data/agenda.js` | généré à partir de `agenda.json` (ne pas modifier à la main) |
| `outils/generer-agenda.py` | génère `data/agenda.js` et la liste simple de `index.html` |
| `css/etourneaux.css`, `js/parametres.js`, `musique.js`, `sync.js`, `nuee.js`, `main.js`, `data/pathetique-iv-extrait.json` | moteur de la nuée : identiques, octet pour octet, à `hypotheses/h4-etourneaux/` |
| `js/reglages.js` | panneau de réglages du moteur, **modifié** (voir « Écarts avec le moteur d'origine ») |
| `assets/img/bleuse-1` à `bleuse-6` | photographies en 992 px |
| `assets/media/pathetique-iv-extrait.m4a` | bande-son de test |
| `assets/vol1_layerUp.png` à `vol3_layerUp.png` | références A/B du panneau de réglages |

Les textes viennent de `docs/site/CONTENU-SITE.md`. Rien n'est écrit dans la page qui n'y figure
pas, ou qui ne vienne pas de `data/agenda.json`. La documentation du moteur reste
`hypotheses/h4-etourneaux/LISEZ-MOI.md` et `SPEC.md`.

Ordre de chargement : `contenu.js` dans le `<head>` ; en fin de page `data/agenda.js`,
`agenda.js`, puis un court script qui pose les textes de la langue choisie et l'agenda calculé ;
ensuite seulement Lenis, GSAP et le moteur. Tout le contenu est donc dans la page quand le moteur
recense les blocs `[data-revele]`. Aucun chargement asynchrone.

## Structure de la page

| Temps | Section | Contenu |
|---|---|---|
| 1 | `nav.nav` | logo (retour en haut de page), ancres Agenda, Parcours, Écouter et voir, Presse, Contact (repliées dans un menu sous 720 px), bascule FR / EN, rouage des réglages |
| 2 | `#ouverture` | surtitre, deux fonctions, ligne « Prochain concert » calculée, bloc Écouter du moteur |
| 3 | `#presentation` (`.respiration`) | deux paragraphes et la citation de presse, sur la grille à trois blocs d'origine |
| 4 | `#orchestre` (`.photo--orchestre`, bleuse-3) | une phrase sur la saison |
| 5 | `#agenda` | À l'affiche, La saison, Derniers concerts (calculés) |
| 6 | `#parcours` | fonctions, opéra et création, orchestres invités, formation, deux liens « à venir » |
| 7 | `#ecouter` (photo bleuse-2) puis `#disques` | titre « Écouter, voir », puis disques et vidéos en liens simples, sans lecteur embarqué |
| 8 | `#presse` | la citation et trois références sans citation |
| 9 | `#professionnels` et `#contact` | trois éléments « à venir », management, communication |
| 10 | `#pied` (`.photo--pied`, bleuse-5) | lettre d'information (bouton inerte, aucun champ), réseaux, ligne de pied |

Chaque section porte `data-section`, chaque bloc de texte `data-revele`. Aucune section ne crée
de contexte d'empilement. Pas d'épinglage, pas de photo en `position: fixed`.

Liens inertes (pas de `href`, `aria-disabled="true"`) tant que les adresses ne sont pas fournies :
biographie complète, téléchargement de la biographie, « Toutes les archives », « Toute la revue de
presse », contact communication, YouTube, LinkedIn, et le bouton d'inscription à la lettre.

## Français et anglais

- Français par défaut, anglais avec `?lang=en`. Le choix est mémorisé (`localStorage`, clé
  `pb-langue`, sans erreur si le stockage est indisponible) et `<html lang>` suit.
- La bascule FR / EN est faite de deux liens qui rechargent la page dans l'autre langue.
- Une seule source : le dictionnaire de `js/contenu.js`. Le HTML porte le français pour le repli
  sans JavaScript. En français, `SiteContenu.ecarts` liste toute différence entre ce repli et le
  dictionnaire : il doit rester vide (à contrôler dans la console après une modification de texte).
- Dates écrites par `Intl.DateTimeFormat` (`fr-FR`, `en-GB`), heures locales de la salle sans
  conversion (`20 h`, `8 pm`). Espaces insécables de la typographie française posées par
  `SiteContenu.typo()`.

## Agenda

La date de référence est la date du jour du visiteur, remplaçable par `?date=AAAA-MM-JJ`.

1. **Prochain concert** (dans l'ouverture) : la prochaine représentation à venir, sur une ligne
   (date, ville et salle, titre), avec un lien vers `#agenda`. « Demain » la veille. « Ce soir » le
   jour même, seulement si la représentation commence à 17 h ou après (sinon la date reste écrite).
2. **À l'affiche** : les événements dont une représentation tombe dans les 30 jours, en fiches
   détaillées. Entre 2 et 4 fiches : moins de 2, on complète avec les suivants ; plus de 4, les
   suivants passent dans « La saison ».
3. **La saison** : les autres événements à venir, une ligne par événement, groupés par mois. Les
   `tempsFort` ont le grand titre serif, le filet et la mention « Temps fort ».
4. **Séries et tournées** : un seul bloc par événement. « 5 représentations, prochaine le 15 mars »
   pour un opéra, « Tournée, 3 villes » pour une tournée (ville, salle et lien portés par chaque
   date). L'événement reste à l'affiche jusqu'à sa dernière date, les dates passées sont atténuées.
5. **Derniers concerts** : les 3 derniers événements passés, avec le lien inerte « Toutes les
   archives ». Un événement y bascule le lendemain de sa dernière représentation.
6. **Disque** : étiquette « Disque », ni heure ni salle. Le titre porte le lien s'il y en a un.
7. Plus aucune date à venir : le texte « Les prochaines dates seront annoncées ici. », et la ligne
   « Prochain concert » de l'ouverture se réduit au lien vers l'agenda.

Les seuils sont en tête de `js/agenda.js` (`JOURS_AFFICHE`, `MIN_AFFICHE`, `MAX_AFFICHE`,
`NB_DERNIERS`, `HEURE_SOIR`). Le filtre facultatif (Tout, France, International) n'a pas été fait.

### Modifier l'agenda

Modifier `data/agenda.json`, puis :

```
python3 site/outils/generer-agenda.py
```

La commande (Python 3 standard, sans dépendance) réécrit `data/agenda.js` et, dans `index.html`,
la liste simple qui sert de repli sans JavaScript. Elle ne change rien si la source n'a pas changé.

### Trois états de référence

| Adresse | Prochain concert | À l'affiche | La saison | Derniers concerts |
|---|---|---|---|---|
| `?date=2026-11-12` | samedi 14 novembre, Paris, Cité de la musique, EIC & Friends | Paris (14 nov.), Odense (19 nov.), Madrid (27 au 29 nov.), Paris (3 déc.) | de décembre 2026 à mai 2027, 9 événements | Bâle, *Ravel Abroad*, Bucarest |
| `?date=2027-03-14` | « Demain », Amsterdam, *Red Fox* | *Red Fox* (« 5 représentations, prochaine le 15 mars », la date du 13 atténuée), *Lamentations*, tournée du Kammerorchester Basel (« Tournée, 3 villes ») | avril et mai 2027, 3 événements | Présences, EIC 50, Valladolid |
| `?date=2027-06-01` | aucun, il reste le lien vers l'agenda | texte « Les prochaines dates seront annoncées ici. » | vide | Milan, Göteborg, *Dialogues des ombres* |

À essayer aussi : `?date=2026-11-13` (« Demain ») et `?date=2026-11-14` (« Ce soir »).

## Paramètres d'adresse

| Paramètre | Effet |
|---|---|
| `?lang=fr`, `?lang=en` | langue (mémorisée) |
| `?date=AAAA-MM-JJ` | date de référence de l'agenda |
| `?panneau=1` | ouvre le panneau de réglages au chargement |
| `?figure=traine`, `masse`, `voile`, `fil` | impose une figure à la nuée |
| `?hud=1` | affiche le HUD de mise au point |
| `?t=96` | place la musique à 96 s |
| `?nolenis=1` | désactive le défilement adouci |
| `?reduit=1` | force le mode « mouvement réduit » |

Ils se combinent : `?lang=en&date=2027-03-14&panneau=1`.

## Réglages de la nuée

Le rouage, à droite de FR / EN, ouvre le panneau (touche `P` aussi, `Échap` pour fermer). Il n'y a
plus de boutons fixes en bas de l'écran : la pause de la musique et la pause de l'animation sont
en tête du panneau, dans le bloc « Lecture ».

### Les trois modes

La nuée a trois jeux de réglages. Elle n'en joue jamais un seul d'un coup : elle les mélange selon
ce qui se passe, et les pourcentages affichés sur les onglets donnent le mélange en direct.

| Onglet | Clé dans le JSON | Quand il joue |
|---|---|---|
| En musique | `video` | en haut de page, pendant que la musique joue |
| Sans musique | `ambiance` | en haut de page, musique arrêtée (l'état à l'arrivée sur le site) |
| Défilement | `scroll` | dès qu'on défile ; seul en jeu une fois l'ouverture sortie de l'écran |

Les clés `video`, `ambiance` et `scroll` sont celles du moteur et des fichiers JSON échangés avec
le directeur artistique : elles ne changent pas, seuls les noms affichés ont changé.

« Appliquer les réglages de … à » copie le mode de l'onglet ouvert vers un autre mode, ou vers les
deux autres, en un clic. « Annuler » revient sur la dernière copie.

### Valeurs par défaut

La configuration fournie le 12/11/2026 est celle du site (`window.H4Variante.defauts`, dans
`index.html`). Seul le mode « En musique » y diffère du moteur d'origine : opacité 36, intensité
0,37, 76 000 points, taille 0,4, sans traînées, brume 0,57, vitesse 0,05, amplitude 0,05,
sensibilité à la musique 1,5, au défilement 1,1, errance 1,3, visage 0,91, derrière 0,83. Les modes
« Sans musique » et « Défilement » de cette configuration sont les valeurs du moteur.

Le JSON fourni cochait aussi « analyse directe ». Ce réglage ne sert qu'au HUD, pour comparer
l'analyse en direct à l'analyse enregistrée. Il n'est pas devenu une valeur par défaut.

Un navigateur qui a déjà mémorisé des réglages (clé `h4-site-reglages-v3`) les garde : « Réinitialiser »,
dans le panneau, revient aux valeurs ci-dessus.

## Écarts avec le moteur d'origine

Référence : `hypotheses/h4-etourneaux/`. Tous les autres fichiers du moteur sont identiques.

**`js/reglages.js`**, à la demande du 12/11/2026. Chaque modification est commentée « Écart site ».

1. Les trois contextes s'appellent « En musique », « Sans musique », « Défilement » dans le panneau
   (au lieu de vidéo, ambiance, scroll). Le bloc s'intitule « Mode édité » et sa note explique le
   mélange.
2. Nouveau : « Appliquer les réglages de … à », avec un niveau d'annulation.
3. Le bloc « Écoute » devient « Lecture », reçoit un bouton « Pause de l'animation » (lié à la case
   « Animation » des commandes) et passe en tête du panneau.
4. Le bouton de musique du panneau affiche « Écouter la musique » ou « Pause de la musique ».

**`index.html`**, par rapport à `hypotheses/h4-2-nuit/index.html`.

5. Les commandes fixes en bas à droite (pause de la musique, pause de l'animation, réglages) sont
   retirées. Le rouage de la navigation porte `data-ouvrir-reglages`, que le moteur sait déjà
   brancher.
6. Valeurs par défaut du site passées par `window.H4Variante.defauts`, prévu par le moteur.

**`css/site.css`**, sans toucher à `etourneaux.css`.

7. Ouverture : le bloc de texte est plus haut que celui de la référence (surtitre, deux fonctions,
   prochain concert). Il garde sa place à 55 % de la hauteur quand il tient, et remonte juste assez
   sinon, de sa hauteur mesurée par `contenu.js` (`--ouverture-bloc-h`). Son halo sombre est étiré
   en proportion. Le cadrage de la nuée est inchangé : même rectangle que la référence à
   1440 × 900, 1280 × 720, 390 × 844 et 844 × 390.
8. Téléphone à l'horizontale et fenêtres de moins de 620 px de haut : bloc plus large, sans le
   crédit ni le choix du son, pour que le bouton Écouter reste à l'écran.
9. Navigation : sous l'ouverture, un voile noir derrière le logo et les ancres (classe `nav-dense`
   posée par `contenu.js`), pour que les textes qui défilent dessous ne se lisent pas à travers.
10. Pied de page : le bloc « La lettre » est à 40 % de la hauteur de la photo au lieu de 13 %, sinon
    il finit sous la navigation en bout de page ; halo sombre dessous, la photo étant claire.

### Points où le brief et le moteur se croisent

- Le panneau de réglages, le HUD et le message d'erreur de lecture sont en français dans les deux
  langues : ce sont des textes du moteur, outils de mise au point compris. L'étiquette du rouage
  (« Réglages ») aussi.
- Le panneau ouvert recouvre la partie droite de la navigation, comme dans la référence.
- Le bouton du menu (téléphone) a reçu le nom accessible « Menu », absent du brief.
- La deuxième photo a un `alt` vide : aucun texte de remplacement n'est fourni dans le brief.

## À retirer ou à masquer avant la mise en ligne

- Le rouage de la navigation et le panneau de réglages (curseurs, « Mode édité », import et export
  JSON, références A/B `vol1` à `vol3`), la touche `P`.
- Le HUD (`?hud=1`) et les paramètres d'adresse de mise au point : `?panneau=1`, `?figure=`, `?t=`,
  `?nolenis=1`, `?date=` (à garder pour les tests si on le souhaite, il ne change que l'agenda).
- La bande-son de test et son crédit (ouverture et ligne de pied), à remplacer par la bande-son
  définitive et son analyse.
- La mention « Site en construction » de la ligne de pied.
- `<meta name="robots" content="noindex">`.

À garder ou à remplacer, pas à supprimer : une commande de pause de l'animation. La nuée bouge en
continu, et l'accessibilité (WCAG 2.2.2) demande un moyen de l'arrêter. Aujourd'hui ce moyen est
dans le panneau ; si le panneau disparaît, il faut lui trouver une autre place.

## Points à confirmer

Les mentions `[À CONFIRMER]` de `CONTENU-SITE.md` ne s'affichent pas sur la page. Elles restent
ouvertes :

1. Odense : intitulé et dates du mandat après le concert du 19 novembre 2026.
2. Citation de *Fanfare* : texte exact, date, droit de citation.
3. Choix des trois extraits de presse (Le Monde, The Guardian, Gramophone).
4. Vidéos : sélection, crédits, droits.
5. Liens d'écoute des disques, comptes YouTube et LinkedIn.
6. Adresse de contact communication.
7. Photographies : le site utilise les images en 992 px, en attente des HD et des crédits.

Relevé en construisant la page :

8. `agenda.json` donne le même titre dans les deux langues pour « EIC 50 : Dialogues des ombres »,
   avec l'espace française avant les deux-points en anglais. À corriger dans la source si le titre
   anglais doit suivre la typographie anglaise.
9. La tournée du Kammerorchester Basel n'a pas de lien de billetterie pour Bâle dans `agenda.json`.

## Vérifications (12/11/2026, Chrome headless)

- 0 erreur et 0 exception en console à 1440 × 900 et à 390 × 844 (tactile), en français puis en
  anglais ; `SiteContenu.ecarts` vide.
- Ouverture : même rectangle de la nuée que `hypotheses/h4-2-nuit/` aux quatre tailles citées plus
  haut. Bouton Écouter (vrai clic, vrai toucher) : la musique part, la nuée passe en mode « En
  musique » (opacité 0,36), la pause la rend au mode « Sans musique ».
- Apparitions : 40 blocs relevés à 36 positions (ordinateur) et 41 positions (téléphone), en
  descendant puis en remontant : opacités et transformations identiques, tout est révélé en bas
  de page.
- Agenda : les trois états du tableau, plus « Demain » et « Ce soir », dans les deux langues.
- `?reduit=1` et `prefers-reduced-motion` : aucun déplacement, fondus seuls, page lisible en entier.
- JavaScript désactivé : page en français, tous les textes visibles, ancres visibles, agenda
  complet en liste simple (18 événements), rouage masqué.
- Panneau : ouverture par le rouage et par `P`, pause et reprise de l'animation, musique depuis le
  panneau, copie d'un mode vers un autre ou vers les deux, annulation, fermeture par `Échap` avec
  retour du focus sur le rouage. Ordinateur et téléphone.
- Ancres (ordinateur et téléphone), menu du téléphone, retour en haut par le logo.
- Bloc de l'ouverture entièrement à l'écran de 667 × 375 à 2560 × 1300, dans les deux langues.
- `hypotheses/` inchangé (`git status` vide sur ce dossier), ses sept pages sans erreur.
- Aucun « hypothèse », « H4 », « maquette » ni faux-texte dans ce qui se voit (textes, titre,
  description, attributs).

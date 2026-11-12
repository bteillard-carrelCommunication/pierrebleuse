# Prompt Claude Code : le site Pierre Bleuse, page d'accueil (code principal)

À lancer à la racine du dépôt `pierrebleuse` (dossier `Documents/Server/pierrebleuse`, celui qui contient `HANDOFF.md`, `hypotheses/` et `site/`).

---

## Contexte

La phase des hypothèses est close. La direction artistique validée par Pierre Bleuse, `hypotheses/h4-2-nuit/`, devient le site. Le code principal du projet est désormais dans `site/`. Le dossier `hypotheses/` est figé : il sert de référence et d'archive, on n'y modifie plus rien.

## Mission

Transforme `site/index.html` en vraie page d'accueil du site de Pierre Bleuse : contenus réels en français et en anglais, structure en dix temps, agenda dynamique.

Le principe visuel et d'animation ne change pas : fond noir, nuée d'étourneaux conduite par la musique puis apaisée par le défilement, typographie, apparitions des textes, commandes, panneau de réglages.

## Ce qui existe déjà dans `site/`

Créé le 12 novembre 2026, non commité.

| Fichier | État |
|---|---|
| `index.html` | Copie de la page validée (`hypotheses/h4-2-nuit/index.html`), chemins locaux, titre et description du site, `noindex`. Contient encore le faux-texte et les cinq dates d'exemple. |
| `css/etourneaux.css`, `js/parametres.js`, `musique.js`, `sync.js`, `nuee.js`, `reglages.js`, `main.js`, `data/pathetique-iv-extrait.json` | Moteur de la nuée : copies identiques, octet pour octet, de `hypotheses/h4-etourneaux/` au commit `a2f5a98`. |
| `css/site.css`, `js/contenu.js`, `js/agenda.js` | Fichiers à écrire, déjà branchés dans `index.html` (les deux scripts sont chargés avant le moteur). |
| `data/agenda.json` | Source de l'agenda : uniquement des dates déjà annoncées par les organisateurs. |
| `assets/img/` | Six photographies en 992 px (`bleuse-1` à `bleuse-6`). |
| `assets/media/pathetique-iv-extrait.m4a` | Bande-son de test. |
| `assets/vol1_layerUp.png` à `vol3_layerUp.png` | Références A/B du panneau de réglages. |
| `LISEZ-MOI.md` | Structure, provenance, règles. |

Configuration en place : `window.H4Variante = { nom: 'site', racine: './', fonds: ['noir'], … }`, donc clé de réglages `h4-site-reglages-v3`.

Les textes sont dans `docs/site/CONTENU-SITE.md`.

## À lire avant d'écrire une ligne

1. `HANDOFF.md`, parties 16 à 18.
2. `hypotheses/BRIEF-COMMUN.md`, parties 3 (grammaire de mouvement), 4 (contraintes techniques) et 6 (protocole de test).
3. `hypotheses/h4-etourneaux/LISEZ-MOI.md` et `SPEC.md` : la documentation du moteur, toujours valable pour les copies de `site/js/`. Puis `site/js/main.js` (variantes, `mesurer()`, apparitions `[data-revele]`) et l'en-tête de `site/css/etourneaux.css` (règle d'empilement).
4. `site/LISEZ-MOI.md` et `site/index.html`.
5. `docs/site/CONTENU-SITE.md` et `site/data/agenda.json`.

## Étape 0 : vérifier le point de départ

Avant toute modification, ouvre `site/` et `hypotheses/h4-2-nuit/` côte à côte (MAMP : `http://localhost:8888/pierrebleuse/site/`, ou la configuration de `.claude/launch.json`). Le rendu et le comportement doivent être les mêmes : même cadrage de la nuée dans l'ouverture, mêmes apparitions, bouton Écouter, panneau de réglages, 0 erreur en console. S'il y a un écart, corrige-le d'abord. Fais ensuite un premier commit, « site : point de départ ».

## Règles non négociables

- `hypotheses/` est figé : aucune modification, y compris dans `h4-etourneaux/` et `h4-2-nuit/`.
- Le moteur de `site/js/` et `site/css/etourneaux.css` appartient maintenant au site. Par défaut tu n'y touches pas : tout ce qui est nouveau va dans `css/site.css`, `js/contenu.js`, `js/agenda.js` et `data/`. Si une modification du moteur est indispensable, elle est minimale, commentée, et listée dans `site/LISEZ-MOI.md` sous un titre « Écarts avec le moteur d'origine ». L'ouverture garde le rendu de la référence.
- Socle technique inchangé : HTML, CSS et JS sans npm ni bundler, bibliothèques par CDN aux versions figées.
- Grammaire de mouvement du brief commun : anacrouse, entrées en pupitres, densité faible (une seule chose parle à la fois), silences entre les temps, amplitudes petites. Toute nouvelle section porte `data-section`, tout nouveau bloc de texte porte `data-revele`.
- Les sections ne créent jamais de contexte d'empilement (ni `z-index`, ni `transform`, ni `opacity`, ni `filter`, ni `isolation` sur la section elle-même), sinon la nuée ne se mélange plus avec les fonds. Pas d'épinglage, pas de `position: fixed` sur les photos, hauteur mobile en `100svh`, gouttières mobiles de 16 px, composition portrait pour les téléphones.
- Le moteur recense les `[data-revele]` au chargement de `main.js`. Tout le contenu (textes dans la bonne langue, agenda calculé) doit donc être dans le DOM avant l'exécution des scripts du moteur : rendu synchrone, pas de `fetch` asynchrone qui arriverait après l'amorçage. Charge les données de l'agenda par un script classique placé avant le moteur (par exemple `data/agenda.js`, généré à partir de `agenda.json`, qui reste la source).
- `prefers-reduced-motion`, navigation au clavier, contrastes et repli sans JavaScript restent au niveau de la référence : sans JS ou après le délai de 4 s, tous les textes sont lisibles (français par défaut, agenda complet en liste simple).
- N'invente aucun contenu : ni date, ni citation, ni chiffre, ni lien. Tout vient de `CONTENU-SITE.md` et de `agenda.json`. Les mentions `[À CONFIRMER]` ne s'affichent pas sur la page, tu les reportes dans le LISEZ-MOI. Plus aucun faux-texte dans la page.
- Plus de vocabulaire de maquette dans ce qui se voit : ni « hypothèse », ni « H4 », ni « maquette » dans les textes, les titres et les descriptions. Les noms techniques du moteur (`window.H4Variante`, classes CSS, clé de réglages) restent tels quels.
- `<meta name="robots" content="noindex">` reste en place tant que le site n'est pas lancé.
- Les outils de mise au point (panneau de réglages, touche P, HUD, paramètres d'URL, références A/B) sont conservés pour le travail avec le directeur artistique. Ne les retire pas : liste-les dans le LISEZ-MOI sous « À retirer ou à masquer avant la mise en ligne ».
- La bande-son de test et son crédit restent en place.
- Ne modifie ni `index.php` ni la page d'accueil du bac à sable à la racine : la bascule de la racine vers `site/` sera décidée à part.
- Git : branche `site`, commits locaux, pas de `push`, pas de déploiement.

## Structure de la page

Une seule page, dix temps. L'alternance sections noires et photos plein cadre est conservée, avec de vrais silences entre les blocs. Les textes exacts sont dans `CONTENU-SITE.md`, numérotés comme ci-dessous.

| # | Temps | Base dans `site/index.html` | Ce qui change |
|---|---|---|---|
| 1 | Navigation | `.nav` | Logo inchangé, lien vers le haut de la page. Ancres discrètes (Agenda, Parcours, Écouter et voir, Presse, Contact), repliées sur téléphone. Bascule FR / EN fonctionnelle. |
| 2 | Ouverture | `.ouverture` | Surtitre, deux lignes de fonctions, ligne « Prochain concert » calculée, bloc Écouter inchangé. |
| 3 | Présentation | `.respiration` | Deux paragraphes et la citation presse, sur la grille à trois blocs existante. |
| 4 | Première photo | `.photo--orchestre` (bleuse-3) | Une phrase de saison à la place du faux-texte. |
| 5 | Agenda | `.agenda` | Trois blocs calculés : À l'affiche, La saison, Derniers concerts (voir plus bas). |
| 6 | Parcours | nouvelle section noire | Fonctions, orchestres invités, opéra et création, formation, deux liens inertes. |
| 7 | Deuxième photo, « Écouter, voir » | nouvelle photo plein cadre, puis section noire | Une image de `assets/img/` non utilisée (à choisir, point focal réglé), puis disques et vidéos en liens simples, sans lecteur intégré. |
| 8 | Presse | nouvelle section noire | La citation réelle et trois références sans citation. |
| 9 | Espace professionnel et contact | nouvelle section noire | Trois items « à venir », management et communication. |
| 10 | Pied de page | `.pied` (bleuse-5) | Lettre d'information (formulaire inerte), réseaux, ligne de pied. |

Pour les nouvelles sections, reprends le vocabulaire visuel existant (grille à 12 colonnes, petites étiquettes `.mention`, serif pour les titres, corps en linéale fine, filets de `.date`). Écris le CSS dans `css/site.css`, sans surcharger les sélecteurs positionnels de `etourneaux.css` pensés pour trois blocs ou cinq dates (`.respiration__grille > :nth-child(n)`, `.date:nth-child(n)`) : utilise de nouvelles classes quand la structure diffère. Le texte courant ne porte plus la classe `.lorem` ni `lang="la"`.

La page d'accueil est la seule page à produire. Les rubriques suivantes (agenda complet, biographie, médias, presse, espace professionnel) viendront plus tard dans `site/` : écris `contenu.js` et `agenda.js` pour qu'ils soient réutilisables par d'autres pages, les données et les règles d'un côté, le rendu de l'accueil de l'autre.

## Français et anglais

- Français par défaut. Anglais avec `?lang=en`. Le choix est mémorisé (`localStorage`, sans erreur si le stockage est indisponible) et `<html lang>` suit.
- La bascule `FR | EN` est faite de deux liens qui rechargent la page dans l'autre langue : ainsi le moteur mesure toujours une mise en page définitive.
- Les deux langues viennent d'une seule source (`js/contenu.js`), pas de deux pages HTML à maintenir.
- Dates écrites avec `Intl.DateTimeFormat` (`fr-FR` : « samedi 14 novembre 2026 » ; `en-GB` : “Saturday 14 November 2026”), heures locales de la salle sans conversion (`20 h` en français, `8 pm` en anglais).

## Agenda : règles d'affichage

Source : `site/data/agenda.json`. Un événement a une ou plusieurs `representations`. Tout champ texte est une chaîne ou un objet `{fr, en}`.

La date de référence est la date du jour, remplaçable par `?date=AAAA-MM-JJ` pour les démonstrations.

1. **Prochain concert** (dans l'ouverture). La prochaine représentation à venir, sur une ligne : date, ville et salle, titre. « Ce soir » le jour même, « Demain » la veille. Lien vers `#agenda`.
2. **À l'affiche.** Les événements dont une représentation tombe dans les 30 prochains jours, en fiches détaillées : dates et heures, ville, salle, ensemble, programme (avec les mentions de création), solistes, lien « Informations et billetterie » s'il existe. Entre 2 et 4 fiches : s'il y en a moins de 2 dans les 30 jours, complète avec les suivants ; au-delà de 4, les suivants passent dans « La saison ».
3. **La saison.** Tous les autres événements à venir, en liste compacte groupée par mois : une ligne par événement (date ou plage de dates, ville, titre ou ensemble). Les `tempsFort` sont mis en avant avec le traitement typographique des cinq dates d'exemple actuelles (grand titre serif, filet, mention « Temps fort »).
4. **Séries et tournées.** Un événement à plusieurs représentations reste un seul bloc : « 5 représentations, prochaine le 15 mars » pour un opéra, « Tournée, 3 villes » avec la liste des villes pour une tournée (ville, pays, salle et lien sont alors portés par chaque représentation). Il reste à l'affiche jusqu'à sa dernière date, les représentations passées sont atténuées.
5. **Derniers concerts.** Les 3 derniers événements passés, en liste discrète, avec un lien inerte « Toutes les archives ». Un événement bascule ici le lendemain de sa dernière représentation.
6. **Disque.** Un événement de type `disque` s'affiche avec l'étiquette « Disque » et n'a ni heure ni salle.
7. S'il n'y a plus d'événement à venir, affiche le texte `vide` du brief.

Vérifie les trois états suivants et décris-les dans le LISEZ-MOI :
- `?date=2026-11-12` : prochain concert le 14 novembre à Paris ; à l'affiche Paris (14 nov.), Odense (19 nov.), Madrid (27 au 29 nov.), Paris (3 déc.) ; derniers concerts Bâle, *Ravel Abroad*, Bucarest.
- `?date=2027-03-14` : *Red Fox* à l'affiche avec « 5 représentations, prochaine le 15 mars », *Lamentations* et la tournée du Kammerorchester Basel à l'affiche.
- `?date=2027-06-01` : plus aucune date à venir, texte `vide`, derniers concerts Milan, Göteborg, *Dialogues des ombres*.

Option, seulement si elle reste discrète et dans la grammaire de mouvement : un filtre à trois entrées (Tout, France, International). Si tu la proposes, toute modification de hauteur après l'amorçage doit rester prise en charge par la mesure du moteur.

## À livrer

1. `site/index.html`, `css/site.css`, `js/contenu.js`, `js/agenda.js`, `data/agenda.js` (généré), sans aucun faux-texte.
2. `site/LISEZ-MOI.md` mis à jour : structure de la page, règles de l'agenda, paramètres d'URL (`?lang=`, `?date=`, et ceux du moteur), commande pour régénérer `data/agenda.js` à partir de `agenda.json` (sans npm), écarts avec le moteur d'origine, outils à retirer avant la mise en ligne, vérifications, liste des points `[À CONFIRMER]`.
3. `HANDOFF.md` : une partie 19 datée, courte : fin des hypothèses, code principal dans `site/`, ce qui a été fait, ce qui reste à trancher.

## Vérifications avant de rendre

Suis le protocole de `BRIEF-COMMUN.md` (partie 6) :
- Chrome headless, 0 erreur et 0 exception en console, à 1440 × 900 et à 390 × 844 (tactile), en français puis en anglais.
- Dans l'ouverture, la nuée garde le cadrage et le comportement de `hypotheses/h4-2-nuit/` (même rectangle). Les apparitions restent une fonction pure du défilement, aller égale retour.
- Les trois états de l'agenda ci-dessus, en capture.
- `?reduit=1` et `prefers-reduced-motion` : fondus simples, page complète lisible.
- JavaScript désactivé : tous les textes visibles.
- `hypotheses/` inchangé (`git status` et `git diff --stat`), et ses pages toujours sans erreur.

Si un point du brief contredit une contrainte du moteur, ne tranche pas seul : garde le comportement du moteur, note le point dans le LISEZ-MOI et signale-le dans ton compte rendu.

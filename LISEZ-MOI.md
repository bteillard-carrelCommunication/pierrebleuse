# Site Pierre Bleuse · LISEZ-MOI

Le site est à la racine du dépôt depuis le 12 novembre 2026 (il était dans `site/`). La phase des
hypothèses est archivée dans `_archives/phase-hypotheses/`, arborescence intacte : ses pages
fonctionnent toujours, on n'y modifie plus rien.

Ouvrir : `http://localhost:8888/pierrebleuse/` (MAMP). Le site n'est pas en ligne :
`<meta name="robots" content="noindex">` reste en place jusqu'au lancement.

Dernier état : **intervention du 27/11/2026** (cahier des charges
`docs/cahier-des-charges-pupitre.md`), décrite dans la section qui suit. Le site est administré par
Pupitre (`pupitre/`, voir « Pupitre » ci-dessous et `pupitre/LISEZ-MOI.md`) : tout ce qui s'édite
est dans `data/`, au format que Pupitre écrit. Les sections plus bas décrivent la page telle
qu'elle était après les retours du 13/11/2026 ; ce qui n'est pas repris dans « Intervention du
27/11/2026 » n'a pas changé (nuée, moteur, matrice, barre du haut, aimantation, musique, lumière,
pages légales), et là où les deux se contredisent, c'est la section du 27/11 qui dit vrai.

## Intervention du 28/11/2026 : ce qui a changé sur le site

Là où cette section et celle du 27/11 se contredisent, c'est celle-ci qui dit vrai.

**`css/reglages.css` : la couleur de fond et les blocs masqués, en un seul fichier.** Pupitre le
génère (écrans « Apparence » et « Blocs du site ») ; chaque page le charge dans son `<head>`, après
`css/site.css`. Il remplace la zone `SITE-STATIQUE` (un bloc `<style>` recopié dans chacune des six
pages) et la clé `fond` de `data/site.js`, que rien ne lisait : changer la couleur de fond ne
modifie plus que ce fichier. Ne pas le modifier à la main.

**L'anglais est la langue de rédaction.** Dans les fichiers de `data/`, un texte écrit comme une
simple chaîne est le texte anglais, qui vaut pour toutes les langues ; un objet `{en, fr, …}` porte
les traductions validées. `data/site.js` donne `langues.reference` et `langues.defaut` à `en`, et la
liste des langues, l'anglais en tête : la bascule de langue des pages (zone `LANGUES-STATIQUE`) suit
cette liste. Ajouter ou retirer une langue se fait dans Pupitre, puis se publie.

**`apercu/` : des copies temporaires du site.** Pupitre y range les aperçus avant publication
(`apercu/<jeton>/`, une copie figée par aperçu), et les retire à leur expiration. Le dossier est
hors git, ne se liste pas et n'est jamais indexé ; il ne fait pas partie du site à déployer.

**Discographie.** Les clés `contexte`, `citations` et `infos` d'un album ne sont plus écrites par
Pupitre (restent `presentation` et `mot`) ; le site les lit encore si elles sont là.

## Intervention du 27/11/2026 : ce qui a changé sur le site

**Réglages généraux (`data/site.js`).** Les langues du site, la langue de la première visite et la
liste des blocs affichés (la couleur de fond, variables CSS `--bg` et `--bg-rvb`, est depuis le 28/11
dans `css/reglages.css`). Un bloc masqué dans Pupitre n'occupe plus de place, quitte le menu, ne
compte ni dans l'aimantation ni dans le parcours du bouton Scroll, et ne charge pas ses médias.

**Langues.** Le site s'ouvre en **anglais**. Le français est proposé par la bascule (FR | EN) ;
le choix du visiteur est retenu par son navigateur, une adresse `?lang=fr` l'emporte, et la langue
du navigateur n'est pas consultée. Le HTML statique est écrit par Pupitre dans la langue de la
première visite : `<html lang>`, titre, description, menus, intitulés, zones de repli, dates. Les
libellés de l'interface sont dans `data/libelles.js` (administrés dans Pupitre) ; le dictionnaire
de `js/contenu.js` n'est plus qu'un secours. Dans la langue du HTML statique, `SiteContenu.ecarts`
(console) doit rester vide. Une langue de plus s'ajoute dans Pupitre, sans toucher au code.

**Hero.** La citation et, sous elle, un lecteur réduit à deux pictogrammes : lecture ou pause, et
liste des titres (volet). Sur téléphone, ces deux pictogrammes sont dans la barre du haut, comme
avant. Le morceau d'ouverture se choisit dans Pupitre (« Playlist ») ; la lecture automatique
garde ses règles (elle démarre au premier geste si le navigateur la retient). Le pictogramme de
défilement est devenu un bouton fixe, centré en bas de l'écran, avec le mot « Scroll » : chaque
clic mène à la section suivante, jusqu'au pied de page, où il s'efface.

**Édito.** Le texte de l'édito est justifié, avec césure.

**Blocs visuels.** Trois emplacements neutres (`visuel-1`, `visuel-2`, `visuel-3`) et le fond de
« Restons en contact » (`suivre`) ; leur contenu se règle dans Pupitre (« Blocs visuels »). Ordre
d'aujourd'hui : la photographie en lumière `bleuse-1`, puis la captation vidéo ; le troisième
bloc (photographie `bleuse-3`) est masqué. Sur un bloc visuel, la nuée n'est pas visible : le
média couvre la section, la nuée continue derrière. Un bloc peut porter plusieurs vidéos : le
visiteur passe de l'une à l'autre (flèches, position, titre et description), en fichier ou sur
YouTube (chargée après accord). Une vidéo qui joue avec son son prend le relais de la musique et
le lui rend. Les animations sont des modules d'un registre (`js/animations.js`) ; l'algorithme de
« lumière » n'a pas été touché.

**Agenda.** Le titre devient « Les prochains concerts ». Le titre d'un concert mène à la page de
l'organisateur quand elle est connue, sinon à sa fiche, un panneau dépliable de `concerts.html`
(`concerts.html#c-<identifiant>`) : programme, interprètes, description, et « Revue de presse de
ce concert » quand des articles lui sont associés.

**Discographie.** Chaque album garde sa vignette ; « En savoir plus » ouvre un panneau détaillé
sous la rangée : visuels en diaporama, informations générales, présentation, mot de l'artiste,
distinctions, « La critique » (articles associés), plateformes d'écoute en pictogrammes, liens de
commande. Sur téléphone, le panneau tient dans la hauteur de l'écran et défile en lui-même. Les
deux distinctions du disque Ligeti figurent sur sa vignette.

**Parcours.** « Fonctions » (la fonction en cours en tête, périodes écrites par le site), puis
« Grands débuts et collaborations », qui remplace « Opéra et création » : cinq lignes au plus,
choisies dans Pupitre. Aucune n'étant encore publiée, la rubrique ne s'affiche pas. La carte du
monde place chaque orchestre dans sa ville.

**Presse.** Sur l'accueil, une grille de quatre colonnes où chaque article choisi occupe une,
deux ou quatre colonnes ; la vignette montre le média (son logo, ou son nom), la citation
principale et le chapeau. Elle ouvre un panneau détaillé : résumé, citations dans leur langue
avec leur traduction présentée comme telle, visuels, concerts et albums associés, « Lire
l'article ». `presse.html` liste tous les articles publiés (`presse.html#a-<identifiant>`).
Les huit exemples de maquette sont désormais dans la collection de Pupitre, marqués `exemple` ;
`data/presse-exemples.js` n'est plus chargé.

**Téléchargement.** Apparence inchangée. Une ressource peut porter un fichier ou une adresse, la
langue du document (`hreflang`) et, s'il y en a, une ligne discrète : description, crédits,
conditions d'utilisation.

**Restons en contact.** Le bloc de texte est à gauche de la matrice (colonnes 1 à 4), sur un fond
sombre légèrement transparent ; la photographie est cadrée plus haut (64 % / 28 %) : le visage
reste dégagé à tous les formats, écrans très larges compris (vérifié à 1024, 1440, 1920, 2560 et
3440 px). Sur téléphone, le bloc reste sous le visage, sur son dégradé.

**Pied de page.** Inchangé. Le texte libre, à droite, garde les retours à la ligne saisis dans
Pupitre ; les liens vers les pages légales, à gauche, restent posés par le site.

**Fichiers de `data/` ajoutés ou changés.**

| Fichier | Variable | Forme |
|---|---|---|
| `data/site.js` | `SITE` | `langues` (`reference`, `defaut`, `liste` de `{ code, nom, locale }`), `fond`, `blocs` (clé du bloc : vrai ou faux) |
| `data/libelles.js` | `LIBELLES` | clé du libellé : chaîne, ou `{ fr, en, … }` |
| `data/playlist.js` | `PLAYLIST` | par titre : `id`, `src`, `piste`, `titre`, `mention`, `ouverture` (le morceau d'ouverture), et, s'ils sont saisis, compositeur, œuvre, mouvement, orchestre, interprètes, nature, crédits |
| `data/visuels.js` | `VISUELS` | par bloc : `elements`, liste de `{ type (image, video, youtube), src ou id, alt, position, positionMobile, animation { module, preset }, affiche, son, piste, titre, description, credit }` |
| `data/agenda.js` | `AGENDA_DONNEES` | par événement, en plus : `description` quand elle est saisie ; un concert dépublié n'y figure pas |
| `data/disques.js` | `DISQUES` | en plus : sous-titre, date de sortie, œuvres, interprètes, présentation, contexte, citations, `ecouter` et `commander` (listes de `{ plateforme, nom, url }`), `distinctions`, `visuels`, `articles` |
| `data/presse.js` | `PRESSE` | par article : `type`, `media`, `logo`, `titre`, `auteur`, `date`, `langue`, `citations` (liste de `{ texte, langue, principale, traduction }`), `chapeau`, `resume`, `infos`, `lien`, `accueil`, `rang`, `largeur` (1, 2 ou 4), `visuels`, `concerts`, `albums`, `exemple` |
| `data/parcours.js` | `PARCOURS` | `fonctions` (`intitule`, `institution`, `debut`, `fin`, `actuelle`, `une`), `collaborations` |
| `data/orchestres.js` | `ORCHESTRES` | inchangé ; seules les lignes « affichées » y figurent |
| `data/telechargements.js` | `TELECHARGEMENTS` | `id`, `libelle`, `fichier` (chemin ou adresse), et, s'ils sont saisis, `type`, `langue`, `description`, `credit`, `conditions` |

**Repères ajoutés dans les pages** (zones réécrites par Pupitre) : `LANGUES` (la bascule de langue ;
le repère `SITE` du 27/11 a laissé la place, le 28/11, au fichier `css/reglages.css`), `VISUEL-1` à `VISUEL-3` et
`VISUEL-SUIVRE`, `PARCOURS-COLLABORATIONS` (qui remplace `PARCOURS-OPERA`). Hors des repères,
Pupitre réécrit aussi, dans la langue de la première visite, `<html lang>`, le `<title>`, le
contenu des éléments `data-t`, les attributs `data-t-attr` et le bloc affiché des pages légales.

**Avant la mise en ligne**, en plus de la liste du bas de ce document : voir
`pupitre/LISEZ-MOI.md`, « Avant la mise en production ».

## Pupitre

Pupitre est l'application qui pilote la communication de l'artiste et administre ce site : agenda,
annuaire, contenus, médiathèque, import de l'extrait de l'agence. Il vit dans `pupitre/` (PHP et
MySQL) et reste séparé du site :

- le site est statique et le reste : aucun fichier PHP hors de `pupitre/`, aucun appel du site
  vers Pupitre, aucun accès à la base depuis le site ;
- Pupitre est la source des contenus. À chaque publication, il écrit les fichiers de `data/`,
  les zones de repli des pages entre leurs repères et le numéro de version `?v=` des scripts, et
  copie dans `assets/pupitre/` les médias employés ;
- le site ne reçoit que les représentations annoncées par leur organisateur ;
- la mise en ligne (envoi vers le serveur, git) reste manuelle. `pupitre/` ne se déploie pas avec
  le site statique : c'est une application à part, à protéger.

On ne modifie donc plus à la main ni les fichiers de `data/` ni le contenu entre deux repères :
la publication suivante l'écraserait. Ce qui se modifie à la main dans le site : les feuilles de
style, les scripts de `js/`, le HTML hors des repères, les pages légales.

## Arborescence

| Chemin | Rôle |
|---|---|
| `index.html` | la page d'accueil : textes français en dur (repli sans JavaScript) |
| `concerts.html`, `presse.html` | pages « techniques » : tous les concerts, tous les articles, en tableaux |
| `mentions-legales.html`, `confidentialite.html`, `credits.html` | pages légales, rédigées en français et en anglais, avec des passages à compléter |
| `css/etourneaux.css` | socle (typographie, variables, calques), canvas de la nuée, HUD, panneau de réglages |
| `css/site.css` | mise en page : matrice 12 colonnes, profondeur, barre du haut, lecteur, sections, pages de texte |
| `js/contenu.js` | libellés d'interface en français et en anglais (menu, boutons, intitulés de colonnes), typographie, menu, ancres en défilement doux, bouton de retour en haut, liens sortants |
| `js/agenda.js` | règles de l'agenda, rendu de l'accueil et de `concerts.html` |
| `js/sections.js` | rendu des visuels, des disques, de la presse, de la carte et de la liste des orchestres, des listes du parcours, des téléchargements, des contacts et des réseaux |
| `js/lumiere.js` | visuels « lumière » : les découpes de l'hypothèse 3, en WebGL |
| `js/main.js` | boucle de la nuée, lecteur de musique, vidéo du visuel 3, aimantation des sections, barre du haut, apparitions |
| `js/nuee.js`, `parametres.js`, `reglages.js`, `musique.js`, `sync.js` | moteur de la nuée, réglages, panneau, pistes, horloge |
| `data/textes.js` | citation, biographie, mention de saison, texte de la lettre, crédit du pied de page, description de la page ; chargé avant `js/contenu.js`, dont il complète le dictionnaire |
| `data/agenda.json`, `data/agenda.js` | l'agenda : uniquement les représentations annoncées par leur organisateur |
| `data/playlist.js` | titres du lecteur |
| `data/visuels.js` | visuels : image (telle quelle ou en « lumière »), vidéo, YouTube ou couleur ; opacité et place de la nuée |
| `data/disques.js`, `data/presse.js`, `data/orchestres.js` | contenus des sections du même nom |
| `data/parcours.js` | fonctions, opéra et création : deux listes |
| `data/contacts.js`, `data/reseaux.js`, `data/telechargements.js` | contacts, réseaux, fichiers à télécharger |
| `data/*-extrait.json`, `data/captation.json` | analyse de chaque bande-son (ce que suit la nuée) |
| `data/presse-exemples.js`, `assets/presse/exemples/` | **faux articles et faux logos pour la maquette**, à retirer avant la mise en ligne |
| `data/cartes.js` | terres de la carte du monde, généré (contient aussi l'Europe, qui n'est plus affichée) |
| `assets/img/` | photographies (992 px) et masques du sujet (`*-masque.png`) des visuels « lumière » |
| `assets/media/` | musiques (.m4a), vidéo du visuel 3 (`captation.mp4`) et son image d'attente |
| `assets/Pictos/` | anciens pictogrammes de la liste des titres (PNG, plus utilisés : le pictogramme est en SVG dans `index.html`) |
| `assets/pupitre/` | médias copiés par Pupitre à la publication (créé à la première copie) |
| `outils/` | scripts Python et shell (voir « Outils ») |
| `pupitre/` | Pupitre : l'application qui administre le site (PHP, MySQL) |
| `docs/site/CONTENU-SITE.md` | textes de référence |
| `docs/pupitre/` | prompts de Pupitre, captures de ses écrans, témoin du rendu du site (`captures/reference/`) |
| `_archives/phase-hypotheses/` | les hypothèses H1 à H4, la maquette, la documentation d'origine des moteurs |
| `_sources/musique/` | fichiers .mov fournis (non suivis par git, hors du site) |

Socle inchangé : HTML, CSS et JS sans npm ni bundler ; Lenis et GSAP par CDN, versions figées.
Polices : Inria Serif (titres, citation ; elle remplace Cormorant Garamond depuis le 13/11/2026)
et Inter, par Google Fonts.

## Données : les fichiers de `data/`

Tout ce qui s'édite sur le site vient de `data/`. Ces fichiers sont écrits par Pupitre
(`pupitre/`, voir son LISEZ-MOI) à chaque publication : on ne les modifie pas à la main. Ils ont
tous la même forme : une ligne de commentaire « Généré par Pupitre, ne pas modifier à la main »,
puis `window.NOM = ` suivi d'un JSON strict. Ils sont chargés comme scripts classiques, avant le
moteur de la nuée. Le site ne lit rien d'autre : il n'interroge jamais Pupitre.

Un champ texte est soit une chaîne (identique dans les deux langues), soit un objet
`{ "fr": …, "en": … }`. `*…*` rend l'italique. Un champ vide ou absent ne s'affiche pas.

| Fichier | Variable | Contenu |
|---|---|---|
| `data/textes.js` | `TEXTES` | `description`, `citation`, `biographie` (liste de paragraphes), `saison`, `lettre`, `credit` |
| `data/agenda.json`, `data/agenda.js` | `AGENDA_DONNEES` | `maisons`, `evenements` (voir « Agenda ») |
| `data/playlist.js` | `PLAYLIST` | liste de titres |
| `data/visuels.js` | `VISUELS` | un objet par emplacement (`visuel-1` à `visuel-4`) |
| `data/disques.js` | `DISQUES` | liste de disques, du plus récent au plus ancien |
| `data/presse.js` | `PRESSE` | liste d'articles ; les quatre premiers sont sur l'accueil |
| `data/orchestres.js` | `ORCHESTRES` | orchestres invités : la liste et les points de la carte |
| `data/parcours.js` | `PARCOURS` | `fonctions`, `opera` : deux listes de lignes |
| `data/contacts.js` | `CONTACTS` | groupes de contacts |
| `data/reseaux.js` | `RESEAUX` | réseaux de l'artiste |
| `data/telechargements.js` | `TELECHARGEMENTS` | fichiers à télécharger |

`data/presse-exemples.js` (faux articles de maquette) et `data/cartes.js` (terres de la carte) ne
sont pas écrits par Pupitre. Les analyses de bandes-son (`*.json`) en place ne changent pas ;
Pupitre y ajoute celle d'une musique ou d'une vidéo nouvelle.

**`TEXTES`.** Chaque champ est un `{ fr, en }`. `js/contenu.js` les range dans son dictionnaire
sous les clés `meta.description`, `ouverture.citation`, `bio.p1`, `bio.p2`…, `agenda.mention`,
`lettre.texte`, `pied.credit`. Les libellés d'interface restent dans `js/contenu.js`.

**`PLAYLIST`.** Le premier titre est celui de l'ouverture (lecture automatique). À la fin d'un
titre, le suivant s'enchaîne, puis la liste reprend au début.

| Champ | Rôle |
|---|---|
| `id` | identifiant court |
| `src` | fichier audio (AAC `.m4a`) |
| `piste` | analyse de la bande-son que suit la nuée (`outils/analyse-bande-son.py`) |
| `titre` | libellé de la liste |
| `mention` | seconde ligne, plus discrète (interprètes, source, droits) |

**`VISUELS`.** Une entrée par emplacement (l'attribut `data-visuel` de la section). Voir
« Visuels » pour le comportement.

| Champ | Rôle |
|---|---|
| `type` | `image`, `video`, `youtube` ou `couleur` |
| `src`, `alt` | fichier et texte alternatif |
| `position`, `positionMobile` | point focal CSS (« 60% 35% »), et sa variante sous 721 px |
| `lumiere` | image : nom d'un préréglage de « lumière » (`bleuse-1`, `bleuse-3`, `generique`) ; absent : l'image telle quelle |
| `affiche`, `son`, `piste` | vidéo : image d'attente ; `true` si elle joue avec son ; analyse de sa bande-son |
| `id` ou `url`, `couleur` | YouTube : la vidéo ; couleur : une couleur CSS |
| `opacite` | 0 à 100, par pas de 5 : 100 = le visuel plein, 0 = on ne voit que la nuée (classe `.fond-N`) ; un lecteur YouTube reste à 100 |
| `nuee` | `arriere-plan` (par défaut) : le visuel passe devant la nuée ; `premier-plan` : la nuée vole par-dessus |

**`DISQUES`.**

| Champ | Rôle |
|---|---|
| `id`, `titre`, `annee`, `maison` | identifiant, titre, année, maison de disques |
| `descriptif` | interprètes, distinctions : un texte, ou une liste de lignes |
| `mot` | le mot de l'artiste sur le disque |
| `commander`, `streaming` | liens d'achat et d'écoute : listes de `{ nom, url }` |
| `lien` | page du disque : lien « En savoir plus » |

**`PRESSE`.**

| Champ | Rôle |
|---|---|
| `id`, `media`, `logo` | identifiant, nom du titre de presse, fichier du logo (SVG ou PNG, affiché en blanc ; vide : le nom seul) |
| `titre`, `auteur`, `lien` | titre, auteur et adresse de l'article |
| `date` | `AAAA-MM-JJ` ou `AAAA-MM` ; vide si inconnue |
| `verbatim`, `langue` | un verbatim clé, cité dans sa langue, et le code de cette langue |
| `resume` | court résumé de l'article |

**`ORCHESTRES`.** Une entrée = un orchestre et la ville où le placer. Ajouter une entrée ajoute
la ligne et le point ; les entrées d'une même ville partagent un point.

| Champ | Rôle |
|---|---|
| `nom` | tel qu'il s'affiche, identique dans les deux langues ; vide : la ligne affiche la ville seule (aucune entrée dans ce cas depuis le 13/11/2026). Un même nom peut revenir dans plusieurs villes (l'Ensemble intercontemporain à Tokyo et à Tongyeong) |
| `ville` | l'étiquette du point |
| `lat`, `lon` | degrés décimaux (nord et est positifs) |
| `zone` | `ameriques`, `europe-afrique` ou `asie-oceanie` ; absent : déduite de la longitude |

**`PARCOURS`.** `fonctions` et `opera` : deux listes de lignes `{ fr, en }`, dans l'ordre
d'affichage.

**`CONTACTS`.** Une liste de groupes. Chaque groupe s'affiche dans cet ordre : la mention
(« Worldwide general management », « Communication »), la structure (« HarrisonParrott », « Carrel
Communication »), puis les personnes.

| Champ | Rôle |
|---|---|
| `mention` | intitulé du groupe, en petites capitales |
| `structure`, `lien` | nom de l'agence ou de la structure, et son adresse (facultatifs) |
| `langue` | si la mention et les fonctions sont écrites dans une langue et ne se traduisent pas, son code (`en`) ; sinon vide |
| `personnes` | liste de `{ nom, role, telephone, email }` ; `nom` vide quand le contact est celui de la structure elle-même |

**`RESEAUX`.** Liste de `{ id, nom, url, barre }`. `barre` : le réseau figure aussi dans la barre
du haut. Sans `url`, le pictogramme reste affiché, inerte. Les pictogrammes sont dans le HTML,
écrits par Pupitre.

**`TELECHARGEMENTS`.** Liste de `{ id, libelle, fichier }`. Sans `fichier`, la ligne porte la
mention « à venir ».

## Repli sans JavaScript : les repères

Le HTML garde le français en dur : la page se lit sans JavaScript. Chaque zone qui dépend des
données est encadrée par deux repères :

```
<!-- CITATION-STATIQUE:DEBUT (repli sans JavaScript, généré par Pupitre, ne pas modifier à la main) -->
<blockquote class="ouverture__citation" data-t="ouverture.citation">«&nbsp;Plus je progresse…&nbsp;»</blockquote>
<!-- CITATION-STATIQUE:FIN -->
```

À la publication, Pupitre réécrit le contenu entre les repères et le numéro de version `?v=` des
feuilles de style et des scripts, et rien d'autre dans les pages.

| Repère | Pages | Zone |
|---|---|---|
| `DESCRIPTION` | toutes | la balise `<meta name="description">` |
| `RESEAUX-BARRE` | toutes | les réseaux de la barre du haut |
| `CITATION`, `BIOGRAPHIE`, `LETTRE`, `CREDIT` | accueil | citation, paragraphes de la bio, texte de la lettre, crédit du pied de page |
| `SAISON` | accueil, `concerts.html` | la mention de saison |
| `VISUEL-1` à `VISUEL-4` | accueil | la balise du média (image ou vidéo) |
| `AGENDA` | accueil | la liste simple de tous les événements |
| `DISQUES`, `PRESSE` | accueil | les fiches |
| `PARCOURS-FONCTIONS`, `PARCOURS-OPERA`, `ORCHESTRES` | accueil | les deux listes, puis les orchestres par zone |
| `TELECHARGEMENTS`, `RESEAUX`, `CONTACTS` | accueil | les lignes, les réseaux de « Restons en contact », les groupes de contacts |

Sur un visuel, seul le média est entre repères : le type, l'opacité et la place de la nuée sont
des classes de la section, posées par `js/sections.js`.

En français, le site vérifie au chargement que le repli et les données disent la même chose :
`SiteContenu.ecarts` (console) doit rester vide.

## La page, section par section

| Section | `id` | Place de la nuée | Fond | Blocs sur la matrice |
|---|---|---|---|---|
| Hero | `ouverture` | premier plan | | citation et lecteur en bas à droite (colonnes 10 à 12), sur leur halo sombre ; pictogramme de défilement au centre, en bas. Sur téléphone, ce bloc n'est pas affiché : la barre du haut porte le lecteur |
| Bio | `bio` | arrière-plan | 0 % | texte : colonnes 10 à 12, sur son propre fond noir à 50 %, à bords vaporeux |
| Visuel 1 | `visuel-1` | arrière-plan | 100 % | pleine largeur, pleine hauteur, sans texte ; image en « lumière » |
| Agenda | `agenda` | arrière-plan | 80 % | 4 dates (3 colonnes chacune) ; tableau « Les concerts » ; « Tous les concerts » (10 à 12) |
| Visuel 2 | `visuel-2` | arrière-plan | 100 % | pleine largeur, pleine hauteur, sans texte ; image en « lumière » |
| Discographie | `disques` | arrière-plan | 80 % | 4 disques par ligne (3 colonnes chacun) |
| Parcours | `parcours` | arrière-plan | 50 %, puis 90 % sous la carte | pas de titre affiché ; fonctions (10 à 12) ; opéra et création (10 à 12) ; bandeau de la carte, sur toute la largeur : carte du monde (1 à 12), puis la liste des orchestres dessous, en trois colonnes égales |
| Visuel 3 | `visuel-3` | arrière-plan | vidéo à 95 % | la captation, avec son ; la nuée se devine à 5 % au travers |
| Presse | `presse` | arrière-plan | 80 % | 4 articles (3 colonnes chacun) ; « Tous les articles » (10 à 12) |
| Téléchargement | `telechargement` | arrière-plan | 80 % | titre et liste des liens : 10 à 12 |
| Restons en contact | `suivre` | arrière-plan | visuel 4 à 100 % | lettre (champ, bouton) et réseaux en pictogrammes : 10 à 12 |
| Contacts | `contact` | arrière-plan | 80 % | liste : 10 à 12 |
| Pied de page | `pied` | arrière-plan | 100 % | toute la largeur, comme la barre du haut |

Une section de contenu fait au moins un écran de haut (`min-height: 100svh`) : l'aimantation
s'arrête sur elle. Son contenu commence sous la barre du haut (`--barre-h` plus 11 % d'écran).

### Matrice

`.grille` : 12 colonnes entre deux marges (`--marge`, 16,15 % de la largeur depuis le 12/11 au
soir : les marges latérales ont été multipliées par 1,7 ; 16 px sur téléphone, inchangé) ; le logo
tient dans la marge gauche, les réseaux et les langues dans la marge droite. Un bloc de trois
colonnes fait 225 px à 1440 px de large. Un bloc se pose avec une classe :

| Classe | Colonnes | Sous 1180 px | Sous 721 px |
|---|---|---|---|
| `.bloc-plein` | 1 à 12 | 1 à 12 | pleine largeur |
| `.bloc-large` | 1 à 9 | 1 à 12 | pleine largeur |
| `.bloc-droite` | 10 à 12 | 7 à 12 | pleine largeur |
| `.bloc-quart` | 3 colonnes (4 par ligne) | 6 colonnes (2 par ligne) | pleine largeur |

Les fiches (`.fiche`) sont le format de l'agenda, repris par les disques et la presse. Elles sont
toutes alignées en haut (plus de décalage vertical). Les marges entre fiches ont été augmentées de
20 % : 6 rem entre deux rangées, et un cinquième de gouttière en plus entre deux colonnes, pris
dans la fiche pour que son bord gauche reste sur la matrice.

### Profondeur : où passe la nuée

De l'arrière vers l'avant : le fond noir, les particules (un canvas fixe), puis la section.

- `.nuee-arriere-plan` : la section passe devant la nuée. `.fond-N` règle l'opacité de son fond
  noir, de `.fond-0` (transparent) à `.fond-100` (opaque), de 5 en 5.
- `.nuee-premier-plan` : la nuée passe devant la section. Un bloc qui doit rester lisible porte
  `.dessus` (le bloc de l'ouverture).
- Sur un visuel, `.fond-N` est l'opacité du visuel lui-même : à 100 il est plein, à 0 on ne voit
  que la nuée.

La nuée ne défile pas avec la page : elle reste dans la fenêtre, les sections glissent dessus.

### Barre du haut

Toujours visibles : le logo (une image, `assets/img/logo-pierre-bleuse-blanc.png`, blanche sur fond
transparent), les pictogrammes Instagram, Facebook, YouTube (dans cet ordre) et la bascule FR / EN. Le menu et le petit lecteur sont invisibles en haut de page et apparaissent sur
le premier cinquième d'écran de défilement, en même temps que le voile de la barre ; le bloc de
l'ouverture s'efface d'autant (variable CSS `--barre`, posée par `main.js`). Une fois apparue, la
barre a une marge basse plus grande (1 rem de plus, voile prolongé de 44 px). Sous 1180 px, le
menu se replie derrière un bouton. Sans JavaScript, la barre est visible en entier.

Sur téléphone (sous 721 px), la barre est entière dès le haut de la page, comme après le début du
défilement sur ordinateur : `main.js` y tient `--barre` à 1, et le bloc de l'ouverture (citation,
lecteur) n'est pas affiché.

Un clic sur une entrée du menu fait défiler la page en douceur jusqu'à la section. Tous les liens
sortants s'ouvrent dans un nouvel onglet (`SiteContenu.liensSortants`).

### Défiler : pictogramme, aimantation, retour en haut

- **Pictogramme de l'ouverture** : un chevron dans un cercle, au centre, en bas. Il mène à la
  section suivante et s'efface dès qu'on défile.
- **Aimantation des sections** (à travailler) : dès que 10 % d'une section sont à l'écran dans le
  sens du défilement, la page défile seule jusqu'à elle. Vers le bas, on s'aligne sur le haut de
  la section. Vers le haut, sur son haut si elle tient dans l'écran, sinon sur son bas (on entre
  dans une section longue par la fin). Dans une section plus haute que l'écran, le défilement
  reste libre. Avec Lenis (ordinateur), l'aimantation part à l'instant où le seuil est franchi et
  verrouille le défilement le temps du trajet ; en défilement natif (tactile), elle part à l'arrêt
  du geste. Elle est suspendue pendant un trajet du menu, du logo ou du bouton de retour en haut,
  pendant 1,5 s après la touche Tab, et coupée en mouvement réduit.
  Réglages : `SEUIL_AIMANT` dans `js/main.js` ; case « Aimantation des sections » du panneau ;
  `?aimant=0` pour la couper.
- **Apparition des blocs** : un bloc apparaît avec le défilement, puis finit d'apparaître seul en
  une seconde environ une fois dans la fenêtre (la page s'arrête sur une section, le bas de
  l'écran ne reste pas à demi révélé).
- **Retour en haut** : un bouton fixe, en bas à droite, sur toutes les pages. Il apparaît quand on
  a parcouru le quart du défilement de la page (`SEUIL_HAUT` dans `js/contenu.js`).

## Musique

`data/playlist.js` est la liste du lecteur (champs : voir « Données »). Trois titres : la *Pathétique* (domaine public),
Berlioz, *Symphonie fantastique* (SWR Symphonieorchester, ARD Klassik), *The Centenary of Pierre
Boulez* (Ensemble intercontemporain & Friends, ARTE Concert).

- **Ouverture** : lecture automatique du premier titre. Les navigateurs la refusent souvent sans
  geste du visiteur : elle part alors au premier clic ou à la première touche (le défilement ne
  compte pas). En attendant, la nuée vole en mode autonome.
- **Lecteur de l'ouverture** (ordinateur et tablette) : bouton Play / Pause, puis la liste des
  titres, alignée sur l'icône de lecture ; un clic change de musique, le titre en cours est en
  surbrillance. Le pictogramme de la liste n'y figure plus. Sur téléphone, seul le lecteur de la
  barre existe.
- **Lecteur de la barre** (dès qu'on défile) : petit bouton lecture / pause, et le pictogramme
  de la liste, qui ouvre la même liste : le rond du bouton lecture / pause (mêmes classe, taille,
  trait et survol) avec une croche en SVG à l'intérieur. Le rond devient bleuté quand la liste est
  ouverte, comme pendant la lecture.
- **Fin d'un titre** : le suivant s'enchaîne, puis la liste reprend au début.
- **Pause** : le choix est mémorisé pour la session (`sessionStorage`, clé `pb-musique`).

Ajouter un titre : dans Pupitre, écran « Musiques ». À la main, hors de Pupitre : déposer le
fichier `.m4a` dans `assets/media/` et lancer l'analyse.

```
python3 outils/analyse-bande-son.py assets/media/titre.m4a data/titre.json --titre "…" --interpretes "…"
```

## La nuée : un seul mode de vol

Les valeurs par défaut (`js/parametres.js`) sont les réglages validés le 12/11/2026.

| Réglage | Valeur | | Réglage | Valeur |
|---|---|---|---|---|
| Opacité globale | 100 % | | Vitesse | 1,8 |
| Opacité des particules | 0,4 | | Amplitude | 0,6 |
| Nombre de particules | 23 000 | | Sensibilité à la musique | 2 |
| Taille | 0,55 | | Sensibilité au défilement | 1,1 |
| Traînées | 0 | | Errance | 1,3 |

**La brume est retirée** : elle vaut 0 tout le temps (`FIXES` dans `js/parametres.js`), n'a plus
de curseur, et un JSON de réglages qui en porte une autre valeur est ramené à 0.

### Réaction au volume

Le volume est l'énergie de la musique, lissée (montée vive, retombée lente). Il règle quatre
choses, chacune avec son curseur dans le panneau. **L'opacité globale ne bouge jamais.**

| Réglage | Défaut | Effet à 1 |
|---|---|---|
| Nombre de particules (`reacNombre`) | 1 | un tiers des points dans le silence, trois fois plus au plus fort (7 700 à 69 000 pour 23 000) |
| Opacité des particules (`reacOpacite`) | 1 | de ×0,45 dans le silence à ×2,2 au plus fort |
| Contraction, dilatation (`contraste`) | 1 | le nuage se resserre (×0,78) puis se dilate (×1,35) |
| Effet de boule (`boule`) | 0,85 | au-dessus du seuil, les points quittent le vol pour une sphère qui occupe la fenêtre |
| Seuil de la boule (`seuilBoule`) | 0,6 | volume à partir duquel la boule se forme |

### Quand la musique s'arrête ou change

- Musique arrêtée : la nuée glisse en 2,5 s vers le programme autonome du moteur (déterministe).
- Changement de titre : l'ancien se fond dans le nouveau en 2 s.
- Vidéo du visuel 3 : sa bande-son prend la conduite de la nuée en 1,5 s (voir « Visuels »).

### Panneau de réglages

Touche `P`, ou « Réglages de la nuée » dans le pied de page (`Échap` ferme). Lecture, curseurs,
figure forcée, HUD, pause de l'animation, aimantation des sections, copier et coller en JSON. Les
réglages sont mémorisés dans le navigateur (clé `pb-nuee-reglages-v1`).

## Visuels

`data/visuels.js` décrit chaque emplacement (`data-visuel`) ; Pupitre l'écrit (écran « Visuels »).
Pour chacun : un type, une opacité de 0 à 100 (`opacite`) et la place de la
nuée (`nuee` : `arriere-plan` par défaut, ou `premier-plan`).

| Type | Champs | Comportement |
|---|---|---|
| `image` | `src`, `alt`, `position`, `positionMobile`, `lumiere` | l'image telle quelle, ou en « lumière » |
| `video` | `src`, `affiche`, `position`, `son`, `piste` | muette et en boucle, ou avec son |
| `youtube` | `id` ou `url` | chargée après accord du visiteur, toujours à 100 % |
| `couleur` | `couleur` | un aplat |

### Image en « lumière » : les découpes (visuels 1 et 2)

C'est le traitement de l'hypothèse 3 (`_archives/…/h3-lever/`) : la photo est dans la pénombre,
la lumière la révèle (spot, faisceaux, barre), et la photo retournée de 180° apparaît à
l'intérieur des faisceaux. `js/lumiere.js` reprend son shader et ses préréglages, sans Pixi : un
canvas WebGL par visuel.

- `lumiere: 'bleuse-3'` (visuel 1) : spot chaud sur le chef, 14 colonnes de lumière bleue.
- `lumiere: 'bleuse-1'` (visuel 2) : spot doré, deux faisceaux, barre de lumière.
- `lumiere: 'generique'` : pour une autre photo, sans masque du sujet (spot et colonnes centrés
  sur le point focal `position`). Un rendu soigné demande un préréglage par photo, dans
  `PRESETS` de `js/lumiere.js`, et un masque du sujet.
- Les découpes apparaissent quand la section arrive, s'effacent quand on la quitte, vers le haut
  comme vers le bas, et reviennent quand on y retourne, d'où qu'on vienne (13/11/2026). La
  progression va de 0 à 1 sur les trois quarts d'écran qui précèdent l'alignement, et de 1 à 0 sur
  les trois quarts d'écran qui le suivent, sans jamais aller plus vite que 0,42 par seconde :
  après une aimantation, la lumière finit de se lever sur l'image arrêtée, en 2,4 s environ.
- Sans JavaScript ou sans WebGL : l'image seule.

### Vidéo avec son (visuel 3)

`son: true` : la vidéo part quand la section occupe la moitié de la fenêtre et prend le relais de
la musique en 1,5 s. Les deux sons ne jouent jamais ensemble : la musique se met en pause une fois
le fondu terminé. Quand on quitte la section, la vidéo s'arrête et la musique reprend où elle en
était. Pendant la vidéo, la nuée suit sa bande-son (`piste`, analysée comme une musique).

Réglage actuel du visuel 3 : `nuee: 'arriere-plan'`, `opacite: 95`. La vidéo est presque opaque et la nuée
reste visible à 5 % au travers. Une nuée à 5 % par-dessus une vidéo à 100 % n'est pas possible
telle quelle : la nuée est un seul calque fixe, son opacité ne peut pas changer d'une section à
l'autre. Pour la nuée franche par-dessus une vidéo assombrie : `nuee: 'premier-plan'`,
`opacite: 60`.

- Le bouton Play / Pause reste le maître du son : musique en pause, la vidéo joue muette.
- Si le navigateur refuse le son de la vidéo (pas encore de geste du visiteur), elle joue muette
  et la musique continue ; le son passe à la vidéo au premier clic.
- « Pause de l'animation » arrête aussi la vidéo. En mouvement réduit, elle ne part pas seule :
  elle montre ses commandes.

## Agenda

Date de référence : le jour du visiteur, ou `?date=AAAA-MM-JJ`.

1. **À l'affiche** : les quatre prochaines dates, en fiches. Une fiche donne, dans cet ordre : le
   titre, la date, l'heure, le lieu, la salle, l'orchestre, le programme, « avec », les mentions,
   le lien. Elle se termine par « En savoir plus » : vers la billetterie ou la salle quand
   `agenda.json` donne une adresse, sinon vers la ligne de l'événement dans `concerts.html`
   (ancre `c-<id>`).
2. **Les concerts** : les dates suivantes, en tableau, avec « En savoir plus » quand l'adresse est
   connue.
3. **Tous les concerts** : bouton vers `concerts.html` (à venir, puis passés, avec la salle). Les
   concerts passés ne sont plus sur l'accueil.

`concerts.html` a une barre pour retrouver un concert (13/11/2026) : un champ de recherche qui
porte sur tous les champs (titre, dates, pays, ville, salle, orchestre, programme, solistes,
mentions ; sans accents ni majuscules), quatre listes (pays, ville, salle, orchestre) et un tri
(date, date inversée, pays et ville, salle, orchestre, titre). « Date » garde l'ordre de l'agenda :
à venir du plus proche au plus lointain, passés du plus récent au plus ancien. Le nombre de
concerts trouvés est annoncé. Les libellés sont dans `js/contenu.js` (`concerts.…`).

« Temps fort » n'a plus d'affichage. Le drapeau `tempsFort` reste dans `agenda.json` et dans le
rendu (classe `fiche--fort`, attribut `data-temps-fort`), pour un usage futur.

Modifier l'agenda : dans Pupitre (« Agenda »), puis publier. Pupitre écrit `data/agenda.json`,
`data/agenda.js` et la liste de repli de l'accueil : il remplace `outils/generer-agenda.py`, qui
est gardé mais n'a plus à être lancé (il réécrirait `data/agenda.js` dans son ancienne forme, que
le site lit aussi).

## Discographie, presse, parcours

- `data/disques.js` : titre, descriptif, mot de Pierre Bleuse, année, maison de disques, liens
  « Commander » et « Écouter », `lien` (« En savoir plus »).
- `data/presse.js` : une fiche contient le logo (affiché en blanc) et le nom du média, le titre de
  l'article, un verbatim clé, un court résumé (`resume`), la date, l'auteur, le lien. Les quatre
  premiers sur l'accueil, tous sur `presse.html`.
- `data/parcours.js` : les fonctions, puis « opéra et création », en deux listes.
- `data/orchestres.js` : un orchestre, sa ville, sa latitude et sa longitude. Ajouter une entrée
  ajoute la ligne et le point sur la carte du monde (la carte de l'Europe est retirée). La liste est
  sous la carte, en trois colonnes égales : Amériques, Europe et Afrique, Asie, Russie et Océanie.
  La colonne se déduit de la longitude (à l'ouest de 25° O : Amériques ; à l'est de 60° E : Asie et
  Océanie ; entre les deux : Europe et Afrique) ; un champ `zone` (`ameriques`, `europe-afrique`,
  `asie-oceanie`) l'impose, nécessaire pour un orchestre russe (Moscou est à 37° E). Survoler
  une ligne allume son point ; survoler un point allume ses lignes. Chaque point de la carte a
  sa ligne dans la liste, et réciproquement : une entrée sans nom d'orchestre a une ligne qui
  affiche la ville seule (plus aucune depuis le 13/11/2026 : Salt Lake City est devenue « Utah
  Symphony Orchestra », placé à Park City). La ville est celle du concert, pas le siège de
  l'orchestre : le Tokyo Symphony Orchestra est à Kawasaki, et l'Ensemble intercontemporain figure
  deux fois, à Tokyo et à Tongyeong. Beyrouth et Ankara portent `zone: "asie-oceanie"`.
- Le bandeau de la carte (`.parcours__bandeau`) a un fond noir sur toute la largeur. Sa variable
  `--bandeau` (0,9) est l'opacité voulue, fond de la section compris.

Un champ vide ne s'affiche pas : rien n'est inventé pour le remplir.

**Presse, maquette** : `data/presse-exemples.js`, chargé après `data/presse.js` dans `index.html` et
`presse.html`, remplace la liste par huit articles inventés (médias, auteurs, citations, résumés,
logos SVG dans `assets/presse/exemples/`, liens vers example.com) pour montrer le rendu final. Les
vrais articles restent dans `window.PRESSE_REELLE`. Retirer les deux balises `<script>` et le
dossier avant la mise en ligne.

## Pages légales

Trois pages, liées depuis le pied de page de toutes les pages : `mentions-legales.html`,
`confidentialite.html`, `credits.html`. Chacune est rédigée en entier en français et en anglais
(un bloc `data-langue-bloc` par langue, `js/contenu.js` affiche celui de la langue courante).

Les passages à fournir sont balisés `<mark class="a-completer">` et s'affichent en ambre :

| Page | À compléter |
|---|---|
| Mentions légales | capital social d'Ortus Creatio, droit applicable à confirmer, date de mise à jour |
| Confidentialité | durée de conservation des journaux de l'hébergeur, lettre d'information à son ouverture, hébergement des polices et des bibliothèques à trancher, date de mise à jour |
| Crédits | photographes des trois photographies, autorisations ARD Klassik et ARTE Concert, source et autorisation de la captation de Prades |

Éditeur : Ortus Creatio (SARL, RCS Nanterre 994 930 899). Hébergeur, conception et réalisation :
Carrel Communication (SAS, RCS Montauban 812 341 121). Consigne du 12/11/2026 : ni nom de personne
ni adresse postale ; le directeur de la publication est désigné par sa fonction (« le gérant
d'Ortus Creatio »). Données lues dans l'annuaire public des entreprises (Pappers refuse la lecture
automatique) ; le capital n'y figure pas. TVA calculée depuis le SIREN.

La page de confidentialité décrit ce que fait le site aujourd'hui : pas de cookie, deux
préférences dans le navigateur (langue, musique en pause), polices Google Fonts et bibliothèques
chargées depuis des CDN. La tenir à jour si un formulaire, un outil de mesure ou un service tiers
est ajouté.

## Langues

Anglais par défaut depuis le 27/11/2026, français avec `?lang=fr` ou par la bascule (choix
mémorisé, clé `pb-langue`). Les libellés de l'interface viennent de `data/libelles.js`, les
contenus des champs `{ fr, en, … }` des fichiers de `data/` ; le dictionnaire de `js/contenu.js`
ne sert que de secours si `data/libelles.js` manque. Une traduction absente laisse place au texte
de la langue de référence (le français). Voir « Intervention du 27/11/2026 », plus haut, et
`pupitre/LISEZ-MOI.md`, « Langues et traductions ».

## Paramètres d'adresse

| Paramètre | Effet |
|---|---|
| `?lang=fr`, `?lang=en` | langue |
| `?date=AAAA-MM-JJ` | date de référence de l'agenda |
| `?muet=1` | pas de lecture automatique |
| `?aimant=0` | coupe l'aimantation des sections |
| `?panneau=1` | ouvre le panneau de réglages |
| `?hud=1` | affiche le HUD (cadence, sources, volume, boule, vidéo, aimantation, lumière) |
| `?figure=traine`, `masse`, `voile`, `fil` | impose une figure |
| `?t=104` | fige la nuée à 104 s du titre en cours |
| `?nolenis=1`, `?reduit=1` | sans défilement adouci ; mouvement réduit |

Dans la console : `__h4.etat()` (dont `video`, `aimant`, `lumieres`), `__h4.geler(104)`,
`__h4.degeler()`, `__h4.titre(1)`, `__h4.ecouter()`, `__h4.lumieres[0].figer(0.6)`.

## Outils

Node n'est pas installé sur le poste : tout est en Python 3 standard ou en shell.

| Commande | Rôle |
|---|---|
| `python3 outils/generer-agenda.py` | remplacé par Pupitre, qui écrit l'agenda à la publication ; gardé, plus nécessaire |
| `python3 outils/analyse-bande-son.py média sortie.json` | analyse d'une bande-son, musique ou vidéo (demande ffmpeg) ; Pupitre le lance quand on ajoute une musique |
| `python3 outils/generer-cartes.py` | régénère `data/cartes.js` (Natural Earth, domaine public) |
| `sh outils/syntaxe.sh` | vérifie la syntaxe des scripts de `js/` et de `data/` |
| `outils/cdp.py` | pilote Chrome headless pour tester et capturer (`molette()` envoie de vrais crans de molette) |
| `python3 outils/temoin.py capturer dossier`, puis `comparer docs/pupitre/captures/reference dossier` | prouve que le rendu n'a pas changé : texte visible, DOM, écarts, erreurs de console, en français et en anglais |

## Choix faits

### En appliquant les retours du 13/11/2026

1. **Fiche de l'agenda** : titre, date, heure, lieu, salle, orchestre, programme, « avec », puis
   les mentions (dont « n représentations » pour un opéra et « n villes » pour une tournée) et le
   lien. L'heure passe sous la date. L'étiquette « Disque » suit le titre. Les fiches des disques
   et de la presse ne changent pas.
2. **Logo** : l'image fournie, à 56 px de haut (45 px sur téléphone). Le nom de l'artiste reste le
   libellé du lien.
3. **Téléphone** : la consigne est lue comme une demande. Le bloc de l'ouverture (citation et
   lecteur) n'est plus affiché sous 721 px ; la barre du haut y est entière d'emblée. La citation
   n'apparaît donc plus sur téléphone.
4. **`concerts.html`** : recherche sur tous les champs, filtres et tri (voir « Agenda »). Le tri se
   choisit dans une liste, pas en cliquant les en-têtes de colonnes.
5. **Inria Serif** remplace Cormorant Garamond partout (graisses 300, 400, 700, et les italiques
   300 et 400). Elle est plus large : le titre des fiches et le nom des structures des contacts
   ont un corps réduit pour tenir dans leurs colonnes. La page des crédits la cite.
6. **Découpes des visuels 1 et 2** : elles s'effacent aussi quand on quitte la section vers le bas,
   et reviennent au retour (voir « Visuels »).
7. **Contacts** : la mention, la structure, les personnes. « Carrel Communication » devient la
   structure du second groupe ; son contact garde son téléphone et son adresse, sans répéter le
   nom. L'orthographe « HarrisonParrott » est gardée. La modification est faite dans Pupitre et
   publiée par lui.
8. **Orchestres invités complétés** (liste fournie le 13/11) : Utah Symphony Orchestra à Park
   City, qui remplace la ligne « Salt Lake City » ; Orchestre symphonique de Québec ; Lebanese
   Philharmonic Orchestra (Beyrouth) ; Bilkent Symphony Orchestra (Ankara) ; China National
   Symphony Orchestra (Pékin) ; Ensemble intercontemporain à Tokyo et à Tongyeong ; le Tokyo
   Symphony Orchestra passe de Tokyo à Kawasaki. 24 lignes, 21 points. Beyrouth et Ankara sont
   rangés dans la troisième colonne, comme dans la liste fournie (« Asie et Moyen-Orient ») ;
   l'intitulé de cette colonne reste « Asie, Russie et Océanie », Auckland y figurant. Dans la
   troisième colonne, l'ordre est celui de la liste fournie, puis Singapour et Auckland. Les
   coordonnées sont celles des villes, à deux décimales. Villes en anglais : Quebec City, Beirut,
   Beijing. Le repli sans JavaScript donne désormais la ville après le nom, comme le rendu.

### En appliquant les consignes du 12/11/2026 (fin de soirée)

1. **Pages légales** : éditeur, directeur de la publication, hébergeur, conception, responsable du
   traitement remplis sans nom propre ni adresse (voir « Pages légales »).
2. **Pictogramme de la liste** : le rond du bouton lecture / pause avec une croche en SVG
   (après un premier essai avec `Playlist.png` tel quel, abandonné le soir même). La variable
   `--picto` est supprimée.
3. **Orchestres invités** : carte sur les 12 colonnes, liste dessous en trois colonnes égales avec
   un intertitre par zone ; survol dans les deux sens inchangé. Une seule colonne sur téléphone.
   Chaque point a sa ligne et chaque ligne son point : Salt Lake City entre dans la liste (la
   ville seule, en attendant le nom de l'orchestre).
4. **Presse** : huit faux articles pour la maquette (voir « Discographie, presse, parcours »).

### En appliquant les retours du 12/11/2026 au soir

1. **Vidéo du visuel 3** : opaque à 95 %, nuée visible à 5 % au travers (voir « Vidéo avec son »).
2. **Marges latérales × 1,7** : sur ordinateur et tablette. Sur téléphone elles restent à 16 px,
   alignées sur la barre du haut.
3. **Adresses de contact** : la colonne ne fait plus que 225 px à 1440 px. Leur corps suit la
   largeur de la colonne pour qu'elles tiennent sur une ligne ; sinon elles se coupent après
   l'arobase.
4. **Salt Lake City** : le point est sur la carte. Faute de nom d'orchestre fourni, sa ligne dans
   la liste (ajoutée en fin de soirée) affiche la ville seule.
5. **Téléphones** : format unique « +33 6 60 73 72 89 ».

### En appliquant les retours de l'atelier (12/11/2026, après-midi)

1. **Visuel 3** : la vidéo de l'hypothèse 4-3, avec son, et la nuée qui suit sa bande-son.
   L'opacité et la place de la nuée ont été revues le soir même (ci-dessus).
2. **Son de la vidéo** : il suit le bouton Play / Pause. Musique en pause, la vidéo joue muette.
3. **Aimantation** : règles décrites plus haut ; toutes les sections font au moins un écran, et la
   bio est centrée en hauteur dans le sien.
4. **« En savoir plus » des fiches** : sans adresse connue, le lien mène à la ligne du concert
   dans `concerts.html`. Aujourd'hui, c'est le cas des quatre fiches à l'affiche.
5. **Marges entre fiches, + 20 %** : voir « Matrice ».
6. **Menu** : « Disques » devient « Discographie » dans le menu aussi.
7. **Parcours** : le titre reste dans la page pour les lecteurs d'écran, il n'est plus affiché.
8. **Champ de la lettre** : désactivé, comme le bouton, avec la mention « Inscription bientôt
   ouverte ». Rien n'est envoyé ni conservé.
9. **Réseaux de « Restons en contact »** : Instagram, Facebook, YouTube, LinkedIn. Les deux
   derniers sont inertes, faute d'adresse.
10. **Pied de page** : « Crédits photographiques » devient « Crédits » (la page couvre aussi les
    musiques, la vidéo, la carte et les polices).
11. **Marge sous la barre du haut** : la barre gagne 1 rem en bas une fois apparue, et le contenu
    des sections commence plus bas (environ 200 px du haut à 900 px d'écran, contre 160).
12. **Pictogramme de défilement sur téléphone** : sous le lecteur, au centre.

### En appliquant les consignes du 12/11/2026 (matin)

1. **Mode unique** : le jeu « En musique » du JSON validé.
2. **Barre du haut** : le logo reste visible en haut de page.
3. **Lecture automatique** : au premier geste si le navigateur la refuse.
4. **Fin d'un titre** : enchaînement sur le suivant.
5. **Pictogramme de la liste** : `assets/Pictos/Playlist.png` servait de masque (remplacé le soir
   même : rond du bouton lecture / pause et croche en SVG).
6. **Rouage** : retiré de la barre. Le panneau s'ouvre par `P` ou par le pied de page.
7. **Contenus sortis de la page** : leurs textes restent dans `docs/site/CONTENU-SITE.md`.

## À fournir ou à confirmer

1. **Droits** : extraits Berlioz (ARD Klassik, SWR) et Boulez (ARTE Concert) ; vidéo du visuel 3,
   qui est une capture d'écran de la captation de Prades publiée sur YouTube. À faire valider
   avant toute mise en ligne ; pour la vidéo, demander le fichier maître au Festival.
2. **Pages légales** : tous les passages en ambre (tableau plus haut), dont le capital d'Ortus Creatio.
3. **Traduction anglaise de la citation** de l'ouverture, à valider.
4. **Disques** : descriptifs, mot de Pierre Bleuse, liens.
5. **Presse** (les faux articles affichés ne sont qu'une maquette) : logos, verbatims de trois articles sur quatre, résumés, date de l'article de
   *Fanfare*.
6. **Agenda** : adresses de billetterie ou de salle (4 événements sur 18 en ont une).
7. **Téléchargement** : biographies, photographies, identité visuelle.
8. **Réseaux** : adresses YouTube et LinkedIn.
9. **Photographies** : fichiers en haute définition (992 px aujourd'hui, visibles en plein écran).
10. Les points déjà ouverts : mandat d'Odense, citation de *Fanfare*.

## À retirer ou à faire avant la mise en ligne

- Retirer le lien « Réglages de la nuée » du pied de page, la touche `P`, le HUD, les paramètres
  d'adresse de mise au point (`?panneau=1`, `?hud=1`, `?figure=`, `?t=`).
- Retirer la mention « Site en construction » du pied de page et `<meta name="robots" content="noindex">`.
- Héberger sur le site les polices (Google Fonts) et les bibliothèques (Lenis, GSAP), ou garder
  les mentions correspondantes de la page de confidentialité.
- Prévoir une version légère de la vidéo pour le téléphone (17 Mo aujourd'hui).
- Supprimer les faux articles de presse dans Pupitre (« Revue de presse », « Supprimer tous les
  faux articles »), publier, puis retirer `data/presse-exemples.js` et `assets/presse/exemples/`.
- `_archives/`, `_sources/`, `docs/` et `pupitre/` ne se déploient pas avec le site statique.

À garder : « Pause de l'animation » dans le pied de page.

## Vérifications du 13/11/2026 (Chrome headless, rendu Metal)

- 0 erreur en console et `SiteContenu.ecarts` vide sur l'accueil et `concerts.html`, en français
  et en anglais, à 1440 × 900 et à 390 × 844 ; aucun débordement horizontal.
- Fiche de l'agenda : titre, dates, lieu, orchestre, programme, « avec », lien, dans cet ordre.
- `concerts.html` : « debussy » trouve 5 concerts, « chamayou » 1, « mars 2027 » 2 ; pays Espagne,
  2 ; ville Paris triée par titre, 6 ; une recherche sans réponse l'annonce ; « Tout afficher »
  rend les 18 ; l'ancre d'un concert reste atteinte.
- Découpes du visuel 1 : 0 loin de la section, 1 sur elle, 0 une fois dépassée vers le bas, 1 au
  retour par le bas, 0 quittée par le haut.
- Téléphone : bloc de l'ouverture absent, barre entière, petit lecteur visible.
- Police Inria Serif chargée ; logo affiché à 155 × 56 px.

## Vérifications (12/11/2026, Chrome headless, rendu Metal)

- 0 erreur en console à 1440 × 900 et 390 × 844 (tactile), en français et en anglais ;
  `SiteContenu.ecarts` vide ; 60 images par seconde ; aucun débordement horizontal.
- Aimantation, avec de vrais crans de molette : 60 px, rien ; 120 px, la bio s'aligne ; une rafale
  de 1 600 px n'avance que d'une section ; défilement libre dans l'agenda ; retour sur le bas de
  l'agenda depuis le visuel 2 ; menu « Contact » sans être happé en route ; retour en haut ;
  aimantation coupée par le panneau. Sur téléphone : un glissé de 200 px aligne la bio, un glissé
  de 60 px reste libre.
- Visuels « lumière » : progression 0,78 à l'arrivée sur le visuel 1, 1 deux secondes et demie
  plus tard ; composition portrait sur téléphone ; mouvement réduit, un seul fondu.
- Vidéo du visuel 3 : relais de la musique en 1,5 s, horloge de la musique figée pendant la
  vidéo, reprise au même endroit à la sortie ; pause puis lecture pendant la vidéo ; pause de
  l'animation ; lecture automatique refusée (vidéo muette, puis son au premier clic).
- Brume à 0, plus de curseur.
- Retours du soir, à 1280, 1440 et 1920 px de large et sur téléphone : marges de 232 px à 1440 px,
  bandeau de la carte à 90 % sur toute la largeur, point de Salt Lake City, vidéo à 95 %, trois
  numéros au même format, adresses de contact sur une ligne, aucun débordement.
- Pages : `concerts.html` (18 lignes ancrées, ligne visée sous la barre), `presse.html`, les trois
  pages légales, dans les deux langues.
- Sans JavaScript : page lisible, images des visuels en pleine lumière, agenda en liste simple.

Non vérifié : Safari et Firefox, téléphones réels (en particulier le son de la vidéo sur iOS, où
le volume d'un média ne se règle pas : le fondu y sera une coupe).

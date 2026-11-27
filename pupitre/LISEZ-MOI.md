# Pupitre · LISEZ-MOI

Pupitre pilote la communication d'un artiste et administre son site. L'interface affiche
« Pupitre » et, en sous-titre, le nom lu dans les réglages. L'agence pilote, l'artiste consulte.

Du PHP 8 et du MySQL, avec du HTML, du CSS et du JS écrits à la main. Ni framework, ni Composer,
ni npm. Une installation sert un artiste. L'intervention du 27/11/2026 (cahier des charges :
`docs/cahier-des-charges-pupitre.md`) a réorganisé Pupitre en blocs et en collections, l'a rendu
multilingue et a étendu son usage de l'IA. La refonte de l'interface qui a suivi, le même jour, lui a
donné un design system, un nouveau menu (Site internet, Carrière, Pilotage de la communication), un
tableau de bord, un centre de notifications et les écrans du pilotage de la communication. Ce
document décrit l'état qui en résulte.

Quatre règles tiennent tout le reste :

- **Le site est statique et le reste.** Il lit ses contenus dans les fichiers de `data/`. Pupitre
  est la source de ces contenus : à chaque publication, il écrit ces fichiers et le HTML de repli
  des pages. Le site n'interroge jamais Pupitre et n'accède jamais à la base. Aucun fichier PHP
  hors de `pupitre/`.
- **Le site ne reçoit que ce qui est publié.** Une date connue, sous embargo ou annulée, un
  concert, un album, un article ou une ressource dépubliés, un média dont les droits ne sont pas
  confirmés, une traduction proposée par l'IA et pas encore validée : rien de cela ne sort dans un
  fichier du site.
- **L'IA propose, une personne valide.** Rien n'entre dans l'agenda, dans la revue de presse, sur
  la carte ou dans une traduction publiée sans validation.
- **Aucun script ne se saisit dans Pupitre.** Une animation est un module du site, choisi dans un
  registre.

## Sommaire des procédures

- [Composer un écran avec le design system](#composer-un-écran)
- [Ajouter une entrée au menu](#ajouter-une-entrée-au-menu)
- [Ajouter une langue](#ajouter-une-langue)
- [Administrer les blocs visuels](#administrer-les-blocs-visuels)
- [Ajouter un module d'animation](#ajouter-un-module-danimation)
- [Configurer l'API Anthropic](#configurer-lapi-anthropic)
- [Sauvegarder et restaurer les données](#sauvegarder-et-restaurer-les-données)
- [Avant la mise en production](#avant-la-mise-en-production)

## Installer en local (MAMP)

1. **Variables d'environnement.** Copier `pupitre/.env.exemple` en `pupitre/.env` et le remplir
   (tableau plus bas). `.env` n'est jamais commité.
2. **Base.** Créer une base `utf8mb4` et un utilisateur qui n'a de droits que sur elle, puis
   reporter leurs noms dans `.env`.
3. **Données.** Restaurer une sauvegarde de la base de référence, puis vérifier les migrations :

   ```
   /Applications/MAMP/bin/php/php8.3.14/bin/php pupitre/sql/restaurer.php <fichier.sql> --oui
   /Applications/MAMP/bin/php/php8.3.14/bin/php pupitre/sql/migrer.php --etat
   ```

   Le détail est dans `sql/installation/LISEZ-MOI.md`. `sql/depart.php` (reprise depuis les
   fichiers d'un site d'avant l'intervention) ne sert plus qu'à ce cas : il refuse de tourner sur
   un site déjà géré par blocs.
4. **Ouvrir** `http://localhost:8888/pierrebleuse/pupitre/`. Compte pilote absent ou mot de passe
   perdu : `php pupitre/sql/depart.php --pilote` donne un lien valable quinze minutes.

`php` n'est pas dans le PATH du poste : appeler celui de MAMP par son chemin complet.

### Variables d'environnement

| Variable | Rôle |
|---|---|
| `APP_URL` | adresse de Pupitre, sans barre finale. Sert aux liens envoyés par mail et à la ligne de commande |
| `APP_ENV` | `dev` (le détail des erreurs s'affiche) ou `prod` |
| `BD_HOTE`, `BD_PORT`, `BD_NOM`, `BD_UTILISATEUR`, `BD_MOT_DE_PASSE` | la base. Avec MAMP : `127.0.0.1`, port `8889` |
| `ARTISTE_NOM` | nom de l'artiste, pour la reprise. Vide : lu sur le logo de la page d'accueil du site |
| `PILOTE_EMAIL`, `PILOTE_NOM` | compte pilote créé par la reprise |
| `ANTHROPIC_API_KEY` | clé de l'API d'Anthropic : import des concerts, lecture d'un article, traduction, géolocalisation. Sans elle, tout reste faisable à la main |
| `ANTHROPIC_MODELE` | modèle. Vide : `claude-opus-5-5` |
| `MAILJET_CLE`, `MAILJET_SECRET`, `MAIL_EXPEDITEUR`, `MAIL_EXPEDITEUR_NOM` | envoi des mails par Mailjet. Sans clé, les messages sont écrits dans `stockage/journaux/mails.log` |
| `PHP_CLI`, `PYTHON3`, `FFMPEG` | chemins complets des outils. Vides : cherchés sur le poste |

Aucune de ces valeurs ne sort dans un journal ni dans un message d'erreur.

### Sans Mailjet

Sur le poste local, le lien de connexion de l'artiste et le lien de choix du mot de passe sont
écrits dans `pupitre/stockage/journaux/mails.log` au lieu d'être envoyés. C'est là qu'on les lit.

## Sauvegarder et restaurer les données

Les contenus du site vivent dans la base de Pupitre ; les fichiers de `data/` n'en sont que la
copie publiée. Trois choses sont à sauvegarder : la base, `pupitre/stockage/` (médias déposés,
extraits importés, sauvegardes de publication) et `pupitre/.env`.

**Sauvegarder la base.** Écran « Base de données » (pilote), bouton « Sauvegarder la base maintenant », ou :

```
php pupitre/sql/sauvegarder.php [motif]
```

Le fichier est écrit dans `pupitre/stockage/sauvegardes/pupitre-AAAAMMJJ-HHMMSS-motif.sql`. C'est
du SQL ordinaire, écrit par PHP (aucun outil du serveur n'est requis), lisible par `mysql` ou par
phpMyAdmin. Il contient tout : contenus, traductions, comptes, journal. Une sauvegarde est faite
d'office avant chaque migration et avant chaque retrait de migration.

**Restaurer.** En ligne de commande seulement, parce que l'opération remplace toute la base :

```
php pupitre/sql/restaurer.php pupitre/stockage/sauvegardes/<fichier>.sql --oui
```

Sans `--oui`, la commande dit ce qu'elle ferait et s'arrête. Après une restauration, l'écran « Le
site » montre les écarts entre la base restaurée et le site : publier les efface.

**Revenir sur une publication** n'est pas une restauration : l'écran « Le site », « Revenir à la
publication précédente », remet les fichiers du site tels qu'ils étaient ; la base ne change pas.

**Sauvegarder `stockage/`.** Le dossier est hors git. Le copier avec la base : une sauvegarde de la
base seule ne contient pas les fichiers de la médiathèque.

### Migrations

La structure évolue par des migrations numérotées, dans `sql/migrations/NNN-nom.php`. Chacune rend
un titre, de quoi monter et de quoi redescendre, et reprend les données existantes sans rien perdre.

```
php pupitre/sql/migrer.php            passe les migrations en attente (sauvegarde d'abord)
php pupitre/sql/migrer.php --etat     dit où en est la base
php pupitre/sql/migrer.php --defaire  retire la dernière migration (sauvegarde d'abord)
php pupitre/sql/structure.php         réécrit sql/installation/2-structure.sql (référence)
```

Tant qu'une migration attend, Pupitre n'ouvre que l'écran « Base de données », où le pilote la
passe d'un bouton. Les dix migrations en place :

| Migration | Ce qu'elle apporte | Données reprises |
|---|---|---|
| 001 | langues, traductions, libellés de l'interface | les textes anglais des colonnes `_en`, 91 libellés du site |
| 002 | blocs du site et leur visibilité | 15 blocs, tous affichés |
| 003 | médias : adresses externes, titres, droits, usages | les usages existants |
| 004 | playlist détaillée, blocs visuels | les 4 visuels, réordonnés ; les 3 titres du lecteur |
| 005 | concerts : publication, description, orchestres ; carte du monde | 18 concerts publiés ; 24 lignes de la carte, « à confirmer » ; le Tokyo Symphony Orchestra replacé à Tokyo |
| 006 | revue de presse : types, citations, relations, mise en avant | 4 articles réels ; 8 exemples de maquette, marqués comme tels |
| 007 | discographie détaillée, distinctions, fonctions, collaborations | 4 albums, leurs liens ; 2 distinctions du disque Ligeti ; 3 fonctions ; 3 collaborations en brouillon |
| 008 | import des concerts : annulation annoncée | aucune |
| 009 | anglais par défaut, téléchargements, cadrage de « Restons en contact » | les 4 ressources, publiées |
| 010 | pilotage de la communication (actions, concerts associés), niveaux des alertes | aucune ; la place gardée `publications_reseaux`, vide, est retirée |

## Tâches de fond et cron

Une table de tâches (`taches`), exécutée par `cron/taches.php` : lecture d'un extrait de l'agence,
lecture d'un article de presse, propositions de traduction, analyse d'une bande-son. Le même
script recalcule les alertes une fois par nuit et efface les lectures d'articles de plus de deux
jours. Une requête web ne porte jamais un travail long (MAMP coupe à trente secondes).

```
0-59/5 * * * * /chemin/vers/php /chemin/vers/pupitre/cron/taches.php >/dev/null 2>&1
php pupitre/cron/taches.php --nuit     force le recalcul des alertes
```

Sur le poste local, pas besoin de cron : déposer un extrait ou demander une analyse démarre le
script en arrière-plan, et le bouton « Lancer maintenant » le relance.

## Structure

```
pupitre/
  index.php              point d'entrée de toutes les pages : index.php?page=…
  connexion.php, deconnexion.php
  media.php              sert un fichier de la médiathèque à une personne connectée
  pages/                 un dossier par module : blocs, agenda, import, contenus (blocs visuels,
                         playlist, orchestres et carte, listes), discographie, parcours, presse,
                         langues, communication, annuaire, mediatheque, site, reglages, journal,
                         artiste ; tableau-de-bord.php, notifications.php, recherche.php
  ajax/                  tache.php (avancement d'une tâche de fond), recherche.php (barre du haut)
  inclus/                amorce, config, base, connexion, droits, journal, mail, reglages ;
                         navigation (menu, fil d'Ariane, recherche), icones (pictogrammes),
                         notifications ; langues (multilingue), blocs, visuels, animations
                         (registre), medias ; agenda, orchestres, discographie, parcours, presse,
                         annuaire, contenus, communication ; publication, taches, alertes,
                         sauvegarde, migrations ;
                         ia (l'appel), ia_services (lecture d'article, traduction, géolocalisation), import
  inclus/site/           l'adaptateur du site : export.php (fichiers de data/), repli.php (HTML statique)
  inclus/consignes/      les consignes données à l'IA, une par service
  gabarits/              le cadre de page (entete.php, pied.php) et les composants (composants.php)
  cron/taches.php
  sql/migrations/        les migrations numérotées
  sql/migrer.php, sauvegarder.php, restaurer.php, structure.php
  sql/schema.sql         structure de la version 1, sur laquelle les migrations s'appliquent
  sql/installation/      création de la base, structure de référence, marche à suivre
  tests/                 contrôles et essais (voir « Vérifier »)
  stockage/              imports, médias déposés, sauvegardes (base, publications), analyses, journaux
  ressources/            css/pupitre.css (le design system), js/pupitre.js, polices/ (Poppins)
```

`inclus/`, `sql/`, `stockage/`, `cron/`, `tests/`, `gabarits/` et `pages/` sont interdits au web
par `.htaccess`. `stockage/` est hors git. Tout Pupitre porte `noindex`.

C'est dans `inclus/site/` que Pupitre connaît la forme du site. Si le site change de gabarit, ce
sont ces deux fichiers qu'on adapte.

## Interface et design system

L'interface est contemporaine et sobre : un fond clair, des cartes blanches aux angles doux, un
indigo pour l'action, Poppins pour le texte. Elle tient en quatre fichiers, sans bibliothèque :

| Fichier | Ce qu'il porte |
|---|---|
| `ressources/css/pupitre.css` | les jetons (variables CSS) et tous les composants |
| `gabarits/composants.php` | les fonctions qui écrivent un composant : champs, tuiles, indicateurs, états vides… |
| `inclus/icones.php` | les pictogrammes, en SVG dans la page : `icone('concerts')` |
| `ressources/js/pupitre.js` | les comportements, par attributs `data-` ; aucun script dans les pages |

« Administration › Bibliothèque d'interface » montre le tout sur pièces : couleurs, typographie,
boutons, étiquettes, messages, champs, tuiles, tableaux, notifications, pictogrammes, chacun avec
son balisage. C'est la référence à ouvrir avant d'écrire un écran.

**Trois étages de jetons.** La palette (`--indigo-600`, `--gris-200`, `--e-4`, `--r-lg`) ; les
rôles, qui disent à quoi sert une valeur (`--primaire`, `--surface`, `--texte-doux`, `--trait`) ;
les réglages propres à un composant (`--tuile-fond`). Un composant passe toujours par un rôle :
changer l'identité de l'interface, c'est changer les deux premiers étages, en tête de la feuille
de style, et rien d'autre.

**Rien ne vient d'ailleurs.** La police (Poppins, licence OFL) est dans `ressources/polices/`, les
pictogrammes (tracés de Lucide, licence ISC) sont recopiés dans `inclus/icones.php` : aucune
requête ne part vers un service tiers, et la politique de sécurité du contenu reste `'self'`.

**Accessibilité.** Tenue au niveau AA des règles WCAG 2.2, et vérifiée par `tests/interface.py` :
contraste des textes (4,5:1), bord des champs perceptible (3:1), un nom accessible pour chaque
lien, bouton et champ, un seul titre de premier niveau, des cibles d'au moins 24 px, aucun
débordement à 320 px de large, le mouvement réduit respecté. Une couleur d'état ne dit jamais rien
seule : le mot la dit. Le menu se replie en tiroir sous 1024 px ; fermé, il sort du parcours au
clavier.

**Écrans étroits.** Les grilles passent sur une colonne, les indicateurs et les vignettes par deux
sur un téléphone. Un tableau à l'étroit défile dans son cadre (`defile`) plutôt que d'écraser ses
colonnes (`tableau--large` à partir de six colonnes). Les listes du quotidien, agenda, tableau de
bord éditorial, publications et lettres, ne défilent pas de côté : leur cadre porte `defile--pile`
(`defile--pile-large` pour l'agenda), et chaque ligne s'empile en fiche dès que le cadre n'a plus
la largeur des colonnes. C'est le cadre qui est mesuré (requête de conteneur), pas la fenêtre :
dans une fenêtre de 1280 px, l'agenda est en fiches menu ouvert, en tableau menu réduit (il est un
tableau à partir de 1332 px de fenêtre menu ouvert, de 1128 px menu réduit). Le balisage de ces tableaux porte les rôles ARIA (`table`, `row`, `cell`…), pour
qu'ils restent lus comme des tableaux. Un navigateur sans requêtes de conteneur garde le tableau,
qui défile.

### Composer un écran

1. Un titre : `<div class="titre"><h1>…</h1><span class="titre__suite">…</span><div class="titre__actions">…</div></div>`.
   Un seul bouton plein (`bouton bouton--plein`) : l'action principale.
2. Du contenu dans des cartes (`carte`), en une colonne ou en deux (`deux`). Une liste : un
   tableau (`tableau`, dans un `div.defile`) ou une liste (`items`, `liste-dates`).
3. Des champs par les fonctions de `gabarits/composants.php` (`champ`, `champ_bi`, `champ_choix`,
   `champ_texte`, `champ_case`) : le libellé, l'aide et l'identifiant unique sont posés pour vous.
4. Quand il n'y a rien à montrer : `etat_vide()`, qui dit pourquoi et quoi faire. Jamais une
   donnée d'exemple.
5. Aucune couleur en dur, aucun style propre à la page : un besoin que rien ne couvre s'ajoute à
   la feuille de style, puis à la bibliothèque d'interface.
6. Déclarer la page dans `index.php` (`PAGES`, avec son droit), puis au menu.

### Ajouter une entrée au menu

Le menu, le fil d'Ariane et la recherche des écrans se lisent dans `inclus/navigation.php`
(`NAVIGATION`). Une entrée : `[libellé, page, paramètres, droit, pages qui l'allument, pictogramme]`.
Les « pages qui l'allument » disent aussi où l'on est : `concert` allume « Concerts ». Une ligne
`['@' => 'Données']` pose un intertitre dans une section. Ajouter un pictogramme : copier
l'intérieur du `<svg>` de Lucide dans `ICONES`, sous un nom français.

| Section du menu | Ce qu'elle réunit |
|---|---|
| Tableau de bord | l'accueil, le centre de notifications, la recherche |
| Site internet | textes du site, blocs visuels ; données (concerts, presse, discographie, distinctions, playlist, téléchargements, contacts) ; mise en ligne (langues, apparence, publication) |
| Carrière | parcours, orchestres du monde, carte interactive, annuaire |
| Pilotage de la communication | tableau de bord éditorial, Social Media, Newsletter |
| Médiathèque | les fichiers, communs au site et à la communication |
| Administration | comptes, réglages, base de données, journal, bibliothèque d'interface |

La section de la page affichée est ouverte ; celles que l'on ouvre à la main le restent d'une page
à l'autre, et le menu se réduit à ses pictogrammes (deux témoins de préférence, sans donnée
personnelle : `pupitre_menu`, `pupitre_lateral`). La vue de l'artiste est dans le menu du compte.

## Tableau de bord et notifications

Le **tableau de bord** est l'accueil : quatre tuiles (points de vigilance, actions à réaliser,
prochain concert, état du site), puis les prochains concerts, les actions à réaliser (ce qui
attend une validation, puis les alertes à traiter), les dernières actualisations du site
(publications, contenus modifiés), les points de vigilance, les prochaines échéances (fins
d'embargo, actions de communication datées), les dernières publications et des raccourcis. Tout
chiffre est lu dans la base ; un bloc sans donnée le dit.

Le **centre de notifications** (la cloche de la barre du haut, et l'écran « Notifications »)
réunit les alertes ouvertes et ce qui attend une validation, sur trois niveaux :

| Niveau | Sens | Exemples |
|---|---|---|
| Alerte prioritaire | une échéance est passée, ou quelque chose ne fonctionne pas | embargo levé, concert à moins de 21 jours dont la fiche est incomplète, publication dont la date est passée, tâche de fond interrompue, site introuvable |
| Action à traiter | demande une intervention, sans urgence | extrait de l'agence à valider, note de l'artiste, concerts annoncés sans communication, traductions à valider, écarts à publier |
| Information | à savoir, rien ne presse | contenu à actualiser, embargo qui se lève dans la semaine, extrait ancien |

Le compteur de la cloche ne compte que les deux premiers niveaux. Le niveau est posé au calcul de
l'alerte (`alertes_calculer()`, dernier argument de `$ajouter`) et rangé avec elle
(`alertes.niveau`). Une alerte se traite, se reporte d'une semaine ou s'ignore.

La **recherche** de la barre du haut (touche `/`) cherche parmi les écrans, les concerts, les
articles, les albums, l'annuaire et les actions de communication, selon les droits du compte.

## Pilotage de la communication

Cette partie centralise les actions de communication. Elle est **préparée** : son modèle, ses
écrans et ses composants sont en place, et tout s'y saisit à la main. Aucune plateforme n'est
connectée : Pupitre ne publie rien sur les réseaux, n'envoie aucune lettre, et ne connaît d'une
publication que ce que l'on y note. Les connexions feront l'objet d'une seconde phase.

Une **action** (table `communications`) est une publication sur un réseau ou une lettre : un canal
(Instagram, Facebook, YouTube, LinkedIn, autre réseau, lettre), un intitulé, un texte, un statut
(à préparer, programmée, publiée ou envoyée), une date prévue, une date réelle, l'adresse de la
publication, des notes internes, et les concerts qu'elle concerne (`communications_concerts` : une
action pour plusieurs concerts, un concert pour plusieurs actions).

| Écran | Ce qu'il montre |
|---|---|
| Tableau de bord éditorial | les indicateurs ; chaque concert à venir avec ses actions, et ceux pour lesquels rien n'est prévu ; le calendrier éditorial du mois (concerts, actions, fins d'embargo) |
| Social Media | les publications à préparer, programmées, publiées ; les comptes de l'artiste |
| Newsletter | les lettres à venir et envoyées ; l'état des inscriptions (le formulaire du site est inactif) |
| Fiche d'une action | sa saisie ; depuis « Prévoir », elle arrive rattachée au concert |

La fiche d'un concert liste sa communication, et le tableau de bord général en montre les
échéances et les dernières publications. Une règle vient de l'agenda : **on ne communique que sur
un concert annoncé par son organisateur**. Un concert « connu » ou « sous embargo » n'est pas
compté « sans communication » : il ne peut pas encore en avoir.

Deux alertes en découlent : les concerts annoncés dans les 30 jours sans aucune action
(`DELAI_COMMUNICATION`), et les actions à préparer dont la date tombe dans les 7 jours
(`DELAI_PREPARATION`), prioritaires quand la date est passée.

Pour la seconde phase, la table attend déjà une action venue d'une plateforme : `origine` (`main`
aujourd'hui) et `reference_externe` (son identifiant là-bas). La table `lettre_inscrits`, place
gardée depuis la version 1, attend les inscriptions.

## Blocs et collections

Le menu suit cette distinction.

**Un bloc** est une section de la page d'accueil : hero, édito, trois blocs visuels, agenda,
discographie, parcours (fonctions, grands débuts et collaborations, carte du monde), presse,
téléchargement, « Restons en contact », contact, pied de page. « Blocs et visibilité » les liste
dans l'ordre de la page. Chaque bloc s'affiche ou se masque d'un geste ; masquer ne supprime rien,
le bloc n'occupe plus de place, quitte le menu du site et ne charge plus ses médias. L'écran d'un
bloc réunit ses textes, ses intitulés et boutons, et les liens vers ce qu'il affiche. « Général »
porte ce qui vaut pour tout le site (titre, description pour les moteurs de recherche).

**Une collection** est une liste d'objets que les blocs affichent : concerts, orchestres,
discographie, distinctions, parcours (fonctions, collaborations), revue de presse, playlist,
téléchargements, contacts et réseaux, médiathèque, annuaire. Les objets se relient entre eux :

| Relation | Où elle se règle | Ce qu'elle produit sur le site |
|---|---|---|
| concert ↔ orchestres | fiche du concert | reconnaissance de l'orchestre ; signalement s'il n'est pas sur la carte |
| orchestre ↔ carte du monde | « Orchestres » | un point sur la carte, dans la ville de l'orchestre |
| article ↔ concerts | fiche de l'article | « Revue de presse de ce concert » sur un concert passé |
| article ↔ albums | fiche de l'article, ou de l'album | « La critique » dans le panneau de l'album |
| album ↔ distinctions | « Distinctions », ou fiche de l'album | la distinction sur la vignette et dans le panneau |
| collaboration ↔ orchestre, concert | « Parcours » | une ligne de « Grands débuts et collaborations » |
| tout objet ↔ médias | l'objet (visuels), les blocs visuels | les visuels, sans dupliquer le fichier |

Un média et ses usages sont deux choses (`medias`, `medias_usages`) : le même fichier sert à
plusieurs endroits, et sa fiche dit où. Un média employé ne se supprime pas sans avertissement.
Un média peut être un fichier déposé ou une adresse externe (image, son, vidéo, YouTube) : rien
n'est alors téléchargé.

**Configuration** : apparence (couleur de fond du site : hexadécimal, RVB ou CMJN, avec retour à
la couleur d'origine), langues et traductions, réglages, base de données, comptes.

## Langues et traductions

Les contenus sont rédigés dans la **langue de référence** (le français). Son texte vit dans les
tables des objets (colonnes `champ_fr`). Toutes les autres langues vivent dans la table
`traductions` : une ligne par objet, champ et langue, avec son statut. Ce qui n'est pas du texte
(dates, coordonnées, liens, relations) n'existe qu'une fois. `TRADUISIBLES`, dans
`inclus/langues.php`, dit table par table quels champs se traduisent.

| Statut | Sens | Sur le site |
|---|---|---|
| validée | saisie ou relue par une personne | oui |
| proposée | proposée par l'IA, pas encore relue | non : le site garde le texte de référence |
| à revoir | validée, mais le texte de référence a changé depuis | oui, et elle est signalée |
| sans traduction | rien n'est saisi | le site affiche le texte de référence |

La **langue de la première visite** est l'anglais. Le site s'ouvre dans cette langue, sauf si
l'adresse en demande une autre (`?lang=fr`) ou si le visiteur en a déjà choisi une, que son
navigateur retient. La langue du navigateur n'est pas consultée. Le HTML statique des pages
(celui que lisent un moteur de recherche et un visiteur sans JavaScript) est écrit dans la langue
de la première visite à chaque publication : `<html lang>`, titre, description, menus, intitulés,
contenus de repli, dates.

L'écran « Langues et traductions » a trois onglets : les langues ; toutes les traductions, filtrées
par langue, par état et par partie du site ; les libellés de l'interface du site.

### Ajouter une langue

1. « Langues et traductions », onglet « Langues », « Ajouter une langue » : son code (`de`), son nom
   dans cette langue (`Deutsch`), son nom en français, le format de ses dates (`de-DE`). Elle est
   créée **inactive** : elle se prépare sans paraître sur le site. Aucune colonne n'est ajoutée à
   la base.
2. Onglet « Traductions », cette langue : traduire. Chaque formulaire de Pupitre montre aussi, sous
   chaque champ traduisible, un champ par langue. « Proposer les traductions manquantes » demande
   une proposition à l'IA pour tout ce qui manque ; chaque proposition se relit, se corrige et se
   valide (case « valider cette proposition », ou « Tout cocher »).
3. Traduire les pages rédigées à la main : `mentions-legales.html`, `confidentialite.html` et
   `credits.html` portent un bloc par langue (`<div class="legal" data-langue-bloc="de" lang="de">`).
   Sans bloc dans cette langue, la page montre celui de la langue de la première visite.
4. Onglet « Langues » : cocher « active », enregistrer. Pour en faire la langue de la première
   visite : « par défaut ».
5. Publier (« Le site »). Le site reçoit la langue dans sa bascule (FR | EN | DE), ses libellés et
   ses contenus ; les dates suivent son format.

Retirer une langue : la décocher (elle quitte le site, ses traductions restent), ou la supprimer
avec ses traductions. La langue de référence et la langue par défaut ne se suppriment pas.


## Blocs visuels

### Administrer les blocs visuels

Écran « Blocs visuels ». Quatre emplacements portent des médias : les trois blocs visuels de la
page (pleine page) et le fond de « Restons en contact » (une photographie derrière le bloc de texte).

Chaque emplacement porte une **liste d'éléments**. Un élément est un média de la médiathèque et la
manière de le montrer :

| Présentation | Ce que le site montre | Réglages |
|---|---|---|
| Image fixe | la photographie | cadrage (horizontal et vertical, en %), cadrage sur téléphone, texte alternatif, crédit |
| Image animée | la photographie, animée par un module du registre | les mêmes, plus le module et ses réglages (préréglage) |
| Vidéo | une vidéo : fichier déposé, adresse directe ou lien YouTube | titre, description, image d'attente, « joue avec son son », crédit |

**Ce que le site affiche se déduit de la liste : le premier élément publié décide.** Si c'est une
image, elle seule est montrée. Si c'est une vidéo, toutes les vidéos publiées du bloc se suivent,
et le visiteur passe de l'une à l'autre (flèches, position « 2 / 3 », titre et description). Les
autres éléments restent en réserve : pour changer ce que montre un bloc, on réordonne (↑ ↓) ou on
publie et dépublie, sans rien ressaisir.

- **Ajouter un élément** : en bas de chaque bloc, choisir un média de la médiathèque, déposer un
  fichier, ou donner une adresse (image, vidéo, lien YouTube). Une adresse externe n'est pas
  téléchargée.
- **Déplacer un élément** vers un autre bloc : liste « Déplacer vers un autre bloc » de l'élément,
  puis « Enregistrer cet élément ». Son média, ses textes et ses réglages le suivent.
- **Remplacer le média** d'un élément : liste « Média ». Les réglages restent. Pour passer d'une
  image à une vidéo, on ajoute un nouvel élément.
- **Vidéo avec son** : cocher « La vidéo joue avec son son ». Elle prend alors le relais de la
  musique quand elle démarre, et la musique reprend à sa fin. « Analyser la bande-son » calcule ce
  que suit la nuée (fichier déposé seulement ; outils requis : Python et ffmpeg).
- **YouTube** : la vidéo n'est chargée qu'après l'accord du visiteur (aucun témoin avant).
- **Masquer un bloc visuel** : « Blocs et visibilité ». Un bloc visuel sans élément publié ne
  s'affiche pas non plus.

Le vol d'étourneaux ne se montre pas sur un bloc visuel : le média couvre la section, la nuée
continue derrière et reparaît à la section suivante. C'est une règle du site, pas un réglage.

Les visuels d'un album et d'un article se règlent sur leur fiche, avec la même médiathèque.

### Ajouter un module d'animation

Une animation est du code du site. Pupitre n'en choisit que l'identifiant et les réglages : aucun
script ne se saisit dans Pupitre, et une valeur hors du registre est refusée.

1. **Écrire le module** dans un fichier du site, par exemple `js/mon-animation.js`. Il expose de
   quoi créer une scène pour une section : `creer(section, options)` rend un objet avec `init()`
   (une promesse), `mesurer()`, `poser()`, `maj(y, dt)`, `respirer(t)` et les propriétés que lit le
   moteur (`section`, `nom`, `pret`, `p`, `cible`, `visible`, `cle`). Le contrat est écrit en tête
   de `js/animations.js`. `options` porte les réglages venus de Pupitre, plus `reduit` (mouvement
   réduit demandé) et `focus` (le point de cadrage). `creer()` rend `null` s'il ne peut pas servir :
   l'image reste alors seule.
2. **Le charger** dans `index.html`, avant `js/animations.js`
   (`<script src="js/mon-animation.js?v=…" defer></script>`, comme `js/lumiere.js`).
3. **Le déclarer au registre du site**, à la fin de `js/animations.js` :

   ```js
   SiteAnimations.declarer('mon-animation', {
     creer: function (section, options) { return MonAnimation.creer(section, options); }
   });
   ```

4. **Le déclarer au registre de Pupitre**, sous le même identifiant, dans `inclus/animations.php`
   (constante `ANIMATIONS`) : son nom, sa description, et ses réglages, chacun avec la liste fermée
   de ses valeurs et sa valeur par défaut.
5. Dans « Blocs visuels », l'élément en « Image animée » propose alors ce module et ses réglages.
   Publier, puis « Renouveler la version des fichiers » si seul le code du site a changé.

Le module existant, `lumiere` (découpes de lumière, `js/lumiere.js`), est déclaré ainsi, sans que
son algorithme ait été modifié. Ses préréglages `bleuse-1` et `bleuse-3` sont dessinés pour ces
deux photographies ; `generique` suit le point de cadrage de toute autre image.

## Rôles

Les droits sont dans un seul fichier, `inclus/droits.php`, et vérifiés côté serveur à chaque page
et à chaque action.

| Rôle | Qui | Droits |
|---|---|---|
| Pilote | Carrel, direction du projet | tout : réglages, comptes, validations, publication |
| Éditeur | équipe Carrel | agenda, fiches, annuaire, contenus, médias, validation des imports, pilotage de la communication. Ni réglages, ni comptes, ni publication |
| Artiste | l'artiste | sa vue, en lecture. Une action : laisser une note sur un concert |

L'agence entre par adresse et mot de passe (douze caractères au moins). L'artiste entre par un
lien à usage unique reçu par mail, valable quinze minutes. Cinq échecs en un quart d'heure
bloquent l'adresse et le poste pendant ce quart d'heure.

## Agenda

Le statut se règle par représentation, avec une action pour tout le concert.

| Statut | Sens | Sur le site |
|---|---|---|
| Connue | dans l'extrait de l'agence, pas encore annoncée par l'organisateur | non |
| Sous embargo | annonce prévue à une date fixe (obligatoire) | non, alerte à l'échéance |
| Annoncée | annoncée par l'organisateur, lien source obligatoire | à la prochaine publication |
| Publiée | présente dans la dernière publication | oui |
| Annulée ou reportée | raison obligatoire | retirée à la prochaine publication |

« Publiée » est posé par la publication, jamais à la main. Les dates passées et publiées restent
dans l'agenda du site. Indépendamment du statut de ses dates, un concert entier se **dépublie**
(il quitte le site, rien n'est supprimé) et se republie d'un bouton. « À la une » le met en
avant ; sa description et sa revue de presse nourrissent sa fiche, qui s'ouvre sur
`concerts.html` (adresse `concerts.html#c-<identifiant>`) quand le concert n'a pas de page chez
l'organisateur. Les dates et les heures sont celles de la salle, jamais converties ; le
fuseau de la salle ne sert qu'à afficher l'heure de l'artiste sur le tableau de bord.

## Publication

L'écran « Le site » montre la dernière publication et les écarts entre Pupitre et le site, fichier
par fichier, avec les lignes qui changent. « Publier » :

1. sauvegarde les fichiers qui vont être remplacés dans `stockage/publications/<date>/` ;
2. copie dans `assets/pupitre/` les médias employés qui n'y sont pas encore ;
3. écrit les fichiers de `data/`, les zones de repli des pages entre leurs repères et le numéro
   de version `?v=` des scripts. Chaque fichier est écrit à côté, puis renommé. Si une écriture
   échoue, tout est remis en place ;
4. passe les représentations annoncées à « publiée ».

« Revenir à la publication précédente » remet les fichiers sauvegardés et rend leur statut
« annoncée » aux représentations que cette publication avait publiées. Les contenus de Pupitre ne
changent pas : les écarts réapparaissent. Un média copié reste dans `assets/pupitre/`.

Un fichier dont les données sont les mêmes mais l'écriture différente (retraits, ordre des clés)
n'est pas compté comme un écart ; il est réécrit à la publication suivante. C'est le cas de
`data/agenda.js` et de `data/agenda.json` à la première publication.

Le HTML statique des pages est écrit dans la langue de la première visite (voir « Langues et
traductions »). Quand aucun contenu n'a changé mais qu'une feuille de style ou un script du site a
été modifié à la main, « Renouveler la version des fichiers » donne aux pages un nouveau numéro
`?v=`, pour que les navigateurs rechargent ces fichiers.

La mise en ligne (envoi vers le serveur, git) reste manuelle.

## Import de l'extrait de l'agence

1. Dépôt d'un PDF, d'un tableur (`.xlsx`, `.csv`) ou d'un texte collé. Le fichier reste dans
   `stockage/imports/`.
2. Lecture en tâche de fond. L'IA rend des engagements dans un JSON que le code contrôle. Un
   échec de contrôle donne une seule relance, puis une erreur lisible.
3. Comparaison avec l'agenda, par le code : nouvelles dates, dates modifiées, programmes changés,
   annulations annoncées par le document, dates de l'agenda absentes du document. Rapprochement
   sur la date et l'ensemble : un concert déjà présent n'est jamais créé une seconde fois. Un cas
   douteux (même jour avec un autre ensemble, même ensemble à trois jours près) est présenté à la
   main.
4. Contrôle : lieu, ville ou heure manquants, date hors saison, date passée, doublon probable.
5. Validation ligne par ligne ou en bloc, avec correction possible et vue des différences. Une
   date validée entre dans l'agenda au statut « connue ». L'orchestre est reconnu parmi les fiches
   de l'annuaire ; s'il n'est pas sur la carte du monde, la fiche du concert et le tableau de bord
   le signalent.
6. « Proposer les traductions des concerts » : les traductions manquantes sont proposées par
   l'IA, à valider dans « Langues et traductions ».

Deux cas se distinguent. Une **annulation annoncée** (le document dit « annulé ») : valider marque
la date « annulée ». Une **date absente** du document : rien ne l'annule ni ne la dépublie
d'office, un extrait pouvant être partiel ; la refuser la garde, la valider l'annule. Rien ne
change dans l'agenda sans validation, et tout reste faisable à la main.

Les consignes sont dans `inclus/consignes/lecture-extrait.md`. Lire un extrait déjà déposé, depuis
le terminal, sans afficher son contenu :

```
php pupitre/tests/extrait.php nom-du-fichier.pdf
```

## Revue de presse : importer un article par son adresse

« Revue de presse », « Nouvel article », carte « Préremplir à partir de l'article ».

1. On donne l'adresse de l'article. Pupitre lit la page lui-même, comme un lecteur sans abonnement
   (`page_recuperer()`, dans `inclus/ia_services.php`) : `http` et `https` seulement, jamais une
   machine du réseau interne, redirections recontrôlées une à une, taille et durée bornées. Aucun
   accès réservé n'est contourné.
2. Sans IA, le programme relève ce que la page déclare : titre, média, auteur, date, langue,
   adresse canonique, image (balises Open Graph, données structurées, balises meta). Le média est
   cherché dans le référentiel, par son nom puis par son site.
3. L'IA structure le reste : type d'article, chapeau et résumé dans chaque langue du site,
   citations, concerts et albums concernés, ce qui reste incertain.
4. Le programme recontrôle : **une citation n'est gardée que si elle se retrouve mot pour mot dans
   le texte de l'article** ; une relation n'est gardée que vers un concert ou un album qui existe.
5. Le formulaire de l'article s'ouvre prérempli. Rien n'est enregistré tant qu'il n'est pas relu
   et créé : on choisit la citation principale, on garde ou on retire les autres, on coche
   « formulation vérifiée », on corrige les traductions. Un doublon (même adresse, même adresse
   canonique, ou même média avec le même titre ou la même date) est signalé dès la lecture.

Si la page ne se laisse pas lire (accès réservé, page écrite par un script), on colle le texte de
l'article : l'analyse porte alors sur ce texte. L'image que propose la page n'est jamais
téléchargée ; gardée par son adresse si on le demande, elle entre « droits à confirmer » et ne
paraît qu'une fois ses droits validés sur sa fiche. Une citation reste dans sa langue et dans sa
formulation ; sa traduction est un champ à part, que le site présente comme une traduction.

## Intelligence artificielle

### Configurer l'API Anthropic

1. Créer une clé dans la console d'Anthropic, pour un espace de travail réservé à Pupitre.
2. L'écrire dans `pupitre/.env`, sur le serveur seulement :

   ```
   ANTHROPIC_API_KEY=…
   ANTHROPIC_MODELE=
   ```

   `ANTHROPIC_MODELE` vide : `claude-opus-5-5`. `.env` n'est pas commité et n'est pas servi par le
   web. La clé ne sort ni dans une page, ni dans un journal, ni dans un message d'erreur ; elle
   n'atteint jamais le navigateur.
3. Vérifier : « Réglages » affiche « IA : clé renseignée » et le modèle. Le bas du « Journal »
   (pilote) liste chaque appel : objet, modèle, jetons, durée, issue, jamais le contenu.
4. Installer le cron des tâches de fond sur le serveur (plus haut) : la lecture d'un extrait ou
   d'un article et les traductions par lots y tournent.

Sans clé, ou si l'API ne répond pas, chaque écran le dit et tout reste faisable à la main : les
formulaires ne dépendent pas de l'IA.

### Un service commun

`inclus/ia.php` est le seul endroit qui parle à l'API (`ia_appeler()`), en cURL : réponse
contrainte par un schéma JSON, lue en flux, trois essais en cas d'erreur passagère, repli sur un
autre modèle si les filtres de sécurité refusent la demande. `ia_structure()`
(`inclus/ia_services.php`) y ajoute le contrôle de la réponse par le programme et une seule
relance. Les consignes sont des fichiers, un par service, dans `inclus/consignes/`.

| Service | Où | Ce qui est fait sans IA d'abord | Ce que l'IA propose |
|---|---|---|---|
| Import des concerts | « Import des concerts » | comparaison à l'agenda, contrôles, reconnaissance des orchestres | les engagements lus dans le document |
| Lecture d'un article | « Nouvel article » | métadonnées de la page, média, doublons, contrôle des citations | résumé, citations, type, relations |
| Traduction | « Langues et traductions », import des concerts | reprise d'une traduction déjà validée pour le même texte ; textes déjà jugés sans traduction propre | les traductions manquantes, à valider |
| Géolocalisation | « Orchestres » | coordonnées d'une ville déjà connue de Pupitre | les coordonnées d'une ville inconnue, à vérifier sur la carte |

Règles communes : ce qui vient d'un document ou d'un site est une donnée à analyser, jamais une
consigne ; une proposition préremplit un formulaire ou attend une validation ; une traduction
validée n'est jamais remplacée. Les paroles de l'artiste (sa citation, son mot sur un album) et
les citations de presse ne sont pas traduites par lots : elles se demandent une à une, ou se
traduisent à la main.

## Alertes

Chacune a un niveau (voir « Tableau de bord et notifications »). Recalculées après chaque
modification et chaque nuit : anomalie technique (tâche de fond interrompue, site introuvable) ;
concerts annoncés sans communication ; action de communication à préparer ou en retard ; embargo
qui se lève dans la semaine ; représentation à venir sans salle, ville
ou heure ; représentation annoncée sans lien source ; concert à moins de 21 jours dont la fiche
est incomplète ; embargo arrivé à échéance ; date passée restée « connue » ; doublon probable
(agenda, annuaire) ; orchestre d'un concert absent de la carte du monde ; album sans lien d'écoute
ni de commande ; article sans citation ; citation à vérifier ; exemples de maquette encore dans la
revue de presse ; écart entre Pupitre et le site ; dernier extrait importé il y a plus de
30 jours ; contenu du site resté vide. « Traiter » mène à l'écran où corriger, « Reporter » cache
l'alerte une semaine, « Ignorer » la cache tant que son sujet ne change pas.

## Vérifier

| Commande | Ce qu'elle prouve | Base |
|---|---|---|
| `sh outils/syntaxe.sh` | aucun script du site n'a d'erreur de syntaxe | non |
| `php pupitre/tests/repli.php` | le HTML statique régénéré est identique aux pages, à l'octet près | non |
| `php pupitre/tests/equivalence.php` | une publication ne changerait rien au contenu du site | oui |
| `php pupitre/tests/annonce.php` | le site ne reçoit que les représentations annoncées ; règles des statuts | oui, rien n'est écrit |
| `php pupitre/tests/import.php` | contrôle du JSON, comparaison, annulation annoncée, contrôle, validation, confidentialité des journaux, sur un extrait fictif et sans appel à l'IA | oui, rien n'est écrit |
| `php pupitre/tests/article.php` | lecture d'un article : refus des adresses du réseau interne, métadonnées d'une page, citations retrouvées mot pour mot, doublons ; sans réseau et sans appel à l'IA | oui, rien n'est écrit |
| `php pupitre/sql/migrer.php --etat` | toutes les migrations sont passées | oui |
| `python3 pupitre/tests/parcours.py` | dans Chrome : chaque écran, parcours complet (citation, album, concert annoncé, publication, vérification sur le site, retour en arrière), droits des trois rôles, vue de l'artiste à 390 px et au clavier | oui, remise en état à la fin |
| `python3 pupitre/tests/interface.py` | dans Chrome : le cadre (menu, tiroir, notifications, compte, recherche), le pilotage de la communication de bout en bout, les tableaux de 320 à 1440 px (aucune colonne écrasée, fiches empilées à l'étroit), l'accessibilité de trente écrans à 1440 px et à 320 px | oui, remise en état à la fin |
| `python3 outils/temoin.py capturer dossier`, puis `comparer` | le rendu du site, section par section, face à un témoin | non |

`tests/annonce.php` et `tests/import.php` travaillent dans une transaction annulée à la fin.
`tests/parcours.py` crée trois comptes d'essai en `.test`, publie, revient en arrière, vérifie avec
git que le site n'a pas changé, et retire ses comptes. Les deux essais de navigateur retirent ce
qu'ils ont créé, mais le journal en garde la trace (note, concert et actions « d'essai ») : il
n'est jamais retouché, même par un essai. Le site s'ouvrant en anglais, les essais
qui lisent du français le demandent par `?lang=fr`. Le témoin d'avant l'intervention est dans
`docs/pupitre/captures/reference-avant-intervention-20261127/`.

## Choix faits

1. **Identifiant d'un concert.** Un concert créé dans Pupitre reçoit un identifiant tiré de son
   titre, sans date : celui du site portait la date de la première représentation, qui peut ne
   pas être annoncée. Les concerts repris gardent leur identifiant.
2. **Lien source à la reprise.** Quand le site donnait une adresse pour un événement (4 sur 18),
   elle devient la page de l'organisateur et le lien source de ses représentations. Les 14 autres
   n'ont pas de lien source : ils restent « publiés », et leur fiche le signale.
3. **Solistes de l'annuaire.** Toute personne citée dans « avec » entre comme soliste, y compris
   un metteur en scène : la liste des catégories n'a pas d'autre place pour elle. À reclasser.
4. **Carte du monde.** Un orchestre se place dans sa ville, pas dans celle du concert (arbitrage
   du 27/11/2026 : le Tokyo Symphony Orchestra est à Tokyo, même s'il a été dirigé à Kawasaki).
   La carte est une liste administrée « orchestre + ville » (`parcours_orchestres`), que l'on
   complète comme on veut. Les coordonnées viennent, dans l'ordre : d'une ville déjà connue de
   Pupitre ; d'une proposition de l'IA, à confirmer ; du point que l'on place ou déplace à la main
   sur la carte du site, reprise dans Pupitre. Aucun service de géocodage externe, aucun fichier
   de villes à tenir à jour. Une ligne non vérifiée reste « à confirmer ».
5. **Blocs visuels.** Le type du média, son module d'animation et ses réglages sont trois choses
   distinctes. Seul le média est entre repères dans le HTML ; les classes de la section sont
   posées par le script du site.
6. **Fuseau de la salle.** Déduit du pays quand il n'en a qu'un, ou de la ville quand un fuseau
   porte son nom ; sinon il reste à choisir sur la fiche (Porto, Valladolid aujourd'hui).
7. **Exemples de maquette de la presse.** Les huit faux articles sont gardés pour l'instant
   (arbitrage du 27/11/2026), dans la collection, marqués « exemple de maquette » ainsi que leurs
   médias inventés : ils se suppriment d'un bouton (voir « Avant la mise en production »).
8. **Albums et liens.** Les liens d'écoute et de commande sont des lignes à part (plateforme
   reconnue à son adresse, pictogramme, ordre) ; les deux distinctions du disque Ligeti sont deux
   objets distincts, rattachés au même album.
9. **Modèle de l'IA.** `claude-opus-5-5` par défaut, avec repli sur un autre modèle si les filtres
   de sécurité refusent la demande. Effort élevé pour la lecture d'un extrait, moyen pour un
   article et une traduction, faible pour une géolocalisation.
10. **Multilingue.** Une table de traductions générique plutôt que des colonnes par langue ; les
    anciennes colonnes `_en` sont gardées en miroir de ce qui est publié, pour que rien de ce qui
    les lisait ne casse. Elles pourront être retirées par une migration ultérieure.
11. **Fiche d'un concert sur le site.** Un panneau dépliable dans `concerts.html`, adressable par
    son ancre, plutôt qu'une page par concert : le site reste un jeu de pages fixes.
12. **Texte d'un article.** Le texte lu ou collé est gardé dans la base (`articles.texte_source`)
    pour recontrôler les citations ; il n'est jamais publié ni exporté.
13. **Lecture d'une page de presse.** Pupitre se présente sous son nom (`Pupitre/1.0`) et ne se
    fait pas passer pour un navigateur ; un site qui refuse la lecture est respecté, et l'on colle
    le texte.
14. **Images et médias externes.** La politique de sécurité de Pupitre (CSP) autorise l'affichage
    d'images et de médias en `https` pour prévisualiser un média externe ; aucun script externe.

15. **Interface sans bibliothèque.** Le design system est écrit à la main (une feuille de style,
    des fonctions PHP, des pictogrammes en SVG) plutôt que tiré d'un gabarit : Pupitre reste sans
    dépendance, et sa politique de sécurité n'autorise que ses propres fichiers. La police et les
    pictogrammes sont embarqués, pas chargés d'un service.
16. **Menu.** L'architecture demandée (Site internet, Carrière, Pilotage de la communication) est
    complétée de ce qui existait : blocs visuels, discographie, playlist, téléchargements, contacts
    sous « Site internet » ; annuaire sous « Carrière » ; médiathèque à part, parce qu'elle sert le
    site et la communication ; « Administration » pour le reste. « Import des concerts » n'est plus
    au menu : il s'ouvre depuis « Concerts ».
17. **Orchestres du monde et carte interactive** sont deux vues du même écran
    (`pages/contenus/orchestres.php`) : la collection d'un côté, les lignes de la carte de l'autre.
18. **Communication saisie à la main.** Plutôt que des écrans vides en attendant les connexions,
    un modèle simple et durable : il rend le tableau de bord éditorial utile tout de suite, et une
    plateforme connectée écrira plus tard dans la même table.
19. **État du site dans la barre du haut.** Il vient du dernier calcul des alertes, pas d'une
    comparaison de fichiers à chaque page.
20. **Pas de thème sombre.** Les jetons le permettent ; il n'a pas été demandé.
21. **Tableaux sur écran étroit.** Deux conduites, selon l'usage : les listes consultées chaque
    jour (agenda, tableau de bord éditorial, publications et lettres) s'empilent en fiches ; les
    tableaux de réglage et de référence (presse, comptes, orchestres, journal…) défilent dans leur
    cadre, comme dans le gabarit de référence. Tout empiler aurait demandé de réécrire chaque
    tableau pour un usage rare sur téléphone.

## Avant la mise en production

- **Supprimer les exemples de maquette de la presse** : « Revue de presse », « Supprimer tous les
  faux articles » (pilote : articles, citations, médias inventés ; le filtre « Exemples de
  maquette » les montre). Puis retirer
  du dépôt `data/presse-exemples.js` et `assets/presse/exemples/`, que le site ne charge plus.
- **Valider ou écarter les traductions proposées** par l'IA : « Langues et traductions », état
  « Proposées par l'IA, à valider ».
- **Confirmer les lignes de la carte du monde** restées « à confirmer » (« Orchestres »).
- **Compléter** : les liens d'écoute et de commande des albums, les fichiers de l'espace de
  téléchargement, les collaborations (trois brouillons non publiés, dont une à confirmer).
- **Droits** : confirmer les droits des médias encore « à confirmer » (médiathèque) et ceux des
  extraits ARD, ARTE et Prades.
- Retirer `<meta name="robots" content="noindex">` des pages du site au moment voulu.
- Sur le serveur : `.env` (base, clé de l'API, Mailjet), le cron des tâches de fond, les réglages
  « racine du site » et « adresse du site », puis une restauration de la base de référence.
- Sauvegarder la base et `stockage/` avant et après.

## Ce qui reste à faire

- Les contenus qui attendent Pierre Bleuse ou une validation éditoriale (section 76 du cahier des
  charges) : nouvelle formulation de la citation, morceau d'ouverture, enregistrements studio,
  notes personnelles et critiques des albums, distinctions, sélection des grands débuts et
  collaborations, confirmation du CBSO, validation des données géographiques, bleu nuit.
- Éprouver l'import des concerts sur les documents réels de l'agence, quand ils seront transmis :
  la lecture par l'IA tourne (essais sur des extraits fictifs), le format réel reste à voir.
- Renseigner Mailjet pour que les liens partent par mail ; installer le cron sur le serveur.
- Compléter les fiches : liens source, billetteries, fuseaux de Porto et de Valladolid.
- Pilotage de la communication, seconde phase : spécifications détaillées, connexion aux réseaux
  sociaux et à l'outil d'envoi de la lettre, inscriptions. Le modèle et les écrans l'attendent.
- Éprouver l'interface sur Safari et Firefox, sur iOS et Android : les essais automatisés tournent
  dans Chrome (bureau, et téléphone émulé).
- Reportée par le cahier des charges (section 77) : la synchronisation sonore avancée entre la
  musique d'ouverture et la première vidéo. Rien n'a été développé sur ce sujet.
- Hors de cette version, avec une place gardée dans la base : mails automatiques, liens de dépôt
  pour les photographes, visuels générés, veille de presse, statistiques, plusieurs artistes, mise
  en ligne automatique.

## Poste de développement (relevé du 12/11/2026, inchangé le 27/11/2026)

| Outil | Version | Où |
|---|---|---|
| PHP servi par Apache | 8.3.14 | MAMP, `http://localhost:8888/` |
| PHP en ligne de commande | 8.3.14 et 8.4.1 | `/Applications/MAMP/bin/php/php8.3.14/bin/php` (absent du PATH) |
| MySQL | 8.0.40 | MAMP, port 8889 |
| Python | 3.15.2 | `/opt/homebrew/bin/python3` |
| ffmpeg | 8.0 | `/opt/homebrew/bin/ffmpeg` |
| Chrome | pour les tests | piloté par `outils/cdp.py` (Node n'est pas installé) |

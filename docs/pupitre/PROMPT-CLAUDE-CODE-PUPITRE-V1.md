# Prompt Claude Code : Pupitre, première version (PHP et MySQL)

À lancer à la racine du dépôt `pierrebleuse` (dossier `Documents/Server/pierrebleuse`, celui qui contient `index.html`, `data/`, `js/` et `LISEZ-MOI.md`).

---

## Contexte

Pupitre est l'application qui pilote la communication de Pierre Bleuse et qui administre son site. L'interface affiche « Pupitre » et, en sous-titre, « Pierre Bleuse ». L'agence Carrel Communication pilote, Pierre consulte.

Cette première version est une maquette qui fonctionne, écrite dans les technologies de Carrel : PHP, MySQL, HTML, JS et CSS. Elle sera réécrite plus tard et proposée à d'autres artistes. Priorité : un outil simple, lisible, utilisable tout de suite. Pas de sur-architecture.

Le site est statique et le reste. Il lit ses contenus dans les fichiers de `data/`, chargés comme scripts avant le moteur de la nuée. Pupitre est la source de ces contenus : à chaque publication, il écrit ces fichiers. Le site n'interroge jamais Pupitre et n'accède jamais à la base.

Règle commune à toute l'application : l'IA propose, une personne valide.

## Mission

1. Préparer le site pour qu'il soit administrable : tous les contenus éditables dans des fichiers de `data/`, au format généré.
2. Construire Pupitre dans le dossier `pupitre/` : connexion et rôles, agenda, fiche concert, annuaire, contenus du site, médiathèque simple, publication vers le site, import de l'extrait de l'agence par l'IA, tableau de bord, vue de Pierre.

À la fin, tout ce qui s'affiche sur le site se modifie dans Pupitre et se publie d'un clic, et Pierre voit sa saison sur son téléphone.

## À lire avant d'écrire une ligne

1. `LISEZ-MOI.md` à la racine : la documentation du site, en particulier « Arborescence », « Visuels », « Agenda », « Discographie, presse, parcours », « Français et anglais », « Outils ».
2. Les fichiers de `data/` et leurs en-têtes de commentaire, `js/contenu.js` (dictionnaire des textes), `js/sections.js`, `js/agenda.js`, `outils/generer-agenda.py`, et `index.html` (repères `AGENDA-STATIQUE`).
3. `../2424-media/v1.0/` : l'application PHP de Carrel qui sert de modèle de style. Lis `includes/database.php`, `includes/auth.php`, `includes/logger.php`, `includes/mailer.php`, `admin/partials/`, `admin/cron/task.php`. N'ouvre ni `includes/config.php`, ni `_infos/`, ni aucun fichier `.env` de ce dossier. `includes/MetricoolAPI.php` servira plus tard pour les publications : rien à porter maintenant.
4. La documentation à jour de l'API d'Anthropic (envoi d'un PDF, sortie structurée) avant d'écrire l'import.

`../2424-media/` est en lecture seule.

## Étape 0 : état des lieux

- `git status`. Le dépôt contient beaucoup de modifications non commitées et un double du site dans `.claude/`. Ne commite rien, ne supprime rien : montre-moi l'état et demande-moi quoi faire avant de continuer.
- Une fois le dépôt propre, travaille sur une branche `pupitre`, un commit par étape, sans push.
- Relève les versions disponibles : `php -v`, MySQL de MAMP, `python3`, `ffmpeg`. Note-les dans `pupitre/LISEZ-MOI.md`.
- Ouvre `http://localhost:8888/pierrebleuse/` et fais des captures de référence de l'accueil, de `concerts.html` et de `presse.html` avec `outils/cdp.py`, en français et en anglais. Elles servent de témoin à l'étape 1.

## Socle technique

| Brique | Choix |
|---|---|
| Serveur | PHP 8 sans framework, sans Composer ni npm, comme 2424media |
| Base | MySQL ou MariaDB, PDO, requêtes préparées, `utf8mb4`, tables et colonnes en français |
| Interface | HTML, CSS et JS écrits à la main, sobres, sans gabarit d'administration du commerce |
| Tâches de fond | une table de tâches, exécutée par un script de cron, avec un bouton « Lancer maintenant » pour le poste local |
| IA | appel direct de l'API d'Anthropic en cURL, côté serveur |
| Mails | Mailjet par son API, derrière une fonction unique ; sans clé, le message est écrit dans un journal |
| Outils du site | les scripts Python de `outils/` restent, Pupitre les appelle quand il en a besoin |

Structure proposée :

```
pupitre/
  index.php            tableau de bord
  connexion.php, deconnexion.php
  pages/               un dossier par module
  ajax/                actions appelées par l'interface
  inclus/              config, base, connexion, journal, mail, ia, publication
  gabarits/            en-tête, pied, composants
  cron/taches.php
  sql/schema.sql       structure complète, rejouable
  sql/depart.php       reprise des données du site
  stockage/            imports, sauvegardes de publication, journaux (hors git, interdit au web)
  ressources/          css, js, images de l'interface
  LISEZ-MOI.md
```

## Règles non négociables

- Le site reste statique : aucun fichier PHP hors de `pupitre/`, aucun appel du site vers Pupitre, aucun accès à la base depuis le site.
- Le rendu du site ne change pas. Après l'étape 1, les pages sont identiques aux captures de référence, `SiteContenu.ecarts` est vide en français, la console est sans erreur, `sh outils/syntaxe.sh` passe.
- Le moteur de la nuée n'est pas modifié : `js/nuee.js`, `js/musique.js`, `js/sync.js`, `js/parametres.js`, `js/reglages.js`, `js/lumiere.js`, `js/main.js`. Les contenus restent chargés par des scripts classiques placés avant le moteur.
- Secrets : `pupitre/.env` n'est jamais commité, `pupitre/.env.exemple` liste les variables sans valeur. Je saisis les clés moi-même : ne me demande jamais de les coller dans la conversation. Aucune clé dans les journaux ni dans les messages d'erreur.
- Sécurité : mots de passe avec `password_hash`, jeton anti-CSRF sur chaque formulaire et chaque action, sessions durcies, sorties échappées, droits vérifiés côté serveur à chaque action, limitation des tentatives de connexion. `inclus/`, `sql/`, `stockage/` et `cron/` sont interdits au web par `.htaccess`. Les fichiers déposés sont contrôlés (type réel, taille), renommés, et rangés là où PHP ne s'exécute pas. `noindex` sur tout `pupitre/`.
- Confidentialité : l'extrait de l'agence est un document interne. Il reste dans `pupitre/stockage/`, hors git. Les dates non annoncées ne sortent jamais dans un fichier du site.
- Le site ne reçoit que les représentations annoncées par leur organisateur. Un test le prouve.
- L'IA propose, une personne valide. Rien n'est écrit dans l'agenda ou l'annuaire sans validation.
- N'invente aucune donnée : ni date, ni lieu, ni compte social, ni citation. Un champ vide reste vide.
- Rien de propre à Pierre Bleuse en dur dans le code de Pupitre : son nom, ses langues, ses maisons, ses réseaux sont des réglages en base. Une installation sert un artiste.
- Dates : date et heure locales de la salle, jamais converties.
- Langue : interface, noms de tables, commentaires, documentation et messages de commit en français.

## Étape 1 : rendre le site administrable

Aujourd'hui, une partie des contenus est écrite à la main dans `js/contenu.js` et dans le HTML. Tout ce qui est éditable doit venir de `data/`.

**Format des fichiers de données.** Chaque fichier de `data/` écrit par Pupitre a la même forme : une ligne de commentaire « Généré par Pupitre, ne pas modifier à la main », puis `window.NOM = ` suivi d'un JSON strict. Convertis les fichiers existants à ce format, sans changer leur contenu ni leurs clés. Leur documentation (les en-têtes actuels) passe dans `LISEZ-MOI.md`.

| Fichier | Variable | État |
|---|---|---|
| `data/agenda.json`, `data/agenda.js` | `AGENDA_DONNEES` | existe, déjà généré |
| `data/playlist.js` | `PLAYLIST` | à convertir |
| `data/visuels.js` | `VISUELS` | à convertir |
| `data/disques.js` | `DISQUES` | à convertir |
| `data/presse.js` | `PRESSE` | à convertir |
| `data/orchestres.js` | `ORCHESTRES` | à convertir |
| `data/textes.js` | `TEXTES` | à créer : citation, biographie, mention de saison, texte de la lettre, crédit du pied de page, description de la page |
| `data/parcours.js` | `PARCOURS` | à créer : fonctions et « opéra et création » en listes, à la place des clés numérotées |
| `data/contacts.js` | `CONTACTS` | à créer : les contacts aujourd'hui en dur dans `index.html` |
| `data/reseaux.js` | `RESEAUX` | à créer : les réseaux, aujourd'hui en dur à deux endroits |
| `data/telechargements.js` | `TELECHARGEMENTS` | à créer : les quatre lignes « à venir » |

Les libellés d'interface (menu, boutons, intitulés de colonnes) restent dans `js/contenu.js`. `data/textes.js` est chargé avant lui et complète son dictionnaire. `data/presse-exemples.js`, `data/cartes.js` et les analyses de bandes-son ne changent pas.

**Repli sans JavaScript.** Le HTML garde le français en dur. Chaque zone qui dépend des données est encadrée par deux repères, sur le modèle de `AGENDA-STATIQUE:DEBUT` et `AGENDA-STATIQUE:FIN` déjà en place. À la publication, Pupitre réécrit le contenu entre les repères et le numéro de version `?v=` des scripts, et rien d'autre dans les pages.

Fais ensuite un commit « site : contenus administrables ».

## Étape 2 : socle de Pupitre

- Configuration par `.env`, connexion à la base, journal, gabarit d'interface avec le titre « Pupitre » et le sous-titre lu dans les réglages.
- Connexion par adresse et mot de passe pour Carrel. Pour l'artiste : lien de connexion à usage unique envoyé par mail, valable quinze minutes.
- Trois rôles, droits dans un seul fichier :

| Rôle | Qui | Droits |
|---|---|---|
| Pilote | Carrel, direction du projet | Tout : réglages, comptes, validations, publication |
| Éditeur | Équipe Carrel | Agenda, fiches, annuaire, contenus, médias, validation des imports. Ni réglages ni publication |
| Artiste | Pierre | Sa vue, en lecture. Il peut laisser une note sur un concert |

- Journal des modifications : qui, quoi, quand, avant, après.
- Réglages : nom et sous-titre, langues, fuseau, maisons, réseaux de l'artiste, racine du site à alimenter.

## Étape 3 : reprise des données

`sql/depart.php` charge dans la base tout ce que le site affiche aujourd'hui, à partir des fichiers de `data/` : les 18 événements de l'agenda (représentations au statut « publiée »), disques, articles de presse réels (pas les faux articles de maquette), orchestres, visuels, titres du lecteur, textes, parcours, contacts, réseaux, téléchargements. L'annuaire est amorcé avec les ensembles, salles, solistes, maisons de disques et médias rencontrés, sans compte social. Le compte pilote est créé avec l'adresse lue dans `PILOTE_EMAIL`.

Contrôle : une publication faite juste après la reprise redonne des fichiers équivalents à ceux du site.

## Étape 4 : agenda, fiche concert, annuaire

**Agenda.** Vue de la saison par mois, filtres par statut, maison et période. Création et modification à la main. Types repris du site : concert, opéra, tournée, disque.

**Statuts d'une représentation.**

| Statut | Sens | Sur le site |
|---|---|---|
| Connue | Dans l'extrait de l'agence, pas encore annoncée par l'organisateur | Non |
| Sous embargo | Annonce prévue à une date fixe | Non, alerte à l'échéance |
| Annoncée | Annoncée par l'organisateur, lien source obligatoire | À la prochaine publication |
| Publiée | Présente dans la dernière publication | Oui |
| Annulée ou reportée | Raison obligatoire | Retirée à la prochaine publication |

Le statut se règle par représentation, avec une action pour tout le concert. « Publiée » est posé par la publication, jamais à la main. Les dates passées et publiées restent dans l'agenda du site (`concerts.html` les affiche).

**Fiche concert.** Un écran : représentations et statuts, salle, ville, pays, ensemble, maison, programme, participants, mentions, lien de billetterie, page de l'organisateur, notes internes, journal. Champs en français et en anglais côte à côte. Indicateur de ce qui manque pour annoncer.

**Annuaire.** Personnes et structures par catégorie (orchestre, ensemble, salle, festival, soliste, compositeur, maison de disques, média, photographe, producteur, agence), avec autres noms, ville, pays, coordonnées géographiques, site, comptes sociaux, mots-dièse, contacts, crédit, logo. Un compte social a un état : proposé, vérifié, à revoir. Détection des doublons. Depuis une fiche : les concerts liés. Les participants d'un concert se choisissent dans l'annuaire.

## Étape 5 : contenus du site et médiathèque

Un écran par objet, chacun avec ses champs en français et en anglais et un aperçu.

| Écran | Alimente | Particularités |
|---|---|---|
| Textes | `data/textes.js` | citation, biographie, mention de saison, lettre, pied de page |
| Parcours | `data/parcours.js` | deux listes ordonnables : fonctions, opéra et création |
| Orchestres invités | `data/orchestres.js` | fiches d'annuaire cochées « dans le parcours », avec ville, coordonnées et zone ; Pupitre propose les orchestres des concerts passés |
| Disques | `data/disques.js` | maison de disques prise dans l'annuaire, liens « Commander » et « Écouter », mot de Pierre, ordre |
| Presse | `data/presse.js` | média et logo pris dans l'annuaire, verbatim dans sa langue, résumé, ordre ; les quatre premiers vont sur l'accueil |
| Visuels | `data/visuels.js` | quatre emplacements ; média choisi dans la médiathèque, point focal réglé au clic sur l'image, opacité, place de la nuée ; préréglage de lumière `generique` pour une nouvelle photo |
| Musiques | `data/playlist.js` | dépôt du fichier, mention, ordre ; Pupitre lance `outils/analyse-bande-son.py` si `python3` et `ffmpeg` sont présents, sinon il affiche la commande à lancer |
| Téléchargements | `data/telechargements.js` | fichier ou « à venir » |
| Contacts, réseaux | `data/contacts.js`, `data/reseaux.js` | |

**Médiathèque.** Version simple : dépôt d'images, de sons, de vidéos et de documents, avec texte alternatif, crédit, droits d'usage en texte libre, et la liste des endroits où le média est utilisé. Les fichiers publiés sont copiés dans `assets/` du site.

## Étape 6 : publication vers le site

- Un écran « Le site » : date et auteur de la dernière publication, liste des écarts entre Pupitre et le site, fichier par fichier.
- Prévisualisation des écarts, puis « Publier » : Pupitre écrit les fichiers de `data/`, réécrit les zones de repli entre repères et le numéro de version des scripts, copie les médias nécessaires.
- Avant d'écrire, il sauvegarde les fichiers remplacés dans `stockage/publications/<date>/`. Un bouton revient à la publication précédente.
- L'écriture est atomique : fichier temporaire, puis renommage. En cas d'erreur, rien n'est laissé à moitié écrit.
- Après publication, les représentations annoncées passent à « publiée ».
- `outils/generer-agenda.py` n'est plus nécessaire : garde-le, et indique dans `LISEZ-MOI.md` que Pupitre le remplace.
- La mise en ligne (envoi vers le serveur, git) reste manuelle dans cette version.

## Étape 7 : import de l'extrait de l'agence

- Dépôt d'un PDF, d'un tableur ou d'un texte collé. La lecture se fait en tâche de fond, l'écran suit son avancement.
- L'IA rend des engagements structurés (dates, heures, salle, ville, pays, ensemble, programme, solistes, mentions), dans un JSON contrôlé par le code. Un échec de contrôle donne une seule relance, puis une erreur lisible.
- La comparaison avec l'agenda en place est faite par le code, pas par l'IA : nouvelles dates, dates modifiées, dates disparues, programmes changés. Rapprochement sur la date et l'ensemble, les cas douteux sont présentés à la main.
- Contrôle : lieu, ville ou heure manquants, date hors saison, date passée, doublon probable.
- Validation ligne par ligne ou en bloc, avec correction possible. Rien n'est écrit avant.
- Clé dans `ANTHROPIC_API_KEY`, modèle dans `ANTHROPIC_MODELE`. Les consignes données à l'IA sont des fichiers dans `pupitre/inclus/consignes/`. Chaque appel est journalisé : objet, modèle, jetons, durée, résultat.
- Pour tester, demande-moi de déposer l'extrait dans `pupitre/stockage/imports/`. Sans lui, fabrique un extrait fictif.

## Étape 8 : tableau de bord et vue de Pierre

**Tableau de bord**, cinq blocs :

| Bloc | Contenu | Actions |
|---|---|---|
| Aujourd'hui | Concerts du jour, heure locale de la salle et heure de l'artiste | Ouvrir la fiche |
| Cette semaine | Concerts des sept prochains jours, embargos à échéance | Ouvrir la fiche |
| À valider | Changements d'import en attente, comptes sociaux proposés, notes de l'artiste | Valider, corriger, refuser |
| Alertes | Voir ci-dessous | Traiter, reporter |
| Le site | Dernière publication, écarts en attente | Prévisualiser, publier, revenir en arrière |

Alertes, recalculées après chaque modification et chaque nuit : représentation à venir sans salle, ville ou heure ; représentation annoncée sans lien source ; concert à moins de 21 jours dont la fiche est incomplète ; embargo arrivé à échéance ; date passée restée « connue » ; doublon probable ; écart entre Pupitre et le site ; dernier extrait de l'agence importé il y a plus de 30 jours ; contenu du site resté vide (lien d'un disque, verbatim d'un article, téléchargement « à venir »).

**Vue de Pierre**, pensée pour le téléphone (390 px), en lecture : prochain concert, cette semaine, la saison à venir par mois avec le statut de chaque date, date de la dernière mise à jour du site. Une seule action : laisser une note sur un concert, qui arrive dans « À valider ».

## À livrer

- La branche `pupitre` avec un commit par étape.
- `pupitre/LISEZ-MOI.md` : installer en local (MAMP), créer la base, variables d'environnement, lancer la reprise, lancer le cron, structure, ce qui reste à faire.
- `LISEZ-MOI.md` du site mis à jour : nouveaux fichiers de `data/`, repères, rôle de Pupitre.
- `pupitre/sql/schema.sql`, `pupitre/.env.exemple`, `.gitignore` complété (`pupitre/.env`, `pupitre/stockage/`).
- Des captures du tableau de bord, d'une fiche concert, de l'écran « Le site » et de la vue de Pierre à 390 px, dans `docs/pupitre/captures/`.

## Vérifications avant de rendre

- Le site est identique aux captures de référence, en français et en anglais ; `SiteContenu.ecarts` est vide ; aucune erreur en console ; `sh outils/syntaxe.sh` passe.
- Reprise puis publication sans modification : les fichiers de `data/` sont équivalents à ceux d'avant.
- Parcours complet : connexion, modification de la citation et d'un disque, ajout d'un concert annoncé, publication, vérification sur le site, retour à la publication précédente.
- Aucune représentation connue, sous embargo ou annulée dans les fichiers du site.
- Un éditeur ne peut ni publier ni ouvrir les réglages ; l'artiste ne peut rien modifier ; sans connexion, aucune page de `pupitre/` ne répond, et `inclus/`, `sql/`, `stockage/` renvoient un refus.
- Avec l'extrait du 21 septembre 2026, le contrôle relève douze dates sans lieu et trois représentations de « Red Fox » datées de mai 2026.
- Aucun secret dans le dépôt ; aucun contenu de l'extrait dans les journaux.
- La vue de Pierre est lisible à 390 px et utilisable au clavier.

## Hors de cette version

Inscription à la lettre (le formulaire du site reste inactif), mails automatiques, liens de dépôt pour les photographes, rédaction et envoi des publications par Metricool, visuels générés, veille et revue de presse automatique, statistiques, plusieurs artistes, mise en ligne automatique. Ne les construis pas, et ne ferme aucune porte : garde une place dans le menu et dans la base.

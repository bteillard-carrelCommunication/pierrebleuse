> PÉRIMÉ depuis le 12 novembre 2026 au soir : la première version de Pupitre se fait en PHP et MySQL. Utiliser `PROMPT-CLAUDE-CODE-PUPITRE-V1.md`.

# Prompt Claude Code : Pupitre, lot 1 (le socle)

À lancer dans un dossier vide `Documents/Server/pupitre`, à côté de `pierrebleuse/` et de `2424-media/`.

---

## Contexte

Pupitre est une application web : le poste de pilotage de la communication d'un artiste et le back-office de son site. Premier artiste : le chef d'orchestre Pierre Bleuse. L'interface affiche « Pupitre » et, en sous-titre, « Pierre Bleuse ». L'agence Carrel Communication pilote, l'artiste consulte. L'application sera ensuite vendue à d'autres artistes.

Tout part de l'agenda. On dépose l'extrait des concerts fourni par l'agence artistique, l'IA le lit, une personne valide, et Pupitre livre au site son calendrier. Règle commune à toute l'application : l'IA propose, une personne valide.

Le projet compte cinq lots : 1 le socle, 2 les médias et les mails, 3 les publications sur les réseaux, 4 la veille et les statistiques, 5 le produit pour plusieurs artistes. Ce prompt couvre le lot 1 et lui seul.

## Mission

Construis le lot 1 : connexion et rôles, agenda (lecture de l'extrait par l'IA, statuts, comparaison), fiche concert, annuaire, calendrier livré au site, tableau de bord, vue de l'artiste.

À la fin du lot, le calendrier du site de Pierre Bleuse sort de Pupitre sans ressaisie, et Pierre voit sa saison sur son téléphone.

## À lire avant d'écrire une ligne

1. `../pierrebleuse/site/data/agenda.json` : le format exact du calendrier à livrer au site (18 événements, 30 représentations au 12 novembre 2026).
2. `../pierrebleuse/site/LISEZ-MOI.md` et, dans `../pierrebleuse/docs/site/PROMPT-CLAUDE-CODE.md`, la partie « Agenda : règles d'affichage » : comment le site lit ce fichier.
3. `../2424-media/v1.0/includes/MetricoolAPI.php` : l'envoi par Metricool dans l'application actuelle de Carrel, en PHP. C'est la référence du lot 3 : rien n'est à porter ici, il suffit de savoir qu'elle existe et qu'elle ne gère qu'une seule marque. N'ouvre ni `includes/config.php`, ni `_infos/`, ni aucun fichier `.env` de ce dossier.
4. La documentation à jour de Next.js, Better Auth, Drizzle, pg-boss et de l'API d'Anthropic, avant d'installer. Ces outils changent vite : ne te fie pas à ta mémoire.

Tout ce qui est hors de `pupitre/` est en lecture seule.

## Socle technique

| Brique | Choix |
|---|---|
| Langage | TypeScript, mode strict, partout |
| Application | Next.js 16 (App Router), React 19 |
| Exécution | Node.js 26 (LTS) |
| Base de données | PostgreSQL |
| Accès aux données | Drizzle ORM, migrations SQL versionnées dans le dépôt |
| Connexion | Better Auth : lien de connexion par mail, organisations pour les espaces et les rôles |
| Interface | Tailwind CSS et shadcn/ui |
| Validation | Zod, les mêmes schémas pour les formulaires, les sorties de l'IA et l'export |
| Tâches de fond | pg-boss, dans un processus `worker` séparé de l'application |
| IA | SDK officiel `@anthropic-ai/sdk`, côté serveur uniquement |
| Mails | Mailjet (API d'envoi), derrière une interface ; en développement, le lien de connexion s'affiche dans la console |
| Tests | Vitest pour la logique, Playwright pour les parcours |
| Conteneurs | Docker Compose avec trois services, `app`, `worker`, `db` ; image Next.js en sortie `standalone` |

Versions relevées le 12 novembre 2026 : Next.js 16.4.5 (LTS active, correctif de sécurité du 3 novembre), Node.js 26 (LTS active), Better Auth 1.7. Installe les dernières versions stables corrigées, fige-les dans `package-lock.json` et note-les dans `docs/DECISIONS.md`.

L'application sera hébergée sur un serveur de Carrel, en Docker, sans dépendance à un hébergeur particulier. Le choix du serveur n'est pas arrêté : ne déploie rien.

## Règles non négociables

- Périmètre : tu n'écris que dans `pupitre/`. Pendant le développement et les tests, l'export du calendrier va dans `./exports/`, jamais dans `../pierrebleuse/`.
- Secrets : `.env` n'est jamais commité, `.env.example` liste les variables sans valeur. Je saisis les clés moi-même : ne me demande jamais de les coller dans la conversation. Aucune clé, aucun jeton dans les journaux, les messages d'erreur ou les tests.
- Confidentialité : l'extrait de l'agence est un document interne. Il ne quitte pas Pupitre et n'est jamais commité (`docs/exemples/prives/` est ignoré par git). Les dates non annoncées n'apparaissent dans aucun export, aucune page accessible sans connexion, aucun journal applicatif.
- Le site ne reçoit que les représentations annoncées par leur organisateur. Un test automatique le prouve.
- L'IA propose, une personne valide. Aucune écriture dans l'agenda ou l'annuaire sans validation. Un compte social proposé par l'IA n'est jamais marqué « vérifié ».
- N'invente aucune donnée : ni date, ni lieu, ni compte social, ni traduction de nom propre. Ce que l'IA ne lit pas dans l'extrait reste vide et devient une anomalie.
- Rien de propre à Pierre Bleuse n'est écrit en dur. Son nom, ses langues, ses maisons, son site sont des données de l'espace, chargées par le jeu de départ.
- Cloisonnement : chaque donnée appartient à un espace. Toute lecture et toute écriture passent par une couche d'accès qui impose l'espace et le rôle, côté serveur, à chaque action. Des tests le prouvent avec deux espaces.
- Pas de cache sur les données d'un espace : n'active pas Cache Components, n'utilise ni `use cache` ni `unstable_cache` dans le back-office. Les correctifs de sécurité de Next.js de novembre 2026 portent sur des fuites de cache entre locataires. Les pages du back-office sont dynamiques.
- Dates : date et heure locales de la salle, avec leur fuseau, jamais converties. Horodatages techniques en UTC.
- Langue : interface en français, textes centralisés pour une traduction ultérieure. Identifiants de code en anglais ; commentaires, documentation et messages de commit en français. `docs/GLOSSAIRE.md` fait le lien entre les deux vocabulaires.
- Accessibilité : navigation au clavier, contrastes AA, vue de l'artiste pensée d'abord pour un téléphone (390 px).
- Git : `git init`, branche `main`, un commit par étape, pas de dépôt distant, pas de push.

## Rôles

| Rôle | Qui | Lot 1 |
|---|---|---|
| Pilote | Carrel, direction du projet | Tout : réglages, membres, validations, publication du calendrier |
| Éditeur | Équipe Carrel | Agenda, fiches, annuaire, validation des imports. Ni réglages ni publication |
| Artiste | Pierre | Sa vue, en lecture. Il peut laisser une note sur un concert |
| Agence | Agent artistique | Rôle défini et testé, lecture de l'agenda. Pas d'écran dédié dans ce lot |

Un pilote peut suivre plusieurs espaces. L'artiste se connecte sans mot de passe, par un lien envoyé à son adresse. Les droits tiennent dans un seul fichier, testé.

## Modèle de données

| Objet | Rôle | Champs clés |
|---|---|---|
| Espace | Un artiste | nom, sous-titre, identifiant d'URL, langues, fuseau, maisons (clé et libellé), réglages du site alimenté |
| Membre | Une personne dans un espace | utilisateur, rôle |
| Concert | Un engagement | identifiant public (`2026-10-15-malmo`), type, titre, ensemble, maison, ville, pays, salle, mentions, niveau (temps fort, standard, discret), lien, page de l'organisateur, notes internes |
| Représentation | Une date d'un concert | date, heure, fuseau, statut, lien source de l'annonce, date d'embargo, raison d'annulation ; pour une tournée : ville, pays, salle et lien propres |
| Programme | Les œuvres | ordre, compositeur, œuvre, mention |
| Participant | Qui est lié au concert | fiche d'annuaire, nom affiché, rôle, nature (ensemble, salle, soliste, organisateur, producteur, photographe), affiché sur le site, à identifier dans les publications |
| Fiche d'annuaire | Une personne ou une structure | catégorie (orchestre, ensemble, salle, festival, soliste, compositeur, label, photographe, producteur, média, agence), nom, autres noms, ville, pays, site, mots-dièse, crédit, langue, notes |
| Compte social | Compte officiel d'une fiche | réseau, identifiant ou adresse, type (Page ou profil), état (proposé, vérifié, à revoir), vérifié le, par qui |
| Contact | Interlocuteur d'une fiche | nom, fonction, mail, téléphone, langue, rôle |
| Import | Un extrait déposé | fichier, origine, état, résultat de l'IA, modèle, jetons, durée, erreur |
| Changement | Une ligne à valider issue d'un import | nature, cible, avant, après, anomalies, décision, par qui, quand |
| Export | Une livraison au site | contenu, empreinte, compteurs, par qui, quand, état |
| Alerte | Un point à traiter | règle, objet, état (ouverte, reportée jusqu'au, traitée) |
| Note | Message de l'artiste sur un concert | auteur, texte, état |
| Journal | Trace de chaque modification | qui, quoi, quand, avant, après |

Les champs de texte suivent la convention de `agenda.json` : une chaîne, ou un objet `{fr, en}`. Le modèle doit accueillir sans refonte les lots suivants (médias, publications, mails, presse rattachés au concert) et d'autres métiers que chef d'orchestre (soliste, chanteur, ensemble).

## Statuts d'une représentation

| Statut | Sens | Où elle apparaît |
|---|---|---|
| Connue | Dans l'extrait de l'agence, pas encore annoncée par l'organisateur | Pupitre seulement |
| Sous embargo | Annonce prévue à une date fixe | Pupitre seulement, alerte à l'échéance |
| Annoncée | Annoncée par l'organisateur, lien source obligatoire | Pupitre, prête pour le site |
| Publiée | Présente dans le dernier calendrier livré au site | Site |
| Annulée ou reportée | Retirée, raison obligatoire | Retirée de la livraison suivante |

Le statut se règle par représentation, avec une action pour tout le concert. Le passage à « publiée » est posé par l'export, jamais à la main. La levée d'un embargo crée une alerte, elle ne publie rien seule.

## Écrans et fonctions

### 1. Agenda

- Vue de la saison par mois, filtres par statut, maison et période, recherche.
- Création et modification à la main d'un concert et de ses représentations.
- Import d'un extrait de l'agence : dépôt d'un PDF, d'un tableur ou d'un texte collé. La lecture se fait en tâche de fond, l'écran suit son avancement.
- Comparaison avec l'agenda en place, calculée par le code et non par l'IA : nouvelles dates, dates modifiées, dates disparues, programmes changés. Rapprochement sur la date et l'ensemble ; les cas douteux sont présentés pour rapprochement manuel.
- Contrôle : lieu, ville ou heure manquants, date hors saison, date passée, doublon probable.
- Validation ligne par ligne ou en bloc, avec correction possible avant d'accepter. Rien n'est écrit avant.
- Chaque import est conservé avec son fichier, son résultat et ses décisions.

### 2. Fiche concert

- Tout sur un concert en un écran : représentations et statuts, salle, programme, participants, liens, notes, journal des modifications.
- Champs bilingues côte à côte ; l'IA propose la traduction manquante, à valider.
- Indicateur de complétude : ce qui manque pour annoncer, puis pour publier.
- Participants choisis dans l'annuaire, avec deux réglages : affiché sur le site, à identifier dans les publications.
- Emplacement prévu pour les médias, les publications, les mails et la presse des lots suivants, sans rien afficher de vide.

### 3. Annuaire

- Personnes et structures, par catégorie, avec comptes sociaux, mots-dièse, contacts et crédit.
- Un compte social porte un état : proposé, vérifié, à revoir. Seule une personne le passe à « vérifié », avec la date.
- Détection des doublons à la création et à l'import (autres noms).
- Depuis une fiche : les concerts liés, passés et à venir.
- Les structures et les personnes repérées dans un extrait sont proposées à la création, jamais créées seules.

### 4. Calendrier livré au site

- Export au format exact de `agenda.json` : clés `version`, `note`, `maisons`, `evenements`, mêmes champs, même convention de texte. Vérifie dans `../pierrebleuse/site/` si le site attend aussi un fichier dérivé (par exemple `agenda.js`) et produis-le dans ce cas.
- N'entrent dans l'export que les représentations annoncées ou publiées. Un concert sans représentation exportable n'est pas exporté.
- Prévisualisation des écarts avec la dernière livraison, puis publication : écriture des fichiers dans le dossier configuré (`SITE_EXPORT_DIR`) et téléchargement possible.
- Historique des livraisons, retour à une version précédente.
- La mise en ligne elle-même (commit du dépôt du site, transfert) reste manuelle dans ce lot.

### 5. Tableau de bord

Cinq blocs dans ce lot. Les blocs Presse, Médias reçus et Chiffres arrivent avec leurs lots : n'affiche pas de bloc vide.

| Bloc | Contenu | Actions |
|---|---|---|
| Aujourd'hui | Concerts du jour, heure locale de la salle et heure du fuseau de l'espace | Ouvrir la fiche |
| Cette semaine | Concerts des sept prochains jours, embargos à échéance | Ouvrir la fiche |
| À valider | Changements d'import en attente, comptes sociaux proposés, notes de l'artiste | Valider, corriger, refuser |
| Alertes | Voir les règles ci-dessous | Traiter, reporter |
| Le site | Dernière livraison (date, auteur, nombre de concerts), écarts en attente | Prévisualiser, publier, revenir en arrière |

Règles d'alerte, recalculées après chaque modification et chaque nuit par le `worker` :

1. Représentation à venir sans salle, sans ville ou sans heure.
2. Représentation annoncée sans lien source.
3. Concert à moins de 21 jours dont la fiche est incomplète.
4. Embargo arrivé à échéance.
5. Date passée restée « connue ».
6. Doublon probable.
7. Écart entre l'agenda et la dernière livraison au site.
8. Participant à identifier, sur un concert à venir, sans compte vérifié.
9. Dernier extrait de l'agence importé il y a plus de 30 jours.

### 6. Vue de l'artiste

- Pensée pour le téléphone, en lecture : prochain concert, cette semaine, la saison à venir par mois, date de la dernière mise à jour du site.
- L'artiste voit toutes ses dates, annoncées ou non, avec leur statut.
- Une seule action : laisser une note sur un concert (erreur à corriger, précision). La note arrive dans « À valider ».
- Ni réglages, ni annuaire, ni import.

### 7. Réglages de l'espace

Nom et sous-titre, langues, fuseau, maisons, dossier d'export, membres et rôles. Prévois l'emplacement des connexions des lots suivants (Metricool avec un identifiant de marque par espace, Mailjet) sans rien brancher.

## L'IA dans ce lot

- Appels côté serveur uniquement, clé dans `ANTHROPIC_API_KEY`, modèle dans `ANTHROPIC_MODEL`. Choisis le modèle par défaut d'après la documentation d'Anthropic au moment du développement.
- Lecture d'un extrait : le PDF est envoyé tel quel comme document, le tableur est converti en texte. La réponse est contrainte par un schéma et validée par Zod. En cas d'échec de validation, une seule relance, puis l'import passe en erreur avec un message lisible.
- L'IA rend des engagements structurés (dates, heures, salle, ville, pays, ensemble, programme, solistes, mentions) et rien d'autre. La comparaison, le contrôle et l'écriture sont faits par le code.
- Deux autres usages : proposer la traduction d'un champ entre français et anglais, proposer le rapprochement d'un nom avec une fiche d'annuaire. Toujours sous forme de proposition.
- Les consignes données à l'IA sont des fichiers versionnés dans `src/ai/prompts/`.
- Chaque appel est journalisé : objet, modèle, jetons, durée, coût estimé, résultat. Sans la clé.

## Jeu de départ

Un espace « Pierre Bleuse » : sous-titre « Pierre Bleuse », langues français et anglais, fuseau `Europe/Paris`, maisons reprises de `agenda.json`. Les 18 événements de `agenda.json` y sont chargés, représentations au statut « publiée ». L'annuaire est amorcé avec les ensembles, salles et solistes de ces événements, sans compte social. Un compte pilote est créé avec l'adresse lue dans `SEED_PILOT_EMAIL`.

## Étapes

Commence par l'étape 0, présente ton plan en quinze lignes, puis avance sans attendre, sauf question bloquante. Un commit à la fin de chaque étape.

0. Lecture des références. `docs/DECISIONS.md` : versions retenues, choix d'architecture, questions ouvertes.
1. Socle : projet, base, migrations, connexion par lien, espaces, rôles, jeu de départ, conteneurs, tests de cloisonnement.
2. Agenda et fiche concert en saisie manuelle, statuts, journal.
3. Annuaire.
4. Calendrier livré au site, avec le test d'aller-retour.
5. Import de l'extrait : lecture par l'IA, comparaison, contrôle, validation.
6. Tableau de bord et alertes.
7. Vue de l'artiste.
8. Vérifications, documentation.

À l'étape 5, demande-moi de déposer l'extrait de l'agence dans `docs/exemples/prives/`. Sans lui, teste avec un extrait fictif que tu fabriques.

## À livrer

- Le dépôt `pupitre/` avec ses commits d'étape.
- `LISEZ-MOI.md` : installer, lancer en local, lancer les tests, variables d'environnement, structure du dépôt.
- `docs/ARCHITECTURE.md`, `docs/DECISIONS.md`, `docs/GLOSSAIRE.md`.
- `docs/SUITE.md` : ce que les lots 2 à 5 trouveront déjà en place, et ce qui reste ouvert.
- `.env.example`, `docker-compose.yml`, `Dockerfile`.
- Des captures d'écran du tableau de bord, d'une fiche concert et de la vue de l'artiste à 390 px, dans `docs/captures/`.

## Vérifications avant de rendre

- Une seule commande lance les types, le lint et les tests, et tout passe.
- Parcours complet rejoué par Playwright : connexion par lien, import d'un extrait, validation, livraison du calendrier.
- Aller-retour : le `agenda.json` actuel, chargé puis réexporté, redonne un fichier équivalent (mêmes événements, mêmes champs).
- L'export ne contient aucune représentation connue, sous embargo ou annulée.
- Un membre d'un espace ne lit ni ne modifie rien d'un autre espace, par l'interface comme par les routes.
- Avec l'extrait du 21 septembre 2026, le contrôle relève douze dates sans lieu et trois représentations de « Red Fox » datées de mai 2026.
- Aucun secret dans le dépôt, `.env` ignoré, aucun contenu de l'extrait dans les journaux.
- La vue de l'artiste est lisible et utilisable à 390 px, au clavier, avec des contrastes AA.

## Hors lot 1

Médiathèque et liens de dépôt, mails automatiques, rédaction et envoi des publications (Metricool), visuels, surveillance des pages des organisateurs, veille et revue de presse, statistiques, lettres, vue agence, facturation. Ne les construis pas, mais ne ferme aucune porte : le modèle de données et les réglages doivent les accueillir.

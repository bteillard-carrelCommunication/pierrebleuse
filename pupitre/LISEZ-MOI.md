# Pupitre · LISEZ-MOI

Pupitre pilote la communication d'un artiste et administre son site. L'interface affiche
« Pupitre » et, en sous-titre, le nom lu dans les réglages. L'agence pilote, l'artiste consulte.

Première version : une maquette qui fonctionne, en PHP 8 et MySQL, avec du HTML, du CSS et du JS
écrits à la main. Ni framework, ni Composer, ni npm. Une installation sert un artiste.

Trois règles tiennent tout le reste :

- **Le site est statique et le reste.** Il lit ses contenus dans les fichiers de `data/`. Pupitre
  est la source de ces contenus : à chaque publication, il écrit ces fichiers. Le site n'interroge
  jamais Pupitre et n'accède jamais à la base. Aucun fichier PHP hors de `pupitre/`.
- **Le site ne reçoit que les représentations annoncées par leur organisateur.** Une date connue,
  sous embargo ou annulée ne sort dans aucun fichier du site.
- **L'IA propose, une personne valide.** Rien n'entre dans l'agenda ou dans l'annuaire sans
  validation.

## Installer en local (MAMP)

1. **Variables d'environnement.** Copier `pupitre/.env.exemple` en `pupitre/.env` et le remplir
   (tableau plus bas). `.env` n'est jamais commité.
2. **Base.** Créer une base `utf8mb4` et un utilisateur qui n'a de droits que sur elle, puis
   reporter leurs noms dans `.env`. Si l'utilisateur de `.env` a le droit de créer une base, le
   script de départ la crée lui-même.
3. **Départ.** Le script applique le schéma, reprend les données du site et crée le compte pilote :

   ```
   /Applications/MAMP/bin/php/php8.3.14/bin/php pupitre/sql/depart.php
   ```

   Il affiche un lien, valable quinze minutes, pour choisir le mot de passe du pilote. Passé ce
   délai : écran de connexion, « Recevoir un lien par mail ».
4. **Ouvrir** `http://localhost:8888/pierrebleuse/pupitre/`.

`php` n'est pas dans le PATH du poste : appeler celui de MAMP par son chemin complet.

### Variables d'environnement

| Variable | Rôle |
|---|---|
| `APP_URL` | adresse de Pupitre, sans barre finale. Sert aux liens envoyés par mail et à la ligne de commande |
| `APP_ENV` | `dev` (le détail des erreurs s'affiche) ou `prod` |
| `BD_HOTE`, `BD_PORT`, `BD_NOM`, `BD_UTILISATEUR`, `BD_MOT_DE_PASSE` | la base. Avec MAMP : `127.0.0.1`, port `8889` |
| `ARTISTE_NOM` | nom de l'artiste, pour la reprise. Vide : lu sur le logo de la page d'accueil du site |
| `PILOTE_EMAIL`, `PILOTE_NOM` | compte pilote créé par la reprise |
| `ANTHROPIC_API_KEY` | clé de l'API d'Anthropic, pour la lecture de l'extrait de l'agence |
| `ANTHROPIC_MODELE` | modèle. Vide : `claude-opus-5-5` |
| `MAILJET_CLE`, `MAILJET_SECRET`, `MAIL_EXPEDITEUR`, `MAIL_EXPEDITEUR_NOM` | envoi des mails par Mailjet. Sans clé, les messages sont écrits dans `stockage/journaux/mails.log` |
| `PHP_CLI`, `PYTHON3`, `FFMPEG` | chemins complets des outils. Vides : cherchés sur le poste |

Aucune de ces valeurs ne sort dans un journal ni dans un message d'erreur.

### Sans Mailjet

Sur le poste local, le lien de connexion de l'artiste et le lien de choix du mot de passe sont
écrits dans `pupitre/stockage/journaux/mails.log` au lieu d'être envoyés. C'est là qu'on les lit.

## La reprise

```
php pupitre/sql/depart.php             base, schéma, reprise, compte pilote
php pupitre/sql/depart.php --schema    le schéma seulement (rejouable, ne détruit rien)
php pupitre/sql/depart.php --forcer    vide les contenus et les reprend ; comptes et journal restent
php pupitre/sql/depart.php --pilote    crée le compte pilote s'il manque, donne son lien
```

La reprise charge tout ce que le site affiche, à partir de ses fichiers de `data/` : les
18 événements de l'agenda (30 représentations, au statut « publiée »), 4 disques, 4 articles de
presse réels, 24 orchestres invités, 4 visuels, 3 titres du lecteur, textes, parcours, contacts,
réseaux, téléchargements. Les faux articles de maquette (`data/presse-exemples.js`) ne sont pas
repris. L'annuaire est amorcé avec les ensembles, salles, solistes, maisons de disques et médias
rencontrés (75 fiches), sans compte social.

Contrôle, à lancer juste après : `php pupitre/tests/equivalence.php`. Une publication faite sans
rien modifier doit redonner des fichiers équivalents à ceux du site.

## Tâches de fond et cron

Une table de tâches (`taches`), exécutée par `cron/taches.php` : lecture d'un extrait par l'IA,
analyse d'une bande-son. Le même script recalcule les alertes une fois par nuit.

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
  pages/                 un dossier par module : agenda, annuaire, contenus, mediatheque, site,
                         import, reglages, journal, artiste ; tableau-de-bord.php ; avenir.php
  ajax/tache.php         avancement d'une tâche de fond
  inclus/                amorce, config, base, connexion, droits, journal, mail, reglages,
                         agenda, annuaire, medias, contenus, publication, taches, ia, import, alertes
  inclus/site/           l'adaptateur du site : export.php (fichiers de data/), repli.php (zones du HTML)
  inclus/consignes/      les consignes données à l'IA
  gabarits/              en-tête, pied, composants de formulaire
  cron/taches.php
  sql/schema.sql         structure complète, rejouable
  sql/depart.php         base, schéma, reprise des données du site
  tests/                 contrôles et essais (voir « Vérifier »)
  stockage/              imports, médias déposés, sauvegardes de publication, analyses, journaux
  ressources/            css et js de l'interface
```

`inclus/`, `sql/`, `stockage/`, `cron/`, `tests/`, `gabarits/` et `pages/` sont interdits au web
par `.htaccess`. `stockage/` est hors git. Tout Pupitre porte `noindex`.

C'est dans `inclus/site/` que Pupitre connaît la forme du site. Si le site change de gabarit, ce
sont ces deux fichiers qu'on adapte.

## Rôles

Les droits sont dans un seul fichier, `inclus/droits.php`, et vérifiés côté serveur à chaque page
et à chaque action.

| Rôle | Qui | Droits |
|---|---|---|
| Pilote | Carrel, direction du projet | tout : réglages, comptes, validations, publication |
| Éditeur | équipe Carrel | agenda, fiches, annuaire, contenus, médias, validation des imports. Ni réglages, ni comptes, ni publication |
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
dans l'agenda du site. Les dates et les heures sont celles de la salle, jamais converties ; le
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

La mise en ligne (envoi vers le serveur, git) reste manuelle.

## Import de l'extrait de l'agence

1. Dépôt d'un PDF, d'un tableur (`.xlsx`, `.csv`) ou d'un texte collé. Le fichier reste dans
   `stockage/imports/`.
2. Lecture en tâche de fond. L'IA rend des engagements dans un JSON que le code contrôle. Un
   échec de contrôle donne une seule relance, puis une erreur lisible.
3. Comparaison avec l'agenda, par le code : nouvelles dates, dates modifiées, dates disparues,
   programmes changés. Rapprochement sur la date et l'ensemble. Un cas douteux (même jour avec un
   autre ensemble, même ensemble à trois jours près) est présenté à la main.
4. Contrôle : lieu, ville ou heure manquants, date hors saison, date passée, doublon probable.
5. Validation ligne par ligne ou en bloc, avec correction possible. Une date validée entre dans
   l'agenda au statut « connue ».

L'appel à l'API d'Anthropic est direct, en cURL (`inclus/ia.php`) : le PDF part en bloc
« document », la réponse est contrainte par un schéma JSON et lue en flux. Les consignes sont dans
`inclus/consignes/lecture-extrait.md`. Chaque appel est journalisé (objet, modèle, jetons, durée,
résultat) dans la table `appels_ia`, visible du pilote au bas du journal. Ni la clé, ni le
document, ni la réponse ne sont écrits dans un journal.

Lire un extrait déjà déposé, depuis le terminal, sans afficher son contenu :

```
php pupitre/tests/extrait.php nom-du-fichier.pdf
```

## Alertes

Recalculées après chaque modification et chaque nuit : représentation à venir sans salle, ville
ou heure ; représentation annoncée sans lien source ; concert à moins de 21 jours dont la fiche
est incomplète ; embargo arrivé à échéance ; date passée restée « connue » ; doublon probable
(agenda, annuaire) ; écart entre Pupitre et le site ; dernier extrait importé il y a plus de
30 jours ; contenu du site resté vide. « Traiter » mène à l'écran où corriger, « Reporter » cache
l'alerte une semaine, « Ignorer » la cache tant que son sujet ne change pas.

## Vérifier

| Commande | Ce qu'elle prouve | Base |
|---|---|---|
| `php pupitre/tests/repli.php` | les zones de repli régénérées sont identiques aux pages, à l'octet près | non |
| `php pupitre/tests/equivalence.php` | après la reprise, une publication ne changerait rien au contenu du site | oui |
| `php pupitre/tests/annonce.php` | le site ne reçoit que les représentations annoncées ; règles des statuts | oui, rien n'est écrit |
| `php pupitre/tests/import.php` | contrôle du JSON, comparaison, contrôle, validation, confidentialité des journaux, sur un extrait fictif et sans appel à l'IA | oui, rien n'est écrit |
| `python3 pupitre/tests/parcours.py` | dans Chrome : chaque écran, parcours complet (modification, concert annoncé, publication, vérification sur le site, retour en arrière), droits des trois rôles, vue de l'artiste à 390 px et au clavier ; refait les captures de `docs/pupitre/captures/` | oui, remise en état à la fin |
| `python3 outils/temoin.py capturer dossier`, puis `comparer` | le rendu du site est identique au témoin (repris le 13/11/2026, après les retours de ce jour) | non |

`tests/annonce.php` et `tests/import.php` travaillent dans une transaction annulée à la fin.
`tests/parcours.py` suppose une base qui sort de la reprise ; il crée trois comptes d'essai en
`.test`, publie, revient en arrière, vérifie avec git que le site n'a pas changé, et retire ses
comptes.

## Choix faits

1. **Identifiant d'un concert.** Un concert créé dans Pupitre reçoit un identifiant tiré de son
   titre, sans date : celui du site portait la date de la première représentation, qui peut ne
   pas être annoncée. Les concerts repris gardent leur identifiant.
2. **Lien source à la reprise.** Quand le site donnait une adresse pour un événement (4 sur 18),
   elle devient la page de l'organisateur et le lien source de ses représentations. Les 14 autres
   n'ont pas de lien source : ils restent « publiés », et leur fiche le signale.
3. **Solistes de l'annuaire.** Toute personne citée dans « avec » entre comme soliste, y compris
   un metteur en scène : la liste des catégories n'a pas d'autre place pour elle. À reclasser.
4. **Orchestres invités.** Une ligne du parcours associe une fiche de l'annuaire au lieu où la
   placer (table `parcours_orchestres`) : la ville du concert, pas le siège de l'orchestre. Une
   même fiche peut y figurer plusieurs fois. Une fiche dont le nom est « à fournir » ne montre que
   sa ville sur le site. Une base créée avant le 13/11/2026 est mise à niveau par
   `sql/depart.php --schema`.
5. **Visuels.** Seul le média est entre repères. Le type, l'opacité et la place de la nuée sont
   des classes de la section, posées par le script du site.
6. **Fuseau de la salle.** Déduit du pays quand il n'en a qu'un, ou de la ville quand un fuseau
   porte son nom ; sinon il reste à choisir sur la fiche (Porto, Valladolid aujourd'hui).
7. **Faux articles de presse.** Le site les charge encore : ils masquent les articles que Pupitre
   publie. L'écran « Presse » le rappelle.
8. **Disques et liens.** Les liens « Commander » et « Écouter » se saisissent un par ligne,
   « Nom | adresse ».
9. **Modèle de l'IA.** `claude-opus-5-5` par défaut, effort élevé, avec repli sur un autre modèle
   si les filtres de sécurité refusent la demande.

## Ce qui reste à faire

- **Lecture réelle d'un extrait.** L'appel à l'API n'a pas tourné : ni clé ni extrait n'étaient
  disponibles. Tout ce qui suit la réponse de l'IA est testé sur un extrait fictif.
- Renseigner Mailjet pour que les liens partent par mail.
- Installer le cron sur le serveur.
- Compléter les fiches : liens source, billetteries, fuseaux de Porto et de Valladolid.
- Retirer les faux articles de presse du site avant la mise en ligne.
- Hors de cette version, avec une place gardée dans le menu et dans la base : inscription à la
  lettre, mails automatiques, liens de dépôt pour les photographes, publications par Metricool,
  visuels générés, veille et revue de presse, statistiques, plusieurs artistes, mise en ligne
  automatique.

## Poste de développement (relevé du 12/11/2026)

| Outil | Version | Où |
|---|---|---|
| PHP servi par Apache | 8.3.14 | MAMP, `http://localhost:8888/` |
| PHP en ligne de commande | 8.3.14 et 8.4.1 | `/Applications/MAMP/bin/php/php8.3.14/bin/php` (absent du PATH) |
| MySQL | 8.0.40 | MAMP, port 8889 |
| Python | 3.15.2 | `/opt/homebrew/bin/python3` |
| ffmpeg | 8.0 | `/opt/homebrew/bin/ffmpeg` |
| Chrome | pour les tests | piloté par `outils/cdp.py` (Node n'est pas installé) |

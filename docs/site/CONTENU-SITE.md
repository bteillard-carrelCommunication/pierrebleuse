# Site Pierre Bleuse, contenus de la page d'accueil

Version du 12 novembre 2026. Contenus réels, en français et en anglais, pour remplacer le faux-texte de `site/index.html`.
Les données de l'agenda sont dans `site/data/agenda.json`.

Conventions :
- `[À CONFIRMER]` signale un point à valider avec Pierre Bleuse. La mention ne s'affiche jamais sur la page, elle est reportée dans le LISEZ-MOI.
- Aucun texte, aucune date, aucune citation ne doit être ajouté en dehors de ce fichier et de `site/data/agenda.json`.
- Typographie française : espaces insécables avant `: ; ? !` et dans les guillemets « ».

---

## 0. Méta

| Clé | Français | English |
|---|---|---|
| title | Pierre Bleuse, chef d'orchestre | Pierre Bleuse, conductor |
| description | Site officiel de Pierre Bleuse, chef d'orchestre : agenda des concerts, biographie, disques, presse, contacts. | Official website of conductor Pierre Bleuse: concert schedule, biography, recordings, press, contacts. |

## 1. Navigation

Logo « Pierre Blɘusɘ » inchangé, lien vers le haut de la page d'accueil.

| Ancre | Français | English |
|---|---|---|
| #agenda | Agenda | Concerts |
| #parcours | Parcours | Biography |
| #ecouter | Écouter, voir | Listen, watch |
| #presse | Presse | Press |
| #contact | Contact | Contact |

Bascule de langue : `FR | EN`.

## 2. Ouverture (le noir, la nuée)

| Clé | Français | English |
|---|---|---|
| surtitre | Chef d'orchestre | Conductor |
| ligne 1 | Directeur musical de l'Ensemble intercontemporain | Music Director, Ensemble intercontemporain |
| ligne 2 | Directeur artistique du Festival Pablo Casals de Prades | Artistic Director, Pablo Casals Festival, Prades |
| prochain, libellé | Prochain concert | Next concert |
| prochain, le jour même | Ce soir | Tonight |
| prochain, la veille | Demain | Tomorrow |
| prochain, lien | Voir l'agenda | See all concerts |
| écouter | Écouter | Listen |
| crédit audio | Tchaïkovski, *Pathétique*, IV, extrait, domaine public | Tchaikovsky, *Pathétique*, IV, excerpt, public domain |
| son | Son : coupé, inaudible, audible | Sound: muted, inaudible, audible |
| défiler | Défiler | Scroll |

La ligne « Prochain concert » est calculée (voir les règles de l'agenda). Exemple au 12 novembre 2026 :
« Prochain concert · samedi 14 novembre · Paris, Cité de la musique · EIC & Friends, Bertrand Chamayou ».

## 3. Présentation (section noire, « respiration »)

**Paragraphe 1**

FR : Pierre Bleuse est directeur musical de l'Ensemble intercontemporain et directeur artistique du Festival Pablo Casals de Prades. Chef principal de l'Orchestre symphonique d'Odense de 2021 à 2026, il est l'invité d'orchestres comme le Royal Concertgebouw Orchestra, le London Philharmonic Orchestra, l'Orchestre de Paris ou le Tokyo Symphony Orchestra.

EN: Pierre Bleuse is Music Director of the Ensemble intercontemporain and Artistic Director of the Pablo Casals Festival in Prades. Chief Conductor of the Odense Symphony Orchestra from 2021 to 2026, he is a guest of orchestras including the Royal Concertgebouw Orchestra, the London Philharmonic Orchestra, the Orchestre de Paris and the Tokyo Symphony Orchestra.

`[À CONFIRMER]` Intitulé et dates du mandat à Odense (concert « Au revoir, Pierre » le 19 novembre 2026).

**Citation presse** (bloc `.citation`)

| Clé | Français | English |
|---|---|---|
| label | La presse | In the press |
| texte | “A simply astonishing disc.” | “A simply astonishing disc.” |
| source | Colin Clarke, *Fanfare*, à propos de *Ravel Abroad* | Colin Clarke, *Fanfare*, on *Ravel Abroad* |

`[À CONFIRMER]` Citation relevée sur le compte Instagram de Pierre Bleuse : à vérifier sur l'article d'origine (texte exact, date, droit de citation) avant mise en ligne.

**Paragraphe 2**

FR : Violoniste de formation, il dirige le grand répertoire symphonique et la musique d'aujourd'hui avec la même attention : faire dialoguer les œuvres plutôt que les opposer.

EN: A violinist by training, he brings the same attention to the great symphonic repertoire and to the music of today, letting works speak to one another rather than setting them apart.

## 4. Première photo, « En concert » (`assets/img/bleuse-3.jpg`)

| Clé | Français | English |
|---|---|---|
| alt | Pierre Bleuse dirigeant l'orchestre en concert | Pierre Bleuse conducting the orchestra in concert |
| texte | Saison 2026-2027 : Mahler, Debussy et Ravel, et des créations de Du Yun, Bastien David et Joan Magrané Figuera. | 2026/27 season: Mahler, Debussy and Ravel, and premieres by Du Yun, Bastien David and Joan Magrané Figuera. |

## 5. Agenda

| Clé | Français | English |
|---|---|---|
| titre | Agenda | Concerts |
| mention | Saison 2026-2027 | 2026/27 season |
| bloc 1 | À l'affiche | Coming up |
| bloc 2 | La saison | The season |
| bloc 3 | Derniers concerts | Recent concerts |
| temps fort | Temps fort | Highlight |
| série | {n} représentations, prochaine le {date} | {n} performances, next on {date} |
| tournée | Tournée, {n} villes | On tour, {n} cities |
| programme | Programme | Programme |
| avec | Avec | With |
| lien | Informations et billetterie | Details and tickets |
| disque | Disque | Recording |
| vide | Les prochaines dates seront annoncées ici. | Upcoming dates will be announced here. |
| archives | Toutes les archives | All past concerts |

Données : `site/data/agenda.json`. Règles d'affichage : voir le prompt, partie « Agenda ».

## 6. Parcours (section noire)

| Clé | Français | English |
|---|---|---|
| titre | Parcours | Biography |

**Fonctions / Positions**

| Français | English |
|---|---|
| Directeur musical, Ensemble intercontemporain, depuis 2023 | Music Director, Ensemble intercontemporain, since 2023 |
| Directeur artistique, Festival Pablo Casals de Prades, depuis 2021 | Artistic Director, Pablo Casals Festival, Prades, since 2021 |
| Chef principal, Orchestre symphonique d'Odense, 2021 à 2026 | Chief Conductor, Odense Symphony Orchestra, 2021 to 2026 |

**Orchestres invités (sélection) / Guest conducting (selection)**

Liste identique dans les deux langues, dans cet ordre :
Royal Concertgebouw Orchestra, London Philharmonic Orchestra, BBC Symphony Orchestra, City of Birmingham Symphony Orchestra, Orchestre de Paris, Orchestre national de France, NDR Elbphilharmonie Orchester, hr-Sinfonieorchester Frankfurt, SWR Symphonieorchester, Royal Stockholm Philharmonic Orchestra, Swedish Radio Symphony Orchestra, Finnish Radio Symphony Orchestra, Orchestre de la Suisse Romande, Tokyo Symphony Orchestra, Singapore Symphony Orchestra, Orquestra Sinfônica do Estado de São Paulo, Auckland Philharmonia.

**Opéra et création / Opera and new music**

| Français | English |
|---|---|
| *Red Fox* de Du Yun, création mondiale, Dutch National Opera, 2027 | *Red Fox* by Du Yun, world premiere, Dutch National Opera, 2027 |
| *Répons* de Pierre Boulez, ouverture du centenaire, Philharmonie de Paris, 2025 | *Répons* by Pierre Boulez, opening of the centenary, Philharmonie de Paris, 2025 |
| BBC Proms avec l'Ensemble intercontemporain, Royal Albert Hall, 2025 | BBC Proms with the Ensemble intercontemporain, Royal Albert Hall, 2025 |
| *Orgia* d'Hèctor Parra, Teatro Arriaga de Bilbao, 2023, Gran Teatre del Liceu de Barcelone, 2024 | *Orgia* by Hèctor Parra, Teatro Arriaga, Bilbao, 2023, Gran Teatre del Liceu, Barcelona, 2024 |

**Formation / Training**

FR : Violon au Conservatoire national supérieur de musique et de danse de Paris, dans la classe de Patrice Fontanarosa, puis à l'Université des arts de Berlin. Direction d'orchestre auprès de Jorma Panula, puis à la Haute École de musique de Genève, dans la classe de Laurent Gay.

EN: Violin at the Paris Conservatoire (CNSMDP) with Patrice Fontanarosa, then at the Berlin University of the Arts. Conducting with Jorma Panula, then at the Haute École de musique de Genève with Laurent Gay.

**Liens**

| Clé | Français | English |
|---|---|---|
| bio complète | Lire la biographie complète | Read the full biography |
| téléchargement | Télécharger la biographie (français, anglais) | Download the biography (French, English) |

Pour l'instant, ces deux liens sont inertes (`aria-disabled`, mention « à venir » / “coming soon”).

## 7. Deuxième photo et « Écouter, voir »

Photo : une image de `site/assets/img/` pas encore utilisée dans la page (bleuse-1, 2, 4 ou 6), à choisir pour son cadrage, avec point focal réglé en portrait.

| Clé | Français | English |
|---|---|---|
| titre | Écouter, voir | Listen, watch |
| disques | Disques | Recordings |
| vidéos | Vidéos | Videos |

**Disques / Recordings** (du plus récent au plus ancien)

| Titre | Français | English |
|---|---|---|
| *Ravel Abroad* | Orchestre symphonique d'Odense, OUR Recordings, 2026 | Odense Symphony Orchestra, OUR Recordings, 2026 |
| *Unsuk Chin* | Ensemble intercontemporain, Alpha Classics, 2026 | Ensemble intercontemporain, Alpha Classics, 2026 |
| *Viola Concertos* | Rafaell Altino, Orchestre symphonique d'Odense, Dacapo, 2026 | Rafaell Altino, Odense Symphony Orchestra, Dacapo, 2026 |
| *Ligeti* | Ensemble intercontemporain, Alpha Classics, 2024. Diapason d'or de l'année, Choc de *Classica* | Ensemble intercontemporain, Alpha Classics, 2024. Diapason d'or of the year, Choc de *Classica* |

Lien connu : *Ravel Abroad*, https://www.ourrecordings.com/albums/ravel-abroad. Les autres liens d'écoute sont à fournir.

**Vidéos / Videos** (liens simples, pas de lecteur intégré pour l'instant)

| Titre | Lien |
|---|---|
| Tchaïkovski, Symphonie n° 5 / Tchaikovsky, Symphony No. 5 | https://www.youtube.com/watch?v=yqHAV-AwNRg |
| Saint-Saëns, Concerto pour violoncelle n° 2 / Saint-Saëns, Cello Concerto No. 2 | https://www.youtube.com/watch?v=nHRm_K8fNVI |

`[À CONFIRMER]` Sélection de vidéos reprise de la page de l'agence artistique : à valider et à compléter (orchestre, date, droits).

## 8. Presse (section noire)

| Clé | Français | English |
|---|---|---|
| titre | Presse | Press |
| lien | Toute la revue de presse | All press |

Une seule citation réelle est disponible (celle de la partie 3). Les trois emplacements suivants affichent la référence, sans citation, en attendant le choix des extraits :

| Média | Français | English |
|---|---|---|
| *Le Monde* | Portrait, Pierre Gervasoni, 22 juin 2024 | Profile, Pierre Gervasoni, 22 June 2024 |
| *The Guardian* | Critique du disque *Unsuk Chin*, Fiona Maddocks, 13 février 2026 | Review of the *Unsuk Chin* album, Fiona Maddocks, 13 February 2026 |
| *Gramophone* | Critique du disque *Ligeti*, Richard Whitehouse, juin 2024 | Review of the *Ligeti* album, Richard Whitehouse, June 2024 |

Liens des articles :
- https://www.lemonde.fr/culture/article/2024/06/22/pierre-bleuse-directeur-musical-de-l-ensemble-intercontemporain-veut-faire-entendre-tout-ce-qui-se-passe-sur-la-planete_6242263_3246.html
- https://www.theguardian.com/music/2026/feb/13/ensemble-intercontemporain-unsuk-chin-album-review-pierre-bleuse-samuel-favre-dimitri-vassilakis-gougalon-double-concerto
- https://gramophone.co.uk/review/ligeti-concertos-and-chamber-music-bleuse

## 9. Espace professionnel et contact (section noire)

| Clé | Français | English |
|---|---|---|
| titre | Espace professionnel | For professionals |
| texte | Biographies, photographies en haute définition et éléments graphiques à l'usage des orchestres, des salles, des festivals et de la presse. | Biographies, high-resolution photographs and graphic material for orchestras, venues, festivals and the press. |
| item 1 | Biographies, français et anglais, versions courte et longue | Biographies, French and English, short and long versions |
| item 2 | Photographies HD, avec crédits | High-resolution photographs, with credits |
| item 3 | Éléments graphiques | Graphic material |
| mention | À venir | Coming soon |
| titre contact | Contact | Contact |
| management | Management : HarrisonParrott, François Guyard | Management: HarrisonParrott, François Guyard |
| communication | Communication et presse : Carrel Communication | Communications and press: Carrel Communication |

Liens : management, https://www.harrisonparrott.com/artists/pierre-bleuse. Communication : adresse dédiée à créer, lien inerte pour l'instant. Aucun numéro de téléphone sur la page.

## 10. Pied de page (`assets/img/bleuse-5.jpg`)

| Clé | Français | English |
|---|---|---|
| alt | Pierre Bleuse accroupi sur la scène, près du clavecin | Pierre Bleuse crouching on stage, beside the harpsichord |
| lettre, titre | La lettre | Newsletter |
| lettre, texte | Les concerts du mois et les actualités, une fois par mois. | The month's concerts and news, once a month. |
| lettre, bouton | S'inscrire (à venir) | Subscribe (coming soon) |
| réseaux | Instagram, Facebook, YouTube, LinkedIn | Instagram, Facebook, YouTube, LinkedIn |
| ligne | Pierre Bleuse · Mentions légales · Confidentialité · Crédits photographiques | Pierre Bleuse · Legal notice · Privacy · Photo credits |
| crédit | Site en construction · bande-son de test : Tchaïkovski, Symphonie n° 6, IV, Musopen (domaine public) | Website in progress · test soundtrack: Tchaikovsky, Symphony No. 6, IV, Musopen (public domain) |

Liens des réseaux : Instagram, https://www.instagram.com/pierrebleuse/ ; Facebook, https://www.facebook.com/pierre.bleuse ; YouTube et LinkedIn, à fournir (liens inertes).
Le formulaire d'inscription est inerte pour l'instant (pas de collecte de données).

---

## Points à confirmer (à reporter dans le LISEZ-MOI)

1. Odense : intitulé et dates du mandat après le concert du 19 novembre 2026.
2. Citation de *Fanfare* : texte exact, date, droit de citation.
3. Choix des trois extraits de presse (Le Monde, The Guardian, Gramophone).
4. Vidéos : sélection, crédits, droits.
5. Liens d'écoute des disques, comptes YouTube et LinkedIn.
6. Adresse de contact communication.
7. Photographies : le site utilise pour l'instant les images en 992 px, en attente des HD et des crédits.

---

## 11. Évolutions du 12 novembre 2026 (site à la racine, matrice 12 colonnes)

La page suit désormais le tableau des sections du 12/11/2026 (voir `LISEZ-MOI.md`). Textes ajoutés
ou modifiés ; le dictionnaire de `js/contenu.js` et les fichiers de `data/` en sont la copie.

**Navigation** : Agenda / Concerts, Disques / Recordings, Parcours / Biography, Presse / Press,
Téléchargement / Downloads, Contact / Contact.

**Ouverture**

| Clé | Français | English |
|---|---|---|
| citation | « Plus je progresse, plus je me sens libre » | “The further I progress, the freer I feel” `[À CONFIRMER]` traduction proposée |
| lecteur | Play, Pause | Play, Pause |
| liste des titres | Choisir la musique | Choose the music |

Le surtitre, les deux fonctions et la ligne « Prochain concert » ne figurent plus dans l'ouverture.

**Musiques** (`data/playlist.js`)

| Titre | Mention |
|---|---|
| Tchaïkovski, *Pathétique*, IV | Extrait, domaine public / Excerpt, public domain |
| Berlioz, *Symphonie fantastique* | Pierre Bleuse, SWR Symphonieorchester · ARD Klassik |
| The Centenary of Pierre Boulez | Ensemble intercontemporain & Friends · ARTE Concert |

`[À CONFIRMER]` Droits des deux captations (ARD Klassik, ARTE Concert) avant mise en ligne.

**Agenda** : En savoir plus / Find out more ; Tous les concerts / All concerts ; en-têtes de
tableau : Mois / Month, Date / Date, Pays, ville / Country, city, Salle / Venue, Orchestre,
programme / Orchestra, programme ; page des concerts : À venir / Upcoming, Concerts passés / Past
concerts.

**Disques** : Disques / Recordings ; Le mot de Pierre Bleuse / A word from Pierre Bleuse ;
Commander / Order ; Écouter / Listen.

**Parcours** : cartes Monde / World et Europe / Europe. Villes des orchestres invités (étiquettes
des points) : Amsterdam, Londres / London, Birmingham, Paris, Hambourg / Hamburg, Francfort /
Frankfurt, Stuttgart, Stockholm, Helsinki, Genève / Geneva, Tokyo, Singapour / Singapore, São
Paulo, Auckland.

**Presse** : Lire l'article / Read the article ; Tous les articles / All articles ; en-têtes :
Média / Publication, Article / Article, Auteur / Author, Extrait / Quote. Nature des articles :
À propos de *Ravel Abroad* / On *Ravel Abroad* (*Fanfare*), Portrait / Profile (*Le Monde*).

**Téléchargement** (ex « Espace professionnel ») : Téléchargement / Downloads ; Biographie, en
français / Biography, in French ; Biographie, en anglais / Biography, in English ; Photographies /
Photographs ; Identité visuelle / Visual identity ; à venir / coming soon.

**Suivre** : Suivre Pierre Bleuse / Follow Pierre Bleuse (la lettre et les réseaux, inchangés).

**Contacts** (fournis le 12/11/2026)

- HarrisonParrott, Worldwide general management
- François Guyard, Senior Director, General Manager HarrisonParrott Paris, +33 660 73 72 89,
  francois.guyard@harrisonparrott.fr
- Yeijin Park, Artist Coordinator, +33 (0)7 62 35 03 38, yeijin.park@harrisonparrott.co.uk
- Communication / Communications : Carrel Communication `[À CONFIRMER]` coordonnées

**Pied de page** : Site en construction · musiques : Tchaïkovski, Symphonie n° 6, IV, Musopen
(domaine public) ; Berlioz, *Symphonie fantastique*, SWR Symphonieorchester (ARD Klassik) ; *The
Centenary of Pierre Boulez*, Ensemble intercontemporain & Friends (ARTE Concert). Réglages de la
nuée / Cloud settings ; Pause de l'animation / Pause the animation ; Reprendre l'animation /
Resume the animation.

**Pages secondaires** : Retour à l'accueil / Back to home.

**Sortis de la page d'accueil** (textes conservés plus haut) : la citation de presse de la partie
3, la phrase de la partie 4, « Formation » et les deux liens de la partie 6, les vidéos de la
partie 7, la phrase d'introduction de la partie 9.


## 12. Retours de l'atelier (12/11/2026, fin d'après-midi) : libellés modifiés

Les libellés ci-dessous remplacent ceux de la partie 11. Source : `js/contenu.js`.

| Emplacement | Français | Anglais |
|---|---|---|
| Menu et titre de section | Discographie | Discography |
| Agenda, tableau | Les concerts | The concerts |
| Agenda, fiche | En savoir plus | Find out more |
| Section « Suivre » | Restons en contact | Stay in touch |
| Lettre, champ | Adresse e-mail | Email address |
| Lettre, bouton | S'inscrire | Subscribe |
| Lettre, mention | Inscription bientôt ouverte | Sign-up opening soon |
| Ouverture, pictogramme de défilement | Faire défiler la page | Scroll down |
| Bouton fixe | Haut de page | Back to top |
| Pied de page | Mentions légales · Confidentialité · Crédits | Legal notice · Privacy · Credits |
| Titres des pages légales | Mentions légales ; Politique de confidentialité ; Crédits | Legal notice ; Privacy policy ; Credits |
| Vidéo du visuel 3 | Pierre Bleuse en concert, extraits d'une captation | Pierre Bleuse in concert, excerpts from a filmed performance |

**Retirés de l'affichage** : « Temps fort » / Highlight (le drapeau reste dans les données),
« Derniers concerts » / Recent concerts (les concerts passés sont sur `concerts.html`), le titre
« Parcours » (gardé pour les lecteurs d'écran), les noms des cartes.

**Presse, champ ajouté** : `resume`, court résumé de l'article, en français et en anglais. Vide
pour les quatre articles : `[À FOURNIR]`.

**Contacts, communication** (fournis le 12/11/2026) : Carrel Communication, +33 6 89 90 57 31,
communication@pierrebleuse.com.

**Pages légales** : textes complets, en français et en anglais, dans `mentions-legales.html`,
`confidentialite.html` et `credits.html`. Les passages `[À COMPLÉTER]` y sont balisés
`<mark class="a-completer">` ; la liste est dans `LISEZ-MOI.md`, « Pages légales ».

### Retours du 12/11/2026 au soir

- Marges latérales de la matrice multipliées par 1,7 (ordinateur et tablette ; téléphone inchangé).
- Parcours : le bandeau de la carte du monde a un fond noir à 90 % sur toute la largeur ; Salt
  Lake City ajoutée à la carte (point seul, nom de l'orchestre à fournir).
- Visuel 3 : vidéo opaque à 95 %, nuée visible à 5 % au travers (`nuee: 'arriere-plan'`).
- Contacts : numéros au même format (+33 6 60 73 72 89, +33 7 62 35 03 38, +33 6 89 90 57 31).
- Rien n'est commité.

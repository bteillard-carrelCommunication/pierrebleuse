# Hypothèse 2 — Tutti, cent voix, un geste

**Pitch.** La piste du DA, prise au mot : **un point de lumière par musicien**.
Quatre-vingt-seize points (la taille d'un orchestre symphonique), quatre familles
(cordes, bois, cuivres, percussions) aux teintes de la graduation chromatique,
vivent sur la page comme une respiration. Le scroll est le geste du chef : il les
rassemble, et les photos apparaissent **à travers eux**. Le site donne à voir la
musique ; il est vivant même quand on ne touche à rien.

**Ce que ça dit de l'excellence.** Un chef n'est rien seul : l'excellence, c'est
cent voix qui deviennent un geste. Les points sont aussi les « voix extérieures »
(musiciens, institutions, presse) qui composent sa reconnaissance. Accessoirement,
la musique rendue visible (piste du DA, accessibilité aux malentendants).

## Système

- **Particules** : N = 96 sur ordinateur, 64 sur mobile. Disques doux (texture
  radiale générée sur un canvas, fusion additive), rayon 2 à 5 px CSS, légère
  scintillation (opacité ± 15 %, période 2 à 5 s, phases aléatoires). Quatre
  familles : cordes 56 (crème #EFE6D3), bois 16 (lavande #B59AC8), cuivres 14 (cyan
  #7FD3E3), percussions 10 (vert profond #6FA08A). Teintes à ajuster d'après
  `../_ref/graduation-chromatique-*.jpg`.
- **Vie au repos** (temps réel, indépendant du scroll) : dérive lente par bruit
  (curl noise ou somme de sinus), vitesse ≤ 12 px/s, chaque point a sa période ;
  le pointeur (souris ou doigt) est un **souffle** : les points s'écartent
  doucement dans un rayon de 140 px (force faible, retour lent de 2 à 3 s).
- **Pupitres** (règle 2) : chaque point a un retard propre (0 à 0,6 de la course)
  tiré par famille (les cordes partent en premier, puis bois, cuivres, percussions)
  pour que les formations se fassent par vagues.
- **Formations** (fonction exacte du scroll, règle 11) : la position de chaque point
  = interpolation entre la position « libre » (dérive) et la position « cible » de
  la formation, par une courbe asymétrique (règle 3) de la progression de la
  section, avec son retard de pupitre.

## Les cinq temps

1. **Ouverture (bleuse-1).** En haut de page, écran noir, les points dérivent
   librement, la photo est invisible. Sur la première hauteur d'écran de scroll :
   les points **se rassemblent sur le sujet** (cibles tirées dans le masque
   `bleuse-1-masque.png`, plus dense sur le visage) et **chaque point est une
   fenêtre douce** qui révèle la photo (rendre les disques dans une RenderTexture
   utilisée comme masque de la photo) ; puis les fenêtres grandissent et fusionnent
   (rayon de masque × 6) jusqu'à révéler tout le sujet ; enfin le fond (bokeh)
   monte doucement (fondu 0 → 1 sur la dernière partie). Les stries du DA
   apparaissent à la fin comme des **colonnes où les points s'alignent** (les
   points de cordes se rangent en 4 colonnes verticales à gauche, aux positions des
   stries du preset bleuse-1 : x 0,18–0,35) ; à l'intérieur des colonnes, la photo
   pivotée de 180° transparaît à 0,5 (reprendre le principe du shader existant ou
   un second sprite retourné masqué par les colonnes). Effet complet à 1 hauteur
   d'écran de scroll ; en remontant, tout se défait à l'identique.
2. **Respiration (noir).** Les points forment le **plan de salle d'un orchestre**
   (éventail : cordes devant, bois au centre, cuivres derrière, percussions au
   fond ; le chef = un point crème plus grand au centre bas, immobile). Une petite
   légende « Cordes · Bois · Cuivres · Percussions » avec les quatre teintes. Deux
   blocs de lorem + un emplacement de citation presse qui entrent par lignes
   (règle 12) UNE FOIS la formation posée (règle 8).
3. **Deuxième photo (bleuse-3).** Les points se rangent en **colonnes** aux
   positions de la grille du DA (reprendre `stripeGrid` du preset bleuse-3 : 14
   stries, vides autour du chef) et révèlent dans chaque colonne la photo pivotée
   teintée lavande (50 % photo 180° + 50 % #5A638D), le chef restant devant
   (masque `bleuse-3-masque.png`). Les colonnes naissent de l'alignement des
   points (d'abord quelques points par colonne, puis la colonne « se remplit »),
   jamais d'un volet.
4. **Agenda = constellation.** Les points se répartissent sur une ligne de temps
   horizontale ; cinq points plus grands et plus lumineux = les cinq temps forts
   (titre serif, lieu et date en petites capitales), qui s'allument quand le scroll
   les atteint. Mention « exemples ».
5. **Pied de page (bleuse-5).** Le sol de cette photo est couvert de **taches de
   lumière** : les points viennent s'y poser (cibles = pixels clairs de la photo
   dans la moitié basse, échantillonnés au chargement depuis un canvas 2D), puis
   les **deux voiles** du DA apparaissent comme deux colonnes de lumière formées
   par les points (plume large, crème chaud #F3EAD8 à 0,45), gauche depuis le
   haut, droite depuis le bas.

## Implémentation (indications, pas des obligations)

- Pixi 7 : un `ParticleContainer` (ou un `Container` de sprites, 96 suffisent) avec
  `blendMode ADD` ; une `RenderTexture` « masque » où l'on dessine les disques
  blancs agrandis, appliquée à la photo (`sprite.mask = maskSprite`) ; un second
  sprite = photo pivotée (`scale.y = -1`, `scale.x = -1`) teintée, masqué par les
  colonnes. Boucle continue (ambiance vivante) mais **mise en pause hors écran et
  onglet caché**, et `app.ticker.maxFPS = 60`.
- Les cibles de formation sont précalculées au chargement (masque, plan de salle,
  colonnes, ligne de temps, taches de lumière) ; le scroll ne fait qu'interpoler.
- Un seul `PIXI.Application` par photo (trois), canvas décoratifs
  (`pointer-events: none`), `<img>` de repli sous chacun.
- `prefers-reduced-motion` : points immobiles (formations posées), photos en fondu.

## Réglages exposés

`N`, teintes par famille, rayon, vitesse de dérive, rayon du souffle, `spread`
des pupitres, rayon des fenêtres, durées de chaque temps.

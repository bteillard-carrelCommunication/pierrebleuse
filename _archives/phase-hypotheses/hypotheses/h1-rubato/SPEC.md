# Hypothèse 1 — Rubato, le temps musical

**Pitch.** Un chef, c'est quelqu'un qui décide *quand*. Le site garde les
compositions du DA (bandeau, stries, voiles) mais les **phrase** comme une
partition : anacrouse, attaque douce, résolution longue, entrées en pupitres,
rubato. La performance, c'est la maîtrise du temps.

**Ce que ça dit de l'excellence.** La justesse du tempo : rien n'est précipité,
rien n'est sec, tout arrive au bon moment. L'identité V4 (inversions, lignes
verticales) est intacte ; seule la manière d'arriver change.

## Point de départ

Copier `../../js/stripes-effect.js`, `../../js/main.js` et `../../css/styles.css`
dans ce dossier (`js/`, `css/`) et les faire évoluer. Le moteur fait déjà :
compositions desktop/portrait, stries « derrière » le sujet par masque, bandeau,
voiles, bicubique, rendu à la demande, ralenti sans épinglage. Les compositions
(positions des stries, du bandeau, des voiles) restent celles des presets
`bleuse-1`, `bleuse-3`, `bleuse-5` : c'est le **mouvement** qui change.

## Ce qu'il faut changer dans le shader et le pilotage

1. **Anacrouse + asymétrie** (règles 1, 3) : remplacer `ease()` (easeInOutCubic
   symétrique) par une courbe asymétrique : `q = easeOutQuart(easeInSine(t))`-like,
   avec un plateau quasi nul sur le premier tiers. Même courbe pour les stries, le
   bandeau et les voiles.
2. **Pupitres** (règle 2) : étaler les retards : `spread` ≥ 0,6 (contre 0,4), ordre
   du sujet vers les bords, par paires ; pour le bandeau, les quatre segments
   n'entrent plus deux à deux symétriques mais en 1 → 3 → 2 → 4 avec des retards
   0 / 0,18 / 0,32 / 0,5.
3. **Lumière avant découpe** (règle 7) : une strie commence par une **variation de
   luminance** (± 8 %) à l'emplacement de la strie, large et diffuse, puis la
   découpe (photo pivotée) monte en opacité, puis le bord se resserre. Dans le
   shader : `m = max(lightPass, cutPass)` avec deux progressions décalées.
4. **Bords vivants** (règle 4) : `uAA` dynamique : plume = mix(8 % de la largeur de
   strie, 1,5 px, q²) ; le `reveal()` a un `soft` qui passe de 0,22 à 0,05 au fil de q.
5. **Rubato interne** (règle 5) : la photo pivotée glisse sur ≤ 3 % de hauteur avec
   une courbe différente (plus lente) que le bord ; le bandeau : ses segments
   glissent horizontalement de 2 % avec un retard sur le volet.
6. **Respiration** (règle 6) : échelle du mesh ± 0,3 % sur 8 s (sinus), plus un
   très léger déplacement de la lumière ; désactivée en reduced-motion. Rendu
   continu seulement pendant que la photo est visible ET que la respiration est
   active (sinon à la demande).
7. **Graduation chromatique** (règle 7) : pendant la construction de l'effet, la
   photo glisse doucement vers une des gradations du DA : bleuse-1 → crème / sépia
   chaud (`mix(col, duotone(col, #3B2A1E, #EFE6D3), 0.35·q)`), bleuse-3 → lavande
   (`#5A638D` déjà dans les stries ; le fond glisse vers un bleu nuit froid),
   bleuse-5 → les voiles sont crème légèrement chaud (#F3EAD8 à 0,45), pas blanc
   pur, et le noir et blanc glisse vers un sépia doux sous les voiles.
8. **Typographie** (règle 12) : les blocs de lorem entrent par lignes (découper le
   paragraphe en `<span>` par ligne avec un petit script, ou `clip-path` par ligne)
   avec flou → net ; pilotés par le scroll (classe + variable CSS `--p`), pas par
   un chronomètre.
9. **Agenda = partition** : une portée (une ligne horizontale fine, couleur
   crème à 30 %) qui se « trace » avec le scroll (dasharray), cinq temps forts posés
   comme des notes (un point + titre serif + lieu/date en petites capitales),
   qui entrent en pupitres (règle 2) quand la ligne les atteint. Mention « exemples ».
10. **Ralenti** : garder le ralenti continu existant (`linger`), mais l'adoucir :
    `linger` 0,5, `lingerSpan` 1,0.

## Mobile (portrait)

Compositions portrait existantes ; vérifier que la lumière-avant-découpe reste
lisible sur 390 px de large. Texte en bas sur dégradé sombre pour bleuse-3 et
bleuse-5 (déjà en place dans `styles.css`).

## Réglages exposés

Dans `LISEZ-MOI.md`, lister les paramètres (spread, plume, amplitude du rubato,
respiration, teintes de gradation, durées) et où ils se règlent.

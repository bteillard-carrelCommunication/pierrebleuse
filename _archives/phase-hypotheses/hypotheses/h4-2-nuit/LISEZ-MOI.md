# Hypothèse 4-2 — Nuit (la nuée seule) · LISEZ-MOI

Variante de l'hypothèse 4 construite le 28/10/2026 : **un fond noir à la place de la photo**.
La nuée d'étourneaux vole seule, conduite par la musique puis apaisée par le scroll, comme dans
H4 ; les sections sous l'ouverture (respiration, deuxième photo, agenda, pied de page) sont les
mêmes. Le moteur, ses réglages et leurs valeurs sont décrits dans `../h4-etourneaux/LISEZ-MOI.md`.

Ouvrir : `http://localhost:8888/pierrebleuse/hypotheses/h4-2-nuit/` (MAMP).

## Ce qui change par rapport à H4

- **Pas d'image** dans l'ouverture : le cadre est noir. La nuée garde le **cadrage de H4** : un
  rectangle 16:9 virtuel en cover, point focal 60 % 40 % (62 % 40 % en portrait), lu dans
  `--focal-x` / `--focal-y` du cadre. Les figures sont donc à la même place et à la même échelle
  que sur la photo : la comparaison avec H4 est directe.
- **Son** : la bande-son de test de H4 (`<audio>`, rien n'est téléchargé avant « Écouter »),
  horloge virtuelle puis média, comme le fond « image fixe » de H4.
- Ni visage dégagé, ni passage derrière le chef, ni masque ▶ (il n'y a pas de chef à l'écran) :
  ces réglages sont cachés et neutralisés. La référence A/B (`volN_layerUp.png` en « éclaircir »)
  reste disponible, sur le noir.
- Un seul fond : pas de choix de fond dans le panneau.
- Réglages mémorisés à part : `localStorage`, clé `h4-nuit-reglages-v3`.

Valeurs par défaut : celles de H4 (`../h4-etourneaux/js/parametres.js`, configuration du 28/10
commune aux trois pages), errance comprise : la nuée voyage d'un côté à l'autre du cadre au lieu
de rester au centre. Une variante pourrait avoir les siennes par `defauts` dans `window.H4Variante`.

## Paramètres d'URL

Ceux de H4, sauf `?fond=` : `?figure=traine|masse|voile|fil`, `?hud=1`, `?t=96`, `?nolenis=1`,
`?reduit=1`, `?panneau=1`.

## Fichiers

| Fichier | Rôle |
|---|---|
| `index.html` | page (copie de celle de H4 sans l'image ni le masque, chemins vers `../h4-etourneaux/`) et configuration `window.H4Variante` (`nom`, `racine`, `fonds: ['noir']`) |
| `vignette.jpg` | vignette du bac à sable |
| `../h4-etourneaux/js/*.js`, `css/etourneaux.css` | modules partagés avec H4 |

## Vérifications (Chrome headless, GPU Metal, 28/10/2026)

- 0 erreur console et 0 exception ; fond `noir`, piste pré-analysée chargée, rectangle de la nuée
  identique à celui de H4 à 1440 × 900 (même x, y, l, h) ; visage et passage derrière à 0.
- Téléphone 390 × 844 (dpr 3, tactile) : même cadrage que H4.

## Limites connues

Celles de H4 (Chrome seulement, grain plus gros sur téléphone…). Sur le noir, la traîne de
l'ouverture (composée à ~54 % comme vol1) est discrète : `FIGURES.traine.g.sortie` dans `nuee.js`
ou l'opacité globale du contexte « ambiance » la règlent.

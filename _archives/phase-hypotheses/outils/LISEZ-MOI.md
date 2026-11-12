# Outils — Pierre Bleuse

Scripts écrits pendant la session du 15/10/2026 pour caler l'effet de stries
sur les maquettes du DA. Rien à installer : Swift (Xcode CLT), Python 3 sans
dépendance, Node ≥ 22, Google Chrome.

Compiler un outil Swift une fois : `swiftc -O seg.swift -o /tmp/seg`.

| Fichier | Rôle |
|---|---|
| `seg.swift` | **Masque de détourage** par Apple Vision. `seg <photo.jpg> <préfixe>` → `<préfixe>-fg1.png` (sujet principal, blanc sur noir, taille de la photo) + `<préfixe>-pN.png` (une image par personne). C'est ainsi qu'a été fait `assets/bleuse-3-masque.png`. |
| `raw.swift` | Exporte une image en RGB brut 8 bits (`raw <in> <out.rgb>`), lu ensuite par les scripts Python (pas de PIL/numpy sur ce poste). |
| `crop.swift` | Recadre une zone pour l'examiner : `crop <in> <out.png> x y w h`. |
| `cols.py` | Diff photo / maquette colonne par colonne → positions exactes des stries. Attend `b3.rgb` (photo) et `b4.rgb` (maquette) dans le dossier passé en argument. |
| `fit.py` | Régression linéaire par canal `maquette = p·photo180 + q·photo + c` sur les stries → a donné « 50 % photo pivotée + 50 % #5A638D, opaque ». |
| `cmp.py` | Compare zone par zone la maquette et le masque Vision (ce qui passe devant les stries). |
| ~~`test-home-cdp.mjs`~~ | Supprimé le 16/10 : il visait `home.html` et les épinglages ScrollTrigger, qui n'existent plus. Utiliser `mesure-molette.mjs` et `test-webkit.cjs`. |

Lancer Chrome pour le harnais (WebGL logiciel nécessaire en headless) :

```bash
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new \
  --remote-debugging-port=9333 --use-angle=swiftshader --enable-unsafe-swiftshader \
  --no-first-run --user-data-dir=/tmp/chrome-bleuse about:blank &
python3 -m http.server 8765   # depuis PierreBleuse/
node outils/mesure-molette.mjs /tmp/captures-bleuse http://localhost:8765 essai
```

Contrôle au pixel d'un preset : dans la page, `heroes[0].params.zoom = 0;
heroes[0].params.parallax = 0; heroes[0].setProgress(1); heroes[0].app.render()`
dans une fenêtre à la taille exacte de la photo (992 × 619), capture, puis
diff avec la maquette (bleuse-3 → bleuse-4 : écart moyen 2,3/255).

## Ajouts du 16/10/2026

| Fichier | Rôle |
|---|---|
| `match.swift` | **Retrouver l'original HD d'une photo** par corrélation d'images (niveaux de gris, tolérant au recadrage et à la retouche). `match <liste> <cible1.jpg> [cible2…]`, la liste étant des lignes `largeur\|hauteur\|taille\|local\|chemin` (obtenues avec `mdfind` + `mdls`). Score > 0,9 = même photo ; ~0,6 = simple ressemblance. Le 16/10 : aucun original HD de bleuse-1/3 sur les Drive. |
| `pdfimg.swift` | Extrait les images (JPEG/JP2/brut) d'un PDF via CoreGraphics : `pdfimg <fichier.pdf> <dossier> <préfixe>`. |
| `mesure-molette.mjs` | **Mesure de fluidité** : vraie molette via CDP (passe par Lenis) + journal par frame de la position des photos à l'écran → rapport vitesse photo / vitesse page, frames figées. `node mesure-molette.mjs <dossier> <url-de-base> <étiquette>` (Chrome headless sur :9333). Lisser sur ~8 frames pour gommer l'arrondi au pixel. |
| `test-webkit.cjs` | **Test Safari (WebKit)** en émulation iPhone 15 puis ordinateur Retina via Playwright : erreurs, WebGL, composition choisie, captures avec effet complet. `node test-webkit.cjs <dossier contenant node_modules/playwright> <dossier captures>`. Installer une fois ailleurs que dans le projet : `npm i playwright && npx playwright install webkit`. |

Notes de mesure :
- SwiftShader (rendu logiciel) suffit pour des captures, pas pour des temps : pour la fluidité réelle, lancer Chrome headless avec `--use-angle=metal`, et `caffeinate -i` pour éviter la mise en veille pendant les mesures longues.
- Geste tactile réel : `Input.synthesizeScrollGesture` avec `gestureSourceType: 'touch'` (Chrome en émulation mobile). Playwright WebKit ne sait faire que des taps.

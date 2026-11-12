#!/bin/sh
# Vérifie la syntaxe des scripts du site avec JavaScriptCore (fourni par macOS ; Node n'est pas installé).
#   sh outils/syntaxe.sh            tous les fichiers de js/ et de data/
#   sh outils/syntaxe.sh js/main.js un fichier
JSC=/System/Library/Frameworks/JavaScriptCore.framework/Versions/Current/Helpers/jsc
cd "$(dirname "$0")/.." || exit 1
[ $# -gt 0 ] || set -- js/*.js data/*.js
code=0
for f in "$@"; do
  r=$("$JSC" -e "try { new Function(readFile('$f')); print('ok') } catch (e) { print('ERREUR ' + e + ' (ligne ' + e.line + ')') }" 2>&1)
  [ "$r" = "ok" ] || { echo "$f : $r"; code=1; }
done
[ $code -eq 0 ] && echo "syntaxe : $# fichier(s) sans erreur"
exit $code

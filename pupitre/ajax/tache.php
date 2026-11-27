<?php
/** Avancement d'une tâche de fond, pour l'écran qui la suit. */

require __DIR__ . '/../inclus/amorce.php';

if (!peut('import') && !peut('contenus')) repondre_json(['erreur' => 'Accès refusé.'], 403);
$t = tache_lire(recu_entier('id') ?? 0);
if (!$t) repondre_json(['erreur' => 'Tâche introuvable.'], 404);
repondre_json(['statut' => $t['statut'], 'avancement' => (int) $t['avancement'], 'message' => $t['message']]);

<?php
/**
 * Tâches de fond de Pupitre. En ligne de commande seulement.
 *
 *     php pupitre/cron/taches.php            exécute les tâches en attente, les travaux réguliers (aperçus expirés,
 *                                            invitations de la veille des concerts), puis les travaux de nuit
 *                                            s'ils n'ont pas encore tourné aujourd'hui
 *     php pupitre/cron/taches.php --nuit     force les travaux de nuit (alertes recalculées)
 *
 * Cron, toutes les cinq minutes (minutes 0, 5, 10… de chaque heure) :
 *     0-59/5 * * * * /chemin/vers/php /chemin/vers/pupitre/cron/taches.php >/dev/null 2>&1
 *
 * Sur le poste local, le bouton « Lancer maintenant » du tableau de bord démarre ce script.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ligne de commande seulement.'); }

require __DIR__ . '/../inclus/amorce.php';
set_time_limit(0);

foreach (taches_executer() as [$id, $type, $statut, $message]) {
    echo date('H:i:s') . " tâche $id ($type) : $statut · $message\n";
}
foreach (travaux_reguliers(true) as $quoi => $n) {
    echo date('H:i:s') . ' ' . ($quoi === 'apercus' ? pluriel($n, 'aperçu expiré retiré', 'aperçus expirés retirés') : pluriel($n, 'invitation à déposer des images envoyée', 'invitations à déposer des images envoyées')) . "\n";
}
// la boîte aux lettres : relevée toutes les demi-heures, quand elle est reliée
if (imap_configure() && strtotime(reglage('courrier_le') ?: '1970-01-01') < time() - 1800) {
    [$gardes, , $erreur] = courrier_relever();
    courrier_suivre_envois();
    echo date('H:i:s') . ' courrier : ' . ($erreur !== '' ? $erreur : pluriel($gardes, 'retour rattaché', 'retours rattachés')) . "\n";
    if ($gardes) alertes_recalculer();
}
if (travaux_de_nuit(in_array('--nuit', $argv, true))) {
    echo date('H:i:s') . " travaux de nuit : alertes recalculées\n";
}

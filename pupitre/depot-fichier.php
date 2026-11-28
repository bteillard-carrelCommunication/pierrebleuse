<?php
/**
 * Sert un fichier déposé dans une zone de dépôt à une personne connectée de l'équipe : ces fichiers
 * sont dans stockage/, où le serveur web n'entre pas. Lecture par plages d'octets pour les vidéos.
 *
 *     depot-fichier.php?id=…            le fichier, à voir
 *     depot-fichier.php?id=…&dl=1       le fichier, à télécharger sous son nom d'origine
 */

require __DIR__ . '/inclus/amorce.php';

$compte = exiger_connexion();
if (!role_peut($compte['role'], 'communication') && !role_peut($compte['role'], 'medias')) { http_response_code(403); exit; }
$f = bd_ligne('SELECT * FROM depots_fichiers WHERE id = ?', [recu_entier('id') ?? 0]);
$fichier = $f ? depot_fichier_chemin($f) : '';
if ($fichier === '') { http_response_code(404); exit('Fichier introuvable.'); }

$taille = filesize($fichier);
header_remove('Cache-Control');
header('Cache-Control: private, max-age=600');
header('Content-Type: ' . (isset(DEPOT_TYPES[$f['type']]) ? $f['type'] : 'application/octet-stream'));
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
// rien de ce qui a été déposé ne s'exécute dans le contexte de Pupitre
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
if (recu('dl') !== '') header('Content-Disposition: attachment; filename="' . addcslashes(preg_replace('/[^\w. ()-]+/u', '_', (string) $f['nom']) ?: 'fichier', '"\\') . '"');

$debut = 0;
$fin = $taille - 1;
if (preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'] ?? '', $p) && ($p[1] !== '' || $p[2] !== '')) {
    if ($p[1] === '') { $debut = max(0, $taille - (int) $p[2]); }
    else { $debut = (int) $p[1]; if ($p[2] !== '') $fin = min($fin, (int) $p[2]); }
    if ($debut > $fin || $debut >= $taille) { http_response_code(416); header('Content-Range: bytes */' . $taille); exit; }
    http_response_code(206);
    header('Content-Range: bytes ' . $debut . '-' . $fin . '/' . $taille);
}
header('Content-Length: ' . ($fin - $debut + 1));
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
while (ob_get_level() > 0) ob_end_clean();
$h = fopen($fichier, 'rb');
fseek($h, $debut);
$reste = $fin - $debut + 1;
while ($reste > 0 && !feof($h)) {
    $bloc = fread($h, min(262144, $reste));
    echo $bloc;
    $reste -= strlen($bloc);
    flush();
}
fclose($h);

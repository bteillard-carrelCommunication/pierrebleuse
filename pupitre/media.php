<?php
/**
 * Sert un fichier de la médiathèque à une personne connectée : les fichiers déposés sont dans
 * stockage/, où le serveur web n'entre pas. Lecture par plages d'octets pour le son et la vidéo.
 */

require __DIR__ . '/inclus/amorce.php';

$compte = exiger_connexion();
if (!role_peut($compte['role'], 'medias') && !role_peut($compte['role'], 'contenus')) { http_response_code(403); exit; }
$m = bd_ligne('SELECT * FROM medias WHERE id = ?', [recu_entier('id') ?? 0]);
$fichier = $m ? media_fichier($m) : '';
if ($fichier === '') { http_response_code(404); exit('Fichier introuvable.'); }

$taille = filesize($fichier);
$mime = $m['mime'] ?: 'application/octet-stream';
header_remove('Cache-Control');
header('Cache-Control: private, max-age=600');
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
// un SVG ou un document s'ouvre sans rien exécuter dans le contexte de Pupitre
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
if ($m['type'] === 'document') header('Content-Disposition: attachment; filename="' . addcslashes(media_nom($m), '"\\') . '"');

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
$f = fopen($fichier, 'rb');
fseek($f, $debut);
$reste = $fin - $debut + 1;
while ($reste > 0 && !feof($f)) {
    $bloc = fread($f, min(262144, $reste));
    echo $bloc;
    $reste -= strlen($bloc);
    flush();
}
fclose($f);

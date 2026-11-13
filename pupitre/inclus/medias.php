<?php
/**
 * Médiathèque : dépôt, contrôle, emplois d'un média.
 *
 * Un fichier déposé est contrôlé (type réel lu dans le fichier, taille), renommé, et rangé dans
 * stockage/medias/, où le serveur web n'entre pas et où PHP ne s'exécute pas. Il n'est copié dans
 * assets/ du site qu'à la publication, s'il est employé.
 */

defined('PUPITRE') or exit;

const TYPES_MEDIA = ['image' => 'Image', 'son' => 'Son', 'video' => 'Vidéo', 'document' => 'Document'];

/** Types réels acceptés : type MIME => [type de média, extension]. */
const MIMES_ACCEPTES = [
    'image/jpeg' => ['image', 'jpg'],
    'image/png' => ['image', 'png'],
    'image/webp' => ['image', 'webp'],
    'image/svg+xml' => ['image', 'svg'],
    'audio/mp4' => ['son', 'm4a'],
    'audio/x-m4a' => ['son', 'm4a'],
    'audio/aac' => ['son', 'aac'],
    'audio/mpeg' => ['son', 'mp3'],
    'video/mp4' => ['video', 'mp4'],
    'application/pdf' => ['document', 'pdf'],
    'application/zip' => ['document', 'zip'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['document', 'docx'],
];

/** Taille maximale par type, en octets (et jamais plus que ce que PHP accepte). */
const TAILLES_MAX = ['image' => 20971520, 'son' => 62914560, 'video' => 62914560, 'document' => 62914560];

function octets_ini(string $v): int
{
    $n = (int) $v;
    return match (strtolower(substr(trim($v), -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
}

function taille_max(string $type): int
{
    $php = min(octets_ini(ini_get('upload_max_filesize')), octets_ini(ini_get('post_max_size')));
    return min(TAILLES_MAX[$type] ?? 20971520, $php > 0 ? $php : PHP_INT_MAX);
}

/** Message lisible pour une erreur de dépôt de PHP. */
function erreur_depot(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop lourd pour ce serveur.',
        UPLOAD_ERR_PARTIAL => 'Le dépôt a été interrompu. Recommencez.',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été choisi.',
        default => 'Le fichier n\'a pas pu être reçu.',
    };
}

/**
 * Contrôle un fichier reçu : type réel et taille. Rend [type de média, extension, type MIME], ou
 * une chaîne : le message d'erreur. $attendus : types de médias acceptés ici.
 */
function fichier_controler(string $chemin, string $nom_origine, array $attendus = [])
{
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($chemin);
    $ext = strtolower(pathinfo($nom_origine, PATHINFO_EXTENSION));
    // un .m4a se présente parfois comme une vidéo MP4 sans image, un .docx comme une archive, un .svg comme du texte
    if ($mime === 'video/mp4' && $ext === 'm4a') $mime = 'audio/mp4';
    if ($mime === 'application/zip' && $ext === 'docx') $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    if (in_array($mime, ['text/plain', 'text/xml', 'application/xml', 'image/svg'], true) && $ext === 'svg') $mime = 'image/svg+xml';
    if (!isset(MIMES_ACCEPTES[$mime])) return 'Ce type de fichier n\'est pas accepté (' . $mime . ').';
    [$type, $extension] = MIMES_ACCEPTES[$mime];
    if ($attendus && !in_array($type, $attendus, true)) {
        return 'Ici, on attend ' . implode(' ou ', array_map(fn($t) => mb_strtolower(TYPES_MEDIA[$t]), $attendus)) . '.';
    }
    $taille = (int) filesize($chemin);
    if ($taille <= 0) return 'Le fichier est vide.';
    if ($taille > taille_max($type)) return 'Le fichier dépasse ' . taille_lisible(taille_max($type)) . '.';
    if ($mime === 'image/svg+xml') {
        // un SVG peut porter du script : on le refuse dans ce cas
        $svg = file_get_contents($chemin);
        if (stripos($svg, '<svg') === false || preg_match('/<script|<foreignObject|\son\w+\s*=|javascript:|<!ENTITY/i', $svg)) {
            return 'Ce SVG contient autre chose qu\'un dessin : il est refusé.';
        }
    }
    return [$type, $extension, $mime];
}

/**
 * Range un fichier reçu ($_FILES) dans la médiathèque. Rend l'identifiant du média, ou une
 * chaîne : le message d'erreur.
 */
function media_deposer(array $recu, array $meta = [], array $attendus = [])
{
    if (($recu['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return erreur_depot((int) ($recu['error'] ?? UPLOAD_ERR_NO_FILE));
    if (!is_uploaded_file($recu['tmp_name'])) return 'Le fichier n\'a pas pu être reçu.';
    $controle = fichier_controler($recu['tmp_name'], (string) $recu['name'], $attendus);
    if (is_string($controle)) return $controle;
    [$type, $extension, $mime] = $controle;
    $nom = date('Ymd') . '-' . bin2hex(random_bytes(4)) . '-' . (slug(pathinfo((string) $recu['name'], PATHINFO_FILENAME), 40) ?: 'fichier') . '.' . $extension;
    $cible = dossier(STOCKAGE . '/medias') . '/' . $nom;
    if (!move_uploaded_file($recu['tmp_name'], $cible)) return 'Le fichier n\'a pas pu être rangé (droits du dossier stockage/medias).';
    @chmod($cible, 0644);
    $largeur = $hauteur = null;
    if ($type === 'image' && $extension !== 'svg' && ($t = @getimagesize($cible))) [$largeur, $hauteur] = [$t[0], $t[1]];
    $c = compte();
    $id = objet_enregistrer('medias', null, [
        'type' => $type,
        'fichier' => $nom,
        'nom_origine' => mb_substr((string) $recu['name'], 0, 255),
        'mime' => $mime,
        'taille' => (int) filesize($cible),
        'largeur' => $largeur,
        'hauteur' => $hauteur,
        'alt_fr' => mb_substr(trim((string) ($meta['alt_fr'] ?? '')), 0, 500),
        'alt_en' => mb_substr(trim((string) ($meta['alt_en'] ?? '')), 0, 500),
        'credit' => mb_substr(trim((string) ($meta['credit'] ?? '')), 0, 255),
        'droits' => trim((string) ($meta['droits'] ?? '')),
        'cree_par' => $c['id'] ?? null,
    ], 'media', (string) $recu['name']);
    $GLOBALS['medias_perimes'] = true;
    return $id;
}

/** Chemin du fichier d'un média sur le disque : dans stockage/, sinon dans le site. '' s'il manque. */
function media_fichier(array $m): string
{
    if (!empty($m['fichier'])) {
        $f = STOCKAGE . '/medias/' . basename($m['fichier']);
        if (is_file($f)) return $f;
    }
    if (!empty($m['chemin_site']) && ($racine = site_racine()) !== '') {
        $f = realpath($racine . '/' . $m['chemin_site']);
        if ($f && str_starts_with($f, $racine . DIRECTORY_SEPARATOR) && is_file($f)) return $f;
    }
    return '';
}

function media_adresse(int $id): string
{
    return chemin_pupitre() . '/media.php?id=' . $id;
}

function media_nom(array $m): string
{
    return $m['nom_origine'] !== '' ? $m['nom_origine'] : basename((string) ($m['chemin_site'] ?: $m['fichier']));
}

/** Les endroits où un média est employé : [[libellé, adresse dans Pupitre], …]. */
function media_emplois(int $id): array
{
    $e = [];
    foreach (bd_lignes('SELECT cle FROM visuels WHERE media_id = ? OR affiche_id = ?', [$id, $id]) as $v) {
        $e[] = ['Visuel « ' . $v['cle'] . ' »', url('visuels')];
    }
    foreach (bd_lignes('SELECT titre_fr FROM musiques WHERE media_id = ?', [$id]) as $t) {
        $e[] = ['Musique « ' . $t['titre_fr'] . ' »', url('musiques')];
    }
    foreach (bd_lignes('SELECT libelle_fr FROM telechargements WHERE media_id = ?', [$id]) as $t) {
        $e[] = ['Téléchargement « ' . $t['libelle_fr'] . ' »', url('contenu', ['type' => 'telechargements'])];
    }
    foreach (bd_lignes('SELECT id, nom FROM annuaire WHERE logo_id = ?', [$id]) as $f) {
        $e[] = ['Logo de « ' . $f['nom'] . ' » (annuaire, presse)', url('fiche', ['id' => $f['id']])];
    }
    return $e;
}

/** Médias proposés dans une liste de choix, par types. */
function medias_choix(array $types): array
{
    $marques = implode(',', array_fill(0, count($types), '?'));
    $choix = [];
    foreach (bd_lignes("SELECT * FROM medias WHERE type IN ($marques) ORDER BY id DESC", $types) as $m) $choix[(int) $m['id']] = media_nom($m);
    return $choix;
}

function media_supprimer(int $id): ?string
{
    $m = bd_ligne('SELECT * FROM medias WHERE id = ?', [$id]);
    if (!$m) return null;
    if (media_emplois($id)) return 'Ce média est employé : retirez-le d\'abord des écrans qui s\'en servent.';
    if (!empty($m['fichier'])) @unlink(STOCKAGE . '/medias/' . basename($m['fichier']));
    objet_supprimer('medias', $id, 'media', media_nom($m));
    $GLOBALS['medias_perimes'] = true;
    return null;
}

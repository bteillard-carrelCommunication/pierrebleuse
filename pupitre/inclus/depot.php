<?php
/**
 * Zones de dépôt : pour chaque concert, une adresse où son contact « communication » dépose ses
 * photos et ses courtes vidéos, sans compte.
 *
 * Le contact se saisit sur la fiche du concert (nom, adresse, langue du mail). La veille de la
 * première représentation, Pupitre lui envoie de lui-même l'adresse de la zone (cron/taches.php) ;
 * le pilote peut aussi l'envoyer quand il veut, ou la copier. La zone reste ouverte quelques
 * semaines après la dernière date, puis se ferme d'elle-même.
 *
 * Les fichiers sont rangés sur le serveur, hors du web (stockage/depots/<zone>/) : ils ne se voient
 * que depuis Pupitre, connecté. Leur type est relu sur le fichier lui-même, jamais sur son nom :
 * seules des images et des vidéos entrent. Un fichier retenu rejoint la médiathèque.
 */

defined('PUPITRE') or exit;

/** Ce qu'une zone accepte : type réel => [famille, extension]. */
const DEPOT_TYPES = [
    'image/jpeg' => ['image', 'jpg'], 'image/png' => ['image', 'png'], 'image/webp' => ['image', 'webp'],
    'image/heic' => ['image', 'heic'], 'image/heif' => ['image', 'heif'], 'image/tiff' => ['image', 'tif'],
    'video/mp4' => ['video', 'mp4'], 'video/quicktime' => ['video', 'mov'], 'video/webm' => ['video', 'webm'],
];
const DEPOT_MAX_FICHIER = 314572800;       // 300 Mo par fichier, et jamais plus que ce que PHP accepte
const DEPOT_MAX_ZONE = 3221225472;         // 3 Go par zone
const DEPOT_MAX_FICHIERS = 200;            // par zone
const DEPOT_JOURS_APRES = 45;              // la zone reste ouverte tant de jours après la dernière représentation
const DEPOT_HEURE_ENVOI = 9;               // l'invitation de la veille ne part pas avant cette heure
const STATUTS_DEPOT = ['recu' => ['Reçu', 'puce--accent'], 'retenu' => ['Retenu', 'puce--ok'], 'ecarte' => ['Écarté', '']];

/** Textes de la page de dépôt et du mail d'invitation, dans les deux langues proposées au contact. */
const DEPOT_TEXTES = [
    'fr' => [
        'titre' => 'Photos et vidéos du concert',
        'intro' => 'Merci de nous confier vos photos et vos courtes vidéos : déposez-les ici, sans créer de compte. Elles ne sont visibles que de l\'équipe de %s.',
        'accepte' => 'Photos (JPEG, PNG, HEIC, WebP, TIFF) et vidéos (MP4, MOV, WebM), jusqu\'à %s par fichier, %s par envoi.',
        'nom' => 'Votre nom', 'credit' => 'Crédit à mentionner', 'credit_aide' => 'Le nom du ou de la photographe, tel qu\'il doit paraître : « © Prénom Nom ».',
        'legende' => 'Un mot sur ces images (facultatif)', 'fichiers' => 'Vos fichiers', 'envoyer' => 'Déposer ces fichiers', 'envoi' => 'Envoi en cours…',
        'deja' => 'Déjà déposés', 'merci' => 'Merci : %s. Vous pouvez en déposer d\'autres.', 'recu1' => '1 fichier reçu', 'recus' => '%d fichiers reçus',
        'ferme' => 'Cette zone de dépôt est fermée.', 'ferme_detail' => 'Pour nous transmettre des images, écrivez-nous : nous vous en ouvrirons une nouvelle.',
        'inconnu' => 'Cette adresse n\'est pas valable.', 'inconnu_detail' => 'Elle est incomplète ou a été remplacée. Reprenez le lien du mail reçu.',
        'trop' => 'trop lourd', 'trop_total' => 'Trop de fichiers à la fois : envoyez-les en plusieurs fois', 'jusquau' => 'Zone ouverte jusqu\'au %s.',
        'droits' => 'En déposant ces fichiers, vous confirmez que vous pouvez nous les transmettre et que leur auteur accepte leur emploi par %s pour sa communication, avec le crédit indiqué.',
        'mail_sujet' => 'Photos et vidéos : %s, %s',
        'mail_titre' => 'Vos photos et vidéos du concert',
        'mail_texte' => "Bonjour %s,\n\nL'équipe de %s serait très heureuse de recevoir vos photos et vos courtes vidéos du concert « %s » (%s).\n\nVous pouvez les déposer ici, sans créer de compte :\n\n%s\n\nPhotos et vidéos sont acceptées, jusqu'à %s par fichier. Pensez à nous indiquer le crédit à mentionner. Le lien reste ouvert jusqu'au %s.\n\nUn grand merci, et très bon concert !",
        'mail_bouton' => 'Déposer des photos ou des vidéos',
    ],
    'en' => [
        'titre' => 'Photos and videos from the concert',
        'intro' => 'Thank you for sharing your photos and short videos with us: drop them here, no account needed. Only %s\'s team can see them.',
        'accepte' => 'Photos (JPEG, PNG, HEIC, WebP, TIFF) and videos (MP4, MOV, WebM), up to %s per file, %s per upload.',
        'nom' => 'Your name', 'credit' => 'Credit to mention', 'credit_aide' => 'The photographer\'s name, as it should appear: "© First name Last name".',
        'legende' => 'A word about these images (optional)', 'fichiers' => 'Your files', 'envoyer' => 'Upload these files', 'envoi' => 'Uploading…',
        'deja' => 'Already uploaded', 'merci' => 'Thank you: %s. You can upload more.', 'recu1' => '1 file received', 'recus' => '%d files received',
        'ferme' => 'This upload area is closed.', 'ferme_detail' => 'To send us images, please write to us: we will open a new one for you.',
        'inconnu' => 'This address is not valid.', 'inconnu_detail' => 'It is incomplete or has been replaced. Please use the link from the email you received.',
        'trop' => 'too large', 'trop_total' => 'Too many files at once: please upload them in several batches', 'jusquau' => 'Open until %s.',
        'droits' => 'By uploading these files, you confirm that you are allowed to share them and that their author agrees to their use by %s for communication purposes, with the credit given.',
        'mail_sujet' => 'Photos and videos: %s, %s',
        'mail_titre' => 'Your photos and videos from the concert',
        'mail_texte' => "Hello %s,\n\n%s's team would be delighted to receive your photos and short videos from the concert “%s” (%s).\n\nYou can drop them here, no account needed:\n\n%s\n\nPhotos and videos are welcome, up to %s per file. Please let us know the credit to mention. The link stays open until %s.\n\nThank you very much, and have a wonderful concert!",
        'mail_bouton' => 'Upload photos or videos',
    ],
];

/** Ce que PHP accepte en un envoi : [par fichier, par envoi, nombre de fichiers]. */
function depot_limites(): array
{
    $fichier = octets_ini((string) ini_get('upload_max_filesize')) ?: DEPOT_MAX_FICHIER;
    $envoi = octets_ini((string) ini_get('post_max_size')) ?: PHP_INT_MAX;
    return [min(DEPOT_MAX_FICHIER, $fichier, $envoi), $envoi, max(1, (int) ini_get('max_file_uploads') ?: 20)];
}

/** Dates d'un concert, pour une zone : [première, dernière] représentation non annulée, ou [null, null]. */
function depot_dates(int $concert_id): array
{
    $r = bd_ligne("SELECT MIN(date) AS premiere, MAX(date) AS derniere FROM representations WHERE concert_id = ? AND statut <> 'annulee'", [$concert_id]);
    return [$r['premiere'] ?? null, $r['derniere'] ?? null];
}

/** La zone de dépôt d'un concert. $creer : l'ouvre si elle n'existe pas encore. */
function depot_de_concert(int $concert_id, bool $creer = false): ?array
{
    $d = bd_ligne('SELECT * FROM depots WHERE concert_id = ? ORDER BY id DESC LIMIT 1', [$concert_id]);
    if ($d || !$creer) return $d;
    [, $derniere] = depot_dates($concert_id);
    $id = bd_inserer('depots', [
        'concert_id' => $concert_id,
        'jeton' => bin2hex(random_bytes(20)),
        'expire_le' => date('Y-m-d 23:59:59', strtotime(($derniere && $derniere >= aujourdhui() ? $derniere : aujourdhui()) . ' +' . DEPOT_JOURS_APRES . ' days')),
    ]);
    return bd_ligne('SELECT * FROM depots WHERE id = ?', [$id]);
}

function depot_adresse(array $d): string
{
    return adresse_pupitre() . '/depot.php?z=' . $d['jeton'];
}

/** La zone que désigne un jeton, avec son concert. null si elle n'existe pas. */
function depot_par_jeton(string $jeton): ?array
{
    if (!preg_match('/^[0-9a-f]{40}$/', $jeton)) return null;
    $d = bd_ligne('SELECT * FROM depots WHERE jeton = ?', [$jeton]);
    if (!$d || !$d['concert_id']) return null;
    $d['concert'] = bd_ligne('SELECT id, titre_en, ensemble_en, salle_en, ville_en, pays_en, contact_com_nom, contact_com_email, contact_com_langue FROM concerts WHERE id = ?', [$d['concert_id']]);
    return $d['concert'] ? $d : null;
}

function depot_ouvert(array $d): bool
{
    return !(int) $d['ferme'] && ($d['expire_le'] === null || $d['expire_le'] > date('Y-m-d H:i:s'));
}

function depot_fichiers(int $depot_id): array
{
    return bd_lignes('SELECT * FROM depots_fichiers WHERE depot_id = ? ORDER BY id DESC', [$depot_id]);
}

/** Chemin, sur le disque, d'un fichier déposé. '' s'il n'y est plus. */
function depot_fichier_chemin(array $f): string
{
    $chemin = STOCKAGE . '/depots/' . (int) $f['depot_id'] . '/' . basename((string) $f['fichier']);
    return is_file($chemin) ? $chemin : '';
}

/**
 * Reçoit un fichier dans une zone. $recu : une entrée de $_FILES (un seul fichier).
 * Rend '' si le fichier est rangé, sinon le refus, dans la langue $langue.
 */
function depot_recevoir(array $d, array $recu, string $deposant, string $credit, string $legende, string $langue = 'fr'): string
{
    $en = $langue === 'en';
    $nom = mb_substr((string) ($recu['name'] ?? ''), 0, 255);
    $dire = fn(string $fr, string $anglais) => ($nom !== '' ? $nom . ' : ' : '') . ($en ? $anglais : $fr);
    if (($recu['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return in_array($recu['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? $dire('trop lourd pour ce serveur.', 'too large for this server.') : $dire('le fichier n\'a pas pu être reçu.', 'the file could not be received.');
    }
    if (!is_uploaded_file($recu['tmp_name'])) return $dire('le fichier n\'a pas pu être reçu.', 'the file could not be received.');
    // le type se lit sur le fichier, pas sur son nom : rien d'autre qu'une image ou une vidéo n'entre
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($recu['tmp_name']);
    if (!isset(DEPOT_TYPES[$mime])) {
        // les photos HEIC des téléphones : une bibliothèque de reconnaissance ancienne ne les nomme pas ; leur en-tête, si
        $debut = (string) file_get_contents($recu['tmp_name'], false, null, 0, 12);
        if (substr($debut, 4, 4) === 'ftyp' && in_array(substr($debut, 8, 4), ['heic', 'heix', 'hevc', 'heim', 'heis', 'mif1', 'msf1'], true)) $mime = 'image/heic';
    }
    if (!isset(DEPOT_TYPES[$mime])) return $dire('ce type de fichier n\'est pas accepté (photos et vidéos seulement).', 'this type of file is not accepted (photos and videos only).');
    [$famille, $extension] = DEPOT_TYPES[$mime];
    $taille = (int) filesize($recu['tmp_name']);
    if ($taille <= 0) return $dire('le fichier est vide.', 'the file is empty.');
    if ($taille > depot_limites()[0]) return $dire('le fichier dépasse ' . taille_lisible(depot_limites()[0]) . '.', 'the file exceeds ' . taille_lisible(depot_limites()[0]) . '.');
    $deja = bd_ligne('SELECT COUNT(*) AS n, COALESCE(SUM(taille), 0) AS octets FROM depots_fichiers WHERE depot_id = ?', [$d['id']]);
    if ((int) $deja['n'] >= DEPOT_MAX_FICHIERS || (int) $deja['octets'] + $taille > DEPOT_MAX_ZONE) {
        return $dire('la zone est pleine : écrivez-nous pour la suite.', 'this upload area is full: please write to us for the rest.');
    }
    $rangement = dossier(STOCKAGE . '/depots/' . (int) $d['id']);
    $fichier = date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $extension;
    if (!move_uploaded_file($recu['tmp_name'], $rangement . '/' . $fichier)) return $dire('le fichier n\'a pas pu être rangé.', 'the file could not be stored.');
    @chmod($rangement . '/' . $fichier, 0640);
    bd_inserer('depots_fichiers', [
        'depot_id' => $d['id'], 'nom' => $nom, 'fichier' => $fichier, 'type' => $mime, 'taille' => $taille,
        'deposant' => mb_substr(trim($deposant), 0, 190), 'credit' => mb_substr(trim($credit), 0, 255), 'legende' => mb_substr(trim($legende), 0, 500), 'ip' => adresse_ip(),
    ]);
    $GLOBALS['donnees_modifiees'] = true;
    return '';
}

/**
 * Envoie au contact « communication » d'un concert l'adresse de sa zone de dépôt.
 * $automatique : l'envoi de la veille (journalisé comme tel). Rend '' si le mail est parti, sinon pourquoi il ne part pas.
 */
function depot_inviter(int $concert_id, bool $automatique = false): string
{
    $c = bd_ligne('SELECT * FROM concerts WHERE id = ?', [$concert_id]);
    if (!$c) return 'Concert introuvable.';
    $email = trim((string) $c['contact_com_email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Ce concert n\'a pas de contact « communication » (une adresse e-mail est nécessaire).';
    $d = depot_de_concert($concert_id, true);
    if (!depot_ouvert($d)) return 'La zone de dépôt de ce concert est fermée : rouvrez-la d\'abord.';
    $langue = isset(DEPOT_TEXTES[$c['contact_com_langue']]) ? $c['contact_com_langue'] : 'en';
    $t = DEPOT_TEXTES[$langue];
    [$premiere, $derniere] = depot_dates($concert_id);
    $quand = $premiere ? ($langue === 'en' ? date('j F Y', strtotime($premiere)) : date_fr($premiere)) : '';
    $ou = implode(', ', array_filter([$c['salle_en'], $c['ville_en']], 'strlen'));
    $artiste = reglage('artiste_nom') ?: 'Pierre Bleuse';
    $nom = trim((string) $c['contact_com_nom']);
    $expire = $langue === 'en' ? date('j F Y', strtotime($d['expire_le'])) : date_fr(substr($d['expire_le'], 0, 10));
    $lien = depot_adresse($d);
    $texte = sprintf($t['mail_texte'], $nom !== '' ? $nom : ($langue === 'en' ? 'there' : ''), $artiste, $c['titre_en'], implode(' · ', array_filter([$quand, $ou], 'strlen')), $lien, taille_lisible(depot_limites()[0]), $expire);
    $texte = str_replace(['Bonjour ,', 'Hello there,'], ['Bonjour,', 'Hello,'], $texte);
    $ok = envoyer_mail($email, $nom, sprintf($t['mail_sujet'], $c['titre_en'], $quand), $texte, [
        'titre' => $t['mail_titre'], 'bouton' => [$t['mail_bouton'], $lien], 'nature' => 'depot', 'objet' => ['concerts', $concert_id],
        'pied' => $artiste . ($langue === 'en' ? ' · message sent by his team' : ' · message envoyé par son équipe'),
    ]);
    // l'envoi est noté même s'il n'a pas pu partir (poste sans Mailjet, adresse d'essai) : la veille ne se rejoue pas en boucle
    bd_modifier('depots', ['invite_le' => date('Y-m-d H:i:s'), 'invite_a' => $email], ['id' => $d['id']]);
    journaliser('envoi', 'depot', $concert_id, $c['titre_en'] . ' : invitation à déposer des images' . ($automatique ? ' (envoi de la veille)' : ''), null, ['a' => trim($nom . ' (' . adresse_masquee($email) . ')'), 'parti' => $ok ? 'oui' : 'non : voir le journal des mails']);
    return $ok ? '' : 'Le mail n\'a pas pu partir (envoi de mails non configuré, ou adresse d\'essai) : copiez l\'adresse de la zone et transmettez-la vous-même.';
}

/**
 * L'envoi de la veille : chaque concert dont la première représentation est demain (ou aujourd'hui, si le contact
 * a été saisi tard), qui a un contact « communication » et n'a pas encore été invité. Rien ne part avant
 * DEPOT_HEURE_ENVOI heures. Rend le nombre d'invitations.
 */
function depots_inviter_veille(?int $heure = null): int
{
    if (($heure ?? (int) date('G')) < DEPOT_HEURE_ENVOI) return 0;      // $heure : pour les essais
    try {
        $concerts = bd_colonne("SELECT c.id FROM concerts c
            WHERE c.contact_com_email <> '' AND c.type <> 'disque'
              AND (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id AND r.statut <> 'annulee') IN (?, ?)
              AND NOT EXISTS (SELECT 1 FROM depots d WHERE d.concert_id = c.id AND (d.invite_le IS NOT NULL OR d.ferme = 1))",
            [aujourdhui(), ajouter_jours(aujourdhui(), 1)]);
    } catch (Throwable $e) {
        return 0;       // base d'avant la migration 012
    }
    $n = 0;
    foreach ($concerts as $id) {
        depot_inviter((int) $id, true);
        $n++;
    }
    return $n;
}

/**
 * Retient un fichier déposé : il rejoint la médiathèque (les types qu'elle accepte), avec son crédit.
 * Rend l'identifiant du média, ou une chaîne : pourquoi il n'y entre pas.
 */
function depot_fichier_retenir(int $fichier_id)
{
    $f = bd_ligne('SELECT * FROM depots_fichiers WHERE id = ?', [$fichier_id]);
    $chemin = $f ? depot_fichier_chemin($f) : '';
    if ($chemin === '') return 'Fichier introuvable.';
    if ($f['media_id']) return (int) $f['media_id'];
    $controle = fichier_controler($chemin, (string) $f['nom'], ['image', 'video']);
    if (is_string($controle)) return 'La médiathèque ne prend pas ce format tel quel (' . $f['type'] . ') : téléchargez le fichier et convertissez-le (JPEG, PNG, WebP ou MP4), puis déposez-le dans la médiathèque.';
    [$type, $extension, $mime] = $controle;
    $nom = date('Ymd') . '-' . bin2hex(random_bytes(4)) . '-' . (slug(pathinfo((string) $f['nom'], PATHINFO_FILENAME), 40) ?: 'depot') . '.' . $extension;
    $cible = dossier(STOCKAGE . '/medias') . '/' . $nom;
    if (!@copy($chemin, $cible)) return 'Le fichier n\'a pas pu être copié dans la médiathèque.';
    @chmod($cible, 0644);
    $largeur = $hauteur = null;
    if ($type === 'image' && ($t = @getimagesize($cible))) [$largeur, $hauteur] = [$t[0], $t[1]];
    $c = compte();
    $concert = bd_valeur('SELECT c.titre_en FROM depots d JOIN concerts c ON c.id = d.concert_id WHERE d.id = ?', [$f['depot_id']]);
    $media = objet_enregistrer('medias', null, [
        'type' => $type, 'fichier' => $nom, 'nom_origine' => mb_substr((string) $f['nom'], 0, 255), 'mime' => $mime, 'taille' => (int) filesize($cible),
        'largeur' => $largeur, 'hauteur' => $hauteur, 'alt_en' => '', 'credit' => (string) $f['credit'],
        'droits' => trim('Déposé par ' . ($f['deposant'] ?: 'le contact communication') . ' le ' . date_fr(substr($f['depose_le'], 0, 10)) . ($concert ? ', pour « ' . $concert . ' »' : '') . '.' . ($f['legende'] !== '' ? ' ' . $f['legende'] : '')),
        'cree_par' => $c['id'] ?? null,
    ], 'media', (string) $f['nom']);
    bd_modifier('depots_fichiers', ['statut' => 'retenu', 'media_id' => $media], ['id' => $fichier_id]);
    $GLOBALS['medias_perimes'] = true;
    $GLOBALS['donnees_modifiees'] = true;
    return (int) $media;
}

/** Supprime un fichier déposé (le fichier et sa ligne). Le média qui en a été tiré reste dans la médiathèque. */
function depot_fichier_supprimer(int $fichier_id): void
{
    $f = bd_ligne('SELECT * FROM depots_fichiers WHERE id = ?', [$fichier_id]);
    if (!$f) return;
    $chemin = depot_fichier_chemin($f);
    if ($chemin !== '') @unlink($chemin);
    bd_supprimer('depots_fichiers', ['id' => $fichier_id]);
    $GLOBALS['donnees_modifiees'] = true;
}

/** Les zones de dépôt, avec leur concert et ce qu'elles ont reçu : la plus récemment servie d'abord. */
function depots_lister(): array
{
    return bd_lignes("SELECT d.*, c.titre_en, c.contact_com_nom, c.contact_com_email,
            (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id AND r.statut <> 'annulee') AS premiere,
            (SELECT COUNT(*) FROM depots_fichiers f WHERE f.depot_id = d.id) AS fichiers,
            (SELECT COUNT(*) FROM depots_fichiers f WHERE f.depot_id = d.id AND f.statut = 'recu') AS nouveaux,
            (SELECT MAX(f.depose_le) FROM depots_fichiers f WHERE f.depot_id = d.id) AS dernier
        FROM depots d JOIN concerts c ON c.id = d.concert_id ORDER BY COALESCE(dernier, d.cree_le) DESC, d.id DESC");
}

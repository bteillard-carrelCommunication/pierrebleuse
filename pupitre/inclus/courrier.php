<?php
/**
 * Courrier : ce que Pupitre envoie, et ce qui lui revient.
 *
 * Chaque mail envoyé par Pupitre (inclus/mail.php) est noté dans `courriers`. Les retours se lisent
 * dans la boîte de l'expéditeur (contact@…, chez OVH), par IMAP, en lecture seule : une réponse, un
 * message d'absence, un avis de non-remise. Chacun est rapproché de l'envoi auquel il répond (même
 * correspondant, même sujet), donc du concert ou de l'aperçu concerné.
 *
 * Pupitre ne lit que ce qui lui revient : seuls les messages rapprochés d'un envoi, et les avis de
 * non-remise, sont gardés ; le reste de la boîte n'est ni copié, ni montré. Rien n'est marqué comme lu,
 * rien n'est déplacé ni supprimé dans la boîte (commande EXAMINE).
 *
 * Réglage, dans pupitre/.env : IMAP_HOTE (ssl0.ovh.net pour une boîte OVH), IMAP_PORT (993),
 * IMAP_UTILISATEUR (l'adresse complète), IMAP_MOT_DE_PASSE, IMAP_DOSSIER (INBOX). Le mot de passe ne
 * sort jamais dans un journal. Le client IMAP est écrit ici, sans extension : la connexion est chiffrée
 * (TLS) dès le premier octet, et le certificat du serveur est vérifié.
 *
 * Le routeur (Mailjet) dit de son côté ce qu'est devenu chaque envoi : remis, ouvert, rejeté.
 */

defined('PUPITRE') or exit;

const STATUTS_ENVOI = ['envoye' => ['Envoyé', ''], 'non_envoye' => ['Non envoyé', 'puce--alerte'], 'echec' => ['Échec', 'puce--erreur'], 'remis' => ['Remis', 'puce--ok'],
    'ouvert' => ['Ouvert', 'puce--ok'], 'clique' => ['Lien suivi', 'puce--ok'], 'rejete' => ['Rejeté', 'puce--erreur'], 'spam' => ['Signalé comme indésirable', 'puce--erreur'], 'bloque' => ['Bloqué', 'puce--erreur']];
const TYPES_RETOUR = ['reponse' => ['Réponse', 'puce--accent'], 'absence' => ['Message d\'absence', ''], 'rejet' => ['Non remis', 'puce--erreur'], 'autre' => ['Autre', '']];
const NATURES_COURRIER = ['connexion' => 'Lien de connexion', 'apercu' => 'Aperçu du site', 'depot' => 'Images d\'un concert', '' => 'Message'];

/** Note un envoi (appelé par envoyer_mail). Sans effet sur une base d'avant la migration 012. */
function courrier_noter_envoi(string $a, string $nom, string $sujet, string $texte, string $statut, string $identifiant, array $o): void
{
    if (!bd_table_existe('courriers')) return;
    bd_inserer('courriers', [
        'sens' => 'envoye', 'statut' => $statut, 'correspondant_email' => mb_strtolower(mb_substr($a, 0, 190)), 'correspondant_nom' => mb_substr($nom, 0, 190),
        'sujet' => mb_substr($sujet, 0, 255), 'texte' => $texte, 'nature' => mb_substr((string) ($o['nature'] ?? ''), 0, 40),
        'objet_table' => mb_substr((string) ($o['objet'][0] ?? ''), 0, 40), 'objet_id' => isset($o['objet'][1]) ? (int) $o['objet'][1] : null,
        'identifiant' => $identifiant !== '' ? mb_substr($identifiant, 0, 255) : null, 'date_message' => date('Y-m-d H:i:s'),
    ]);
}

function imap_configure(): bool
{
    return env('IMAP_HOTE') !== '' && env('IMAP_UTILISATEUR') !== '' && env('IMAP_MOT_DE_PASSE') !== '';
}

/** Client IMAP minimal : se connecter, ouvrir une boîte en lecture seule, chercher, lire. Rien d'autre. */
final class BoiteImap
{
    private $flux = null;
    private int $n = 0;

    /** Ouvre la connexion et s'identifie. Lève ErreurLisible, sans jamais citer le mot de passe. */
    public function __construct(string $hote, int $port, string $utilisateur, string $mot_de_passe, bool $chiffre = true)
    {
        $contexte = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $this->flux = @stream_socket_client(($chiffre ? 'ssl://' : 'tcp://') . $hote . ':' . $port, $code, $message, 15, STREAM_CLIENT_CONNECT, $contexte);
        if (!$this->flux) throw new ErreurLisible('La boîte aux lettres ne répond pas (' . $hote . ':' . $port . ').');
        stream_set_timeout($this->flux, 30);
        if (!str_starts_with((string) fgets($this->flux), '* OK')) throw new ErreurLisible('Le serveur de la boîte aux lettres a refusé la connexion.');
        [$ok] = $this->commande('LOGIN ' . $this->chaine($utilisateur) . ' ' . $this->chaine($mot_de_passe));
        if (!$ok) throw new ErreurLisible('La boîte aux lettres refuse l\'identifiant ou le mot de passe (IMAP_UTILISATEUR, IMAP_MOT_DE_PASSE).');
    }

    private function chaine(string $s): string
    {
        return '"' . addcslashes($s, "\\\"") . '"';
    }

    /** Envoie une commande. Rend [réussie, lignes de la réponse, données littérales dans l'ordre]. */
    private function commande(string $commande): array
    {
        $tag = 'p' . (++$this->n);
        fwrite($this->flux, $tag . ' ' . $commande . "\r\n");
        $lignes = $litteraux = [];
        while (($ligne = fgets($this->flux)) !== false) {
            // un littéral : « {n} » en fin de ligne annonce n octets, qui suivent tels quels
            if (preg_match('/\{(\d+)\}\r?\n$/', $ligne, $m)) {
                $reste = (int) $m[1];
                $donnees = '';
                while ($reste > 0 && !feof($this->flux)) {
                    $bloc = fread($this->flux, min(65536, $reste));
                    if ($bloc === false || $bloc === '') break;
                    $donnees .= $bloc;
                    $reste -= strlen($bloc);
                }
                $litteraux[] = $donnees;
                $lignes[] = rtrim($ligne);
                continue;
            }
            if (str_starts_with($ligne, $tag . ' ')) return [str_starts_with($ligne, $tag . ' OK'), $lignes, $litteraux];
            $lignes[] = rtrim($ligne);
            if (stream_get_meta_data($this->flux)['timed_out']) break;
        }
        return [false, $lignes, $litteraux];
    }

    /** Ouvre une boîte en lecture seule : rien n'y est marqué comme lu. */
    public function ouvrir(string $dossier = 'INBOX'): bool
    {
        return $this->commande('EXAMINE ' . $this->chaine($dossier))[0];
    }

    /** Numéros (UID) des messages arrivés depuis une date. */
    public function depuis(int $horodatage): array
    {
        [$ok, $lignes] = $this->commande('UID SEARCH SINCE ' . date('j-M-Y', $horodatage));
        $uids = [];
        foreach ($lignes as $l) if ($ok && str_starts_with($l, '* SEARCH')) foreach (preg_split('/\s+/', trim(substr($l, 8))) as $u) if (ctype_digit($u)) $uids[] = (int) $u;
        sort($uids);
        return $uids;
    }

    /** Un message, brut (ses $max premiers octets). '' s'il ne se lit pas. */
    public function lire(int $uid, int $max = 262144): string
    {
        [$ok, , $litteraux] = $this->commande('UID FETCH ' . $uid . ' (BODY.PEEK[]<0.' . $max . '>)');
        return $ok && $litteraux ? (string) $litteraux[0] : '';
    }

    public function fermer(): void
    {
        if ($this->flux) { @fwrite($this->flux, "pz LOGOUT\r\n"); @fclose($this->flux); $this->flux = null; }
    }
}

/** Texte en UTF-8, quel que soit le jeu de caractères annoncé. */
function mail_utf8(string $texte, string $jeu): string
{
    $jeu = strtoupper(trim($jeu, " \"'"));
    if ($jeu === '' || $jeu === 'UTF-8' || $jeu === 'US-ASCII') return mb_check_encoding($texte, 'UTF-8') ? $texte : mb_convert_encoding($texte, 'UTF-8', 'ISO-8859-1');
    $converti = @iconv($jeu, 'UTF-8//IGNORE', $texte);
    return $converti !== false ? $converti : mb_convert_encoding($texte, 'UTF-8', 'ISO-8859-1');
}

/** En-têtes d'un bloc de message : nom en minuscules => valeur (lignes repliées dépliées). */
function mail_entetes(string $bloc): array
{
    $e = [];
    foreach (preg_split('/\r?\n(?![ \t])/', preg_replace('/\r?\n[ \t]+/', ' ', trim($bloc))) as $ligne) {
        if (strpos($ligne, ':') === false) continue;
        [$nom, $valeur] = explode(':', $ligne, 2);
        $e[strtolower(trim($nom))] ??= trim($valeur);
    }
    return $e;
}

/** Valeur d'un en-tête codé (=?UTF-8?B?…?=), en clair. */
function mail_entete_decoder(string $v): string
{
    $d = function_exists('iconv_mime_decode') ? @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : false;
    if ($d === false || $d === '') $d = mb_decode_mimeheader($v);
    return trim(preg_replace('/\s+/u', ' ', (string) $d));
}

/** Le texte d'une partie de message : décodage du transfert, puis du jeu de caractères ; une partie HTML est ramenée à son texte. */
function mail_partie_texte(array $entetes, string $corps): string
{
    $transfert = strtolower($entetes['content-transfer-encoding'] ?? '');
    if ($transfert === 'base64') $corps = (string) base64_decode($corps);
    elseif ($transfert === 'quoted-printable') $corps = quoted_printable_decode($corps);
    $type = $entetes['content-type'] ?? 'text/plain';
    $texte = mail_utf8($corps, preg_match('/charset="?([^";\s]+)/i', $type, $m) ? $m[1] : '');
    if (stripos($type, 'text/html') !== false) {
        $texte = html_entity_decode(strip_tags(preg_replace(['#<(script|style)\b.*?</\1>#is', '#<br\s*/?>|</p>|</div>|</tr>#i'], ['', "\n"], $texte)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return trim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $texte)));
}

/** Le texte d'un message : la première partie « texte » trouvée (les pièces jointes sont ignorées). */
function mail_corps(array $entetes, string $corps, int $profondeur = 0): string
{
    $type = $entetes['content-type'] ?? 'text/plain';
    if (stripos($type, 'multipart/') !== false && preg_match('/boundary="?([^";]+)"?/i', $type, $m) && $profondeur < 4) {
        $html = '';
        foreach (array_slice(explode('--' . $m[1], $corps), 1) as $partie) {
            if (str_starts_with($partie, '--')) break;
            $coupe = preg_split('/\r?\n\r?\n/', ltrim($partie, "\r\n"), 2);
            $e = mail_entetes($coupe[0] ?? '');
            $t = $e['content-type'] ?? 'text/plain';
            if (stripos($e['content-disposition'] ?? '', 'attachment') !== false) continue;
            if (stripos($t, 'text/plain') !== false) return mail_partie_texte($e, $coupe[1] ?? '');
            if (stripos($t, 'multipart/') !== false || stripos($t, 'message/') !== false) { $x = mail_corps($e, $coupe[1] ?? '', $profondeur + 1); if ($x !== '') return $x; }
            if ($html === '' && stripos($t, 'text/html') !== false) $html = mail_partie_texte($e, $coupe[1] ?? '');
        }
        return $html;
    }
    return stripos($type, 'text/') !== false || !isset($entetes['content-type']) ? mail_partie_texte($entetes, $corps) : '';
}

/** Un message brut, lu : de (nom, adresse), sujet, date, identifiant, texte, type de retour. */
function mail_lire(string $brut): array
{
    $coupe = preg_split('/\r?\n\r?\n/', $brut, 2);
    $e = mail_entetes($coupe[0] ?? '');
    $de = mail_entete_decoder($e['from'] ?? '');
    $adresse = preg_match('/<([^<>\s]+@[^<>\s]+)>/', $de, $m) ? $m[1] : (preg_match('/[^\s<>"]+@[^\s<>"]+/', $de, $m) ? $m[0] : '');
    $nom = trim(preg_replace('/<[^>]*>/', '', $de), " \"'");
    $sujet = mail_entete_decoder($e['subject'] ?? '');
    $t = strtotime($e['date'] ?? '');
    $texte = mb_substr(mail_corps($e, $coupe[1] ?? ''), 0, 20000);
    $m = ['de_email' => mb_strtolower($adresse), 'de_nom' => $nom !== '' && $nom !== $adresse ? $nom : '', 'sujet' => $sujet, 'date' => $t ? date('Y-m-d H:i:s', $t) : null,
        'identifiant' => trim($e['message-id'] ?? ''), 'texte' => $texte, 'en_reponse' => isset($e['in-reply-to']) || isset($e['references'])];
    // ce qu'est ce retour : un avis de non-remise, un message automatique, une réponse
    $auto = stripos($e['auto-submitted'] ?? 'no', 'auto-') !== false || isset($e['x-autoreply']) || isset($e['x-autorespond']) || stripos($e['precedence'] ?? '', 'auto_reply') !== false;
    if (preg_match('/mailer-daemon|postmaster@|mail delivery/i', $de) || stripos($e['content-type'] ?? '', 'multipart/report') !== false
        || preg_match('/undeliver|delivery status notification|failure notice|returned mail|returned to sender|non remis|échec de la remise|could not be delivered/iu', $sujet)) $m['type'] = 'rejet';
    elseif ($auto || preg_match('/^(automatic reply|auto(matic)?[- ]?(reply|response)|réponse automatique|out of office|absen[ct]e?\b|abwesenheit)/iu', $sujet)) $m['type'] = 'absence';
    elseif ($m['en_reponse'] || preg_match('/^\s*(re|tr|fwd?|aw|rv)\s*:/i', $sujet)) $m['type'] = 'reponse';
    else $m['type'] = 'autre';
    return $m;
}

/** Sujet ramené à ce qui se compare : sans « Re: », « TR: »…, en minuscules. */
function sujet_nu(string $sujet): string
{
    return mb_strtolower(trim(preg_replace('/^\s*((re|tr|fwd?|aw|rv|réf)\s*:\s*|réponse automatique\s*:\s*|automatic reply\s*:\s*)+/iu', '', $sujet)));
}

/** L'envoi auquel un retour répond : même correspondant (ou, pour un rejet, l'adresse citée dans l'avis), le plus proche par le sujet, depuis soixante jours. */
function courrier_rapprocher(array $m): ?array
{
    $depuis = date('Y-m-d H:i:s', time() - 60 * 86400);
    $candidats = [];
    if ($m['type'] === 'rejet') {
        preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $m['texte'] . ' ' . $m['sujet'], $x);
        foreach (array_unique(array_map('mb_strtolower', $x[0])) as $adresse) {
            $candidats = array_merge($candidats, bd_lignes("SELECT * FROM courriers WHERE sens = 'envoye' AND correspondant_email = ? AND date_message >= ? ORDER BY id DESC LIMIT 5", [$adresse, $depuis]));
        }
    } elseif ($m['de_email'] !== '') {
        $candidats = bd_lignes("SELECT * FROM courriers WHERE sens = 'envoye' AND correspondant_email = ? AND date_message >= ? ORDER BY id DESC LIMIT 10", [$m['de_email'], $depuis]);
    }
    if (!$candidats) return null;
    $nu = sujet_nu($m['sujet']);
    foreach ($candidats as $c) if ($nu !== '' && (sujet_nu($c['sujet']) === $nu || str_contains($nu, sujet_nu($c['sujet'])))) return $c;
    return $candidats[0];
}

/**
 * Relève la boîte : les messages des $jours derniers jours que Pupitre n'a pas encore vus. Ne garde que les
 * retours rapprochés d'un envoi, et les avis de non-remise. Rend [gardés, lus, erreur].
 */
function courrier_relever(int $jours = 21): array
{
    if (!imap_configure()) return [0, 0, 'La boîte aux lettres n\'est pas réglée : IMAP_HOTE, IMAP_UTILISATEUR et IMAP_MOT_DE_PASSE dans pupitre/.env.'];
    if (!bd_table_existe('courriers')) return [0, 0, 'La base n\'est pas à niveau.'];
    $boite = null;
    try {
        $boite = new BoiteImap(env('IMAP_HOTE'), (int) env('IMAP_PORT', '993'), env('IMAP_UTILISATEUR'), env('IMAP_MOT_DE_PASSE'), env('IMAP_SECURITE', 'ssl') !== 'aucune');
        if (!$boite->ouvrir(env('IMAP_DOSSIER', 'INBOX'))) throw new ErreurLisible('Le dossier « ' . env('IMAP_DOSSIER', 'INBOX') . ' » de la boîte ne s\'ouvre pas.');
        $dernier = (int) reglage('courrier_uid');
        $gardes = $lus = 0;
        $plus_haut = $dernier;
        foreach ($boite->depuis(time() - $jours * 86400) as $uid) {
            if ($uid <= $dernier) continue;
            $plus_haut = max($plus_haut, $uid);
            $brut = $boite->lire($uid);
            if ($brut === '') continue;
            $lus++;
            $m = mail_lire($brut);
            if ($m['identifiant'] !== '' && bd_valeur("SELECT id FROM courriers WHERE sens = 'recu' AND identifiant = ?", [mb_substr($m['identifiant'], 0, 255)])) continue;
            $envoi = courrier_rapprocher($m);
            // ce qui ne répond à aucun envoi de Pupitre n'est pas son affaire : la boîte garde ce message, Pupitre non
            if (!$envoi && $m['type'] !== 'rejet') continue;
            bd_inserer('courriers', [
                'sens' => 'recu', 'statut' => 'a_lire', 'type_retour' => $m['type'], 'correspondant_email' => mb_substr($m['de_email'], 0, 190), 'correspondant_nom' => mb_substr($m['de_nom'], 0, 190),
                'sujet' => mb_substr($m['sujet'], 0, 255), 'texte' => $m['texte'], 'nature' => $envoi['nature'] ?? '', 'objet_table' => $envoi['objet_table'] ?? '', 'objet_id' => $envoi['objet_id'] ?? null,
                'identifiant' => $m['identifiant'] !== '' ? mb_substr($m['identifiant'], 0, 255) : null, 'uid' => $uid, 'reponse_a' => $envoi['id'] ?? null, 'date_message' => $m['date'] ?? date('Y-m-d H:i:s'),
            ]);
            if ($envoi && $m['type'] === 'rejet') bd_modifier('courriers', ['statut' => 'rejete'], ['id' => $envoi['id']]);
            $gardes++;
        }
        if ($plus_haut > $dernier) reglage_ecrire('courrier_uid', (string) $plus_haut);
        reglage_ecrire('courrier_le', date('Y-m-d H:i:s'));
        if ($gardes) $GLOBALS['donnees_modifiees'] = true;
        return [$gardes, $lus, ''];
    } catch (ErreurLisible $e) {
        return [0, 0, $e->getMessage()];
    } catch (Throwable $e) {
        journal_fichier('erreurs', 'courrier : ' . get_class($e) . ' ' . basename($e->getFile()) . ':' . $e->getLine());
        return [0, 0, 'La lecture de la boîte a échoué. Le détail technique est dans le journal des erreurs.'];
    } finally {
        if ($boite) $boite->fermer();
    }
}

/**
 * Demande au routeur (Mailjet) ce que sont devenus les envois récents : remis, ouvert, rejeté.
 * Rend le nombre d'envois dont l'état a changé.
 */
function courrier_suivre_envois(int $jours = 10): int
{
    $cle = env('MAILJET_CLE');
    $secret = env('MAILJET_SECRET');
    if ($cle === '' || $secret === '' || !bd_table_existe('courriers')) return 0;
    $etats = ['sent' => 'remis', 'opened' => 'ouvert', 'clicked' => 'clique', 'bounce' => 'rejete', 'hardbounced' => 'rejete', 'softbounced' => 'rejete', 'spam' => 'spam', 'blocked' => 'bloque', 'unsub' => 'remis'];
    $n = 0;
    foreach (bd_lignes("SELECT id, identifiant, statut FROM courriers WHERE sens = 'envoye' AND identifiant IS NOT NULL AND statut IN ('envoye', 'remis', 'ouvert') AND date_message >= ? ORDER BY id DESC LIMIT 60",
                       [date('Y-m-d H:i:s', time() - $jours * 86400)]) as $c) {
        if (!ctype_digit((string) $c['identifiant'])) continue;
        $ch = curl_init(rtrim(env('MAILJET_URL', 'https://api.mailjet.com'), '/') . '/v3/REST/message/' . $c['identifiant']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $cle . ':' . $secret, CURLOPT_TIMEOUT => 15]);
        $r = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !is_string($r)) continue;
        $etat = $etats[strtolower((string) (json_decode($r, true)['Data'][0]['Status'] ?? ''))] ?? null;
        if ($etat && $etat !== $c['statut']) { bd_modifier('courriers', ['statut' => $etat], ['id' => $c['id']]); $n++; }
    }
    if ($n) $GLOBALS['donnees_modifiees'] = true;
    return $n;
}

/** Tâche de fond : relever la boîte et suivre les envois. */
function courrier_tache(array $t): string
{
    tache_avancer((int) $t['id'], 20, 'Lecture de la boîte aux lettres');
    [$gardes, $lus, $erreur] = courrier_relever();
    tache_avancer((int) $t['id'], 70, 'Suivi des envois');
    $suivis = courrier_suivre_envois();
    if ($erreur !== '' && !$suivis) throw new ErreurLisible($erreur);
    return ($gardes ? pluriel($gardes, 'retour rattaché', 'retours rattachés') . ' à un envoi de Pupitre' : 'Aucun nouveau retour') . ' (' . pluriel($lus, 'message lu', 'messages lus') . ' dans la boîte)'
        . ($suivis ? ', ' . pluriel($suivis, 'envoi suivi', 'envois suivis') : '') . '.' . ($erreur !== '' ? ' ' . $erreur : '');
}

/** Les retours (reçus), le plus récent d'abord, chacun avec l'envoi auquel il répond. */
function courrier_retours(string $statut = '', int $limite = 100): array
{
    return bd_lignes("SELECT r.*, e.sujet AS envoi_sujet, e.date_message AS envoi_date, e.correspondant_email AS envoi_a FROM courriers r LEFT JOIN courriers e ON e.id = r.reponse_a
        WHERE r.sens = 'recu'" . ($statut !== '' ? ' AND r.statut = ?' : '') . ' ORDER BY r.date_message DESC, r.id DESC LIMIT ' . (int) $limite, $statut !== '' ? [$statut] : []);
}

/** Les envois, le plus récent d'abord, avec le nombre de retours reçus. */
function courrier_envois(int $limite = 100): array
{
    return bd_lignes("SELECT e.*, (SELECT COUNT(*) FROM courriers r WHERE r.reponse_a = e.id) AS retours FROM courriers e WHERE e.sens = 'envoye' ORDER BY e.id DESC LIMIT " . (int) $limite);
}

/** Lien, dans Pupitre, vers ce à quoi un courrier se rapporte. '' s'il n'y en a pas. */
function courrier_lien(array $c): string
{
    if (!$c['objet_id']) return '';
    return match ($c['objet_table']) {
        'concerts' => url('concert', ['id' => $c['objet_id']]) . '#depot',
        'apercus' => url('apercu', ['id' => $c['objet_id']]),
        default => '',
    };
}

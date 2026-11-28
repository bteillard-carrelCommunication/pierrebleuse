<?php
/**
 * Mails : une seule fonction, envoyer_mail(). Envoi par l'API de Mailjet (v3.1), en texte et en HTML.
 *
 * Sans clé (MAILJET_CLE, MAILJET_SECRET), le message est écrit dans stockage/journaux/mails.log :
 * c'est là qu'on lit, sur le poste local, le lien de connexion ou de choix du mot de passe.
 *
 * Le gabarit HTML suit la charte de Pupitre (ressources/charteGraphique, « Mailing ») : tableaux de
 * 600 px, styles en ligne, Arial, bandeau marine et logo, bouton mandarine à texte marine, liens cuivre.
 * Le texte seul part toujours avec : c'est lui que lisent les messageries qui refusent le HTML.
 *
 * Deux garde-fous : une adresse d'essai (domaine réservé aux tests) ne reçoit jamais rien, et chaque
 * envoi est noté dans le courrier de Pupitre (inclus/courrier.php) pour pouvoir en suivre les retours.
 */

defined('PUPITRE') or exit;

/** Domaines réservés aux essais (RFC 2606 et 6761) : rien ne leur est jamais envoyé. */
const MAIL_DOMAINES_ESSAI = ['test', 'example', 'invalid', 'localhost', 'example.com', 'example.org', 'example.net'];

/** Vrai si l'adresse appartient à un domaine d'essai : « x@essai.pupitre.test », « y@example.com »… */
function mail_adresse_essai(string $adresse): bool
{
    $domaine = mb_strtolower((string) substr(strrchr($adresse, '@') ?: '', 1));
    if ($domaine === '') return true;
    foreach (MAIL_DOMAINES_ESSAI as $d) {
        if ($domaine === $d || str_ends_with($domaine, '.' . $d)) return true;
    }
    return false;
}

/**
 * Envoie un mail. Options :
 *   titre       le titre du message dans le gabarit (par défaut : le sujet) ;
 *   bouton      ['Libellé', 'https://…'] : l'action attendue, en bouton ; son adresse reste écrite dans le texte ;
 *   pied        une ligne de pied (par défaut : d'où vient ce message) ;
 *   repondre_a  l'adresse qui recevra la réponse (par défaut : l'expéditeur, dont Pupitre peut lire la boîte) ;
 *   nature      ce qu'est ce message (connexion, apercu, depot…), pour le courrier ;
 *   objet       [table, id] : ce à quoi il se rapporte, pour le courrier ;
 *   secret      vrai : le texte porte un lien personnel, il n'est pas conservé dans le courrier.
 * Renvoie vrai si Mailjet a accepté le message.
 */
function envoyer_mail(string $a, string $nom, string $sujet, string $texte, array $o = []): bool
{
    $cle = env('MAILJET_CLE');
    $secret = env('MAILJET_SECRET');
    $expediteur = env('MAIL_EXPEDITEUR');
    $noter = function (string $statut, string $identifiant = '') use ($a, $nom, $sujet, $texte, $o): void {
        if (!function_exists('courrier_noter_envoi')) return;
        try {
            courrier_noter_envoi($a, $nom, $sujet, !empty($o['secret']) ? '' : $texte, $statut, $identifiant, $o);
        } catch (Throwable $e) {
            journal_fichier('erreurs', 'courrier : ' . $e->getMessage());
        }
    };

    if (mail_adresse_essai($a)) {
        journal_fichier('mails', "non envoyé (adresse d'essai)\n  À : $nom <$a>\n  Sujet : $sujet\n  " . str_replace("\n", "\n  ", $texte));
        $noter('non_envoye');
        return false;
    }
    if ($cle === '' || $secret === '' || $expediteur === '') {
        journal_fichier('mails', "non envoyé (Mailjet non configuré)\n  À : $nom <$a>\n  Sujet : $sujet\n  " . str_replace("\n", "\n  ", $texte));
        $noter('non_envoye');
        return false;
    }
    $message = [
        'From' => ['Email' => $expediteur, 'Name' => env('MAIL_EXPEDITEUR_NOM', 'Pupitre')],
        'To' => [['Email' => $a, 'Name' => $nom ?: $a]],
        'Subject' => $sujet,
        'TextPart' => $texte . (!empty($o['bouton'][1]) && !str_contains($texte, $o['bouton'][1]) ? "\n\n" . $o['bouton'][0] . ' : ' . $o['bouton'][1] : ''),
        'HTMLPart' => mail_gabarit($o['titre'] ?? $sujet, $texte, $o),
    ];
    if (!empty($o['repondre_a']) && filter_var($o['repondre_a'], FILTER_VALIDATE_EMAIL)) $message['ReplyTo'] = ['Email' => $o['repondre_a']];
    $charge = json_encode(['Messages' => [$message]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://api.mailjet.com/v3.1/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $charge,
        CURLOPT_USERPWD => $cle . ':' . $secret,
        CURLOPT_TIMEOUT => 15,
    ]);
    $reponse = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur = curl_errno($ch);
    curl_close($ch);
    $ok = ($erreur === 0 && $code >= 200 && $code < 300);
    // l'identifiant que Mailjet donne au message : c'est par lui qu'on lira plus tard ce qu'il est devenu (remis, ouvert, rejeté)
    $identifiant = '';
    if ($ok && is_string($reponse)) {
        $r = json_decode($reponse, true);
        $identifiant = (string) ($r['Messages'][0]['To'][0]['MessageID'] ?? '');
    }
    // ni la clé, ni le corps du message : seulement l'issue
    journal_fichier('mails', ($ok ? 'envoyé' : 'échec (HTTP ' . $code . ', cURL ' . $erreur . ')') . ' · sujet : ' . $sujet);
    $noter($ok ? 'envoye' : 'echec', $identifiant);
    return $ok;
}

/**
 * Le message en HTML, selon la charte. Le texte est échappé, ses lignes vides font les paragraphes,
 * ses adresses deviennent des liens cuivre ; celle du bouton, déjà portée par le bouton, n'est pas répétée.
 */
function mail_gabarit(string $titre, string $texte, array $o = []): string
{
    $police = "font-family:Manrope,Arial,Helvetica,sans-serif";
    $bouton = (!empty($o['bouton'][0]) && !empty($o['bouton'][1])) ? $o['bouton'] : null;
    $paragraphes = '';
    foreach (preg_split('/\n\s*\n/', trim($texte)) ?: [] as $p) {
        $p = trim($p);
        if ($bouton && trim($p) === $bouton[1]) continue;       // l'adresse seule sur sa ligne : le bouton la porte
        $html = nl2br(e($p), false);
        $html = preg_replace_callback('#https?://[^\s<]+#u', function ($m) use ($bouton) {
            $url = rtrim($m[0], '.,;:!?)');
            $reste = substr($m[0], strlen($url));
            return '<a href="' . $url . '" style="color:#B84E0C;text-decoration:underline;word-break:break-all">' . $url . '</a>' . $reste;
        }, $html);
        $paragraphes .= '<p style="margin:0 0 16px;' . $police . ';font-size:16px;line-height:1.55;color:#586273">' . $html . '</p>';
    }
    $base = adresse_pupitre();
    $action = '';
    if ($bouton) {
        $action = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 8px"><tr>'
            . '<td bgcolor="#FF8B3D" style="background:#FF8B3D;border-radius:24px">'
            . '<a href="' . e($bouton[1]) . '" style="display:inline-block;padding:13px 26px;' . $police . ';font-size:16px;font-weight:bold;line-height:1.2;color:#12263F;text-decoration:none">' . e($bouton[0]) . '</a>'
            . '</td></tr></table>';
    }
    $pied = $o['pied'] ?? ('Pupitre, le pilotage de la communication' . (reglage_sur('artiste_nom') !== '' ? ' de ' . reglage_sur('artiste_nom') : '') . '.');
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . e($titre) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#FAF6F0">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#FAF6F0" style="background:#FAF6F0"><tr><td align="center" style="padding:32px 12px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background:#FFFFFF;border:1px solid #EAE3D9;border-radius:20px;overflow:hidden">'
        . '<tr><td bgcolor="#12263F" style="background:#12263F;padding:28px 36px"><img src="' . e($base) . '/ressources/marque/mailing/logo-email-inverse.png" width="140" alt="Pupitre" style="display:block;border:0;width:140px;height:auto"></td></tr>'
        . '<tr><td style="padding:36px 36px 28px">'
        . '<h1 style="margin:0 0 16px;font-family:Outfit,Arial,Helvetica,sans-serif;font-size:28px;line-height:1.15;font-weight:bold;color:#12263F">' . e($titre) . '</h1>'
        . $paragraphes . $action
        . '</td></tr>'
        . '<tr><td style="padding:18px 36px 22px;border-top:1px solid #EAE3D9;' . $police . ';font-size:13px;line-height:1.5;color:#586273">' . e($pied) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Un réglage, sans jamais faire échouer l'envoi d'un mail si la base n'est pas joignable. */
function reglage_sur(string $cle): string
{
    try {
        return (string) reglage($cle);
    } catch (Throwable $e) {
        return '';
    }
}

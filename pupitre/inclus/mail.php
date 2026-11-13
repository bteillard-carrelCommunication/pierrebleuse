<?php
/**
 * Mails : une seule fonction, envoyer_mail(). Envoi par l'API de Mailjet (v3.1).
 * Sans clé (MAILJET_CLE, MAILJET_SECRET), le message est écrit dans stockage/journaux/mails.log :
 * c'est là qu'on lit, sur le poste local, le lien de connexion ou de choix du mot de passe.
 */

defined('PUPITRE') or exit;

function envoyer_mail(string $a, string $nom, string $sujet, string $texte): bool
{
    $cle = env('MAILJET_CLE');
    $secret = env('MAILJET_SECRET');
    $expediteur = env('MAIL_EXPEDITEUR');
    if ($cle === '' || $secret === '' || $expediteur === '') {
        journal_fichier('mails', "non envoyé (Mailjet non configuré)\n  À : $nom <$a>\n  Sujet : $sujet\n  " . str_replace("\n", "\n  ", $texte));
        return false;
    }
    $charge = json_encode(['Messages' => [[
        'From' => ['Email' => $expediteur, 'Name' => env('MAIL_EXPEDITEUR_NOM', 'Pupitre')],
        'To' => [['Email' => $a, 'Name' => $nom ?: $a]],
        'Subject' => $sujet,
        'TextPart' => $texte,
    ]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://api.mailjet.com/v3.1/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $charge,
        CURLOPT_USERPWD => $cle . ':' . $secret,
        CURLOPT_TIMEOUT => 15,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreur = curl_errno($ch);
    curl_close($ch);
    $ok = ($erreur === 0 && $code >= 200 && $code < 300);
    // ni la clé, ni le corps du message : seulement l'issue
    journal_fichier('mails', ($ok ? 'envoyé' : 'échec (HTTP ' . $code . ', cURL ' . $erreur . ')') . ' · sujet : ' . $sujet);
    return $ok;
}

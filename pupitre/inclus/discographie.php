<?php
/**
 * Discographie : albums, liens d'écoute et de commande, distinctions.
 *
 * Un album est un objet indépendant. Sa vignette, sur le site, reste sobre ; « En savoir plus » y
 * ouvre un panneau qui montre tout ce que Pupitre sait de lui et qui est renseigné : présentation,
 * contexte, notes personnelles de l'artiste (« le mot »), œuvres, interprètes, distinctions,
 * critiques (les articles de la revue de presse associés à l'album : il n'y a pas de collection de
 * critiques à part), visuels, liens « Écouter » et « Commander ».
 *
 * Rien d'inventé : un champ vide ne sort pas, et le mot de l'artiste comme ses citations ne
 * reçoivent que ce qu'il a fourni ou validé.
 */

defined('PUPITRE') or exit;

/**
 * Plateformes d'écoute et distributeurs connus : clé => [nom, domaines qui la désignent]. La clé
 * choisit le pictogramme sur le site (js/sections.js, PICTOS_PLATEFORME) ; une plateforme absente
 * d'ici s'ajoute en une ligne, et reçoit le pictogramme « lien » tant qu'elle n'a pas le sien.
 */
const PLATEFORMES = [
    'spotify' => ['Spotify', ['spotify.com']],
    'apple-music' => ['Apple Music', ['music.apple.com', 'itunes.apple.com']],
    'deezer' => ['Deezer', ['deezer.com', 'deezer.page.link']],
    'youtube' => ['YouTube', ['youtube.com', 'youtu.be', 'music.youtube.com']],
    'qobuz' => ['Qobuz', ['qobuz.com']],
    'tidal' => ['Tidal', ['tidal.com']],
    'amazon' => ['Amazon', ['amazon.', 'music.amazon.']],
    'idagio' => ['Idagio', ['idagio.com']],
    'presto' => ['Presto Music', ['prestomusic.com']],
    'fnac' => ['Fnac', ['fnac.com']],
];

const NATURES_LIEN = ['ecoute' => 'Écouter', 'commande' => 'Commander'];

/** Plateforme désignée par un nom ou par une adresse ; '' si aucune n'est reconnue (le lien garde alors son nom et le pictogramme « lien »). */
function plateforme_deviner(string $nom, string $url): string
{
    $hote = preg_replace('/^www\./', '', mb_strtolower((string) parse_url($url, PHP_URL_HOST)));
    $n = normaliser($nom);
    foreach (PLATEFORMES as $cle => [$libelle, $domaines]) {
        if ($n !== '' && $n === normaliser($libelle)) return $cle;
        foreach ($domaines as $d) {
            if ($hote !== '' && (str_ends_with($hote, $d) || str_starts_with($hote, $d) || str_contains($hote, '.' . $d))) return $cle;
        }
    }
    return '';
}

function disque_lire(int $id): ?array
{
    $d = bd_ligne('SELECT d.*, a.nom AS maison FROM disques d LEFT JOIN annuaire a ON a.id = d.maison_id WHERE d.id = ?', [$id]);
    if (!$d) return null;
    $d['liens'] = bd_lignes('SELECT * FROM disques_liens WHERE disque_id = ? ORDER BY nature, ordre, id', [$id]);
    $d['distinctions'] = bd_lignes('SELECT * FROM distinctions WHERE disque_id = ? ORDER BY ordre, id', [$id]);
    return $d;
}

/** Enregistre les liens d'un album, d'une nature (ecoute ou commande), dans l'ordre reçu. Rend les erreurs. */
function disque_liens_enregistrer(int $disque_id, string $nature, array $recus): array
{
    $erreurs = [];
    $lignes = [];
    foreach (array_values($recus) as $r) {
        if (!is_array($r)) continue;
        $url = trim((string) ($r['url'] ?? ''));
        $nom = mb_substr(trim((string) ($r['nom'] ?? '')), 0, 120);
        if ($url === '' && $nom === '') continue;
        if ($url === '' || !adresse_valide($url)) { $erreurs[] = NATURES_LIEN[$nature] . ' : « ' . ($nom ?: $url) . ' » demande une adresse complète (https://…).'; continue; }
        $plateforme = isset(PLATEFORMES[(string) ($r['plateforme'] ?? '')]) ? (string) $r['plateforme'] : plateforme_deviner($nom, $url);
        if ($nom === '') $nom = $plateforme !== '' ? PLATEFORMES[$plateforme][0] : (string) parse_url($url, PHP_URL_HOST);
        $lignes[] = ['plateforme' => $plateforme, 'nom' => $nom, 'url' => mb_substr($url, 0, 500)];
    }
    if ($erreurs) return $erreurs;
    $avant = array_map(fn($l) => ['plateforme' => $l['plateforme'], 'nom' => $l['nom'], 'url' => $l['url']], bd_lignes('SELECT * FROM disques_liens WHERE disque_id = ? AND nature = ? ORDER BY ordre, id', [$disque_id, $nature]));
    if ($avant === $lignes) return [];
    bd_requete('DELETE FROM disques_liens WHERE disque_id = ? AND nature = ?', [$disque_id, $nature]);
    foreach ($lignes as $i => $l) bd_inserer('disques_liens', $l + ['disque_id' => $disque_id, 'nature' => $nature, 'ordre' => $i]);
    journaliser('modification', 'disque', $disque_id, 'Album : liens « ' . NATURES_LIEN[$nature] . ' »', ['liens' => $avant], ['liens' => $lignes]);
    return [];
}

function disque_supprimer(int $id): void
{
    $d = bd_ligne('SELECT * FROM disques WHERE id = ?', [$id]);
    if (!$d) return;
    bd_transaction(function () use ($id, $d) {
        bd_requete('DELETE FROM disques_liens WHERE disque_id = ?', [$id]);
        bd_requete('DELETE FROM articles_disques WHERE disque_id = ?', [$id]);
        bd_supprimer('distinctions', ['disque_id' => $id]);
        bd_supprimer('medias_usages', ['objet' => 'disques', 'objet_id' => (string) $id]);
        objet_supprimer('disques', $id, 'disque', preg_replace('/\*/', '', $d['titre']));
    });
}

/** Les champs d'une distinction, pour les listes ordonnables (écran Distinctions, fiche d'un album). */
function distinction_champs(bool $avec_album = true): array
{
    $champs = [];
    if ($avec_album) $champs[] = ['disque_id', 'choix', 'Album', 'options' => 'disques_choix', 'vide' => '— aucun album —', 'rang' => 'tete'];
    return array_merge($champs, [
        ['intitule', 'bi', 'Intitulé', 'requis' => true, 'aide' => '*…* pour l\'italique.'],
        ['organisme', 'ligne', 'Organisme ou média', 'max' => 190, 'rang' => 'qui'],
        ['annee', 'date-souple-annee', 'Date ou année', 'aide' => 'AAAA, AAAA-MM ou AAAA-MM-JJ.', 'rang' => 'qui'],
        ['url', 'adresse', 'Adresse (facultative)', 'rang' => 'plus'],
        ['logo_id', 'media', 'Logo ou visuel (facultatif)', 'types' => ['image'], 'rang' => 'plus'],
        ['description', 'bi-texte', 'Description (facultative)'],
        ['une', 'case', 'Mise en avant : montrée dès la vignette de l\'album'],
        ['publie', 'case', 'Publiée', 'defaut' => 1],
    ]);
}

function disques_choix(): array
{
    $c = [];
    foreach (bd_lignes('SELECT id, titre, annee FROM disques ORDER BY ordre, id') as $d) $c[(int) $d['id']] = preg_replace('/\*/', '', $d['titre']) . ($d['annee'] ? ' (' . $d['annee'] . ')' : '');
    return $c;
}

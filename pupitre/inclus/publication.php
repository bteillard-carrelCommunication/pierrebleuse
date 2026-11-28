<?php
/**
 * Publication vers le site : écarts, publication, retour à la publication précédente.
 *
 * Le site est statique et le reste. Pupitre écrit ses fichiers de data/, réécrit dans ses pages
 * les zones de repli entre repères et le numéro de version ?v= des scripts, copie les médias
 * employés. Rien d'autre. Avant d'écrire, les fichiers remplacés sont sauvegardés dans
 * stockage/publications/<date>/. L'écriture est atomique : fichier temporaire, puis renommage ;
 * en cas d'erreur, tout est remis en place.
 *
 * La mise en ligne (envoi vers le serveur, git) reste manuelle.
 */

defined('PUPITRE') or exit;

class ErreurPublication extends RuntimeException {}

/**
 * Forme canonique d'une donnée du site : { fr: X, en: X } vaut X (une traduction identique au texte
 * de référence ne dit rien de plus), l'ordre des clés ne compte pas.
 */
function donnee_canon($v)
{
    if (!is_array($v)) return $v;
    $codes = array_keys(langues_toutes());
    $reference = langue_reference();
    if ($v && !array_is_list($v) && isset($v[$reference]) && !array_diff(array_keys($v), $codes) && !array_filter($v, 'is_array')) {
        $texte = (string) $v[$reference];
        foreach ($v as $langue => $t) {
            if ($langue !== $reference && ((string) $t === '' || (string) $t === $texte)) unset($v[$langue]);
        }
        if (count($v) === 1) return $texte;
    }
    $v = array_map('donnee_canon', $v);
    if (!array_is_list($v)) ksort($v);
    return $v;
}

/**
 * Deux versions d'un fichier disent-elles la même chose ? Pour un fichier de données : mêmes
 * données, quelle que soit l'écriture (retraits, { fr, en } identiques repliés en chaîne, ordre
 * des clés ; la version et la note de l'agenda ne comptent pas). Pour une page : même texte.
 */
function fichiers_equivalents(string $chemin, ?string $avant, string $apres): bool
{
    if ($avant === null) return false;
    if ($avant === $apres) return true;
    if (!str_starts_with($chemin, 'data/')) return false;
    $lire = function (string $t) use ($chemin) {
        if (!str_ends_with($chemin, '.json')) {
            if (!preg_match('/window\.[A-Z_]+\s*=\s*(.*);\s*$/s', $t, $m)) return null;
            $t = $m[1];
        }
        $d = json_decode($t, true);
        if (is_array($d)) unset($d['version'], $d['note']);
        return $d === null ? null : donnee_canon($d);
    };
    $a = $lire($avant);
    $b = $lire($apres);
    return $a !== null && $a === $b;
}

/** Médias employés par le site dont le fichier n'y est pas encore : [source => chemin dans le site]. */
function publication_medias(string $racine): array
{
    $ids = array_merge(
        bd_colonne('SELECT media_id FROM medias_usages WHERE publie = 1'),
        bd_colonne('SELECT affiche_id FROM medias_usages WHERE publie = 1 AND affiche_id IS NOT NULL'),
        bd_colonne('SELECT media_id FROM musiques WHERE media_id IS NOT NULL AND publie = 1'),
        bd_colonne('SELECT media_id FROM telechargements WHERE media_id IS NOT NULL'),
        bd_colonne('SELECT a.logo_id FROM articles p JOIN annuaire a ON a.id = p.media_id WHERE a.logo_id IS NOT NULL AND p.publie = 1'),
        bd_colonne('SELECT logo_id FROM distinctions WHERE logo_id IS NOT NULL AND publie = 1')
    );
    $copies = [];
    $medias = medias_par_id();
    foreach (array_unique(array_map('intval', $ids)) as $id) {
        $m = $medias[$id] ?? null;
        if (!$m || empty($m['fichier']) || !media_publiable($m)) continue;       // une adresse externe n'a pas de fichier à copier
        $source = STOCKAGE . '/medias/' . basename($m['fichier']);
        $cible = media_chemin_site($m);
        if (!is_file($source) || $cible === '') continue;
        if (!is_file($racine . '/' . $cible) || filesize($racine . '/' . $cible) !== filesize($source)) $copies[$source] = $cible;
    }
    // analyses de bandes-son faites par Pupitre : rangées dans stockage/analyses/, attendues dans data/
    $pistes = bd_colonne("SELECT piste FROM musiques WHERE piste <> ''");
    foreach (bd_colonne("SELECT parametres FROM medias_usages WHERE objet = 'blocs' AND parametres LIKE '%piste%'") as $json) {
        $piste = (string) (json_decode((string) $json, true)['piste'] ?? '');
        if ($piste !== '') $pistes[] = $piste;
    }
    foreach (array_unique($pistes) as $piste) {
        $source = STOCKAGE . '/analyses/' . basename($piste);
        if (preg_match('#^data/[\w.-]+\.json$#', $piste) && is_file($source)
            && (!is_file($racine . '/' . $piste) || md5_file($racine . '/' . $piste) !== md5_file($source))) {
            $copies[$source] = $piste;
        }
    }
    return $copies;
}

/**
 * Où en est le site par rapport à Pupitre.
 * Rend ['racine', 'fichiers' => [chemin => ['etat', 'avant', 'apres']], 'medias' => [...], 'ecarts' => n].
 * États : identique ; equivalent (mêmes données, autre écriture : sera réécrit, ne compte pas
 * comme un écart) ; modifie ; nouveau.
 */
function publication_etat(): array
{
    $racine = site_racine();
    if ($racine === '' || !is_dir($racine . '/data')) {
        throw new ErreurPublication('La racine du site est introuvable (réglage « racine du site ») : on y attend un dossier data/.');
    }
    $exports = site_exports();
    $fichiers = [];
    foreach (site_fichiers($exports) as $chemin => $contenu) $fichiers[$chemin] = $contenu;
    $zones = repli_zones($exports);
    foreach (site_pages($racine) as $page) {
        $actuelle = file_get_contents($racine . '/' . $page);
        $fichiers[$page] = repli_appliquer($actuelle, $zones);
    }
    $etat = [];
    $ecarts = 0;
    foreach ($fichiers as $chemin => $apres) {
        $avant = is_file($racine . '/' . $chemin) ? file_get_contents($racine . '/' . $chemin) : null;
        if ($avant === null) $e = 'nouveau';
        elseif ($avant === $apres) $e = 'identique';
        elseif (fichiers_equivalents($chemin, $avant, $apres)) $e = 'equivalent';
        else $e = 'modifie';
        if ($e === 'nouveau' || $e === 'modifie') $ecarts++;
        $etat[$chemin] = ['etat' => $e, 'avant' => $avant, 'apres' => $apres];
    }
    $medias = publication_medias($racine);
    return ['racine' => $racine, 'fichiers' => $etat, 'medias' => $medias, 'ecarts' => $ecarts + count($medias)];
}

/** Représentations annoncées qui passeront à « publiée », et publiées qui quitteront le site. */
function publication_representations(): array
{
    return [
        'entrent' => bd_lignes("SELECT r.id, r.date, c.titre_en FROM representations r JOIN concerts c ON c.id = r.concert_id WHERE r.statut = 'annoncee' ORDER BY r.date"),
    ];
}

/** Numéro de version suivant : la date du jour, puis une lettre (20261112a, 20261112b…). */
function version_suivante(string $actuelle): string
{
    $jour = date('Ymd');
    if (preg_match('/^' . $jour . '([a-z])$/', $actuelle, $m) && $m[1] !== 'z') return $jour . chr(ord($m[1]) + 1);
    if (str_starts_with($actuelle, $jour)) return $jour . date('His');
    return $jour . 'a';
}

function version_actuelle(string $page): string
{
    return preg_match('/\?v=([0-9A-Za-z]+)/', $page, $m) ? $m[1] : '';
}

/** Écrit un fichier d'un seul geste : fichier temporaire à côté, puis renommage. */
function ecrire_atomique(string $chemin, string $contenu): void
{
    dossier(dirname($chemin));
    $tmp = $chemin . '.pupitre-' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $contenu) !== strlen($contenu)) {
        @unlink($tmp);
        throw new ErreurPublication('Écriture impossible : ' . basename($chemin) . ' (droits du dossier ?).');
    }
    @chmod($tmp, is_file($chemin) ? (fileperms($chemin) & 0777) : 0644);
    if (!@rename($tmp, $chemin)) {
        @unlink($tmp);
        throw new ErreurPublication('Remplacement impossible : ' . basename($chemin) . '.');
    }
}

/**
 * Remplace un lot de fichiers : tout, ou rien. Les fichiers remplacés sont d'abord copiés dans
 * $sauvegarde. Si une écriture échoue, ceux qui ont déjà été remplacés sont remis en place et
 * ceux qui ont été créés sont retirés. Rend ['remplaces' => [...], 'crees' => [...]].
 */
function ecrire_lot(string $racine, array $fichiers, string $sauvegarde): array
{
    $remplaces = $crees = [];
    foreach ($fichiers as $chemin => $_) {
        if (!is_file($racine . '/' . $chemin)) continue;
        dossier(dirname($sauvegarde . '/' . $chemin));
        if (!@copy($racine . '/' . $chemin, $sauvegarde . '/' . $chemin)) {
            throw new ErreurPublication('Sauvegarde impossible : ' . $chemin . '. Rien n\'a été écrit.');
        }
    }
    try {
        foreach ($fichiers as $chemin => $contenu) {
            $existait = is_file($racine . '/' . $chemin);
            ecrire_atomique($racine . '/' . $chemin, $contenu);
            if ($existait) $remplaces[] = $chemin; else $crees[] = $chemin;
        }
    } catch (Throwable $e) {
        foreach ($remplaces as $chemin) @copy($sauvegarde . '/' . $chemin, $racine . '/' . $chemin);
        foreach ($crees as $chemin) @unlink($racine . '/' . $chemin);
        throw new ErreurPublication($e->getMessage() . ' La publication est annulée, le site est resté tel qu\'il était.');
    }
    return ['remplaces' => $remplaces, 'crees' => $crees];
}

/**
 * Publie. Rend un résumé : ['fichiers' => n (écrits), 'contenus' => n (dont le contenu change), 'pages_version' => n
 * (pages qui ne reçoivent que le numéro de version), 'medias' => n, 'representations' => n, 'version' => …].
 */
function publier(bool $version_seule = false): array
{
    $etat = publication_etat();
    $racine = $etat['racine'];
    $a_ecrire = [];
    foreach ($etat['fichiers'] as $chemin => $f) {
        if ($f['etat'] !== 'identique') $a_ecrire[$chemin] = $f['apres'];
    }
    // $version_seule : aucun contenu n'a changé, mais une feuille de style ou un script du site a été modifié à la
    // main ; les pages reçoivent un nouveau numéro de version pour que les navigateurs rechargent ces fichiers
    if (!$a_ecrire && !$etat['medias'] && !$version_seule) throw new ErreurPublication('Le site est déjà à jour : rien à publier.');

    // ce dont le contenu change : c'est ce nombre que la publication annonce
    $contenus = array_keys(array_filter($etat['fichiers'], fn($f) => in_array($f['etat'], ['modifie', 'nouveau'], true)));

    // le numéro de version des scripts et des feuilles de style, dans toutes les pages : c'est lui qui fait
    // recharger aux navigateurs un fichier modifié (une donnée, css/reglages.css), sans attendre la fin de leur cache
    $pages = site_pages($racine);
    $version = '';
    foreach ($pages as $page) $version = max($version, version_actuelle($etat['fichiers'][$page]['apres'] ?? ''));
    $nouvelle = version_suivante($version);
    foreach ($pages as $page) {
        $contenu = $etat['fichiers'][$page]['apres'];
        $a_ecrire[$page] = preg_replace('/\?v=[0-9A-Za-z]+/', '?v=' . $nouvelle, $contenu);
    }

    $dossier = date('Ymd-His');
    $sauvegarde = dossier(STOCKAGE . '/publications/' . $dossier);

    // les médias d'abord : une page ne doit jamais citer un fichier absent
    $medias_copies = [];
    foreach ($etat['medias'] as $source => $cible) {
        dossier(dirname($racine . '/' . $cible));
        if (is_file($racine . '/' . $cible)) {
            dossier(dirname($sauvegarde . '/' . $cible));
            @copy($racine . '/' . $cible, $sauvegarde . '/' . $cible);
        }
        $tmp = $racine . '/' . $cible . '.pupitre.tmp';
        if (!@copy($source, $tmp) || !@rename($tmp, $racine . '/' . $cible)) {
            @unlink($tmp);
            throw new ErreurPublication('Copie impossible : ' . $cible . '. Rien d\'autre n\'a été écrit.');
        }
        $medias_copies[] = $cible;
    }

    $lot = ecrire_lot($racine, $a_ecrire, $sauvegarde);

    // la base suit : les représentations annoncées sont désormais publiées
    $c = compte();
    return bd_transaction(function () use ($lot, $medias_copies, $dossier, $sauvegarde, $nouvelle, $c, $etat, $contenus, $pages) {
        $reps = array_map('intval', bd_colonne("SELECT id FROM representations WHERE statut = 'annoncee'"));
        if ($reps) bd_requete("UPDATE representations SET statut = 'publiee' WHERE statut = 'annoncee'");
        foreach ($etat['medias'] as $source => $cible) {
            if (str_starts_with($cible, 'assets/')) bd_modifier('medias', ['chemin_site' => $cible], ['fichier' => basename($source)]);
        }
        $GLOBALS['medias_perimes'] = true;
        $manifeste = ['remplaces' => $lot['remplaces'], 'crees' => $lot['crees'], 'medias' => $medias_copies];
        file_put_contents($sauvegarde . '/manifeste.json', json_encode($manifeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $id = bd_inserer('publications', [
            'nature' => 'publication',
            'compte_id' => $c['id'] ?? null,
            'compte_nom' => $c['nom'] ?? 'ligne de commande',
            'dossier' => $dossier,
            'fichiers' => json_encode($manifeste, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'representations' => json_encode($reps),
            'version' => $nouvelle,
        ]);
        // « fichiers » : tout ce qui a été écrit ; « contenus » : ce dont le contenu a changé ; « pages_version » : les pages qui n'ont reçu que le numéro de version
        $resume = ['fichiers' => count($lot['remplaces']) + count($lot['crees']), 'contenus' => count($contenus), 'pages_version' => count(array_diff($pages, $contenus)),
            'medias' => count($medias_copies), 'representations' => count($reps), 'version' => $nouvelle];
        journaliser('publication', 'site', $id, 'Publication du ' . date('d/m/Y à H:i'), null, $resume);
        return $resume;
    });
}

/** La dernière publication encore en place (ni défaite, ni un retour). */
function publication_derniere(): ?array
{
    return bd_ligne("SELECT * FROM publications WHERE nature = 'publication' AND annulee = 0 ORDER BY id DESC LIMIT 1");
}

/**
 * Revient à la publication précédente : remet en place les fichiers que la dernière publication
 * a remplacés, retire ceux qu'elle a créés, et rend leur statut « annoncée » aux représentations
 * qu'elle avait publiées. Les contenus de Pupitre ne changent pas : les écarts réapparaissent.
 */
function publication_revenir(): array
{
    $p = publication_derniere();
    if (!$p) throw new ErreurPublication('Aucune publication à défaire.');
    $racine = site_racine();
    $sauvegarde = STOCKAGE . '/publications/' . basename($p['dossier']);
    $manifeste = json_decode((string) $p['fichiers'], true) ?: [];
    if ($racine === '' || !is_dir($sauvegarde)) throw new ErreurPublication('La sauvegarde de cette publication est introuvable (stockage/publications/' . $p['dossier'] . ').');

    $a_remettre = [];
    foreach ($manifeste['remplaces'] ?? [] as $chemin) {
        if (!is_file($sauvegarde . '/' . $chemin)) throw new ErreurPublication('Sauvegarde incomplète : ' . $chemin . ' manque. Rien n\'a été touché.');
        $a_remettre[$chemin] = file_get_contents($sauvegarde . '/' . $chemin);
    }
    // l'état d'aujourd'hui est gardé à son tour, au cas où
    $retour = dossier(STOCKAGE . '/publications/' . date('Ymd-His') . '-retour');
    ecrire_lot($racine, $a_remettre, $retour);
    foreach ($manifeste['crees'] ?? [] as $chemin) {
        if (preg_match('#^(data/[\w.-]+|css/reglages\.css|[\w.-]+\.html)$#', $chemin) && is_file($racine . '/' . $chemin)) {
            dossier(dirname($retour . '/' . $chemin));
            @copy($racine . '/' . $chemin, $retour . '/' . $chemin);
            @unlink($racine . '/' . $chemin);
        }
    }
    $c = compte();
    return bd_transaction(function () use ($p, $a_remettre, $c) {
        $reps = array_filter(array_map('intval', json_decode((string) $p['representations'], true) ?: []));
        $n = 0;
        if ($reps) {
            $n = bd_requete("UPDATE representations SET statut = 'annoncee' WHERE statut = 'publiee' AND id IN (" . implode(',', $reps) . ')')->rowCount();
        }
        bd_modifier('publications', ['annulee' => 1], ['id' => $p['id']]);
        $id = bd_inserer('publications', [
            'nature' => 'retour',
            'compte_id' => $c['id'] ?? null,
            'compte_nom' => $c['nom'] ?? 'ligne de commande',
            'dossier' => $p['dossier'],
            'fichiers' => $p['fichiers'],
            'representations' => $p['representations'],
        ]);
        $resume = ['fichiers' => count($a_remettre), 'representations' => $n, 'publication_defaite' => horodatage_fr($p['cree_le'])];
        journaliser('retour', 'site', $id, 'Retour à l\'état d\'avant la publication du ' . horodatage_fr($p['cree_le']), null, $resume);
        return $resume;
    });
}

// ---------- Prévisualisation : les lignes qui changent ----------

/**
 * Différences entre deux textes, ligne à ligne : liste de ['-'|'+'|' ', ligne], réduite aux
 * passages qui changent (avec $contexte lignes autour). Les longs passages sont abrégés.
 */
function differences_lignes(?string $avant, string $apres, int $contexte = 2): array
{
    $a = $avant === null ? [] : explode("\n", $avant);
    $b = explode("\n", $apres);
    // début et fin communs : on ne compare que le milieu
    $debut = 0;
    while ($debut < count($a) && $debut < count($b) && $a[$debut] === $b[$debut]) $debut++;
    $fin = 0;
    while ($fin < count($a) - $debut && $fin < count($b) - $debut && $a[count($a) - 1 - $fin] === $b[count($b) - 1 - $fin]) $fin++;
    $ma = array_slice($a, $debut, count($a) - $debut - $fin);
    $mb = array_slice($b, $debut, count($b) - $debut - $fin);
    $ops = [];
    $n = count($ma);
    $m = count($mb);
    if ($n * $m > 250000) {
        // trop long pour un alignement fin : l'ancien bloc, puis le nouveau
        foreach ($ma as $l) $ops[] = ['-', $l];
        foreach ($mb as $l) $ops[] = ['+', $l];
    } else {
        // plus longue sous-suite commune
        $t = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $t[$i][$j] = $ma[$i] === $mb[$j] ? $t[$i + 1][$j + 1] + 1 : max($t[$i + 1][$j], $t[$i][$j + 1]);
            }
        }
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($ma[$i] === $mb[$j]) { $ops[] = [' ', $ma[$i]]; $i++; $j++; }
            elseif ($t[$i + 1][$j] >= $t[$i][$j + 1]) { $ops[] = ['-', $ma[$i]]; $i++; }
            else { $ops[] = ['+', $mb[$j]]; $j++; }
        }
        while ($i < $n) $ops[] = ['-', $ma[$i++]];
        while ($j < $m) $ops[] = ['+', $mb[$j++]];
    }
    // contexte autour du milieu
    $avant_ctx = array_slice($a, max(0, $debut - $contexte), min($contexte, $debut));
    $apres_ctx = array_slice($a, count($a) - $fin, $contexte);
    $ops = array_merge(array_map(fn($l) => [' ', $l], $avant_ctx), $ops, array_map(fn($l) => [' ', $l], $apres_ctx));
    // les longues plages inchangées sont abrégées
    $sortie = [];
    $k = count($ops);
    for ($x = 0; $x < $k; $x++) {
        if ($ops[$x][0] !== ' ') { $sortie[] = $ops[$x]; continue; }
        $proche = false;
        for ($y = max(0, $x - $contexte); $y <= min($k - 1, $x + $contexte); $y++) {
            if ($ops[$y][0] !== ' ') { $proche = true; break; }
        }
        if ($proche) $sortie[] = $ops[$x];
        elseif (!$sortie || end($sortie)[0] !== '…') $sortie[] = ['…', ''];
    }
    return $sortie;
}

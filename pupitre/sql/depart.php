<?php
/**
 * Départ : crée la base si elle manque, applique le schéma, reprend les données du site.
 *
 *     php pupitre/sql/depart.php              tout : base, schéma, reprise (refuse si l'agenda existe déjà)
 *     php pupitre/sql/depart.php --schema     le schéma seulement (rejouable, ne détruit rien)
 *     php pupitre/sql/depart.php --forcer     vide les contenus et les reprend ; les comptes et le journal restent
 *     php pupitre/sql/depart.php --pilote     crée le compte pilote s'il manque et donne un lien pour choisir son mot de passe
 *
 * La reprise charge dans la base tout ce que le site affiche, à partir de ses fichiers de data/ :
 * agenda (représentations au statut « publiée »), disques, articles de presse réels (pas les faux
 * articles de maquette, qui sont dans un autre fichier), orchestres, visuels, titres du lecteur,
 * textes, parcours, contacts, réseaux, téléchargements. L'annuaire est amorcé avec les ensembles,
 * salles, solistes, maisons de disques et médias rencontrés, sans compte social.
 * Le compte pilote est créé avec l'adresse lue dans PILOTE_EMAIL.
 *
 * Rien n'est inventé : un champ absent du site reste vide.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Ligne de commande seulement.'); }

require __DIR__ . '/../inclus/config.php';

$forcer = in_array('--forcer', $argv, true);
$schema_seul = in_array('--schema', $argv, true);
$pilote_seul = in_array('--pilote', $argv, true);
$dire = fn(string $s) => print($s . "\n");

// ---------- 1. La base ----------
if (env('BD_NOM') === '' || env('BD_UTILISATEUR') === '') {
    fwrite(STDERR, "Renseignez d'abord BD_HOTE, BD_PORT, BD_NOM, BD_UTILISATEUR et BD_MOT_DE_PASSE dans pupitre/.env (modèle : pupitre/.env.exemple).\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9_]+$/', env('BD_NOM'))) { fwrite(STDERR, "BD_NOM : lettres, chiffres et tiret bas seulement.\n"); exit(1); }
try {
    $serveur = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('BD_HOTE', '127.0.0.1'), env('BD_PORT', '3306')),
        env('BD_UTILISATEUR'), env('BD_MOT_DE_PASSE'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $serveur->exec('CREATE DATABASE IF NOT EXISTS `' . env('BD_NOM') . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $dire('Base « ' . env('BD_NOM') . ' » : prête.');
} catch (PDOException $e) {
    $dire('La base n\'a pas pu être créée d\'ici (droits de l\'utilisateur ?) : on continue si elle existe déjà.');
}
$serveur = null;

require RACINE . '/inclus/amorce.php';

// ---------- 2. Le schéma ----------
$sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/schema.sql'));
$n = 0;
foreach (preg_split('/;\s*\n/', $sql) as $requete) {
    if (trim($requete) === '') continue;
    bd()->exec($requete);
    $n++;
}
$dire("Schéma : $n instructions appliquées.");

// Mise à niveau d'une base créée avant le 13/11/2026 : le parcours des orchestres vivait dans trois
// colonnes de l'annuaire (une fiche, un seul lieu). Il a désormais sa table.
if (bd_valeur("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'annuaire' AND column_name = 'dans_parcours'")) {
    if (!bd_valeur('SELECT COUNT(*) FROM parcours_orchestres')) {
        bd()->exec('INSERT INTO parcours_orchestres (fiche_id, ville_fr, ville_en, latitude, longitude, zone, ordre)
                    SELECT id, ville_fr, ville_en, latitude, longitude, parcours_zone, parcours_ordre FROM annuaire WHERE dans_parcours = 1 ORDER BY parcours_ordre, id');
    }
    bd()->exec('ALTER TABLE annuaire DROP COLUMN dans_parcours, DROP COLUMN parcours_zone, DROP COLUMN parcours_ordre');
    $dire('Mise à niveau : le parcours des orchestres a sa table.');
}
if ($schema_seul) exit(0);

// ---------- 3. La reprise ----------
if (!$pilote_seul) {
$racine = site_racine();
if ($racine === '' || !is_dir($racine . '/data')) { fwrite(STDERR, "Racine du site introuvable : on y attend un dossier data/.\n"); exit(1); }

$tables_de_contenu = ['concerts', 'representations', 'programme', 'participants', 'participants_fiches', 'notes_artiste', 'maisons',
    'annuaire', 'annuaire_comptes', 'annuaire_contacts', 'medias', 'textes', 'parcours_lignes', 'disques', 'articles', 'visuels',
    'musiques', 'telechargements', 'contacts_groupes', 'contacts_personnes', 'reseaux', 'parcours_orchestres', 'publications', 'imports', 'import_lignes',
    'alertes', 'taches'];
if (bd_valeur('SELECT COUNT(*) FROM concerts') || bd_valeur('SELECT COUNT(*) FROM textes')) {
    if (!$forcer) {
        fwrite(STDERR, "La base contient déjà des contenus. Pour tout reprendre depuis le site : php pupitre/sql/depart.php --forcer\n(les comptes et le journal sont gardés ; les contenus saisis dans Pupitre sont perdus).\n");
        exit(1);
    }
    foreach ($tables_de_contenu as $t) bd()->exec('TRUNCATE TABLE ' . bd_nom($t));
    $dire('Contenus vidés (--forcer).');
}

$lire = function (string $fichier) use ($racine) {
    $d = site_lire_donnees($racine . '/data/' . $fichier);
    if ($d === null) { fwrite(STDERR, "Fichier illisible : data/$fichier\n"); exit(1); }
    return $d;
};
$compte = [];

/** Média déjà présent dans le site : une ligne de médiathèque qui pointe sur son fichier. */
$media_du_site = function (string $chemin, string $type, array $alt = ['', '']) use ($racine): ?int {
    if ($chemin === '') return null;
    $id = bd_valeur('SELECT id FROM medias WHERE chemin_site = ?', [$chemin]);
    if ($id) return (int) $id;
    $f = $racine . '/' . $chemin;
    $taille = is_file($f) ? filesize($f) : 0;
    $mime = is_file($f) ? (string) (new finfo(FILEINFO_MIME_TYPE))->file($f) : '';
    if ($mime === 'video/mp4' && str_ends_with($chemin, '.m4a')) $mime = 'audio/mp4';
    $dim = ($type === 'image' && is_file($f)) ? @getimagesize($f) : false;
    return bd_inserer('medias', ['type' => $type, 'chemin_site' => $chemin, 'nom_origine' => basename($chemin), 'mime' => $mime, 'taille' => $taille,
        'largeur' => $dim ? $dim[0] : null, 'hauteur' => $dim ? $dim[1] : null, 'alt_fr' => $alt[0], 'alt_en' => $alt[1]]);
};

/** Fiche d'annuaire, créée si aucune ne porte ce nom ; l'autre nom (anglais) est noté. */
$fiche = function (string $categorie, $nom, array $champs = [], array $voisines = []): ?int {
    [$fr, $en] = bi_lire($nom);
    if ($fr === '') return null;
    $id = fiche_trouver($fr, array_merge([$categorie], $voisines));
    if (!$id) {
        $id = bd_inserer('annuaire', ['categorie' => $categorie, 'nom' => $fr, 'autres_noms' => $en] + $champs);
        $GLOBALS['annuaire_perime'] = true;
    }
    return $id;
};
$est_ensemble = fn(string $nom) => (bool) preg_match('/\b(ensemble|compagnie|ch[oœ]e?ur|choir|quatuor|quartet|orchestr|philharmoni)/iu', $nom);

bd_transaction(function () use ($lire, $racine, $media_du_site, $fiche, $est_ensemble, &$compte, $dire) {
    // --- Réglages : le nom de l'artiste vient de .env, sinon du logo de la page d'accueil
    $nom = env('ARTISTE_NOM');
    if ($nom === '' && preg_match('/<a class="logo"[^>]*aria-label="([^"]+)"/', (string) @file_get_contents($racine . '/index.html'), $m)) $nom = html_entity_decode($m[1]);
    if ($nom !== '' && reglage('artiste_nom') === '') {
        reglage_ecrire('artiste_nom', $nom);
        reglage_ecrire('sous_titre', $nom);
    }

    // --- Agenda : maisons, concerts, représentations publiées, programme, participants ; l'annuaire s'amorce au passage
    $agenda = $lire('agenda.json');
    $maisons = [];
    $ordre = 0;
    foreach ($agenda['maisons'] ?? [] as $cle => $libelle) {
        [$fr, $en] = bi_lire($libelle);
        $maisons[$cle] = bd_inserer('maisons', ['cle' => $cle, 'nom_fr' => $fr, 'nom_en' => $en, 'ordre' => $ordre++]);
    }
    $compte['représentations'] = 0;
    foreach ($agenda['evenements'] as $e) {
        [$ens_fr, $ens_en] = bi_lire($e['ensemble'] ?? '');
        [$ville_fr, $ville_en] = bi_lire($e['ville'] ?? '');
        [$pays_fr, $pays_en] = bi_lire($e['pays'] ?? '');
        [$salle_fr, $salle_en] = bi_lire($e['salle'] ?? '');
        [$titre_fr, $titre_en] = bi_lire($e['titre'] ?? '');
        [$mentions_fr, $mentions_en] = bi_lire($e['mentions'] ?? '');
        $lieu = ['ville_fr' => $ville_fr, 'ville_en' => $ville_en, 'pays_fr' => $pays_fr, 'pays_en' => $pays_en];
        $premiere = min(array_column($e['representations'], 'date'));
        $ensemble_id = $fiche(preg_match('/ensemble/i', $ens_fr) ? 'ensemble' : 'orchestre', $e['ensemble'] ?? '', [], ['orchestre', 'ensemble']);
        $fuseau = fuseau_deviner($pays_fr, $ville_fr, $ville_en, $premiere);
        $salle_id = $fiche('salle', $e['salle'] ?? '', $lieu + ['fuseau' => $fuseau], ['festival']);
        $lien = (string) ($e['lien'] ?? '');
        $id = bd_inserer('concerts', [
            'cle' => $e['id'], 'type' => $e['type'], 'titre_fr' => $titre_fr, 'titre_en' => $titre_en,
            'ensemble_id' => $ensemble_id, 'ensemble_fr' => $ens_fr, 'ensemble_en' => $ens_en,
            'maison_id' => $maisons[$e['maison'] ?? ''] ?? null,
            'salle_id' => $salle_id, 'salle_fr' => $salle_fr, 'salle_en' => $salle_en,
            'mentions_fr' => $mentions_fr, 'mentions_en' => $mentions_en,
            'page_organisateur' => $lien, 'temps_fort' => empty($e['tempsFort']) ? 0 : 1, 'fuseau' => $fuseau,
        ] + $lieu);
        foreach ($e['representations'] as $r) {
            [$rv_fr, $rv_en] = bi_lire($r['ville'] ?? '');
            [$rp_fr, $rp_en] = bi_lire($r['pays'] ?? '');
            [$rs_fr, $rs_en] = bi_lire($r['salle'] ?? '');
            $rlien = (string) ($r['lien'] ?? '');
            if ($rs_fr !== '') $fiche('salle', $r['salle'], ['ville_fr' => $rv_fr, 'ville_en' => $rv_en, 'pays_fr' => $rp_fr, 'pays_en' => $rp_en, 'fuseau' => fuseau_deviner($rp_fr, $rv_fr, $rv_en, $r['date'])], ['festival']);
            bd_inserer('representations', [
                'concert_id' => $id, 'date' => $r['date'], 'heure' => $r['heure'] ?? null, 'statut' => 'publiee', 'origine' => 'reprise',
                // la page de l'organisateur, quand le site la donne, est la source de l'annonce
                'lien_source' => $rlien !== '' ? $rlien : $lien,
                'salle_fr' => $rs_fr, 'salle_en' => $rs_en, 'ville_fr' => $rv_fr, 'ville_en' => $rv_en, 'pays_fr' => $rp_fr, 'pays_en' => $rp_en,
                'lien_billetterie' => $rlien,
            ]);
            $compte['représentations']++;
        }
        foreach ($e['programme'] ?? [] as $i => $p) {
            [$c_fr, $c_en] = bi_lire($p['compositeur'] ?? '');
            [$o_fr, $o_en] = bi_lire($p['oeuvre'] ?? '');
            [$m_fr, $m_en] = bi_lire($p['mention'] ?? '');
            bd_inserer('programme', ['concert_id' => $id, 'ordre' => $i, 'compositeur_fr' => $c_fr, 'compositeur_en' => $c_en,
                'oeuvre_fr' => $o_fr, 'oeuvre_en' => $o_en, 'mention_fr' => $m_fr, 'mention_en' => $m_en]);
        }
        foreach ($e['avec'] ?? [] as $i => $p) {
            [$r_fr, $r_en] = bi_lire($p['role'] ?? '');
            bd_inserer('participants', ['concert_id' => $id, 'ordre' => $i, 'nom' => $p['nom'], 'role_fr' => $r_fr, 'role_en' => $r_en]);
            // une ligne peut nommer plusieurs personnes : une fiche chacune
            foreach ($est_ensemble($p['nom']) ? [$p['nom']] : array_map('trim', explode(',', $p['nom'])) as $personne) {
                $fiche($est_ensemble($personne) ? 'ensemble' : 'soliste', $personne, [], ['ensemble', 'orchestre', 'soliste']);
            }
        }
        participants_relier($id);
    }
    $compte['événements'] = count($agenda['evenements']);

    // --- Disques : la maison de disques entre dans l'annuaire
    foreach ($lire('disques.js') as $i => $d) {
        $descriptif = $d['descriptif'] ?? '';
        $lignes = (is_array($descriptif) && array_is_list($descriptif)) ? $descriptif : [$descriptif];
        $fr = $en = [];
        $bilingue = false;
        foreach ($lignes as $l) {
            [$a, $b] = bi_lire($l);
            if ($a === '') continue;
            $fr[] = $a;
            $en[] = $b !== '' ? $b : $a;
            $bilingue = $bilingue || $b !== '';
        }
        [$mot_fr, $mot_en] = bi_lire($d['mot'] ?? '');
        $liens = fn(array $l) => implode("\n", array_map(fn($x) => ($x['nom'] ?? '') . ' | ' . ($x['url'] ?? ''), $l));
        bd_inserer('disques', [
            'cle' => $d['id'], 'titre' => $d['titre'], 'annee' => ($d['annee'] ?? '') === '' ? null : (int) $d['annee'],
            'maison_id' => $fiche('maison_de_disques', $d['maison'] ?? ''),
            'descriptif_fr' => implode("\n", $fr), 'descriptif_en' => $bilingue ? implode("\n", $en) : '',
            'mot_fr' => $mot_fr, 'mot_en' => $mot_en,
            'commander' => $liens($d['commander'] ?? []), 'streaming' => $liens($d['streaming'] ?? []),
            'lien' => (string) ($d['lien'] ?? ''), 'ordre' => $i,
        ]);
    }
    $compte['disques'] = (int) bd_valeur('SELECT COUNT(*) FROM disques');

    // --- Presse : les vrais articles (data/presse.js) ; le média entre dans l'annuaire avec son logo
    foreach ($lire('presse.js') as $i => $a) {
        $media = $fiche('media', $a['media'] ?? '');
        if ($media && !empty($a['logo'])) bd_modifier('annuaire', ['logo_id' => $media_du_site($a['logo'], 'image')], ['id' => $media]);
        [$t_fr, $t_en] = bi_lire($a['titre'] ?? '');
        [$r_fr, $r_en] = bi_lire($a['resume'] ?? '');
        bd_inserer('articles', ['cle' => $a['id'], 'media_id' => $media, 'titre_fr' => $t_fr, 'titre_en' => $t_en, 'auteur' => (string) ($a['auteur'] ?? ''),
            'date_article' => (string) ($a['date'] ?? ''), 'verbatim' => (string) ($a['verbatim'] ?? ''), 'langue' => (string) ($a['langue'] ?? ''),
            'resume_fr' => $r_fr, 'resume_en' => $r_en, 'lien' => (string) ($a['lien'] ?? ''), 'ordre' => $i]);
    }
    $compte['articles'] = (int) bd_valeur('SELECT COUNT(*) FROM articles');

    // --- Orchestres invités : une fiche de l'annuaire et le lieu où la placer. Le lieu est celui du
    //     concert : une même fiche peut revenir dans plusieurs villes.
    foreach ($lire('orchestres.js') as $i => $o) {
        [$v_fr, $v_en] = bi_lire($o['ville'] ?? '');
        if (($o['nom'] ?? '') === '') {
            // le nom de l'orchestre reste à fournir : la fiche porte la ville, le site n'affiche qu'elle
            $id = bd_inserer('annuaire', ['categorie' => 'orchestre', 'nom' => $v_fr, 'nom_provisoire' => 1]);
            $GLOBALS['annuaire_perime'] = true;
        } else {
            $id = $fiche(preg_match('/ensemble/i', $o['nom']) ? 'ensemble' : 'orchestre', $o['nom'], [], ['orchestre', 'ensemble']);
        }
        bd_inserer('parcours_orchestres', ['fiche_id' => $id, 'ville_fr' => $v_fr, 'ville_en' => $v_en, 'latitude' => $o['lat'], 'longitude' => $o['lon'],
            'zone' => (string) ($o['zone'] ?? ''), 'ordre' => $i]);
    }
    $compte['orchestres invités'] = (int) bd_valeur('SELECT COUNT(*) FROM parcours_orchestres');

    // --- Visuels et musiques : les fichiers sont déjà dans le site, la médiathèque les référence
    $pourcent = fn(?string $p) => preg_match('/^\s*(\d+)%\s+(\d+)%\s*$/', (string) $p, $m) ? [(int) $m[1], (int) $m[2]] : [null, null];
    $ordre = 0;
    foreach ($lire('visuels.js') as $cle => $v) {
        $alt = bi_lire($v['alt'] ?? '');
        [$x, $y] = $pourcent($v['position'] ?? null);
        [$mx, $my] = $pourcent($v['positionMobile'] ?? null);
        $type = $v['type'] ?? 'image';
        bd_inserer('visuels', [
            'cle' => $cle, 'type' => $type,
            'media_id' => in_array($type, ['image', 'video'], true) ? $media_du_site((string) ($v['src'] ?? ''), $type, $alt) : null,
            'affiche_id' => $media_du_site((string) ($v['affiche'] ?? ''), 'image'),
            'position_x' => $x, 'position_y' => $y, 'mobile_x' => $mx, 'mobile_y' => $my,
            'lumiere' => (string) ($v['lumiere'] ?? ''), 'alt_fr' => $alt[0], 'alt_en' => $alt[1],
            'opacite' => (int) ($v['opacite'] ?? 100), 'nuee' => (string) ($v['nuee'] ?? ''),
            'son' => empty($v['son']) ? 0 : 1, 'piste' => (string) ($v['piste'] ?? ''),
            'source' => (string) ($v['id'] ?? $v['url'] ?? $v['couleur'] ?? ''), 'ordre' => $ordre++,
        ]);
    }
    $compte['visuels'] = $ordre;
    foreach ($lire('playlist.js') as $i => $t) {
        [$t_fr, $t_en] = bi_lire($t['titre'] ?? '');
        [$m_fr, $m_en] = bi_lire($t['mention'] ?? '');
        bd_inserer('musiques', ['cle' => $t['id'], 'media_id' => $media_du_site((string) ($t['src'] ?? ''), 'son'), 'piste' => (string) ($t['piste'] ?? ''),
            'titre_fr' => $t_fr, 'titre_en' => $t_en, 'mention_fr' => $m_fr, 'mention_en' => $m_en, 'ordre' => $i]);
    }
    $compte['musiques'] = (int) bd_valeur('SELECT COUNT(*) FROM musiques');

    // --- Textes, parcours, contacts, réseaux, téléchargements
    $textes = $lire('textes.js');
    foreach (TEXTES as $cle => $_) {
        if ($cle === 'biographie') {
            $paires = array_map('bi_lire', $textes['biographie'] ?? []);
            $fr = implode("\n\n", array_column($paires, 0));
            $en = array_filter(array_column($paires, 1), 'strlen') ? implode("\n\n", array_map(fn($p) => $p[1] !== '' ? $p[1] : $p[0], $paires)) : '';
        } else {
            [$fr, $en] = bi_lire($textes[$cle] ?? '');
        }
        bd_inserer('textes', ['cle' => $cle, 'fr' => $fr, 'en' => $en]);
    }
    $parcours = $lire('parcours.js');
    foreach (['fonctions', 'opera'] as $liste) {
        foreach ($parcours[$liste] ?? [] as $i => $l) {
            [$fr, $en] = bi_lire($l);
            bd_inserer('parcours_lignes', ['liste' => $liste, 'texte_fr' => $fr, 'texte_en' => $en, 'ordre' => $i]);
        }
    }
    foreach ($lire('contacts.js') as $i => $g) {
        [$m_fr, $m_en] = bi_lire($g['mention'] ?? '');
        $gid = bd_inserer('contacts_groupes', ['structure' => (string) ($g['structure'] ?? ''), 'lien' => (string) ($g['lien'] ?? ''),
            'mention_fr' => $m_fr, 'mention_en' => $m_en, 'langue' => (string) ($g['langue'] ?? ''), 'ordre' => $i]);
        foreach ($g['personnes'] ?? [] as $j => $p) {
            [$r_fr, $r_en] = bi_lire($p['role'] ?? '');
            bd_inserer('contacts_personnes', ['groupe_id' => $gid, 'nom' => (string) ($p['nom'] ?? ''), 'role_fr' => $r_fr, 'role_en' => $r_en,
                'telephone' => (string) ($p['telephone'] ?? ''), 'email' => (string) ($p['email'] ?? ''), 'ordre' => $j]);
        }
    }
    foreach ($lire('reseaux.js') as $i => $r) {
        bd_inserer('reseaux', ['cle' => (string) $r['id'], 'nom' => (string) ($r['nom'] ?? ''), 'url' => (string) ($r['url'] ?? ''), 'barre' => empty($r['barre']) ? 0 : 1, 'ordre' => $i]);
    }
    foreach ($lire('telechargements.js') as $i => $t) {
        [$l_fr, $l_en] = bi_lire($t['libelle'] ?? '');
        bd_inserer('telechargements', ['cle' => (string) $t['id'], 'libelle_fr' => $l_fr, 'libelle_en' => $l_en,
            'media_id' => $media_du_site((string) ($t['fichier'] ?? ''), 'document'), 'ordre' => $i]);
    }
    $compte['fiches d\'annuaire'] = (int) bd_valeur('SELECT COUNT(*) FROM annuaire');
    $compte['médias'] = (int) bd_valeur('SELECT COUNT(*) FROM medias');
    journaliser('creation', 'reprise', null, 'Reprise des données du site', null, $compte);
});
foreach ($compte as $quoi => $n) $dire(sprintf('  %-22s %d', $quoi, $n));
}

// ---------- 4. Le compte pilote ----------
$email = mb_strtolower(env('PILOTE_EMAIL'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $dire('Compte pilote : PILOTE_EMAIL manque dans pupitre/.env. Renseignez-le, puis : php pupitre/sql/depart.php --pilote');
} else {
    $pilote = bd_ligne('SELECT * FROM comptes WHERE email = ?', [$email]);
    if (!$pilote) {
        $id = bd_inserer('comptes', ['email' => $email, 'nom' => env('PILOTE_NOM') ?: ucfirst(strtok($email, '@')), 'role' => 'pilote']);
        $dire('Compte pilote créé : ' . $email);
    } else {
        $id = (int) $pilote['id'];
        $dire('Compte pilote déjà présent : ' . $email);
    }
    if (empty($pilote['mot_de_passe'])) {
        if (adresse_pupitre() === '') {
            $dire('Pour choisir le mot de passe : ouvrez Pupitre, « Recevoir un lien par mail ». Sans Mailjet, le lien est écrit dans pupitre/stockage/journaux/mails.log.');
        } else {
            $dire('Pour choisir le mot de passe (lien valable quinze minutes, à usage unique) :');
            $dire('  ' . lien_creer($id, 'mot_de_passe'));
            $dire('Passé ce délai : écran de connexion, « Recevoir un lien par mail » (sans Mailjet, le lien est écrit dans pupitre/stockage/journaux/mails.log).');
        }
    }
}

$dire(pluriel(alertes_recalculer(), 'alerte ouverte', 'alertes ouvertes') . '.');
if (!$pilote_seul) $dire('Reprise terminée. Contrôle : php pupitre/tests/equivalence.php');

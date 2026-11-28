<?php
/**
 * Aperçus du site avant publication, et leur validation.
 *
 * Un aperçu est une copie figée du site tel qu'il serait publié à cet instant, rangée dans
 * <site>/apercu/<jeton>/ : les pages avec leurs zones à jour, les fichiers de data/ tels que Pupitre
 * les écrirait, css/reglages.css, et une copie des feuilles de style et des scripts du moment.
 * Rien du site publié n'est touché. Les médias déjà en ligne ne sont pas recopiés (les chemins
 * « assets/… » de la copie remontent vers ceux du site) ; ceux qui attendent leur première
 * publication sont rangés dans la copie (nouveautes/).
 *
 * Le lien de l'aperçu se partage tel quel : qui l'a voit le site à venir, jusqu'à son expiration.
 * Pour une validation, Pupitre envoie à un compte enregistré un lien personnel : il mène au même
 * aperçu, avec un bouton « Valider » qui ouvre le procès-verbal (apercu.php, hors connexion). La
 * décision, la déclaration acceptée, la date, l'adresse IP et le navigateur sont gardés ; le pilote
 * est prévenu par mail et par une notification, le journal en garde la trace.
 *
 * L'empreinte d'un aperçu (SHA-256 de tout ce qui serait publié) dit, au moment de publier, si le
 * site à publier est bien celui qui a été validé, à l'octet près.
 *
 * Expiration : un aperçu expiré est retiré du disque au premier passage de Pupitre (cron, écran « Le
 * site », tableau de bord). D'ici là, son bandeau le déclare expiré et masque la page ; sur un serveur
 * Apache, une règle du dossier le refuse d'elle-même.
 */

defined('PUPITRE') or exit;

const APERCU_DOSSIER = 'apercu';
const APERCU_DUREES = [1 => '24 heures', 3 => '3 jours', 7 => '7 jours', 15 => '15 jours', 30 => '30 jours'];
const DECISIONS_APERCU = ['attente' => ['En attente', 'puce--alerte'], 'validee' => ['Validé', 'puce--ok'], 'corrections' => ['Corrections demandées', 'puce--erreur']];
/** Ce dont parle un fichier du site, pour dire en clair ce qu'un aperçu présente. */
const APERCU_RUBRIQUES = [
    'data/agenda.js' => 'Agenda des concerts', 'data/agenda.json' => 'Agenda des concerts', 'data/presse.js' => 'Revue de presse', 'data/disques.js' => 'Discographie',
    'data/textes.js' => 'Textes des pages', 'data/libelles.js' => 'Intitulés de l\'interface', 'data/visuels.js' => 'Visuels', 'data/playlist.js' => 'Playlist',
    'data/parcours.js' => 'Parcours', 'data/orchestres.js' => 'Orchestres et carte du monde', 'data/telechargements.js' => 'Téléchargements', 'data/contacts.js' => 'Contacts',
    'data/reseaux.js' => 'Réseaux sociaux', 'data/site.js' => 'Langues et blocs affichés', 'css/reglages.css' => 'Couleur de fond et blocs masqués',
];

/** Dossier d'un aperçu sur le disque. '' si la racine du site est introuvable ou le jeton mal formé. */
function apercu_chemin(string $jeton): string
{
    $racine = site_racine();
    return ($racine !== '' && preg_match('/^[0-9a-f]{32}$/', $jeton)) ? $racine . '/' . APERCU_DOSSIER . '/' . $jeton : '';
}

/** Adresse publique d'un aperçu : celle qui se partage. */
function apercu_adresse(array $a, string $page = ''): string
{
    return site_adresse() . APERCU_DOSSIER . '/' . $a['jeton'] . '/' . $page;
}

function apercu_expire(array $a): bool
{
    return $a['retire_le'] !== null || $a['expire_le'] <= date('Y-m-d H:i:s');
}

/** Empreinte de ce qui serait publié : chaque fichier tel que Pupitre l'écrirait, et les médias à copier. */
function apercu_empreinte(array $etat): string
{
    $h = hash_init('sha256');
    $fichiers = $etat['fichiers'];
    ksort($fichiers);
    foreach ($fichiers as $chemin => $f) hash_update($h, $chemin . "\0" . hash('sha256', (string) $f['apres']) . "\n");
    $medias = $etat['medias'];
    asort($medias);
    foreach ($medias as $source => $cible) hash_update($h, $cible . "\0" . (is_file($source) ? hash_file('sha256', $source) : '') . "\n");
    return hash_final($h);
}

/** Ce qu'un aperçu présente, en clair : rubriques touchées, médias nouveaux, dates annoncées, contenus modifiés depuis la dernière publication. */
function apercu_resume(array $etat): array
{
    $rubriques = [];
    $pages = [];
    foreach ($etat['fichiers'] as $chemin => $f) {
        if (!in_array($f['etat'], ['modifie', 'nouveau'], true)) continue;
        if (isset(APERCU_RUBRIQUES[$chemin])) $rubriques[APERCU_RUBRIQUES[$chemin]] = true;
        elseif (str_ends_with($chemin, '.html')) $pages[] = $chemin;
        else $rubriques[$chemin] = true;
    }
    $depuis = (string) (bd_valeur("SELECT MAX(cree_le) FROM publications WHERE nature = 'publication' AND annulee = 0") ?: '1970-01-01 00:00:00');
    $contenus = [];
    foreach (bd_lignes("SELECT action, objet_libelle FROM journal WHERE cree_le > ? AND objet_type NOT IN ('site', 'communication', 'import', 'compte', 'sauvegarde', 'base', 'reprise', 'apercu', 'depot', 'veille', 'courrier')
            AND action IN ('creation', 'modification', 'suppression', 'statut') ORDER BY id DESC LIMIT 200", [$depuis]) as $l) {
        $cle = $l['action'] . '|' . $l['objet_libelle'];
        if (!isset($contenus[$cle]) && count($contenus) < 40) $contenus[$cle] = ['action' => $l['action'], 'libelle' => $l['objet_libelle']];
    }
    return [
        'rubriques' => array_keys($rubriques),
        'pages' => $pages,
        'medias' => count($etat['medias']),
        'representations' => array_map(fn($r) => date_fr($r['date']) . ' · ' . $r['titre_en'], publication_representations()['entrent']),
        'contenus' => array_values($contenus),
        'ecarts' => (int) $etat['ecarts'],
    ];
}

/**
 * Dans une copie rangée deux dossiers sous la racine du site, un chemin « assets/… » doit remonter vers le site
 * (« ../../assets/… »), ou viser la copie d'un média pas encore publié (« nouveautes/assets/… »).
 * $genre : html, js, donnees (chemins relatifs à la page), css (chemins relatifs à la feuille de style).
 */
function apercu_chemins(string $texte, string $genre, array $nouveaux): string
{
    if ($genre === 'css') {
        return preg_replace_callback('#url\(\s*([\'"]?)\.\./(assets/[^\'")\s]*)#', fn($m) => 'url(' . $m[1] . (isset($nouveaux[preg_replace('/[?\#].*$/', '', $m[2])]) ? '../nouveautes/' : '../../../') . $m[2], $texte);
    }
    return preg_replace_callback('#(?<=["\'(=\s,`])assets/[^"\'\s)<>`]*#', function ($m) use ($nouveaux) {
        return (isset($nouveaux[preg_replace('/[?\#].*$/', '', $m[0])]) ? 'nouveautes/' : '../../') . $m[0];
    }, $texte);
}

/** Le bandeau ajouté aux pages d'un aperçu : ce que c'est, jusqu'à quand, et « Valider » pour qui arrive par son lien personnel. */
function apercu_bandeau(array $a): string
{
    $reglages = json_encode([
        'expire' => strtotime($a['expire_le']) * 1000,
        'titre' => $a['titre'],
        'valider' => adresse_pupitre() . '/apercu.php?pv=1&j=',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $css = '.pp-apercu{position:fixed;left:16px;bottom:16px;z-index:2147483000;display:flex;align-items:center;gap:12px;max-width:calc(100vw - 32px);padding:8px 8px 8px 16px;'
        . 'background:#12263F;color:#FAF6F0;border:1px solid #2C405A;border-radius:999px;font:500 14px/1.35 Manrope,Inter,system-ui,-apple-system,"Segoe UI",Arial,sans-serif;letter-spacing:0;text-transform:none}'
        . '.pp-apercu[hidden]{display:none}.pp-apercu__point{flex:none;width:10px;height:10px;border-radius:50%;background:#FF8B3D}'
        . '.pp-apercu__texte{min-width:0}.pp-apercu__texte strong{font-weight:700}.pp-apercu__detail{color:#B9C4D4}'
        . '.pp-apercu__bouton{flex:none;display:inline-flex;align-items:center;min-height:40px;padding:0 20px;border-radius:999px;background:#FF8B3D;color:#12263F !important;font-weight:700;text-decoration:none !important}'
        . '.pp-apercu__bouton:hover{background:#F57B2A}.pp-apercu__bouton[hidden]{display:none}'
        . '.pp-apercu__reduire{flex:none;width:40px;height:40px;border:1px solid #2C405A;border-radius:50%;background:none;color:#B9C4D4;font:inherit;cursor:pointer}'
        . '.pp-apercu__reduire:hover{background:#1E3552;color:#FAF6F0}.pp-apercu :focus-visible{outline:2px solid #FF8B3D;outline-offset:2px}'
        . '.pp-apercu--reduit .pp-apercu__texte,.pp-apercu--reduit .pp-apercu__bouton{display:none}.pp-apercu--reduit{padding:8px}'
        . '.pp-apercu-fin{position:fixed;inset:0;z-index:2147483001;display:grid;place-items:center;padding:24px;background:#FAF6F0;color:#12263F;font:500 18px/1.5 Manrope,Inter,system-ui,Arial,sans-serif;text-align:center}'
        . '@media (max-width:640px){.pp-apercu{left:8px;right:8px;bottom:8px;border-radius:24px;flex-wrap:wrap;padding:10px 10px 10px 14px}.pp-apercu__texte{flex:1 1 60%}}'
        . '@media print{.pp-apercu{display:none}}';
    $js = '(function(){var A=' . $reglages . ';var fr=(navigator.language||"fr").toLowerCase().indexOf("fr")===0;'
        . 'function t(f,e){return fr?f:e;}'
        . 'if(Date.now()>A.expire){var f=document.createElement("div");f.className="pp-apercu-fin";f.setAttribute("role","alert");f.textContent=t("Cet aperçu a expiré : il n\'est plus consultable.","This preview has expired and can no longer be viewed.");'
        . 'document.documentElement.style.overflow="hidden";document.body.innerHTML="";document.body.appendChild(f);return;}'
        . 'var v=null;try{var m=location.search.match(/[?&]v=([0-9a-f]{64})/);if(m){v=m[1];sessionStorage.setItem("pp-apercu-v",v);'
        . 'var u=new URL(location.href);u.searchParams.delete("v");history.replaceState(null,"",u.pathname+u.search+u.hash);}else{v=sessionStorage.getItem("pp-apercu-v");}}catch(x){}'
        . 'var b=document.getElementById("pp-apercu");if(!b)return;'
        . 'var d=new Date(A.expire);var quand=d.toLocaleDateString(fr?"fr-FR":"en-GB",{day:"numeric",month:"long",year:"numeric"})+t(" à "," at ")+d.toLocaleTimeString(fr?"fr-FR":"en-GB",{hour:"2-digit",minute:"2-digit"});'
        . 'b.querySelector("[data-pp=titre]").textContent=t("Aperçu avant publication","Preview before publication");'
        . 'b.querySelector("[data-pp=detail]").textContent=" · "+A.titre+" · "+t("valable jusqu\'au ","available until ")+quand;'
        . 'var val=b.querySelector("[data-pp=valider]");if(v&&/^[0-9a-f]{64}$/.test(v)){val.href=A.valider+v;val.textContent=t("Valider","Approve");val.hidden=false;}'
        . 'var r=b.querySelector("[data-pp=reduire]");r.setAttribute("aria-label",t("Réduire ou déplier le bandeau de l\'aperçu","Collapse or expand the preview banner"));'
        . 'function reduit(o){b.classList.toggle("pp-apercu--reduit",o);r.textContent=o?"+":"–";r.setAttribute("aria-expanded",o?"false":"true");try{sessionStorage.setItem("pp-apercu-r",o?"1":"");}catch(x){}}'
        . 'r.addEventListener("click",function(){reduit(!b.classList.contains("pp-apercu--reduit"));});'
        . 'var deja="";try{deja=sessionStorage.getItem("pp-apercu-r")||"";}catch(x){}reduit(deja==="1");b.hidden=false;})();';
    return "\n<!-- Aperçu Pupitre : ce bandeau n'existe que dans cette copie, jamais sur le site publié -->\n"
        . '<style>' . $css . "</style>\n"
        . '<div class="pp-apercu" id="pp-apercu" role="region" aria-label="Aperçu avant publication" hidden><span class="pp-apercu__point" aria-hidden="true"></span>'
        . '<span class="pp-apercu__texte"><strong data-pp="titre"></strong><span class="pp-apercu__detail" data-pp="detail"></span></span>'
        . '<a class="pp-apercu__bouton" data-pp="valider" hidden></a><button type="button" class="pp-apercu__reduire" data-pp="reduire">–</button></div>' . "\n"
        . '<script>' . $js . "</script>\n";
}

/** Copie un dossier (ses fichiers, sans descendre), en passant chaque texte par $transformer(nom, contenu). */
function apercu_copier(string $source, string $cible, ?callable $transformer = null): void
{
    dossier($cible);
    foreach (glob($source . '/*') ?: [] as $f) {
        if (!is_file($f)) continue;
        $nom = basename($f);
        if ($nom[0] === '.') continue;
        $contenu = file_get_contents($f);
        if ($transformer) $contenu = $transformer($nom, $contenu);
        file_put_contents($cible . '/' . $nom, $contenu);
    }
}

/** Supprime un dossier d'aperçu et tout ce qu'il contient. Ne touche à rien hors de <site>/apercu/. */
function apercu_dossier_supprimer(string $jeton): void
{
    $dossier = apercu_chemin($jeton);
    if ($dossier === '' || !is_dir($dossier)) return;
    $parcours = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($parcours as $f) {
        if ($f->isLink() || $f->isFile()) @unlink($f->getPathname()); else @rmdir($f->getPathname());
    }
    @rmdir($dossier);
}

/**
 * Crée un aperçu de l'état présent : écrit sa copie figée, l'enregistre. $jours : sa durée de validité.
 * Rend l'aperçu. Lève ErreurPublication si la copie ne peut pas être écrite.
 */
function apercu_creer(string $titre, string $note, int $jours): array
{
    $etat = publication_etat();
    $racine = $etat['racine'];
    $jours = isset(APERCU_DUREES[$jours]) ? $jours : 7;
    $jeton = bin2hex(random_bytes(16));
    $expire = date('Y-m-d H:i:s', time() + $jours * 86400);
    $c = compte();
    $a = ['jeton' => $jeton, 'titre' => mb_substr(trim($titre), 0, 190) ?: 'Évolutions du ' . date_fr(aujourdhui()), 'note' => trim($note), 'expire_le' => $expire, 'retire_le' => null,
        'empreinte' => apercu_empreinte($etat), 'resume' => json_encode(apercu_resume($etat), JSON_UNESCAPED_UNICODE), 'cree_par' => $c['id'] ?? null, 'cree_par_nom' => $c['nom'] ?? 'ligne de commande'];

    $base = dossier($racine . '/' . APERCU_DOSSIER);
    if (!is_writable($base)) throw new ErreurPublication('Le dossier des aperçus ne peut pas être écrit (' . APERCU_DOSSIER . '/ à la racine du site : droits du dossier ?).');
    // le dossier des aperçus ne se liste pas : un aperçu ne se trouve que par son adresse
    if (!is_file($base . '/index.html')) file_put_contents($base . '/index.html', "<!doctype html><meta charset=\"utf-8\"><meta name=\"robots\" content=\"noindex, nofollow\"><title></title>\n");
    if (!is_file($base . '/.htaccess')) {
        file_put_contents($base . '/.htaccess', "# Aperçus du site avant publication (Pupitre) : jamais listés, jamais indexés, jamais gardés en cache.\nOptions -Indexes\n<IfModule mod_headers.c>\n  Header set X-Robots-Tag \"noindex, nofollow, noarchive\"\n  Header set Cache-Control \"no-store\"\n</IfModule>\n");
    }
    $dossier = $base . '/' . $jeton;
    try {
        dossier($dossier);
        // les médias qui attendent leur première publication : rangés dans la copie
        $nouveaux = [];
        foreach ($etat['medias'] as $source => $cible) {
            if (!is_file($source)) continue;
            $ou = str_starts_with($cible, 'assets/') ? $dossier . '/nouveautes/' . $cible : $dossier . '/' . $cible;
            dossier(dirname($ou));
            if (!@link($source, $ou) && !@copy($source, $ou)) throw new ErreurPublication('Copie impossible : ' . $cible . '.');
            if (str_starts_with($cible, 'assets/')) $nouveaux[$cible] = true;
        }
        // les feuilles de style et les scripts du moment, puis les données du site, puis ce que Pupitre écrirait
        apercu_copier($racine . '/css', $dossier . '/css', fn($nom, $t) => str_ends_with($nom, '.css') ? apercu_chemins($t, 'css', $nouveaux) : $t);
        apercu_copier($racine . '/js', $dossier . '/js', fn($nom, $t) => str_ends_with($nom, '.js') ? apercu_chemins($t, 'js', $nouveaux) : $t);
        apercu_copier($racine . '/data', $dossier . '/data', fn($nom, $t) => preg_match('/\.(js|json)$/', $nom) ? apercu_chemins($t, 'donnees', $nouveaux) : $t);
        $bandeau = apercu_bandeau($a);
        foreach ($etat['fichiers'] as $chemin => $f) {
            $contenu = (string) $f['apres'];
            if (str_ends_with($chemin, '.html')) {
                $contenu = apercu_chemins($contenu, 'html', $nouveaux);
                // jamais indexée, et son bandeau avant la fin du corps
                if (!preg_match('/<meta name="robots"/i', $contenu)) $contenu = preg_replace('/<head([^>]*)>/i', '<head$1>' . "\n  " . '<meta name="robots" content="noindex, nofollow">', $contenu, 1);
                $i = strripos($contenu, '</body>');
                $contenu = $i === false ? $contenu . $bandeau : substr($contenu, 0, $i) . $bandeau . substr($contenu, $i);
            } elseif (str_starts_with($chemin, 'css/')) {
                $contenu = apercu_chemins($contenu, 'css', $nouveaux);
            } else {
                $contenu = apercu_chemins($contenu, 'donnees', $nouveaux);
            }
            dossier(dirname($dossier . '/' . $chemin));
            if (file_put_contents($dossier . '/' . $chemin, $contenu) === false) throw new ErreurPublication('Écriture impossible : ' . $chemin . '.');
        }
        // sur un serveur Apache, le dossier se refuse de lui-même un jour après son expiration (l'heure du serveur peut différer)
        file_put_contents($dossier . '/.htaccess', "# Aperçu Pupitre, valable jusqu'au " . $expire . ".\n<IfModule mod_rewrite.c>\n  RewriteEngine On\n  RewriteCond %{TIME} >" . date('YmdHis', strtotime($expire) + 86400) . "\n  RewriteRule ^ - [G]\n</IfModule>\n");
    } catch (Throwable $e) {
        apercu_dossier_supprimer($jeton);
        throw $e instanceof ErreurPublication ? $e : new ErreurPublication('L\'aperçu n\'a pas pu être écrit : ' . $e->getMessage());
    }
    $a['id'] = bd_inserer('apercus', $a);
    journaliser('creation', 'apercu', $a['id'], 'Aperçu « ' . $a['titre'] . ' »', null, ['valable_jusqu_au' => $expire, 'ecarts' => (int) $etat['ecarts']]);
    return apercu_lire((int) $a['id']);
}

/** Un aperçu, avec ses demandes de validation. */
function apercu_lire(int $id): ?array
{
    $a = bd_ligne('SELECT * FROM apercus WHERE id = ?', [$id]);
    if (!$a) return null;
    $a['resume'] = json_decode((string) $a['resume'], true) ?: [];
    $a['validations'] = bd_lignes('SELECT * FROM apercus_validations WHERE apercu_id = ? ORDER BY id', [$id]);
    return $a;
}

/** Les aperçus, du plus récent au plus ancien. $actifs : seulement ceux qui se consultent encore. */
function apercus_lister(bool $actifs = false, int $limite = 30): array
{
    $l = [];
    foreach (bd_colonne('SELECT id FROM apercus' . ($actifs ? ' WHERE retire_le IS NULL AND expire_le > NOW()' : '') . ' ORDER BY id DESC LIMIT ' . (int) $limite) as $id) $l[] = apercu_lire((int) $id);
    return $l;
}

/** Retire un aperçu : son dossier disparaît, sa trace (et ses validations) reste. */
function apercu_retirer(int $id, bool $expiration = false): void
{
    $a = bd_ligne('SELECT * FROM apercus WHERE id = ?', [$id]);
    if (!$a || $a['retire_le'] !== null) return;
    apercu_dossier_supprimer($a['jeton']);
    bd_modifier('apercus', ['retire_le' => date('Y-m-d H:i:s')], ['id' => $id]);
    journaliser('suppression', 'apercu', $id, 'Aperçu « ' . $a['titre'] . ' »' . ($expiration ? ' (expiré)' : ''), ['etat' => 'en ligne'], ['etat' => $expiration ? 'expiré, retiré' : 'retiré']);
}

/** Retire les aperçus expirés. Rend leur nombre. Sans effet (et sans erreur) sur une base d'avant la migration 012. */
function apercus_purger(): int
{
    try {
        $expires = bd_colonne('SELECT id FROM apercus WHERE retire_le IS NULL AND expire_le <= NOW()');
    } catch (Throwable $e) {
        return 0;
    }
    foreach ($expires as $id) apercu_retirer((int) $id, true);
    return count($expires);
}

/**
 * Demande à un compte de valider un aperçu : crée son lien personnel et le lui envoie.
 * Rend [erreur, lien] : l'erreur est '' si la demande est faite ; le lien est rendu pour être montré une fois
 * au pilote (il ne se relit pas ensuite : seule son empreinte est gardée).
 */
function apercu_inviter(array $a, int $compte_id): array
{
    if (apercu_expire($a)) return ['Cet aperçu a expiré : créez-en un nouveau.', ''];
    $c = bd_ligne('SELECT id, nom, email FROM comptes WHERE id = ? AND actif = 1', [$compte_id]);
    if (!$c) return ['Compte introuvable.', ''];
    $jeton = bin2hex(random_bytes(32));
    $lien = adresse_pupitre() . '/apercu.php?j=' . $jeton;
    // une demande par personne et par aperçu : une nouvelle demande remplace le lien précédent, tant que rien n'est décidé
    $v = bd_ligne("SELECT * FROM apercus_validations WHERE apercu_id = ? AND compte_id = ? AND decision = 'attente'", [$a['id'], $c['id']]);
    $d = ['jeton' => hash('sha256', $jeton), 'envoye_le' => date('Y-m-d H:i:s'), 'nom' => $c['nom'], 'email' => $c['email']];
    if ($v) bd_modifier('apercus_validations', $d, ['id' => $v['id']]);
    else $v = ['id' => bd_inserer('apercus_validations', $d + ['apercu_id' => $a['id'], 'compte_id' => $c['id']])];
    $moi = compte();
    $texte = "Bonjour " . $c['nom'] . ",\n\n"
        . ($moi ? $moi['nom'] : 'L\'équipe') . " vous propose de découvrir les évolutions du site" . (reglage('artiste_nom') !== '' ? ' de ' . reglage('artiste_nom') : '') . " avant leur publication : « " . $a['titre'] . " ».\n\n"
        . (trim((string) $a['note']) !== '' ? trim((string) $a['note']) . "\n\n" : '')
        . "Le lien ci-dessous ouvre l'aperçu du site. Une fois que vous l'aurez parcouru, le bouton « Valider », en bas de l'écran, vous permettra de donner votre accord ou de demander des corrections.\n\n"
        . $lien . "\n\n"
        . "Ce lien vous est personnel : ne le transférez pas. L'aperçu est consultable jusqu'au " . horodatage_fr($a['expire_le']) . ".\n";
    envoyer_mail($c['email'], $c['nom'], 'Aperçu à valider : ' . $a['titre'], $texte, [
        'titre' => 'Un aperçu attend votre validation', 'bouton' => ['Ouvrir l\'aperçu', $lien], 'nature' => 'apercu', 'objet' => ['apercus', (int) $a['id']], 'secret' => true,
        'repondre_a' => $moi['email'] ?? '',
    ]);
    journaliser('envoi', 'apercu', (int) $a['id'], 'Aperçu « ' . $a['titre'] . ' » : demande de validation à ' . $c['nom'], null, ['destinataire' => $c['nom'] . ' (' . adresse_masquee($c['email']) . ')']);
    return ['', $lien];
}

/** La demande de validation que désigne un lien personnel, avec son aperçu. null si le lien n'existe pas. */
function validation_par_jeton(string $jeton): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $jeton)) return null;
    $v = bd_ligne('SELECT * FROM apercus_validations WHERE jeton = ?', [hash('sha256', $jeton)]);
    if (!$v) return null;
    $v['apercu'] = apercu_lire((int) $v['apercu_id']);
    return $v['apercu'] ? $v : null;
}

/** Le texte que la personne accepte en validant : il est gardé tel quel au procès-verbal. */
function validation_declaration(array $v): string
{
    $a = $v['apercu'];
    return 'Je soussigné(e) ' . $v['nom'] . ' déclare avoir pris connaissance de l\'aperçu du site ' . rtrim(site_adresse(), '/') . ' intitulé « ' . $a['titre'] . ' », établi le '
        . horodatage_fr($a['cree_le']) . ' (empreinte ' . substr($a['empreinte'], 0, 16) . '), et valide les évolutions qu\'il présente en vue de leur publication.';
}

/**
 * Enregistre la décision : « validee », ou « corrections » (avec des remarques). Prévient le pilote
 * (mail, alerte) et journalise. Une décision prise ne se reprend pas par le même lien.
 */
function validation_decider(array $v, string $decision, string $remarques): void
{
    $a = $v['apercu'];
    $d = [
        'decision' => $decision === 'validee' ? 'validee' : 'corrections',
        'decide_le' => date('Y-m-d H:i:s'),
        'remarques' => mb_substr(trim($remarques), 0, 4000),
        'declaration' => $decision === 'validee' ? validation_declaration($v) : '',
        'ip' => adresse_ip(),
        'agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ];
    bd_modifier('apercus_validations', $d, ['id' => $v['id']]);
    $valide = $d['decision'] === 'validee';
    journaliser('validation', 'apercu', (int) $a['id'], 'Aperçu « ' . $a['titre'] . ' » : ' . ($valide ? 'validé' : 'corrections demandées') . ' par ' . $v['nom'], ['decision' => 'attente'],
        ['decision' => $d['decision'], 'par' => $v['nom'] . ' (' . adresse_masquee($v['email']) . ')', 'empreinte' => substr($a['empreinte'], 0, 16), 'remarques' => $d['remarques']]);
    // le pilote qui a créé l'aperçu ; à défaut, tous les pilotes
    $pilotes = $a['cree_par'] ? bd_lignes('SELECT nom, email FROM comptes WHERE id = ? AND actif = 1', [$a['cree_par']]) : [];
    if (!$pilotes) $pilotes = bd_lignes("SELECT nom, email FROM comptes WHERE role = 'pilote' AND actif = 1");
    $lien = adresse_pupitre() . '/index.php?page=apercu&id=' . (int) $a['id'];
    foreach ($pilotes as $p) {
        envoyer_mail($p['email'], $p['nom'], ($valide ? 'Aperçu validé : ' : 'Corrections demandées : ') . $a['titre'],
            "Bonjour " . $p['nom'] . ",\n\n" . $v['nom'] . ($valide ? " a validé l'aperçu « " : " demande des corrections sur l'aperçu « ") . $a['titre'] . " », le " . horodatage_fr($d['decide_le']) . ".\n\n"
            . ($d['remarques'] !== '' ? "Ses remarques :\n" . $d['remarques'] . "\n\n" : '')
            . ($valide ? "Le procès-verbal de validation est dans Pupitre. Vous pouvez publier." : "Rien n'est publié : l'aperçu reste consultable, et un nouvel aperçu pourra lui être envoyé après correction.") . "\n\n" . $lien . "\n",
            ['titre' => $valide ? 'Aperçu validé' : 'Corrections demandées', 'bouton' => [$valide ? 'Voir le procès-verbal' : 'Voir les remarques', $lien], 'nature' => 'apercu', 'objet' => ['apercus', (int) $a['id']]]);
    }
    $GLOBALS['donnees_modifiees'] = true;
}

/**
 * Où en est la validation de ce qui serait publié maintenant.
 * Rend ['identique' => la validation d'un aperçu dont l'empreinte est celle de l'état présent, ou null ;
 *       'autre' => la dernière validation d'un état différent (faite depuis la dernière publication), ou null].
 */
function apercu_validation_courante(array $etat): array
{
    try {
        $empreinte = apercu_empreinte($etat);
        $depuis = (string) (bd_valeur("SELECT MAX(cree_le) FROM publications WHERE nature = 'publication' AND annulee = 0") ?: '1970-01-01 00:00:00');
        $v = bd_lignes("SELECT v.*, a.titre, a.empreinte, a.id AS apercu FROM apercus_validations v JOIN apercus a ON a.id = v.apercu_id
            WHERE v.decision = 'validee' AND v.decide_le > ? ORDER BY v.decide_le DESC", [$depuis]);
    } catch (Throwable $e) {
        return ['identique' => null, 'autre' => null];
    }
    $identique = $autre = null;
    foreach ($v as $x) {
        if ($x['empreinte'] === $empreinte) $identique ??= $x; else $autre ??= $x;
    }
    return ['identique' => $identique, 'autre' => $autre];
}

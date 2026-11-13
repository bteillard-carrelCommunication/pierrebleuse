<?php
/**
 * Adaptateur du site, seconde moitié : les zones de repli du HTML.
 *
 * Le HTML du site garde le français en dur, pour se lire sans JavaScript. Chaque zone qui dépend
 * des données est encadrée par deux repères (NOM-STATIQUE:DEBUT, NOM-STATIQUE:FIN). À la
 * publication, Pupitre réécrit le contenu entre les repères, et rien d'autre.
 *
 * repli_zones() rend, pour chaque repère, les lignes de HTML à y placer. Ce HTML est celui que
 * produit le site lui-même (js/contenu.js, js/agenda.js, js/sections.js) : mêmes balises, mêmes
 * classes, même typographie. Les écrans de Pupitre s'en servent aussi pour leur aperçu, dans la
 * langue demandée.
 */

defined('PUPITRE') or exit;

const INSECABLE = "\u{00A0}";

/** Libellés d'interface du site repris dans les zones de repli (js/contenu.js). */
const LIBELLES_SITE = [
    'disque' => ['fr' => 'Disque', 'en' => 'Recording'],
    'lire' => ['fr' => 'Lire l\'article', 'en' => 'Read the article'],
    'a_venir' => ['fr' => 'à venir', 'en' => 'coming soon'],
    'vide' => ['fr' => 'Les prochaines dates seront annoncées ici.', 'en' => 'Upcoming dates will be announced here.'],
    'ameriques' => ['fr' => 'Amériques', 'en' => 'Americas'],
    'europe-afrique' => ['fr' => 'Europe et Afrique', 'en' => 'Europe and Africa'],
    'asie-oceanie' => ['fr' => 'Asie, Russie et Océanie', 'en' => 'Asia, Russia and Oceania'],
];

const MOIS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/** Pictogrammes des réseaux : l'intérieur d'un <svg viewBox="0 0 24 24">. « lien » sert aux réseaux sans pictogramme. */
const PICTOGRAMMES = [
    'instagram' => '<path fill-rule="evenodd" d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3H7zm5 3.5a4.5 4.5 0 1 1 0 9 4.5 4.5 0 0 1 0-9zm0 2a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zm4.75-3.3a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2z"/>',
    'facebook' => '<path d="M13.5 22v-8.5h2.9l.45-3.4H13.5V7.95c0-.98.28-1.65 1.7-1.65h1.8V3.25a24 24 0 0 0-2.65-.15c-2.6 0-4.4 1.6-4.4 4.5v2.5H7v3.4h2.95V22h3.55z"/>',
    'youtube' => '<path fill-rule="evenodd" d="M21.6 7.2a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.84.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.84-.43a2.5 2.5 0 0 0 1.76-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8zM10 15V9l5.2 3-5.2 3z"/>',
    'linkedin' => '<path d="M6.94 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0zM3.2 8.6h3.5V21H3.2V8.6zm5.7 0h3.35v1.7h.05c.47-.88 1.6-1.8 3.3-1.8 3.53 0 4.18 2.32 4.18 5.34V21h-3.5v-6.3c0-1.5-.03-3.43-2.1-3.43-2.1 0-2.42 1.63-2.42 3.32V21H8.9V8.6z"/>',
    'lien' => '<path fill-rule="evenodd" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.9 9h-3.02a15.6 15.6 0 0 0-1.2-5.56A8.02 8.02 0 0 1 18.9 11zM12 4.1c.95 1.18 1.75 3.6 1.88 6.9h-3.76c.13-3.3.93-5.72 1.88-6.9zM9.32 5.44A15.6 15.6 0 0 0 8.12 11H5.1a8.02 8.02 0 0 1 4.22-5.56zM5.1 13h3.02c.1 2.1.53 4.04 1.2 5.56A8.02 8.02 0 0 1 5.1 13zm6.9 6.9c-.95-1.18-1.75-3.6-1.88-6.9h3.76c-.13 3.3-.93 5.72-1.88 6.9zm2.68-1.34c.67-1.52 1.1-3.46 1.2-5.56h3.02a8.02 8.02 0 0 1-4.22 5.56z"/>',
];

// ---------- Textes : les règles de js/contenu.js ----------

/** Champ écrit en chaîne ou en { fr, en } : la version demandée, sinon le français. */
function site_choisir($champ, string $langue = 'fr'): string
{
    if ($champ === null) return '';
    if (is_array($champ)) return (string) (($champ[$langue] ?? '') !== '' ? $champ[$langue] : ($champ['fr'] ?? ''));
    return (string) $champ;
}

/** Espaces insécables : avant : ; ? ! », après « et après n° ou No. */
function site_typo(string $s): string
{
    $s = preg_replace('/ ([:;?!»])/u', INSECABLE . '$1', $s);
    $s = str_replace('« ', '«' . INSECABLE, $s);
    return preg_replace('/(n°|No\.) (?=\d)/u', '$1' . INSECABLE, $s);
}

/** Texte prêt pour le HTML : échappé, typographié, *italique* en <i>, insécables en &nbsp;. */
function site_html(string $s): string
{
    $s = site_typo(str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s));
    $s = preg_replace('/\*([^*]+)\*/u', '<i>$1</i>', $s);
    return str_replace(INSECABLE, '&nbsp;', $s);
}

/** Valeur d'attribut : sans les astérisques, échappée. */
function site_attribut(string $s): string
{
    $s = preg_replace('/\*([^*]+)\*/u', '$1', $s);
    return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], $s);
}

function site_libelle(string $cle, string $langue): string
{
    return LIBELLES_SITE[$cle][$langue] ?? LIBELLES_SITE[$cle]['fr'];
}

/** Lien sortant, comme SiteContenu.lienSortant. */
function site_lien(string $url, string $contenu, string $classe = ''): string
{
    return '<a' . ($classe ? ' class="' . $classe . '"' : '') . ' href="' . site_attribut($url) . '" target="_blank" rel="noopener">' . $contenu . '</a>';
}

function site_jour(int $j, string $langue): string
{
    return ($langue === 'fr' && $j === 1) ? '1er' : (string) $j;
}

function site_mois(int $m, string $langue): string
{
    return $langue === 'en' ? MOIS_EN[$m - 1] : MOIS_FR[$m - 1];
}

/** « 14 novembre 2026 », « 27–29 novembre 2026 », « 30 avril – 2 mai 2027 ». */
function site_plage(string $a, string $b, string $langue = 'fr'): string
{
    [$ya, $ma, $da] = array_map('intval', explode('-', $a));
    [$yb, $mb, $db] = array_map('intval', explode('-', $b));
    $fin = site_jour($db, $langue) . ' ' . site_mois($mb, $langue) . ' ' . $yb;
    if ($a === $b) return $fin;
    if ($ya === $yb && $ma === $mb) return site_jour($da, $langue) . '–' . $fin;
    if ($ya === $yb) return site_jour($da, $langue) . ' ' . site_mois($ma, $langue) . ' – ' . $fin;
    return site_jour($da, $langue) . ' ' . site_mois($ma, $langue) . ' ' . $ya . ' – ' . $fin;
}

/** Date d'un article : AAAA-MM-JJ ou AAAA-MM. */
function site_date_article(string $iso, string $langue = 'fr'): string
{
    if (!preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/', $iso, $m)) return '';
    $mois = site_mois((int) $m[2], $langue) . ' ' . $m[1];
    return isset($m[3]) ? site_jour((int) $m[3], $langue) . ' ' . $mois : $mois;
}

// ---------- Une zone, une fonction. Chacune rend des lignes, sans le retrait de la page. ----------

function repli_texte(string $balise, string $classe, string $cle, $champ, string $langue): array
{
    $t = site_choisir($champ, $langue);
    if ($t === '') return [];
    return ['<' . $balise . ($classe ? ' class="' . $classe . '"' : '') . ' data-t="' . $cle . '">' . site_html($t) . '</' . $balise . '>'];
}

function repli_biographie(array $paragraphes, string $langue): array
{
    $l = [];
    foreach ($paragraphes as $i => $p) {
        $l = array_merge($l, repli_texte('p', 'texte', 'bio.p' . ($i + 1), $p, $langue));
    }
    return $l;
}

function repli_visuel(?array $v, string $langue): array
{
    if (!$v) return [];
    $alt = site_attribut(site_choisir($v['alt'] ?? '', $langue));
    $position = !empty($v['position']) ? ' style="object-position: ' . site_attribut($v['position']) . '"' : '';
    if (($v['type'] ?? '') === 'image' && !empty($v['src'])) {
        return ['<img class="visuel__media" src="' . site_attribut($v['src']) . '" alt="' . $alt . '"' . $position . ' decoding="async">'];
    }
    if (($v['type'] ?? '') === 'video' && !empty($v['src'])) {
        return ['<video class="visuel__media" src="' . site_attribut($v['src']) . '"'
            . (!empty($v['affiche']) ? ' poster="' . site_attribut($v['affiche']) . '"' : '') . $position
            . ' muted loop playsinline preload="none" disablepictureinpicture'
            . ($alt !== '' ? ' aria-label="' . $alt . '"' : ' aria-hidden="true"') . '></video>'];
    }
    if (($v['type'] ?? '') === 'couleur') {
        return ['<div class="visuel__media" style="background-color: ' . site_attribut($v['couleur'] ?? '#000') . '"></div>'];
    }
    return ['<div class="visuel__media"></div>'];           // YouTube : rien n'est chargé sans l'accord du visiteur
}

/** Liste simple de tous les événements : date ou plage, ville(s), titre. */
function repli_agenda(array $agenda, string $langue): array
{
    $evts = array_values(array_filter($agenda['evenements'] ?? [], fn($e) => !empty($e['representations'])));
    if (!$evts) return ['<p class="agenda__vide bloc-plein" data-revele>' . site_html(site_libelle('vide', $langue)) . '</p>'];
    $premiere = fn($e) => min(array_column($e['representations'], 'date'));
    $rangs = array_keys($evts);
    usort($rangs, fn($i, $j) => [$premiere($evts[$i]), $i] <=> [$premiere($evts[$j]), $j]);
    $l = ['<ol class="agenda__simple bloc-plein" data-revele>'];
    foreach ($rangs as $i) {
        $e = $evts[$i];
        $dates = array_column($e['representations'], 'date');
        sort($dates);
        if (($e['type'] ?? '') === 'disque') {
            $ou = site_libelle('disque', $langue);
        } else {
            $villes = [];
            foreach ($e['representations'] as $r) {
                $v = site_choisir($r['ville'] ?? ($e['ville'] ?? ''), $langue);
                if ($v !== '' && !in_array($v, $villes, true)) $villes[] = $v;
            }
            $ou = implode(', ', $villes);
        }
        $l[] = '  <li class="ligne"><span class="ligne__quand">' . site_html(site_plage($dates[0], end($dates), $langue)) . '</span> '
            . '<span class="ligne__ville">' . site_html($ou) . '</span> '
            . '<span class="ligne__titre">' . site_html(site_choisir($e['titre'] ?? '', $langue)) . '</span></li>';
    }
    $l[] = '</ol>';
    return $l;
}

/** $complet (aperçu de Pupitre) : avec le mot de l'artiste et les liens, comme le rend le site. */
function repli_disques(array $disques, string $langue, bool $complet = false): array
{
    $l = [];
    foreach ($disques as $d) {
        $s = '<article class="fiche bloc-quart">';
        $tete = array_filter([(string) ($d['annee'] ?? ''), site_choisir($d['maison'] ?? '', $langue)], 'strlen');
        if ($tete) $s .= '<p class="fiche__dates">' . implode(' · ', array_map('site_html', $tete)) . '</p>';
        $s .= '<h3 class="fiche__titre">' . site_html(site_choisir($d['titre'] ?? '', $langue)) . '</h3>';
        $descriptif = $d['descriptif'] ?? '';
        $lignes = (is_array($descriptif) && array_is_list($descriptif)) ? $descriptif : [$descriptif];
        foreach ($lignes as $ligne) {
            $t = site_choisir($ligne, $langue);
            if ($t !== '') $s .= '<p class="fiche__texte">' . site_html($t) . '</p>';
        }
        if ($complet) {
            $mot = site_choisir($d['mot'] ?? '', $langue);
            if ($mot !== '') $s .= '<p class="fiche__mot">' . site_html($mot) . '</p>';
            foreach (['commander' => ['fr' => 'Commander', 'en' => 'Order'], 'streaming' => ['fr' => 'Écouter', 'en' => 'Listen']] as $k => $nom) {
                $liens = array_filter($d[$k] ?? [], fn($x) => !empty($x['url']));
                if (!$liens) continue;
                $s .= '<p class="fiche__label">' . $nom[$langue] . '</p><ul class="fiche__liens">';
                foreach ($liens as $x) $s .= '<li>' . site_lien($x['url'], site_html($x['nom'] ?: $x['url']), 'lien') . '</li>';
                $s .= '</ul>';
            }
            if (!empty($d['lien'])) $s .= site_lien($d['lien'], $langue === 'en' ? 'Find out more' : 'En savoir plus', 'lien');
        }
        $l[] = $s . '</article>';
    }
    return $l;
}

/** Les quatre premiers articles sont sur l'accueil ; $complet (aperçu de Pupitre) : tous. */
function repli_presse(array $articles, string $langue, bool $complet = false): array
{
    $l = [];
    foreach ($complet ? $articles : array_slice($articles, 0, 4) as $a) {
        $s = '<article class="fiche bloc-quart"><p class="article__media">'
            . (!empty($a['logo']) ? '<img class="article__logo" src="' . site_attribut($a['logo']) . '" alt="" decoding="async">' : '')
            . '<span class="article__nom">' . site_html((string) ($a['media'] ?? '')) . '</span></p>';
        $titre = site_choisir($a['titre'] ?? '', $langue);
        if ($titre !== '') $s .= '<h3 class="article__titre">' . site_html($titre) . '</h3>';
        if (!empty($a['verbatim'])) {
            $s .= '<blockquote class="article__verbatim" lang="' . site_attribut(($a['langue'] ?? '') ?: $langue) . '">' . site_html($a['verbatim']) . '</blockquote>';
        }
        $resume = site_choisir($a['resume'] ?? '', $langue);
        if ($resume !== '') $s .= '<p class="article__resume">' . site_html($resume) . '</p>';
        $date = site_date_article((string) ($a['date'] ?? ''), $langue);
        if ($date !== '' || !empty($a['auteur'])) {
            $s .= '<p class="article__date">' . ($date !== '' ? '<span>' . site_html($date) . '</span>' : '')
                . (!empty($a['auteur']) ? '<span class="article__auteur">' . site_html($a['auteur']) . '</span>' : '') . '</p>';
        }
        if (!empty($a['lien'])) $s .= site_lien($a['lien'], site_html(site_libelle('lire', $langue)), 'lien');
        $l[] = $s . '</article>';
    }
    return $l;
}

function repli_liste(array $lignes, string $langue): array
{
    $l = [];
    foreach ($lignes as $ligne) {
        $t = site_choisir($ligne, $langue);
        if ($t !== '') $l[] = '<li>' . site_html($t) . '</li>';
    }
    return $l;
}

/** Zone d'un orchestre : son champ zone, sinon déduite de la longitude (comme js/sections.js). */
function zone_orchestre(array $o): string
{
    $z = $o['zone'] ?? '';
    if (in_array($z, ['ameriques', 'europe-afrique', 'asie-oceanie'], true)) return $z;
    $lon = (float) ($o['lon'] ?? 0);
    return $lon < -25 ? 'ameriques' : ($lon > 60 ? 'asie-oceanie' : 'europe-afrique');
}

function repli_orchestres(array $orchestres, string $langue): array
{
    $l = [];
    foreach (['ameriques', 'europe-afrique', 'asie-oceanie'] as $z) {
        $l[] = '<div class="orchestres-zone">';
        $l[] = '  <h4 class="orchestres-zone__titre" data-t="parcours.zone.' . $z . '">' . site_html(site_libelle($z, $langue)) . '</h4>';
        $l[] = '  <ul class="orchestres">';
        foreach ($orchestres as $o) {
            if (zone_orchestre($o) !== $z) continue;
            $nom = ($o['nom'] ?? '') !== '' ? $o['nom'] : site_choisir($o['ville'] ?? '', $langue);
            $l[] = '    <li>' . site_html($nom) . '</li>';
        }
        $l[] = '  </ul>';
        $l[] = '</div>';
    }
    return $l;
}

function repli_telechargements(array $liste, string $langue): array
{
    $l = [];
    foreach ($liste as $t) {
        $libelle = site_html(site_choisir($t['libelle'] ?? '', $langue));
        $l[] = !empty($t['fichier'])
            ? '<li><a href="' . site_attribut($t['fichier']) . '" download>' . $libelle . '</a></li>'
            : '<li><span>' . $libelle . '</span> <span class="avenir">' . site_html(site_libelle('a_venir', $langue)) . '</span></li>';
    }
    return $l;
}

/** Réseaux : $barre vrai pour la barre du haut (les seuls réseaux marqués « barre »). */
function repli_reseaux(array $reseaux, bool $barre): array
{
    $l = [];
    foreach ($reseaux as $r) {
        if ($barre && empty($r['barre'])) continue;
        $svg = '<svg viewBox="0 0 24 24" aria-hidden="true">' . (PICTOGRAMMES[$r['id'] ?? ''] ?? PICTOGRAMMES['lien']) . '</svg>';
        $nom = site_attribut(($r['nom'] ?? '') ?: ($r['id'] ?? ''));
        $a = !empty($r['url'])
            ? '<a href="' . site_attribut($r['url']) . '" target="_blank" rel="noopener" aria-label="' . $nom . '">'
            : '<a class="inerte" role="link" aria-disabled="true" aria-label="' . $nom . '">';
        $l[] = '<li data-reseau="' . site_attribut((string) ($r['id'] ?? '')) . '">' . $a . $svg . '</a></li>';
    }
    return $l;
}

function repli_contacts(array $groupes, string $langue): array
{
    $l = [];
    foreach ($groupes as $g) {
        $lang = !empty($g['langue']) ? ' lang="' . site_attribut($g['langue']) . '"' : '';
        $l[] = '<div class="contacts__groupe" data-revele>';
        if (!empty($g['structure'])) {
            $nom = site_html($g['structure']);
            $l[] = '  <p class="contacts__agence">' . (!empty($g['lien']) ? site_lien($g['lien'], $nom) : $nom) . '</p>';
        }
        $mention = site_choisir($g['mention'] ?? '', $langue);
        if ($mention !== '') $l[] = '  <p class="mention"' . $lang . '>' . site_html($mention) . '</p>';
        foreach ($g['personnes'] ?? [] as $p) {
            $l[] = '  <div class="contact">';
            if (!empty($p['nom'])) $l[] = '    <p class="contact__nom">' . site_html($p['nom']) . '</p>';
            $role = site_choisir($p['role'] ?? '', $langue);
            if ($role !== '') $l[] = '    <p class="contact__role"' . $lang . '>' . site_html($role) . '</p>';
            if (!empty($p['telephone'])) {
                $l[] = '    <p><a href="tel:' . site_attribut(preg_replace('/[^\d+]/', '', $p['telephone'])) . '">' . site_html($p['telephone']) . '</a></p>';
            }
            if (!empty($p['email'])) {
                $i = strpos($p['email'], '@');
                $affiche = $i === false ? site_html($p['email']) : site_html(substr($p['email'], 0, $i + 1)) . '<wbr>' . site_html(substr($p['email'], $i + 1));
                $l[] = '    <p><a href="mailto:' . site_attribut($p['email']) . '">' . $affiche . '</a></p>';
            }
            $l[] = '  </div>';
        }
        $l[] = '</div>';
    }
    return $l;
}

/**
 * Toutes les zones de repli : nom du repère => lignes de HTML.
 * $exports : les données du site par variable (site_exports(), ou les fichiers relus).
 */
function repli_zones(array $exports, string $langue = 'fr'): array
{
    $d = json_decode(json_encode($exports), true);          // objets et tableaux : tout en tableaux
    $t = $d['TEXTES'] ?? [];
    $v = $d['VISUELS'] ?? [];
    $description = site_choisir($t['description'] ?? '', $langue);
    $z = [
        'DESCRIPTION' => $description !== '' ? ['<meta name="description" content="' . site_attribut($description) . '">'] : [],
        'CITATION' => repli_texte('blockquote', 'ouverture__citation', 'ouverture.citation', $t['citation'] ?? '', $langue),
        'BIOGRAPHIE' => repli_biographie($t['biographie'] ?? [], $langue),
        'SAISON' => repli_texte('p', 'mention', 'agenda.mention', $t['saison'] ?? '', $langue),
        'LETTRE' => repli_texte('p', 'texte', 'lettre.texte', $t['lettre'] ?? '', $langue),
        'CREDIT' => repli_texte('p', '', 'pied.credit', $t['credit'] ?? '', $langue),
        'AGENDA' => repli_agenda($d['AGENDA_DONNEES'] ?? [], $langue),
        'DISQUES' => repli_disques($d['DISQUES'] ?? [], $langue),
        'PRESSE' => repli_presse($d['PRESSE'] ?? [], $langue),
        'PARCOURS-FONCTIONS' => repli_liste($d['PARCOURS']['fonctions'] ?? [], $langue),
        'PARCOURS-OPERA' => repli_liste($d['PARCOURS']['opera'] ?? [], $langue),
        'ORCHESTRES' => repli_orchestres($d['ORCHESTRES'] ?? [], $langue),
        'TELECHARGEMENTS' => repli_telechargements($d['TELECHARGEMENTS'] ?? [], $langue),
        'RESEAUX-BARRE' => repli_reseaux($d['RESEAUX'] ?? [], true),
        'RESEAUX' => repli_reseaux($d['RESEAUX'] ?? [], false),
        'CONTACTS' => repli_contacts($d['CONTACTS'] ?? [], $langue),
    ];
    foreach ($v as $cle => $visuel) {
        $z[strtoupper($cle)] = repli_visuel($visuel, $langue);
    }
    return $z;
}

/**
 * Réécrit dans une page le contenu des zones présentes. Rend la page et, par référence, les
 * repères trouvés. Une zone dont les lignes sont vides est laissée telle quelle si $garder_vides.
 */
function repli_appliquer(string $page, array $zones, array &$trouves = []): string
{
    foreach ($zones as $nom => $lignes) {
        $debut = '<!-- ' . $nom . '-STATIQUE:DEBUT';
        $fin = '<!-- ' . $nom . '-STATIQUE:FIN -->';
        $i = strpos($page, $debut);
        if ($i === false) continue;
        $j = strpos($page, $fin, $i);
        $k = strpos($page, '-->', $i);
        if ($j === false || $k === false || $k > $j) continue;
        $k += 3;
        // le retrait de la zone : les blancs qui précèdent le repère de début sur sa ligne
        $ligne = strrpos(substr($page, 0, $i), "\n");
        $retrait = substr($page, $ligne === false ? 0 : $ligne + 1, $i - ($ligne === false ? 0 : $ligne + 1));
        if (trim($retrait) !== '') $retrait = '';
        $corps = '';
        foreach ($lignes as $l) $corps .= "\n" . $retrait . $l;
        $page = substr($page, 0, $k) . $corps . "\n" . $retrait . substr($page, $j);
        $trouves[] = $nom;
    }
    return $page;
}

/** Les pages du site que Pupitre réécrit : toutes les pages HTML de la racine. */
function site_pages(string $racine): array
{
    $pages = glob($racine . '/*.html') ?: [];
    sort($pages);
    return array_map('basename', $pages);
}

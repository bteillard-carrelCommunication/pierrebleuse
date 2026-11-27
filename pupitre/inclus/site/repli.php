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

/**
 * Libellés d'interface du site repris dans les zones de repli : nom court => [clé du libellé dans
 * data/libelles.js, texte de secours en français et en anglais si ce fichier ne le porte pas].
 */
const LIBELLES_SITE = [
    'disque' => ['agenda.disque', 'fr' => 'Disque', 'en' => 'Recording'],
    'lire' => ['presse.lire', 'fr' => 'Lire l\'article', 'en' => 'Read the article'],
    'parcours.depuis' => ['parcours.depuis', 'fr' => 'depuis {annee}', 'en' => 'since {annee}'],
    'parcours.periode' => ['parcours.periode', 'fr' => '{debut} à {fin}', 'en' => '{debut} to {fin}'],
    'a_venir' => ['commun.aVenir', 'fr' => 'à venir', 'en' => 'coming soon'],
    'vide' => ['agenda.vide', 'fr' => 'Les prochaines dates seront annoncées ici.', 'en' => 'Upcoming dates will be announced here.'],
    'ameriques' => ['parcours.zone.ameriques', 'fr' => 'Amériques', 'en' => 'Americas'],
    'europe-afrique' => ['parcours.zone.europe-afrique', 'fr' => 'Europe et Afrique', 'en' => 'Europe and Africa'],
    'asie-oceanie' => ['parcours.zone.asie-oceanie', 'fr' => 'Asie, Russie et Océanie', 'en' => 'Asia, Russia and Oceania'],
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

/**
 * Contexte du rendu en cours, tiré des données que l'on rend et d'elles seules : les libellés
 * d'interface (data/libelles.js) et la langue de référence (data/site.js). repli_zones() le pose.
 * Ce fichier ne lit donc jamais la base : il se contrôle sans elle (tests/repli.php).
 */
function repli_contexte(?array $donnees = null): array
{
    static $c = ['libelles' => [], 'reference' => 'fr', 'langue' => null];
    if ($donnees !== null) {
        $c = ['libelles' => (array) ($donnees['LIBELLES'] ?? []), 'reference' => (string) ($donnees['SITE']['langues']['reference'] ?? 'fr'), 'langue' => $donnees['@langue'] ?? null];
    }
    return $c;
}

/** Champ écrit en chaîne ou en { fr, en, … } : la version demandée, sinon celle de la langue de référence. */
function site_choisir($champ, string $langue = 'fr'): string
{
    if ($champ === null) return '';
    if (is_array($champ)) return (string) (($champ[$langue] ?? '') !== '' ? $champ[$langue] : ($champ[repli_contexte()['reference']] ?? ($champ['fr'] ?? '')));
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

/** Libellé d'interface : celui des données (nom court ci-dessus, ou clé complète), sinon le texte de secours. */
function site_libelle(string $cle, string $langue): string
{
    $t = site_choisir(repli_contexte()['libelles'][LIBELLES_SITE[$cle][0] ?? $cle] ?? null, $langue);
    if ($t !== '') return $t;
    return LIBELLES_SITE[$cle][$langue] ?? (LIBELLES_SITE[$cle]['fr'] ?? '');
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
    if ($langue === 'fr') return MOIS_FR[$m - 1];
    if ($langue === 'en') return MOIS_EN[$m - 1];
    // autre langue : le nom du mois selon ses usages (extension intl), sinon l'anglais
    if (class_exists('IntlDateFormatter')) {
        $f = new IntlDateFormatter($langue, IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', null, 'LLLL');
        $nom = $f->format(gmmktime(12, 0, 0, $m, 15, 2026));
        if (is_string($nom) && $nom !== '') return $nom;
    }
    return MOIS_EN[$m - 1];
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

/**
 * Média de repli d'un bloc à médias : le premier élément que le bloc montre (data/visuels.js),
 * sans animation. Une image, la vidéo avec son image d'attente (rien n'est chargé d'avance), ou une
 * boîte vide pour YouTube.
 */
function repli_visuel(?array $bloc, string $langue): array
{
    $v = $bloc['elements'][0] ?? null;
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
    return ['<div class="visuel__media"></div>'];           // YouTube : rien n'est chargé sans l'accord du visiteur
}

/** Repère de la zone de repli d'un bloc à médias : VISUEL-1 pour « visuel-1 », VISUEL-SUIVRE pour « suivre ». */
function zone_visuel(string $bloc): string
{
    return strtoupper(str_starts_with($bloc, 'visuel-') ? $bloc : 'visuel-' . $bloc);
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

/**
 * Vignettes des albums, premier niveau : année et label, titre, descriptif, distinctions mises en
 * avant. Le panneau détaillé (« En savoir plus ») demande JavaScript : il n'est pas dans le repli.
 */
function repli_disques(array $disques, string $langue): array
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
        $unes = array_filter($d['distinctions'] ?? [], fn($x) => !empty($x['une']));
        if ($unes) $s .= '<p class="fiche__texte fiche__distinctions">' . site_html(implode(', ', array_map(fn($x) => site_choisir($x['intitule'] ?? '', $langue), $unes))) . '</p>';
        $l[] = $s . '</article>';
    }
    return $l;
}

/** Citation principale d'un article : la première marquée, sinon la première. */
function article_citation(array $a): ?array
{
    foreach ($a['citations'] ?? [] as $c) if (!empty($c['principale'])) return $c;
    return ($a['citations'] ?? [])[0] ?? null;
}

/** Articles de l'accueil : ceux qui sont sélectionnés, dans leur rang. */
function articles_accueil(array $articles): array
{
    $l = array_values(array_filter($articles, fn($a) => !empty($a['accueil'])));
    usort($l, fn($x, $y) => [(int) ($x['rang'] ?? 0), (string) $x['id']] <=> [(int) ($y['rang'] ?? 0), (string) $y['id']]);
    return $l;
}

/**
 * Articles de l'accueil, dans l'ordre demandé : la citation, le média (son logo, ou son nom), le
 * chapeau ou le résumé, la date, le lien vers l'article. Le panneau « En savoir plus » demande
 * JavaScript : il n'est pas dans le repli.
 */
function repli_presse(array $articles, string $langue): array
{
    $l = [];
    foreach (articles_accueil($articles) as $a) {
        $largeur = in_array((int) ($a['largeur'] ?? 1), [1, 2, 4], true) ? (int) $a['largeur'] : 1;
        $s = '<article class="fiche article article--l' . $largeur . '">';
        $c = article_citation($a);
        if ($c) $s .= '<blockquote class="article__verbatim" lang="' . site_attribut(($c['langue'] ?? '') ?: $langue) . '">' . site_html($c['texte']) . '</blockquote>';
        $nom = site_attribut((string) ($a['media'] ?? ''));
        $s .= '<p class="article__media">' . (!empty($a['logo'])
            ? '<img class="article__logo" src="' . site_attribut($a['logo']) . '" alt="' . $nom . '" decoding="async">'
            : '<span class="article__nom' . (!empty($a['typo']) ? ' article__nom--' . site_attribut($a['typo']) : '') . '">' . site_html((string) ($a['media'] ?? '')) . '</span>') . '</p>';
        $texte = site_choisir($a['chapeau'] ?? '', $langue) ?: site_choisir($a['resume'] ?? '', $langue);
        if ($texte === '' && !$c) $texte = site_choisir($a['titre'] ?? '', $langue);            // ni citation ni résumé : le titre tient lieu de texte
        if ($texte !== '') $s .= '<p class="article__resume">' . site_html($texte) . '</p>';
        $date = site_date_article((string) ($a['date'] ?? ''), $langue);
        if ($date !== '') $s .= '<p class="article__date"><span>' . site_html($date) . '</span></p>';
        if (!empty($a['lien'])) $s .= '<p class="article__liens">' . site_lien($a['lien'], site_html(site_libelle('lire', $langue)), 'lien') . '</p>';
        $l[] = $s . '</article>';
    }
    return $l;
}

/** Période d'une fonction : « depuis 2023 », « 2021 à 2026 », ou rien. */
function fonction_periode(array $f, string $langue): string
{
    $debut = (string) ($f['debut'] ?? '');
    $fin = (string) ($f['fin'] ?? '');
    if ($debut !== '' && $fin !== '') return strtr(site_libelle('parcours.periode', $langue) ?: '{debut} – {fin}', ['{debut}' => $debut, '{fin}' => $fin]);
    if ($debut !== '' && !empty($f['actuelle'])) return strtr(site_libelle('parcours.depuis', $langue) ?: '{annee}', ['{annee}' => $debut]);
    return $debut !== '' ? $debut : $fin;
}

/** Une fonction, sur une ligne : intitulé, institution, période. */
function fonction_ligne(array $f, string $langue): string
{
    return implode(', ', array_filter([site_choisir($f['intitule'] ?? '', $langue), site_choisir($f['institution'] ?? '', $langue), fonction_periode($f, $langue)], 'strlen'));
}

/** Une collaboration, sur une ligne : intitulé, orchestre s'il n'y figure pas, année. */
function collaboration_ligne(array $c, string $langue): string
{
    $intitule = site_choisir($c['intitule'] ?? '', $langue);
    $orchestre = (string) ($c['orchestre'] ?? '');
    return implode(', ', array_filter([$intitule, ($orchestre !== '' && !str_contains($intitule, $orchestre)) ? $orchestre : '', (string) ($c['annee'] ?? '')], 'strlen'));
}

function repli_fonctions(array $fonctions, string $langue): array
{
    $l = [];
    foreach ($fonctions as $f) {
        $t = fonction_ligne($f, $langue);
        if ($t !== '') $l[] = '<li' . (!empty($f['une']) ? ' class="liste__une"' : '') . '>' . site_html($t) . '</li>';
    }
    return $l;
}

function repli_collaborations(array $collaborations, string $langue): array
{
    $l = [];
    foreach ($collaborations as $c) {
        $t = collaboration_ligne($c, $langue);
        if ($t === '') continue;
        $h = site_html($t);
        if (!empty($c['concert'])) $h = '<a href="concerts.html#c-' . site_attribut(rawurlencode((string) $c['concert'])) . '">' . $h . '</a>';
        $l[] = '<li' . (!empty($c['une']) ? ' class="liste__une"' : '') . '>' . $h . '</li>';
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
            // comme le rend le site : le nom, puis la ville (deux lignes peuvent porter le même nom) ;
            // sans nom, la ville seule
            $ville = site_choisir($o['ville'] ?? '', $langue);
            $l[] = ($o['nom'] ?? '') !== ''
                ? '    <li>' . site_html($o['nom']) . ($ville !== '' ? '<span class="orchestres__ville">' . site_html($ville) . '</span>' : '') . '</li>'
                : '    <li>' . site_html($ville) . '</li>';
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
        $fichier = (string) ($t['fichier'] ?? '');
        $hreflang = !empty($t['langue']) ? ' hreflang="' . site_attribut((string) $t['langue']) . '"' : '';
        // description, crédits, conditions d'utilisation : une ligne discrète, seulement s'il y a quelque chose à dire
        $info = implode(' · ', array_map('site_html', array_values(array_filter([site_choisir($t['description'] ?? '', $langue), (string) ($t['credit'] ?? ''), site_choisir($t['conditions'] ?? '', $langue)], 'strlen'))));
        $suite = $info !== '' ? ' <small class="tel__info">' . $info . '</small>' : '';
        $li = $info !== '' ? '<li class="tel--detail">' : '<li>';
        if ($fichier === '') $l[] = $li . '<span>' . $libelle . '</span> <span class="avenir">' . site_html(site_libelle('a_venir', $langue)) . '</span>' . $suite . '</li>';
        elseif (preg_match('#^https?://#i', $fichier)) $l[] = $li . '<a href="' . site_attribut($fichier) . '"' . $hreflang . ' target="_blank" rel="noopener">' . $libelle . '</a>' . $suite . '</li>';
        else $l[] = $li . '<a href="' . site_attribut($fichier) . '"' . $hreflang . ' download>' . $libelle . '</a>' . $suite . '</li>';
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
        // dans l'ordre du site : la mention, la structure, puis les personnes
        $mention = site_choisir($g['mention'] ?? '', $langue);
        if ($mention !== '') $l[] = '  <p class="mention"' . $lang . '>' . site_html($mention) . '</p>';
        if (!empty($g['structure'])) {
            $nom = site_html($g['structure']);
            $l[] = '  <p class="contacts__agence">' . (!empty($g['lien']) ? site_lien($g['lien'], $nom) : $nom) . '</p>';
        }
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
 * Réglages généraux, dans le <head> de chaque page : la couleur de fond (variables CSS --bg et
 * --bg-rvb) et les blocs masqués, qui n'occupent ainsi aucune place dès le premier affichage, avec
 * ou sans JavaScript. Leur entrée de menu disparaît avec eux.
 */
function repli_site(array $site): array
{
    $fond = couleur_lire((string) ($site['fond'] ?? '')) ?: '#000000';
    $regles = [':root { --bg: ' . $fond . '; --bg-rvb: ' . couleur_rvb($fond) . '; }'];
    $masques = array_keys(array_filter($site['blocs'] ?? [], fn($visible) => $visible === false));
    foreach ($masques as $cle) {
        if (!preg_match('/^[a-z0-9-]+$/', $cle)) continue;
        $regles[] = '#' . $cle . ', [data-menu-liste] a[href="#' . $cle . '"] { display: none !important; }';
        $regles[] = '[data-menu-liste] li:has(> a[href="#' . $cle . '"]) { display: none !important; }';
    }
    return array_merge(['<style data-site>'], array_map(fn($r) => '  ' . $r, $regles), ['</style>']);
}

/** Bascule de langue de la barre du haut : un lien par langue du site, la langue de la page marquée. */
function repli_langues(array $site, string $langue): array
{
    $liens = [];
    foreach ($site['langues']['liste'] ?? [] as $l) {
        $code = site_attribut((string) $l['code']);
        $liens[] = '<a href="?lang=' . $code . '" data-langue="' . $code . '" lang="' . $code . '" hreflang="' . $code . '"'
            . ($l['code'] === $langue ? ' class="is-on" aria-current="true"' : '') . '>' . strtoupper($code) . '</a>';
    }
    return $liens ? [implode('<span class="sep" aria-hidden="true">|</span>', $liens)] : [];
}

/**
 * Toutes les zones de repli : nom du repère => lignes de HTML.
 * $exports : les données du site par variable (site_exports(), ou les fichiers relus).
 * $langue : la langue du HTML de repli ; sans elle, la langue par défaut du site.
 */
function repli_zones(array $exports, ?string $langue = null): array
{
    $d = json_decode(json_encode($exports), true);          // objets et tableaux : tout en tableaux
    $site = $d['SITE'] ?? [];
    $langue = $langue ?? (string) ($site['langues']['defaut'] ?? 'fr');
    // la langue du HTML statique n'est posée que si le site publie ses libellés (data/libelles.js) : sans eux, les pages restent telles quelles
    repli_contexte($d + ['@langue' => !empty($d['LIBELLES']) ? $langue : null]);
    $t = $d['TEXTES'] ?? [];
    $v = $d['VISUELS'] ?? [];
    $description = site_choisir($t['description'] ?? '', $langue);
    $z = [
        'SITE' => $site ? repli_site($site) : [],
        'LANGUES' => $site ? repli_langues($site, $langue) : [],
        'DESCRIPTION' => $description !== '' ? ['<meta name="description" content="' . site_attribut($description) . '">'] : [],
        'CITATION' => repli_texte('blockquote', 'ouverture__citation', 'ouverture.citation', $t['citation'] ?? '', $langue),
        'BIOGRAPHIE' => repli_biographie($t['biographie'] ?? [], $langue),
        'SAISON' => repli_texte('p', 'mention', 'agenda.mention', $t['saison'] ?? '', $langue),
        'LETTRE' => repli_texte('p', 'texte', 'lettre.texte', $t['lettre'] ?? '', $langue),
        'CREDIT' => repli_texte('p', '', 'pied.credit', $t['credit'] ?? '', $langue),
        'AGENDA' => repli_agenda($d['AGENDA_DONNEES'] ?? [], $langue),
        'DISQUES' => repli_disques($d['DISQUES'] ?? [], $langue),
        'PRESSE' => repli_presse($d['PRESSE'] ?? [], $langue),
        'PARCOURS-FONCTIONS' => repli_fonctions($d['PARCOURS']['fonctions'] ?? [], $langue),
        'PARCOURS-COLLABORATIONS' => repli_collaborations($d['PARCOURS']['collaborations'] ?? [], $langue),
        'ORCHESTRES' => repli_orchestres($d['ORCHESTRES'] ?? [], $langue),
        'TELECHARGEMENTS' => repli_telechargements($d['TELECHARGEMENTS'] ?? [], $langue),
        'RESEAUX-BARRE' => repli_reseaux($d['RESEAUX'] ?? [], true),
        'RESEAUX' => repli_reseaux($d['RESEAUX'] ?? [], false),
        'CONTACTS' => repli_contacts($d['CONTACTS'] ?? [], $langue),
    ];
    // un bloc masqué, ou sans média, ne charge rien : sa zone de repli est vide
    foreach (array_keys($site['blocs'] ?? []) as $cle) $z[zone_visuel($cle)] = [];
    foreach ($v as $cle => $visuel) {
        if (($site['blocs'][$cle] ?? true) !== false) $z[zone_visuel($cle)] = repli_visuel($visuel, $langue);
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
    $langue = repli_contexte()['langue'];
    return $langue ? repli_statique($page, $langue) : $page;
}

/**
 * Écrit le HTML statique d'une page dans la langue par défaut du site : c'est ce que lisent un
 * visiteur sans JavaScript et un moteur de recherche. Sont réécrits : <html lang>, le <title>, le
 * contenu des éléments à libellé (data-t="clé"), leurs attributs (data-t-attr="attribut:clé"), et,
 * sur les pages rédigées en entier dans plusieurs langues, le bloc affiché (data-langue-bloc).
 * Les textes éditables (citation, édito…) sont déjà écrits dans cette langue par leurs zones.
 * js/contenu.js compare ensuite ce HTML au dictionnaire (SiteContenu.ecarts, qui doit rester vide).
 */
function repli_statique(string $page, string $langue): string
{
    $libelles = repli_contexte()['libelles'];
    $connu = fn(string $cle): bool => isset($libelles[$cle]);
    $brut = fn(string $cle): string => str_replace(INSECABLE, '&nbsp;', site_attribut(site_typo(site_libelle($cle, $langue))));
    if (!preg_match('/^[a-z]{2,3}$/', $langue)) return $page;

    $page = preg_replace('/(<html\b[^>]*?\blang=")[^"]*(")/', '${1}' . $langue . '${2}', $page, 1);

    // contenu des éléments à libellé : aucun n'en contient un autre de même balise
    $page = preg_replace_callback('/(<([a-z][a-z0-9]*)\b[^>]*?\bdata-t="([^"]+)"[^>]*>)(.*?)(<\/\2>)/su', function (array $m) use ($connu, $langue) {
        return $connu($m[3]) ? $m[1] . site_html(site_libelle($m[3], $langue)) . $m[5] : $m[0];
    }, $page) ?? $page;

    // attributs à libellé : aria-label, placeholder, alt…
    $page = preg_replace_callback('/<[a-z][a-z0-9]*\b[^>]*?\bdata-t-attr="([^"]+)"[^>]*>/su', function (array $m) use ($connu, $brut) {
        $balise = $m[0];
        foreach (explode(';', $m[1]) as $paire) {
            if (!str_contains($paire, ':')) continue;
            [$attr, $cle] = array_map('trim', explode(':', $paire, 2));
            if (!preg_match('/^[a-z][a-z-]*$/', $attr) || !$connu($cle)) continue;
            $valeur = $attr . '="' . $brut($cle) . '"';
            $n = 0;
            $balise = preg_replace('/(?<=\s)' . preg_quote($attr, '/') . '="[^"]*"/', $valeur, $balise, 1, $n);
            if (!$n) $balise = preg_replace('/\bdata-t-attr=/', $valeur . ' data-t-attr=', $balise, 1);
        }
        return $balise;
    }, $page) ?? $page;

    // titre de la page : la clé de <html data-titre>, sinon le titre du site
    if (preg_match('/<title>([^<]*)<\/title>/', $page, $t)) {
        $suite = ($i = mb_strrpos($t[1], ' · ')) !== false ? mb_substr($t[1], $i) : '';
        $cle = preg_match('/<html\b[^>]*\bdata-titre="([^"]+)"/', $page, $h) ? $h[1] : '';
        $titre = ($cle !== '' && $connu($cle)) ? $brut($cle) . ($suite !== '' ? $suite : ' · ' . $brut('meta.titre')) : ($connu('meta.titre') ? $brut('meta.titre') : '');
        if ($titre !== '') $page = str_replace($t[0], '<title>' . $titre . '</title>', $page);
    }

    // pages rédigées dans plusieurs langues : seul le bloc de la langue du HTML est affiché (à défaut, rien ne change)
    if (preg_match_all('/<[a-z][a-z0-9]*\b[^>]*\bdata-langue-bloc="([a-z]{2,3})"[^>]*>/', $page, $blocs) && in_array($langue, $blocs[1], true)) {
        foreach ($blocs[0] as $i => $balise) {
            $sans = preg_replace('/\s+hidden(?=[\s>])/', '', $balise);
            $page = str_replace($balise, $blocs[1][$i] === $langue ? $sans : substr($sans, 0, -1) . ' hidden>', $page);
        }
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

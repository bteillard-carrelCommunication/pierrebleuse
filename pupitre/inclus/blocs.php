<?php
/**
 * Blocs du site : les sections de la page d'accueil, leur visibilité, ce que chacune donne à régler.
 *
 * Deux familles de données dans Pupitre. Les blocs correspondent aux sections du site : chacun a
 * une clé stable (l'identifiant de la section dans le HTML), une visibilité, ses textes, ses
 * libellés, ses médias. Les collections (concerts, orchestres, albums, articles, playlist…) sont
 * des objets indépendants, que les blocs ne font qu'afficher : la source des données reste la
 * collection.
 *
 * L'ordre des blocs est celui de la page. Ce n'est pas un constructeur de pages.
 */

defined('PUPITRE') or exit;

/** Tous les blocs, dans l'ordre de la page : clé => ligne de la table. */
function blocs_tous(): array
{
    static $b = null;
    if ($b === null || !empty($GLOBALS['blocs_perimes'])) {
        $b = [];
        try {
            foreach (bd_lignes('SELECT * FROM blocs ORDER BY ordre, cle') as $l) {
                $l['parametres'] = json_decode((string) $l['parametres'], true) ?: [];
                $b[$l['cle']] = $l;
            }
        } catch (Throwable $e) {
            // base d'avant la migration des blocs
        }
        $GLOBALS['blocs_perimes'] = false;
    }
    return $b;
}

/** Un bloc est-il affiché ? Un bloc inconnu l'est (base d'avant la migration, section sans réglage). */
function bloc_visible(string $cle): bool
{
    $b = blocs_tous()[$cle] ?? null;
    return !$b || !$b['masquable'] || (bool) $b['visible'];
}

/**
 * Visibilité de toutes les sections, pour le site : les blocs, et les sections qui ne sont que la
 * réunion de plusieurs blocs (le parcours), affichées tant qu'une de leurs parties l'est.
 */
function blocs_visibilite(): array
{
    $v = [];
    $parents = [];
    foreach (blocs_tous() as $cle => $b) {
        $v[$cle] = bloc_visible($cle);
        if ($b['parent'] !== '') $parents[$b['parent']] = ($parents[$b['parent']] ?? false) || $v[$cle];
    }
    return $parents + $v;
}

/** Un réglage d'un bloc (colonne JSON `parametres`), ou sa valeur par défaut. */
function bloc_parametre(string $cle, string $nom, $defaut = null)
{
    return blocs_tous()[$cle]['parametres'][$nom] ?? $defaut;
}

/** Affiche ou masque un bloc. Rien d'autre ne change : contenus, médias et réglages restent en place. */
function bloc_afficher(string $cle, bool $visible): bool
{
    $b = blocs_tous()[$cle] ?? null;
    if (!$b || !$b['masquable'] || (bool) $b['visible'] === $visible) return false;
    bd_modifier('blocs', ['visible' => $visible ? 1 : 0], ['cle' => $cle]);
    journaliser('statut', 'bloc', null, 'Bloc « ' . $b['nom'] . ' »', ['visible' => (int) $b['visible']], ['visible' => $visible ? 1 : 0]);
    $GLOBALS['blocs_perimes'] = true;
    return true;
}

/** Écrit les réglages d'un bloc (fusionnés avec ceux qu'il a déjà). */
function bloc_regler(string $cle, array $parametres): void
{
    $b = blocs_tous()[$cle] ?? null;
    if (!$b) return;
    $neufs = array_merge($b['parametres'], $parametres);
    if ($neufs == $b['parametres']) return;
    bd_modifier('blocs', ['parametres' => json_encode($neufs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['cle' => $cle]);
    journaliser('modification', 'bloc', null, 'Bloc « ' . $b['nom'] . ' »', $b['parametres'], $neufs);
    $GLOBALS['blocs_perimes'] = true;
}

/**
 * Ce que chaque bloc donne à régler dans Pupitre, dans l'ordre de l'écran :
 *   intro     une phrase qui situe le bloc ;
 *   textes    clés de la table `textes` (inclus/contenus.php, TEXTES) ;
 *   libelles  clés de la table `libelles` : les intitulés et boutons du bloc ;
 *   liens     [libellé, page de Pupitre, paramètres, ancre] : les collections et écrans que le bloc affiche ;
 *   apercu    repères des zones de repli à montrer en aperçu.
 * « general » n'est pas un bloc de la page : il réunit ce qui vaut pour tout le site.
 */
function blocs_description(): array
{
    return [
        'general' => [
            'nom' => 'Titre du site, description et menu',
            'intro' => 'Ce qui vaut pour toutes les pages : le titre de l\'onglet, la description lue par les moteurs de recherche, les entrées du menu.',
            'textes' => ['description'],
            'libelles' => ['meta.titre', 'nav.menu', 'nav.agenda', 'nav.disques', 'nav.parcours', 'nav.presse', 'nav.telechargement', 'nav.contact', 'page.retour', 'page.haut',
                'video.lire', 'video.pause', 'video.precedente', 'video.suivante', 'video.position', 'yt.texte', 'yt.bouton'],
        ],
        'ouverture' => [
            'intro' => 'Le premier écran : la nuée, la citation et le lecteur de musique.',
            'textes' => ['citation'],
            'libelles' => ['lecteur.play', 'lecteur.pause', 'lecteur.titres', 'ouverture.scroll', 'ouverture.defiler'],
            'liens' => [['Playlist et morceau d\'ouverture', 'musiques', [], '']],
            'apercu' => ['CITATION' => ''],
        ],
        'bio' => [
            'intro' => 'Le texte de présentation, à droite de la page.',
            'textes' => ['biographie'],
            'apercu' => ['BIOGRAPHIE' => ''],
        ],
        'visuel-1' => [
            'intro' => 'Bloc visuel, en pleine page, sans texte : une image, une image animée, une ou plusieurs vidéos. Le vol d\'étourneaux n\'y est pas visible.',
            'liens' => [['Médias de ce bloc', 'visuels', [], '#visuel-1']],
        ],
        'agenda' => [
            'intro' => 'Les prochains concerts, tirés de la collection Concerts.',
            'textes' => ['saison'],
            'libelles' => ['agenda.titre', 'agenda.affiche', 'agenda.plus', 'agenda.tous', 'agenda.saison', 'agenda.vide', 'concerts.detail', 'concerts.presse', 'concerts.description'],
            'liens' => [['Concerts', 'agenda', [], '']],
            'apercu' => ['SAISON' => ''],
        ],
        'visuel-2' => [
            'intro' => 'Bloc visuel, en pleine page, sans texte : une image, une image animée, une ou plusieurs vidéos. Le vol d\'étourneaux n\'y est pas visible.',
            'liens' => [['Médias de ce bloc', 'visuels', [], '#visuel-2']],
        ],
        'disques' => [
            'intro' => 'Les albums, tirés de la collection Discographie.',
            'libelles' => ['disques.titre', 'disques.plus', 'disques.presentation', 'disques.contexte', 'disques.mot', 'disques.oeuvres', 'disques.compositeurs', 'disques.interpretes', 'disques.infos',
                'disques.distinctions', 'disques.critiques', 'disques.streaming', 'disques.commander', 'disques.page', 'disques.sortie', 'panneau.fermer'],
            'liens' => [['Discographie', 'disques', [], ''], ['Distinctions', 'distinctions', [], '']],
        ],
        'parcours-fonctions' => [
            'intro' => 'Les fonctions de l\'artiste, première partie du parcours.',
            'libelles' => ['parcours.titre', 'parcours.fonctions', 'parcours.depuis', 'parcours.periode'],
            'liens' => [['Fonctions', 'parcours', [], '#fonctions']],
            'apercu' => ['PARCOURS-FONCTIONS' => ''],
        ],
        'parcours-collaborations' => [
            'intro' => 'Quatre ou cinq collaborations prestigieuses, tirées des concerts et des orchestres. Son intitulé se règle ci-dessous. Sans collaboration publiée, le bloc ne s\'affiche pas.',
            'libelles' => ['parcours.collaborations'],
            'liens' => [['Grands débuts et collaborations', 'parcours', [], '#collaborations']],
            'apercu' => ['PARCOURS-COLLABORATIONS' => ''],
        ],
        'parcours-monde' => [
            'intro' => 'La carte du monde et la liste des orchestres, tirées de la collection Orchestres.',
            'libelles' => ['parcours.invites', 'parcours.zone.ameriques', 'parcours.zone.europe-afrique', 'parcours.zone.asie-oceanie', 'parcours.carte.monde'],
            'liens' => [['Carte interactive', 'carte', [], ''], ['Orchestres du monde', 'orchestres', [], '']],
        ],
        'visuel-3' => [
            'intro' => 'Bloc visuel, en pleine page, sans texte : une image, une image animée, une ou plusieurs vidéos. Le vol d\'étourneaux n\'y est pas visible.',
            'liens' => [['Médias de ce bloc', 'visuels', [], '#visuel-3']],
        ],
        'presse' => [
            'intro' => 'Les articles mis en avant, tirés de la collection Revue de presse.',
            'libelles' => ['presse.titre', 'presse.plus', 'presse.lire', 'presse.tous', 'presse.traduction', 'presse.citations', 'presse.concerts', 'presse.albums', 'presse.auteur'],
            'liens' => [['Revue de presse : articles, sélection de l\'accueil, largeurs', 'presse', [], '']],
        ],
        'telechargement' => [
            'intro' => 'Les ressources à télécharger.',
            'libelles' => ['tel.titre', 'commun.aVenir'],
            'liens' => [['Téléchargements', 'contenu', ['type' => 'telechargements'], '']],
            'apercu' => ['TELECHARGEMENTS' => ''],
        ],
        'suivre' => [
            'intro' => 'L\'inscription à la lettre et les réseaux, sur une photographie.',
            'textes' => ['lettre'],
            'libelles' => ['suivre.titre', 'lettre.titre', 'lettre.champ', 'lettre.bouton', 'lettre.note'],
            'liens' => [['Photographie de fond', 'visuels', [], '#visuel-4'], ['Réseaux de l\'artiste', 'contenu', ['type' => 'contacts'], '']],
            'apercu' => ['LETTRE' => ''],
        ],
        'contact' => [
            'intro' => 'Les contacts de l\'artiste.',
            'libelles' => ['contact.titre'],
            'liens' => [['Contacts et réseaux', 'contenu', ['type' => 'contacts'], '']],
            'apercu' => ['CONTACTS' => ''],
        ],
        'pied' => [
            'intro' => 'Le pied de page : à gauche, les liens vers les pages légales, posés par le site ; à droite, un texte libre.',
            'textes' => ['credit'],
            'libelles' => ['pied.mentions', 'pied.confidentialite', 'pied.credits', 'pied.pause', 'pied.reprise', 'pied.reglages'],
            'apercu' => ['CREDIT' => ''],
        ],
    ];
}

// ---------- Apparence ----------

/** La couleur de fond générale du site, « #rrggbb ». */
function couleur_fond(): string
{
    return couleur_lire(reglage('fond')) ?: '#000000';
}

/** Couleurs proposées d'un geste sur l'écran « Apparence » : la couleur d'origine et quelques bleus nuit à essayer. */
const COULEURS_PROPOSEES = [
    '#000000' => 'Noir (couleur d\'origine)',
    '#04070d' => 'Bleu nuit, presque noir',
    '#060b16' => 'Bleu nuit profond',
    '#0a1524' => 'Bleu nuit (celui du halo de la nuée)',
    '#0b1a30' => 'Bleu nuit soutenu',
];

// ---------- Libellés de l'interface du site ----------

function libelles_tous(): array
{
    $l = [];
    foreach (bd_lignes('SELECT * FROM libelles ORDER BY ordre, cle') as $x) $l[$x['cle']] = $x;
    return $l;
}

/** Texte d'un libellé du site dans une langue, tel qu'il sortira : la traduction validée, sinon la langue de référence. */
function libelle_site(string $cle, string $langue): string
{
    static $l = null;
    if ($l === null || !empty($GLOBALS['libelles_perimes'])) {
        $l = [];
        try { $l = libelles_tous(); } catch (Throwable $e) { /* base d'avant la migration */ }
        $GLOBALS['libelles_perimes'] = false;
    }
    if (!isset($l[$cle])) return '';
    $reference = (string) $l[$cle]['texte_' . langue_reference()];
    if ($langue === langue_reference()) return $reference;
    return traduction_publiee('libelles', $cle, 'texte', $langue) ?: $reference;
}

/**
 * Enregistre des libellés reçus d'un formulaire : champs lib[<clé>][texte_fr], lib[<clé>][texte_en]…
 * Seules les clés de $cles sont lues. Un libellé ne peut pas être vidé dans la langue de référence.
 */
function libelles_enregistrer(array $recus, array $cles): int
{
    $tous = libelles_tous();
    $n = 0;
    foreach ($cles as $cle) {
        if (!isset($tous[$cle]) || !is_array($recus[$cle] ?? null)) continue;
        $neuf = recu_bi($recus[$cle], 'texte', 2000);
        $reference = 'texte_' . langue_reference();
        if ($neuf[$reference] === '') $neuf[$reference] = (string) $tous[$cle][$reference];
        $avant = [$reference => (string) $tous[$cle][$reference]];
        foreach (langues_autres() as $langue) $avant['texte_' . $langue] = (string) (traduction('libelles', $cle, 'texte', $langue)['texte'] ?? '');
        if ($avant == $neuf) continue;
        bd_modifier('libelles', $neuf, ['cle' => $cle]);
        journaliser('modification', 'libelle', null, 'Libellé « ' . $cle . ' »', $avant, $neuf);
        $n++;
    }
    if ($n) $GLOBALS['libelles_perimes'] = true;
    return $n;
}

/** Crée un libellé s'il manque (migrations) : texte de référence, puis traductions [langue => texte]. */
function libelle_creer(string $cle, string $texte, array $traductions = []): void
{
    if (bd_valeur('SELECT COUNT(*) FROM libelles WHERE cle = ?', [$cle])) return;
    $ordre = (int) bd_valeur('SELECT COALESCE(MAX(ordre), 0) + 1 FROM libelles');
    bd_requete('INSERT INTO libelles (cle, texte_fr, groupe, ordre) VALUES (?, ?, ?, ?)', [$cle, $texte, explode('.', $cle)[0], $ordre]);
    foreach ($traductions as $langue => $t) {
        if (trim((string) $t) === '') continue;
        bd_requete("INSERT IGNORE INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES ('libelles', ?, 'texte', ?, ?, 'validee', 'reprise', ?)", [$cle, $langue, $t, md5($texte)]);
    }
    $GLOBALS['libelles_perimes'] = $GLOBALS['traductions_perimees'] = true;
}

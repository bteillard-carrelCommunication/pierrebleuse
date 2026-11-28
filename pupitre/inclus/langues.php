<?php
/**
 * Langues et traductions : le socle multilingue de Pupitre.
 *
 * Les langues sont dans la table `langues`. L'une est la langue de rédaction (la « langue de
 * référence ») : l'anglais, depuis la migration 011. Les contenus sont rédigés dans cette langue,
 * et dans elle seule, sur les écrans de saisie ; son texte vit dans les tables des objets, dans les
 * colonnes « champ_en ». Toutes les autres langues, le français compris, vivent dans la table
 * `traductions` : une ligne par objet, champ et langue, avec son statut. Elles se saisissent sur
 * l'écran « Traductions », où chaque texte a sa ligne. Ajouter une langue ne demande aucune colonne.
 *
 * Trois statuts :
 *   validee    relue par une personne : elle sort sur le site ;
 *   proposee   proposée par l'IA, pas encore relue : elle ne sort pas, le site garde l'anglais ;
 *   a_revoir   (calculé) validée, mais le texte anglais a changé depuis : elle sort, et elle est signalée.
 * Traduction absente ou vide : le site affiche le texte anglais.
 *
 * Dans le code, un champ traduisible « x » d'une table s'écrit x_en dans les tableaux de données ;
 * une autre langue peut s'y écrire x_fr, x_de… : bd_inserer() et bd_modifier() (inclus/base.php) la
 * rangent d'eux-mêmes dans la table des traductions. Les fonctions de ce fichier ne nomment jamais
 * la langue de référence : elles la lisent (langue_reference(), colonne_reference()).
 *
 * TRADUISIBLES dit, table par table, quels champs se traduisent. Tout le reste (dates, coordonnées,
 * liens, relations) est commun à toutes les langues et n'existe qu'une fois.
 */

defined('PUPITRE') or exit;

/**
 * Champs traduisibles : table => [nom lisible, clé primaire, colonne qui nomme la ligne, [champ => libellé]].
 * L'écran qui modifie le texte anglais d'une ligne, et le groupe sous lequel elle se range sur l'écran
 * « Traductions », sont donnés par traductions_contexte().
 */
const TRADUISIBLES = [
    'libelles' => ['Intitulés et boutons', 'cle', 'cle', ['texte' => 'Intitulé']],
    'textes' => ['Textes des pages', 'cle', 'cle', ['texte' => 'Texte']],
    'concerts' => ['Concerts', 'id', 'titre_en', ['titre' => 'Titre', 'ensemble' => 'Orchestre ou ensemble', 'salle' => 'Salle', 'ville' => 'Ville', 'pays' => 'Pays', 'mentions' => 'Informations complémentaires', 'description' => 'Description']],
    'representations' => ['Représentations', 'id', 'date', ['salle' => 'Salle', 'ville' => 'Ville', 'pays' => 'Pays']],
    'programme' => ['Programmes des concerts', 'id', 'oeuvre_en', ['compositeur' => 'Compositeur', 'oeuvre' => 'Œuvre', 'mention' => 'Mention']],
    'participants' => ['Participants des concerts', 'id', 'nom', ['role' => 'Rôle']],
    'maisons' => ['Maisons', 'id', 'nom_en', ['nom' => 'Nom']],
    'annuaire' => ['Annuaire', 'id', 'nom', ['ville' => 'Ville', 'pays' => 'Pays']],
    'medias' => ['Médiathèque', 'id', 'nom_origine', ['alt' => 'Texte alternatif']],
    'medias_usages' => ['Médias des blocs et des collections', 'id', 'titre_en', ['titre' => 'Titre', 'description' => 'Description', 'alt' => 'Texte alternatif']],
    'parcours_lignes' => ['Parcours (anciennes lignes)', 'id', 'texte_en', ['texte' => 'Ligne']],
    'parcours_orchestres' => ['Carte du monde', 'id', 'ville_en', ['ville' => 'Ville']],
    'disques' => ['Discographie', 'id', 'titre', ['sous_titre' => 'Sous-titre', 'descriptif' => 'Lignes de la vignette', 'oeuvres' => 'Œuvres', 'interpretes' => 'Interprètes', 'presentation' => 'Présentation',
        'mot' => 'Note de l\'artiste']],
    'distinctions' => ['Distinctions', 'id', 'intitule_en', ['intitule' => 'Intitulé', 'description' => 'Description']],
    'articles' => ['Revue de presse', 'id', 'titre_en', ['titre' => 'Titre', 'chapeau' => 'Chapeau', 'resume' => 'Résumé', 'infos' => 'Informations complémentaires']],
    'articles_citations' => ['Revue de presse : citations', 'id', 'texte', ['traduction' => 'Traduction de la citation']],
    'fonctions' => ['Fonctions', 'id', 'intitule_en', ['intitule' => 'Intitulé', 'institution' => 'Institution', 'description' => 'Description']],
    'collaborations' => ['Grands débuts et collaborations', 'id', 'intitule_en', ['intitule' => 'Intitulé', 'description' => 'Description']],
    'visuels' => ['Visuels (anciens emplacements)', 'cle', 'cle', ['alt' => 'Texte alternatif']],
    'musiques' => ['Playlist', 'id', 'titre_en', ['titre' => 'Titre', 'mention' => 'Mention', 'compositeur' => 'Compositeur', 'oeuvre' => 'Œuvre', 'mouvement' => 'Mouvement', 'interpretes' => 'Interprètes']],
    'telechargements' => ['Téléchargements', 'id', 'libelle_en', ['libelle' => 'Titre', 'description' => 'Description', 'conditions' => 'Conditions d\'utilisation']],
    'contacts_groupes' => ['Contacts : groupes', 'id', 'structure', ['mention' => 'Mention']],
    'contacts_personnes' => ['Contacts : personnes', 'id', 'nom', ['role' => 'Fonction']],
];

/**
 * Sections de l'écran « Traductions » : clé => [nom, tables]. Les textes des pages d'abord, puis une
 * section par collection de données. Une table absente d'ici n'y paraît pas (anciennes tables gardées
 * pour mémoire : parcours_lignes, visuels).
 */
const SECTIONS_TRADUCTION = [
    'pages' => ['Textes des pages', ['textes', 'libelles']],
    'concerts' => ['Concerts', ['concerts', 'representations', 'programme', 'participants', 'maisons']],
    'presse' => ['Presse', ['articles', 'articles_citations']],
    'disques' => ['Discographie', ['disques', 'distinctions']],
    'parcours' => ['Parcours et carte du monde', ['fonctions', 'collaborations', 'parcours_orchestres']],
    'playlist' => ['Playlist', ['musiques']],
    'telechargements' => ['Téléchargements', ['telechargements']],
    'contacts' => ['Contacts', ['contacts_groupes', 'contacts_personnes']],
    'medias' => ['Médias', ['medias_usages', 'medias']],
    'annuaire' => ['Annuaire : villes et pays', ['annuaire']],
];

/**
 * Tables dont les colonnes ne suivent pas la règle « champ_langue » : table => champ => [langue => colonne].
 * `textes` range son texte de référence dans une colonne au nom de la langue (« en » ; « fr » avant la migration 011).
 */
const COLONNES_PARTICULIERES = [
    'textes' => ['texte' => ['fr' => 'fr', 'en' => 'en']],
];

const STATUTS_TRADUCTION = ['validee' => 'Validée', 'proposee' => 'Proposée par l\'IA', 'a_revoir' => 'À revoir', 'manquante' => 'Manquante'];

// ---------- Les langues ----------

/** Toutes les langues connues, actives ou non, dans l'ordre : la langue de rédaction d'abord (l'anglais), puis les autres dans leur rang. */
function langues_toutes(): array
{
    static $l = null;
    if ($l === null || !empty($GLOBALS['langues_perimees'])) {
        $l = [];
        try {
            foreach (bd_lignes('SELECT * FROM langues ORDER BY reference DESC, ordre, code') as $x) $l[$x['code']] = $x;
        } catch (Throwable $e) {
            // base d'avant la migration des langues : on lit l'ancien réglage
        }
        if (!$l) {
            foreach (array_values(array_filter(array_map('trim', explode(',', reglage('langues') ?: 'fr')))) as $i => $code) {
                $l[$code] = ['code' => $code, 'nom' => strtoupper($code), 'nom_fr' => strtoupper($code), 'locale' => $code, 'active' => 1, 'reference' => $i === 0 ? 1 : 0, 'defaut' => $i === 0 ? 1 : 0, 'ordre' => $i];
            }
        }
        $GLOBALS['langues_perimees'] = false;
    }
    return $l;
}

/** Codes des langues du site, la langue de rédaction en premier : ['en', 'fr']. */
function langues(): array
{
    $l = [];
    foreach (langues_toutes() as $code => $x) if ($x['active'] || $x['reference']) $l[] = $code;
    return $l ?: ['en'];
}

/** La langue dans laquelle les contenus sont rédigés : l'anglais (le français sur une base d'avant la migration 011). */
function langue_reference(): string
{
    foreach (langues_toutes() as $code => $x) if ($x['reference']) return $code;
    return 'fr';
}

/**
 * La langue que le site affiche à la première visite, et celle de son HTML de repli : la langue de
 * rédaction, toujours (l'anglais est la première langue du site, et sa langue par défaut).
 */
function langue_defaut(): string
{
    return langue_reference();
}

/** Les langues actives autres que la langue de rédaction : celles dont les traductions sortent sur le site. */
function langues_autres(): array
{
    return array_values(array_diff(langues(), [langue_reference()]));
}

/** Toutes les langues de traduction, actives ou en préparation : celles de l'écran « Traductions ». */
function langues_a_traduire(): array
{
    return array_values(array_diff(array_keys(langues_toutes()), [langue_reference()]));
}

function langue_nom(string $code): string
{
    return (string) (langues_toutes()[$code]['nom_fr'] ?? strtoupper($code));
}

/** La table des traductions existe-t-elle ? Faux sur une base d'avant la migration 001 : tout s'écrit alors comme en version 1. */
function langues_installees(bool $relire = false): bool
{
    static $oui = null;
    if ($oui === null || $relire) $oui = bd_table_existe('traductions');
    return $oui;
}

// ---------- Où vit un champ ----------

/** Les colonnes d'une table, lues une fois. */
function bd_colonnes(string $table): array
{
    static $c = [];
    if (!empty($GLOBALS['colonnes_perimees'])) { $c = []; $GLOBALS['colonnes_perimees'] = false; }
    if (!isset($c[$table])) {
        $c[$table] = bd_colonne('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
    }
    return $c[$table];
}

/** Colonne qui porte un champ dans une langue : « titre_fr » en général. */
function colonne_langue(string $table, string $champ, string $langue): string
{
    return COLONNES_PARTICULIERES[$table][$champ][$langue] ?? ($champ . '_' . $langue);
}

/** Colonne du texte de référence d'un champ. */
function colonne_reference(string $table, string $champ): string
{
    return colonne_langue($table, $champ, langue_reference());
}

/** Clé primaire d'une table traduisible. */
function cle_primaire(string $table): string
{
    return TRADUISIBLES[$table][1] ?? 'id';
}

/**
 * Sépare, dans des données prêtes à écrire, ce qui va dans la table et ce qui est une traduction.
 * Rend [données de la table, [champ => [langue => texte]]].
 */
function traductions_separer(string $table, array $donnees): array
{
    if (!isset(TRADUISIBLES[$table]) || !$donnees || !langues_installees()) return [$donnees, []];
    $reference = langue_reference();
    $traductions = [];
    foreach (TRADUISIBLES[$table][3] as $champ => $_) {
        foreach (langues_toutes() as $code => $x) {
            if ($code === $reference) continue;
            $col = colonne_langue($table, $champ, $code);
            if (!array_key_exists($col, $donnees)) continue;
            $traductions[$champ][$code] = (string) ($donnees[$col] ?? '');
            unset($donnees[$col]);
        }
    }
    return [$donnees, $traductions];
}

// ---------- Lire les traductions ----------

/** Toutes les traductions d'une table, lues une fois : [clé de la ligne][champ][langue] => ligne de `traductions`. */
function traductions_de(string $objet): array
{
    static $cache = [];
    if (!empty($GLOBALS['traductions_perimees'])) { $cache = []; $GLOBALS['traductions_perimees'] = false; }
    if (!isset($cache[$objet])) {
        $cache[$objet] = [];
        try {
            foreach (bd_lignes('SELECT * FROM traductions WHERE objet = ?', [$objet]) as $t) $cache[$objet][$t['objet_id']][$t['champ']][$t['langue']] = $t;
        } catch (Throwable $e) {
            // base d'avant la migration des langues
        }
    }
    return $cache[$objet];
}

function traduction(string $objet, $id, string $champ, string $langue): ?array
{
    return traductions_de($objet)[(string) $id][$champ][$langue] ?? null;
}

/** Statut d'une traduction au regard du texte de référence d'aujourd'hui. */
function traduction_statut(?array $t, string $reference): string
{
    if (!$t || trim((string) $t['texte']) === '') return 'manquante';
    if ($t['statut'] === 'proposee') return 'proposee';
    return ($t['empreinte'] !== '' && $t['empreinte'] !== md5($reference)) ? 'a_revoir' : 'validee';
}

/** Texte qui sort sur le site pour un champ et une langue : la traduction validée, sinon ''. */
function traduction_publiee(string $objet, $id, string $champ, string $langue): string
{
    $t = traduction($objet, $id, $champ, $langue);
    return ($t && $t['statut'] === 'validee') ? trim((string) $t['texte']) : '';
}

/** Traductions qui sortent sur le site pour un champ : [langue => texte], les seules validées. */
function traductions_publiees(string $objet, $id, string $champ): array
{
    $v = [];
    foreach (langues_autres() as $langue) $v[$langue] = traduction_publiee($objet, $id, $champ, $langue);
    return $v;
}

/**
 * Les noms qu'un texte porte dans les autres langues (traductions validées), sans doublon : pour
 * reconnaître un orchestre ou une ville sous son nom français quand la fiche le donne en anglais.
 */
function traductions_noms(string $objet, $id, string $champ): array
{
    $noms = [];
    foreach (traductions_de($objet)[(string) $id][$champ] ?? [] as $t) {
        if ($t['statut'] === 'validee' && trim((string) $t['texte']) !== '') $noms[] = trim((string) $t['texte']);
    }
    return array_values(array_unique($noms));
}

/** Textes d'un champ pour le formulaire : [langue => texte], propositions de l'IA comprises. */
function traductions_champ(string $objet, $id, string $champ): array
{
    $v = [];
    foreach (langues_autres() as $langue) $v[$langue] = (string) (traduction($objet, $id, $champ, $langue)['texte'] ?? '');
    return $v;
}

/**
 * Champ d'une ligne, pour les fichiers du site : une chaîne si aucune traduction ne diffère du
 * texte de référence, sinon { fr: …, en: …, … }. $ligne est la ligne de la table.
 */
function multi(string $objet, array $ligne, string $champ)
{
    $reference = langue_reference();
    $texte = trim((string) ($ligne[colonne_reference($objet, $champ)] ?? ''));
    $id = $ligne[cle_primaire($objet)] ?? null;
    $v = [$reference => $texte];
    if ($id !== null) {
        foreach (langues_autres() as $langue) {
            $t = traduction_publiee($objet, $id, $champ, $langue);
            if ($t !== '' && $t !== $texte) $v[$langue] = $t;
        }
    }
    return count($v) === 1 ? $texte : $v;
}

/** Comme multi(), à partir d'un texte de référence et de ses traductions [langue => texte]. */
function multi_valeurs(string $texte, array $traductions)
{
    $texte = trim($texte);
    $v = [langue_reference() => $texte];
    foreach (langues_autres() as $langue) {
        $t = trim((string) ($traductions[$langue] ?? ''));
        if ($t !== '' && $t !== $texte) $v[$langue] = $t;
    }
    return count($v) === 1 ? $texte : $v;
}

/**
 * Les champs traduisibles d'une ligne dans la langue de rédaction, tels qu'un formulaire les envoie :
 * ['titre_en' => …, 'mention_en' => …]. Sert à comparer ce qui est en base à ce qui vient d'être saisi.
 */
function ligne_langues(string $objet, array $ligne, array $champs): array
{
    $v = [];
    foreach ($champs as $champ) $v[colonne_reference($objet, $champ)] = (string) ($ligne[colonne_reference($objet, $champ)] ?? '');
    return $v;
}

/**
 * Synchronise les lignes d'une liste rattachée à un objet (programme, participants d'un concert…)
 * sans les détruire : une ligne déjà là est modifiée sur place et garde ses traductions ; une ligne
 * nouvelle est créée ; une ligne absente est supprimée, avec ses traductions.
 *
 * $lignes : dans l'ordre voulu, chacune avec ses colonnes et, si elle existe déjà, son « id ». Une
 * ligne sans identifiant reprend celle qui porte exactement les mêmes textes de rédaction ($champs),
 * s'il en reste une : un programme relu dans un document, ou une ligne déplacée, garde ainsi ses
 * traductions. Rend [créées, modifiées, supprimées].
 */
function lignes_synchroniser(string $table, string $colonne_parent, int $parent_id, array $lignes, array $champs): array
{
    $existantes = [];
    foreach (bd_lignes('SELECT * FROM ' . bd_nom($table) . ' WHERE ' . bd_nom($colonne_parent) . ' = ? ORDER BY ordre, id', [$parent_id]) as $l) $existantes[(int) $l['id']] = $l;
    $empreinte = fn(array $l) => implode("\x1f", array_map(fn($c) => trim((string) ($l[colonne_reference($table, $c)] ?? '')), $champs));
    $libres = $existantes;
    // d'abord les lignes désignées par leur identifiant, puis celles que l'on reconnaît à leurs textes
    foreach ($lignes as $i => $l) {
        $id = (int) ($l['id'] ?? 0);
        if ($id && isset($libres[$id])) { $lignes[$i]['@id'] = $id; unset($libres[$id]); }
    }
    foreach ($lignes as $i => $l) {
        if (isset($l['@id'])) continue;
        foreach ($libres as $id => $x) {
            if ($empreinte($x) === $empreinte($l)) { $lignes[$i]['@id'] = $id; unset($libres[$id]); break; }
        }
    }
    $n = [0, 0, 0];
    foreach (array_values($lignes) as $ordre => $l) {
        $id = $l['@id'] ?? null;
        unset($l['@id'], $l['id']);
        $l['ordre'] = $ordre;
        if ($id) {
            [, $b] = differences(array_intersect_key($existantes[$id], $l), $l);
            if ($b) { bd_modifier($table, $b, ['id' => $id]); $n[1]++; }
        } else {
            bd_inserer($table, $l + [$colonne_parent => $parent_id]);
            $n[0]++;
        }
    }
    foreach (array_keys($libres) as $id) { bd_supprimer($table, ['id' => $id]); $n[2]++; }
    return $n;
}

// ---------- Écrire les traductions ----------

/**
 * Le texte que l'on traduit, pour un champ d'une ligne : son texte de rédaction. Une citation de
 * presse fait exception : on traduit la citation elle-même (sa formulation d'origine, dans sa
 * langue), pas sa traduction anglaise.
 */
function traduction_source(string $objet, array $ligne, string $champ): string
{
    if ($objet === 'articles_citations' && $champ === 'traduction') return trim((string) ($ligne['texte'] ?? ''));
    return trim((string) ($ligne[colonne_reference($objet, $champ)] ?? ''));
}

/** Texte de référence d'un champ (celui que l'on traduit), relu dans la table. */
function reference_lire(string $objet, string $id, string $champ): string
{
    if (!isset(TRADUISIBLES[$objet])) return '';
    $ligne = bd_ligne('SELECT * FROM ' . bd_nom($objet) . ' WHERE ' . bd_nom(cle_primaire($objet)) . ' = ?', [$id]);
    return $ligne ? traduction_source($objet, $ligne, $champ) : '';
}

/** Tient à jour une colonne de langue restée dans une table (bases d'avant la migration 011), quand elle existe : la copie de ce qui sort sur le site. */
function traduction_miroir(string $objet, string $id, string $champ, string $langue): void
{
    $col = colonne_langue($objet, $champ, $langue);
    if (!in_array($col, bd_colonnes($objet), true)) return;
    $t = bd_ligne('SELECT texte, statut FROM traductions WHERE objet = ? AND objet_id = ? AND champ = ? AND langue = ?', [$objet, $id, $champ, $langue]);
    $texte = ($t && $t['statut'] === 'validee') ? (string) $t['texte'] : '';
    bd_requete('UPDATE ' . bd_nom($objet) . ' SET ' . bd_nom($col) . ' = ? WHERE ' . bd_nom(cle_primaire($objet)) . ' = ?', [$texte, $id]);
}

/**
 * Pose une traduction. $origine : main (saisie par une personne) ou ia.
 *
 * Saisie par une personne : le texte est validé, sauf s'il est identique à une proposition de l'IA
 * encore en attente (la proposition reste alors à valider, d'un geste exprès). Venue de l'IA : la
 * proposition est notée « proposee » ; une traduction déjà validée n'est jamais remplacée, sauf si
 * $forcer. Un texte vide retire la traduction. Rend vrai si quelque chose a changé.
 */
function traduction_poser(string $objet, string $id, string $champ, string $langue, string $texte, string $origine = 'main', bool $forcer = false): bool
{
    if ($langue === langue_reference() || !isset(TRADUISIBLES[$objet][3][$champ])) return false;
    $texte = trim(str_replace("\r\n", "\n", $texte));
    $ou = [$objet, $id, $champ, $langue];
    $avant = bd_ligne('SELECT * FROM traductions WHERE objet = ? AND objet_id = ? AND champ = ? AND langue = ?', $ou);
    if ($texte === '') {
        if (!$avant || $origine === 'ia') return false;
        bd_requete('DELETE FROM traductions WHERE id = ?', [$avant['id']]);
    } elseif ($origine === 'ia') {
        if ($avant && $avant['statut'] === 'validee' && trim((string) $avant['texte']) !== '' && !$forcer) return false;
        if ($avant && $avant['statut'] === 'proposee' && $avant['texte'] === $texte) return false;
        $empreinte = md5(reference_lire($objet, $id, $champ));
        bd_requete('INSERT INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, ?, ?, \'proposee\', \'ia\', ?)
                    ON DUPLICATE KEY UPDATE texte = VALUES(texte), statut = VALUES(statut), origine = VALUES(origine), empreinte = VALUES(empreinte)',
            array_merge($ou, [$texte, $empreinte]));
    } else {
        if ($avant && $avant['texte'] === $texte) return false;        // rien de neuf : une proposition reste une proposition
        $empreinte = md5(reference_lire($objet, $id, $champ));
        bd_requete('INSERT INTO traductions (objet, objet_id, champ, langue, texte, statut, origine, empreinte) VALUES (?, ?, ?, ?, ?, \'validee\', \'main\', ?)
                    ON DUPLICATE KEY UPDATE texte = VALUES(texte), statut = VALUES(statut), origine = VALUES(origine), empreinte = VALUES(empreinte)',
            array_merge($ou, [$texte, $empreinte]));
    }
    $GLOBALS['traductions_perimees'] = true;
    $GLOBALS['donnees_modifiees'] = true;
    traduction_miroir($objet, $id, $champ, $langue);
    return true;
}

/** Pose d'un coup les traductions d'une ligne : [champ => [langue => texte]]. */
function traductions_poser(string $objet, string $id, array $par_champ, string $origine = 'main'): void
{
    foreach ($par_champ as $champ => $par_langue) {
        foreach ($par_langue as $langue => $texte) traduction_poser($objet, $id, $champ, $langue, (string) $texte, $origine);
    }
}

/**
 * Valide une traduction telle qu'elle est : une proposition de l'IA devient une traduction validée,
 * une traduction « à revoir » est confirmée au regard du texte de référence d'aujourd'hui.
 */
function traduction_valider(string $objet, string $id, string $champ, string $langue): bool
{
    $t = bd_ligne('SELECT * FROM traductions WHERE objet = ? AND objet_id = ? AND champ = ? AND langue = ?', [$objet, $id, $champ, $langue]);
    if (!$t || trim((string) $t['texte']) === '') return false;
    bd_requete("UPDATE traductions SET statut = 'validee', empreinte = ? WHERE id = ?", [md5(reference_lire($objet, $id, $champ)), $t['id']]);
    $GLOBALS['traductions_perimees'] = true;
    $GLOBALS['donnees_modifiees'] = true;
    traduction_miroir($objet, $id, $champ, $langue);
    return true;
}

/** Retire toutes les traductions d'une ligne supprimée. */
function traductions_retirer(string $objet, string $id): void
{
    bd_requete('DELETE FROM traductions WHERE objet = ? AND objet_id = ?', [$objet, $id]);
    $GLOBALS['traductions_perimees'] = true;
}

/**
 * Traite les cases « valider » des formulaires (champ tr_ok[] = « objet|clé|champ|langue ») :
 * appelé par index.php avant la page, pour tout envoi de formulaire.
 */
function traductions_valider_recues(): void
{
    $recues = $_POST['tr_ok'] ?? [];
    if (!is_array($recues)) return;
    foreach ($recues as $r) {
        $p = explode('|', (string) $r);
        if (count($p) !== 4 || !isset(TRADUISIBLES[$p[0]][3][$p[2]])) continue;
        // si le texte a été retouché dans le même envoi, l'enregistrement de la page le validera
        $nom = (string) ($_POST['tr_nom'][$r] ?? '');
        $t = traduction($p[0], $p[1], $p[2], $p[3]);
        if ($t && traduction_valider($p[0], $p[1], $p[2], $p[3])) {
            journaliser('validation', 'traduction', null, (TRADUISIBLES[$p[0]][0] ?? $p[0]) . ' · ' . (TRADUISIBLES[$p[0]][3][$p[2]] ?? $p[2]) . ' · ' . strtoupper($p[3]) . ($nom !== '' ? ' · ' . $nom : ''), null, ['texte' => couper((string) $t['texte'], 200)]);
        }
    }
}

// ---------- Vue d'ensemble : ce qui manque, ce qui attend ----------

/** Section de l'écran « Traductions » où se range une table ; '' si elle n'y paraît pas. */
function traduction_section(string $table): string
{
    foreach (SECTIONS_TRADUCTION as $cle => [, $tables]) if (in_array($table, $tables, true)) return $cle;
    return '';
}

/** Ancre de la ligne d'un texte sur l'écran « Traductions » : t-concerts-12-titre. */
function traduction_ancre(string $objet, $id, string $champ): string
{
    return 't-' . trim(preg_replace('/[^a-z0-9]+/i', '-', $objet . '-' . $id . '-' . $champ), '-');
}

/** Adresse de la ligne d'un texte sur l'écran « Traductions », dans une langue. */
function traduction_adresse(string $objet, $id, string $champ, string $langue = ''): string
{
    return url('traductions', array_filter(['langue' => $langue, 'cible' => $objet . '|' . $id . '|' . $champ], 'strlen')) . '#' . traduction_ancre($objet, $id, $champ);
}

/** Noms des familles d'intitulés qui ne tiennent à aucun bloc de l'accueil (pages Concerts et Presse, pages légales…). */
const FAMILLES_LIBELLES = ['meta' => 'Titre du site', 'nav' => 'Menu', 'ouverture' => 'Hero', 'lecteur' => 'Lecteur de musique', 'agenda' => 'Agenda', 'concerts' => 'Page des concerts',
    'disques' => 'Discographie', 'parcours' => 'Parcours', 'commun' => 'Communs', 'presse' => 'Page Presse', 'tel' => 'Téléchargements', 'suivre' => 'Restons en contact',
    'lettre' => 'Lettre', 'contact' => 'Contact', 'pied' => 'Pied de page', 'page' => 'Pages secondaires', 'legal' => 'Pages légales', 'yt' => 'Vidéos YouTube', 'video' => 'Lecteur vidéo', 'panneau' => 'Panneaux'];

/**
 * Où se situe un texte : le groupe sous lequel il se range sur l'écran « Traductions » (son bloc, son
 * concert, son article, son album…), de quoi trier ce groupe, et l'écran où se modifie son texte
 * anglais. Rend ['groupe' => …, 'tri' => …, 'lien' => …].
 */
function traductions_contexte(string $table, array $ligne): array
{
    static $c = null;
    if ($c === null || !empty($GLOBALS['contexte_perime'])) {
        $GLOBALS['contexte_perime'] = false;
        $c = ['concerts' => [], 'articles' => [], 'disques' => [], 'groupes' => [], 'textes' => [], 'libelles' => [], 'blocs' => []];
        foreach (bd_lignes('SELECT c.id, c.titre_en, (SELECT MIN(r.date) FROM representations r WHERE r.concert_id = c.id) AS premiere FROM concerts c') as $x) {
            $c['concerts'][(int) $x['id']] = [trim(date_fr($x['premiere']) . ' · ' . $x['titre_en'], ' ·'), ($x['premiere'] ?: '9999') . sprintf('-%06d', $x['id'])];
        }
        foreach (bd_lignes('SELECT p.id, p.titre_en, p.cle, p.ordre, m.nom AS media FROM articles p LEFT JOIN annuaire m ON m.id = p.media_id') as $x) {
            $c['articles'][(int) $x['id']] = [trim(($x['media'] ?? '') . ' · ' . couper(preg_replace('/\*/', '', (string) $x['titre_en']) ?: $x['cle'], 70), ' ·'), sprintf('%06d-%06d', 500000 + $x['ordre'], $x['id'])];
        }
        foreach (bd_lignes('SELECT id, titre, ordre FROM disques') as $x) $c['disques'][(int) $x['id']] = [preg_replace('/\*/', '', (string) $x['titre']), sprintf('%06d-%06d', 500000 + $x['ordre'], $x['id'])];
        foreach (bd_lignes('SELECT id, structure, mention_en, ordre FROM contacts_groupes') as $x) $c['groupes'][(int) $x['id']] = [$x['structure'] !== '' ? $x['structure'] : ($x['mention_en'] ?: 'Groupe ' . $x['id']), sprintf('%06d', 500000 + $x['ordre'])];
        $rang = 0;
        foreach (blocs_description() as $cle => $d) {
            $nom = blocs_tous()[$cle]['nom'] ?? ($d['nom'] ?? $cle);
            $c['blocs'][$cle] = $nom;
            foreach ($d['textes'] ?? [] as $t) $c['textes'][$t] ??= [$cle, $nom, $rang];
            foreach ($d['libelles'] ?? [] as $l) $c['libelles'][$l] ??= [$cle, $nom, $rang];
            $rang++;
        }
    }
    $id = (int) ($ligne['id'] ?? 0);
    $ordre = sprintf('%06d-%06d', 500000 + (int) ($ligne['ordre'] ?? 0), $id);
    $du_concert = function (int $concert_id, string $suite) use (&$c): array {
        [$nom, $tri] = $c['concerts'][$concert_id] ?? ['Concert supprimé', '9999'];
        return ['groupe' => $nom, 'tri' => $tri . $suite, 'lien' => url('concert', ['id' => $concert_id])];
    };
    switch ($table) {
        case 'textes':
        case 'libelles':
            $cle = (string) $ligne['cle'];
            if (isset($c[$table][$cle])) {
                [$bloc, $nom, $rang] = $c[$table][$cle];
                return ['groupe' => $nom, 'tri' => sprintf('%03d-%d-%06d', $rang, $table === 'textes' ? 0 : 1, (int) ($ligne['ordre'] ?? 0)), 'lien' => url('bloc', ['cle' => $bloc])];
            }
            $famille = (string) ($ligne['groupe'] ?? explode('.', $cle)[0]);
            return ['groupe' => 'Autres intitulés · ' . (FAMILLES_LIBELLES[$famille] ?? ucfirst($famille)), 'tri' => '900-' . $famille . sprintf('-%06d', (int) ($ligne['ordre'] ?? 0)), 'lien' => url('langues', ['onglet' => 'libelles']) . '#lib-' . preg_replace('/[^a-z0-9]+/i', '-', $cle)];
        case 'concerts': return $du_concert($id, '-0');
        case 'representations': return $du_concert((int) $ligne['concert_id'], '-1-' . ($ligne['date'] ?? ''));
        case 'programme': return $du_concert((int) $ligne['concert_id'], '-2-' . $ordre);
        case 'participants': return $du_concert((int) $ligne['concert_id'], '-3-' . $ordre);
        case 'maisons': return ['groupe' => 'Maisons de l\'agenda', 'tri' => 'zzzz-' . $ordre, 'lien' => url('reglages') . '#maisons'];
        case 'articles':
            [$nom, $tri] = $c['articles'][$id] ?? ['Article', '9'];
            return ['groupe' => $nom, 'tri' => $tri . '-0', 'lien' => url('article', ['id' => $id])];
        case 'articles_citations':
            [$nom, $tri] = $c['articles'][(int) $ligne['article_id']] ?? ['Article supprimé', '9'];
            return ['groupe' => $nom, 'tri' => $tri . '-1-' . $ordre, 'lien' => url('article', ['id' => (int) $ligne['article_id']])];
        case 'disques':
            [$nom, $tri] = $c['disques'][$id] ?? ['Album', '9'];
            return ['groupe' => $nom, 'tri' => $tri . '-0', 'lien' => url('disque', ['id' => $id])];
        case 'distinctions':
            if (!empty($ligne['disque_id']) && isset($c['disques'][(int) $ligne['disque_id']])) {
                [$nom, $tri] = $c['disques'][(int) $ligne['disque_id']];
                return ['groupe' => $nom, 'tri' => $tri . '-1-' . $ordre, 'lien' => url('disque', ['id' => (int) $ligne['disque_id']]) . '#distinctions'];
            }
            return ['groupe' => 'Distinctions sans album', 'tri' => 'zzzz-' . $ordre, 'lien' => url('distinctions')];
        case 'fonctions': return ['groupe' => 'Fonctions', 'tri' => '1-' . $ordre, 'lien' => url('parcours') . '#fonctions'];
        case 'collaborations': return ['groupe' => 'Grands débuts et collaborations', 'tri' => '2-' . $ordre, 'lien' => url('parcours') . '#collaborations'];
        case 'parcours_orchestres': return ['groupe' => 'Carte du monde : villes', 'tri' => '3-' . $ordre, 'lien' => url('carte', ['orchestre' => (int) ($ligne['fiche_id'] ?? 0)]) . '#placer'];
        case 'musiques': return ['groupe' => preg_replace('/\*/', '', (string) ($ligne['titre_en'] ?? '')) ?: 'Titre ' . $id, 'tri' => $ordre, 'lien' => url('musiques')];
        case 'telechargements': return ['groupe' => (string) ($ligne['libelle_en'] ?? '') ?: 'Ressource ' . $id, 'tri' => $ordre, 'lien' => url('contenu', ['type' => 'telechargements'])];
        case 'contacts_groupes':
            [$nom, $tri] = $c['groupes'][$id] ?? ['Groupe ' . $id, '9'];
            return ['groupe' => $nom, 'tri' => $tri . '-0', 'lien' => url('contenu', ['type' => 'contacts'])];
        case 'contacts_personnes':
            [$nom, $tri] = $c['groupes'][(int) $ligne['groupe_id']] ?? ['Contacts', '9'];
            return ['groupe' => $nom, 'tri' => $tri . '-1-' . $ordre, 'lien' => url('contenu', ['type' => 'contacts'])];
        case 'medias_usages':
            $objet = (string) ($ligne['objet'] ?? '');
            $cle = (string) ($ligne['objet_id'] ?? '');
            if ($objet === 'blocs') return ['groupe' => 'Bloc « ' . ($c['blocs'][$cle] ?? $cle) . ' »', 'tri' => '1-' . $cle . '-' . $ordre, 'lien' => url('visuels') . '#e-' . $id];
            if ($objet === 'disques') return ['groupe' => 'Album « ' . ($c['disques'][(int) $cle][0] ?? $cle) . ' »', 'tri' => '2-' . $cle . '-' . $ordre, 'lien' => url('disque', ['id' => (int) $cle]) . '#visuels'];
            if ($objet === 'articles') return ['groupe' => 'Article « ' . ($c['articles'][(int) $cle][0] ?? $cle) . ' »', 'tri' => '3-' . $cle . '-' . $ordre, 'lien' => url('article', ['id' => (int) $cle]) . '#visuels'];
            return ['groupe' => 'Médias employés', 'tri' => '4-' . $ordre, 'lien' => url('media', ['id' => (int) ($ligne['media_id'] ?? 0)])];
        case 'medias': return ['groupe' => 'Médiathèque : textes alternatifs', 'tri' => '9-' . sprintf('%06d', $id), 'lien' => url('media', ['id' => $id])];
        case 'annuaire':
            $categorie = CATEGORIES[$ligne['categorie'] ?? ''] ?? 'Annuaire';
            return ['groupe' => $categorie . ' · ' . ($ligne['nom'] ?? ''), 'tri' => $categorie . '-' . normaliser((string) ($ligne['nom'] ?? '')), 'lien' => url('fiche', ['id' => $id])];
    }
    return ['groupe' => TRADUISIBLES[$table][0] ?? $table, 'tri' => $ordre, 'lien' => ''];
}

/**
 * Tous les champs traduisibles qui portent un texte à traduire, avec l'état de leur traduction dans
 * une langue. $objets : une table, plusieurs, ou toutes. Rend des lignes
 * [objet, id, champ, libelle, nom, reference, texte, statut, origine], et, avec $contexte,
 * [section, groupe, tri, lien, precision] : de quoi les ranger et les situer sur l'écran « Traductions ».
 *
 * La traduction d'une citation de presse se fait depuis la citation elle-même ; elle n'a pas lieu
 * d'être dans la langue de la citation.
 */
function traductions_inventaire(string $langue, string|array|null $objets = null, bool $contexte = false): array
{
    $objets = $objets === null ? null : (array) $objets;
    $l = [];
    foreach (TRADUISIBLES as $table => [$nom, $cle, $col_nom, $champs]) {
        if ($objets !== null && !in_array($table, $objets, true)) continue;
        if (!bd_table_existe($table)) continue;
        $section = traduction_section($table);
        $lignes = bd_lignes('SELECT * FROM ' . bd_nom($table) . ' ORDER BY ' . bd_nom($cle));
        foreach ($lignes as $ligne) {
            if ($table === 'articles_citations' && (string) $ligne['langue'] === $langue) continue;
            foreach ($champs as $champ => $libelle) {
                $reference = traduction_source($table, $ligne, $champ);
                if ($reference === '') continue;
                $t = traduction($table, $ligne[$cle], $champ, $langue);
                $x = [
                    'objet' => $table, 'id' => (string) $ligne[$cle], 'champ' => $champ, 'libelle' => $libelle,
                    'nom' => (string) ($ligne[$col_nom] ?? $ligne[$cle]), 'reference' => $reference,
                    'texte' => (string) ($t['texte'] ?? ''), 'statut' => traduction_statut($t, $reference),
                    'origine' => (string) ($t['origine'] ?? ''),
                ];
                if ($contexte) {
                    $x += ['section' => $section, 'precision' => ''] + traductions_contexte($table, $ligne);
                    // ce qui distingue la ligne dans son groupe : la date d'une représentation, la clé d'un intitulé, l'œuvre, le nom…
                    if ($table === 'representations') $x['precision'] = date_fr($ligne['date']);
                    elseif ($table === 'libelles') $x['precision'] = (string) $ligne['cle'];
                    elseif ($table === 'textes') $x['libelle'] = TEXTES[$ligne['cle']][0] ?? $libelle;
                    elseif ($table === 'participants') $x['precision'] = (string) $ligne['nom'];
                    elseif ($table === 'contacts_personnes') $x['precision'] = (string) $ligne['nom'];
                    elseif ($table === 'articles_citations') $x['precision'] = strtoupper((string) $ligne['langue']) . (trim((string) ($ligne[colonne_reference($table, 'traduction')] ?? '')) !== '' ? ' · traduction anglaise sur la fiche de l\'article' : '');
                }
                $l[] = $x;
            }
        }
    }
    if ($contexte) {
        $rangs = array_flip(array_keys(SECTIONS_TRADUCTION));
        $tables = array_flip(array_keys(TRADUISIBLES));
        usort($l, fn($a, $b) => [$rangs[$a['section']] ?? 99, $a['tri'], $tables[$a['objet']]] <=> [$rangs[$b['section']] ?? 99, $b['tri'], $tables[$b['objet']]]);
    }
    return $l;
}

/** État des traductions d'un texte, langue par langue : [langue => statut], pour le lien d'un champ de formulaire. */
function traductions_etat(string $objet, $id, string $champ, string $reference): array
{
    $e = [];
    foreach (langues_a_traduire() as $langue) $e[$langue] = traduction_statut(traduction($objet, $id, $champ, $langue), $reference);
    return $e;
}

/** Décompte par statut d'un inventaire : ['validee' => n, 'proposee' => n, 'a_revoir' => n, 'manquante' => n]. */
function traductions_decompte(array $inventaire): array
{
    $n = ['validee' => 0, 'proposee' => 0, 'a_revoir' => 0, 'manquante' => 0];
    foreach ($inventaire as $x) $n[$x['statut']]++;
    return $n;
}

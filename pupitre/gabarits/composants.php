<?php
/**
 * Composants d'interface : champs de formulaire (dont le champ traduisible, saisi en anglais), étiquettes, listes ordonnables.
 * Tout ce qui sort passe par e().
 */

defined('PUPITRE') or exit;

/** Nom d'un champ, éventuellement dans une ligne de liste : nom_champ('lignes[3]', 'titre') → lignes[3][titre]. */
function nom_champ(string $prefixe, string $nom): string
{
    return $prefixe === '' ? $nom : $prefixe . '[' . $nom . ']';
}

function id_champ(string $nom): string
{
    // le jeton « __i__ » du modèle d'une ligne ajoutée est gardé : le script le remplace par le rang de la ligne, l'identifiant reste unique
    $parties = array_map(fn($p) => trim(preg_replace('/[^a-z0-9]+/i', '-', $p), '-'), explode('__i__', $nom));
    return 'c-' . implode('-__i__-', array_filter($parties, 'strlen') ?: ['']);
}

/**
 * Un identifiant qui ne sert qu'une fois dans la page : quand deux formulaires portent un champ du
 * même nom, le second reçoit un suffixe. Sans cela, le libellé du second désignerait le champ du premier.
 */
function id_unique(string $id): string
{
    static $vus = [];
    $vus[$id] = ($vus[$id] ?? 0) + 1;
    return $vus[$id] === 1 ? $id : $id . '-' . $vus[$id];
}

/**
 * Champ sur une ligne. Options : type (text, url, email, date, number), aide, requis, liste
 * (identifiant d'une liste de choix), max, indication (texte en filigrane), prefixe, classe.
 */
function champ(string $nom, string $libelle, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    $s = '<div class="champ ' . e($o['classe'] ?? '') . '"><label for="' . $id . '">' . e($libelle) . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '') . '</label>';
    $s .= '<input type="' . e($o['type'] ?? 'text') . '" id="' . $id . '" name="' . e($n) . '" value="' . e($valeur) . '"'
        . (!empty($o['requis']) ? ' required' : '')
        . (!empty($o['liste']) ? ' list="' . e($o['liste']) . '"' : '')
        . (!empty($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '')
        . (!empty($o['indication']) ? ' placeholder="' . e($o['indication']) . '"' : '')
        . (!empty($o['aide']) ? ' aria-describedby="' . $id . '-aide"' : '') . '>';
    if (!empty($o['aide'])) $s .= '<p class="aide" id="' . $id . '-aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

function champ_texte(string $nom, string $libelle, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    $lignes = $o['lignes'] ?? max(2, min(12, substr_count((string) $valeur, "\n") + 2));
    $s = '<div class="champ ' . e($o['classe'] ?? '') . '"><label for="' . $id . '">' . e($libelle) . '</label>';
    $s .= '<textarea id="' . $id . '" name="' . e($n) . '" rows="' . (int) $lignes . '"' . (!empty($o['aide']) ? ' aria-describedby="' . $id . '-aide"' : '') . '>' . e($valeur) . '</textarea>';
    if (!empty($o['aide'])) $s .= '<p class="aide" id="' . $id . '-aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

/** Ce que dit l'état d'une traduction, en deux mots : pour les liens d'un champ et l'écran « Traductions ». */
const ETATS_TRADUCTION = ['validee' => 'validée', 'proposee' => 'à valider', 'a_revoir' => 'à revoir', 'manquante' => 'à traduire'];

/**
 * Liens d'un texte vers sa ligne sur l'écran « Traductions » : un par langue de traduction, avec
 * l'état de la traduction. $objet : [table, clé de la ligne, champ]. Rien si la ligne n'existe pas
 * encore, si le texte est vide, ou s'il n'y a pas d'autre langue que celle de rédaction.
 */
function liens_traduction(?array $objet, string $reference, array $sauf = []): string
{
    if (!$objet || $objet[1] === null || $objet[1] === '' || trim($reference) === '' || !isset(TRADUISIBLES[$objet[0]][3][$objet[2]])) return '';
    // la valeur affichée peut être une saisie en cours : l'état se juge sur le texte enregistré
    $etats = array_diff_key(traductions_etat($objet[0], $objet[1], $objet[2], reference_lire($objet[0], (string) $objet[1], $objet[2])), array_flip($sauf));
    if (!$etats) return '';
    $s = '<span class="tr-liens">';
    foreach ($etats as $langue => $etat) {
        $nom = mb_strtolower(langue_nom($langue));
        $s .= '<a class="tr-lien tr-lien--' . e($etat) . '" href="' . e(traduction_adresse($objet[0], $objet[1], $objet[2], $langue)) . '" title="Traduction en ' . e($nom) . ' : ' . e(ETATS_TRADUCTION[$etat]) . '. Ouvrir sa ligne sur l\'écran « Traductions ».">'
            . '<span lang="' . e($langue) . '">' . e(strtoupper($langue)) . '</span> <span class="tr-lien__etat">' . e(ETATS_TRADUCTION[$etat]) . '</span><span class="sr"> : traduction en ' . e($nom) . '</span></a>';
    }
    return $s . '</span>';
}

/**
 * Champ traduisible : il se saisit dans la langue de rédaction (l'anglais), et dans elle seule. Le
 * champ envoyé s'appelle nom_en ; bd_inserer() et bd_modifier() le rangent dans sa colonne. Ses
 * traductions se saisissent sur l'écran « Traductions » : un lien par langue y mène, à la ligne de
 * ce texte, et dit où en est la traduction.
 *
 * Options : texte (zone de plusieurs lignes), lignes, requis, liste, max, aide, prefixe, classe ;
 *   objet    [table, clé de la ligne, champ] : la ligne dont on montre les traductions ; le champ vaut
 *            $nom s'il n'est pas donné. Sans clé (fiche pas encore créée), aucun lien ;
 *   source   le texte que l'on traduit, s'il n'est pas celui du champ (une citation de presse se
 *            traduit depuis sa formulation d'origine) ; sauf : langues sans objet (celle de la citation).
 */
function champ_tr(string $nom, string $libelle, $valeur, array $o = []): string
{
    $reference = langue_reference();
    $objet = $o['objet'] ?? null;
    if ($objet) $objet[2] = $objet[2] ?? $nom;
    $n = nom_champ($o['prefixe'] ?? '', $nom . '_' . $reference);
    $id = id_unique(id_champ($n));
    $valeur = (string) ($valeur ?? '');
    $s = '<div class="champ champ--tr ' . e($o['classe'] ?? '') . '"><div class="champ__tete"><label for="' . $id . '">' . e($libelle)
        . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '')
        . ' <abbr class="langue" title="Rédigé en ' . e(mb_strtolower(langue_nom($reference))) . '">' . e(strtoupper($reference)) . '</abbr></label>'
        . liens_traduction($objet, (string) ($o['source'] ?? $valeur), (array) ($o['sauf'] ?? [])) . '</div>';
    $decrit = !empty($o['aide']) ? ' aria-describedby="' . $id . '-aide"' : '';
    if (!empty($o['texte'])) {
        $lignes = $o['lignes'] ?? max(2, min(14, substr_count($valeur, "\n") + 2 + (int) (mb_strlen($valeur) / 90)));
        $s .= '<textarea id="' . $id . '" name="' . e($n) . '" rows="' . (int) $lignes . '" lang="' . e($reference) . '"' . $decrit . '>' . e($valeur) . '</textarea>';
    } else {
        $s .= '<input type="text" id="' . $id . '" name="' . e($n) . '" value="' . e($valeur) . '" lang="' . e($reference) . '"'
            . (!empty($o['requis']) ? ' required' : '')
            . (!empty($o['liste']) ? ' list="' . e($o['liste']) . '"' : '')
            . (!empty($o['max']) ? ' maxlength="' . (int) $o['max'] . '"' : '') . $decrit . '>';
    }
    if (!empty($o['aide'])) $s .= '<p class="aide" id="' . $id . '-aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

/** Le champ traduisible « nom » lu dans un envoi de formulaire : [colonne de la langue de rédaction => texte], coupé à $max. */
function recu_tr(array $recu, string $nom, int $max = 500): array
{
    $colonne = $nom . '_' . langue_reference();
    return [$colonne => mb_substr(trim(str_replace("\r\n", "\n", (string) ($recu[$colonne] ?? ''))), 0, $max)];
}

/**
 * Champ relié à l'annuaire : on choisit une fiche existante (la liste propose leurs noms), ou l'on saisit un nom
 * nouveau, dont la fiche sera créée à l'enregistrement et complétée plus tard. Le champ dit lequel des deux, au fil
 * de la saisie. $liste : l'identifiant de la liste de choix (liste_choix()). Options : fiche (identifiant de la fiche
 * déjà reliée : un lien y mène), nouveau (ce que dit le champ pour un nom nouveau), aide, requis, max, prefixe.
 */
function champ_annuaire(string $nom, string $libelle, $valeur, string $liste, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    $aide = trim(($o['aide'] ?? '') . ' Choisissez dans la liste, ou saisissez un nom nouveau.');
    $s = '<div class="champ champ--annuaire ' . e($o['classe'] ?? '') . '" data-annuaire>'
        . '<div class="champ__tete"><label for="' . $id . '">' . e($libelle) . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '') . '</label>'
        . '<span class="champ__etat" data-annuaire-etat role="status"></span></div>'
        . '<input type="text" id="' . $id . '" name="' . e($n) . '" value="' . e($valeur) . '" list="' . e($liste) . '" autocomplete="off" maxlength="' . (int) ($o['max'] ?? 190) . '"'
        . ' data-annuaire-connu="dans l\'annuaire" data-annuaire-nouveau="' . e($o['nouveau'] ?? 'nouveau : sa fiche sera créée dans l\'annuaire') . '"'
        . (!empty($o['requis']) ? ' required' : '') . ' aria-describedby="' . $id . '-aide">'
        . '<p class="aide" id="' . $id . '-aide">' . e($aide)
        . (!empty($o['fiche']) ? ' <a href="' . e(url('fiche', ['id' => (int) $o['fiche']])) . '">Ouvrir sa fiche</a>' : '') . '</p>';
    return $s . '</div>';
}

/** Liste de choix. $options : valeur => libellé. Option vide : libellé du choix « aucun ». */
function champ_choix(string $nom, string $libelle, array $options, $valeur, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    $s = '<div class="champ ' . e($o['classe'] ?? '') . '"><label for="' . $id . '">' . e($libelle) . (!empty($o['requis']) ? ' <span class="requis" title="obligatoire">*</span>' : '') . '</label>';
    $s .= '<select id="' . $id . '" name="' . e($n) . '">';
    if (array_key_exists('vide', $o)) $s .= '<option value="">' . e($o['vide']) . '</option>';
    foreach ($options as $v => $l) {
        $s .= '<option value="' . e($v) . '"' . ((string) $v === (string) $valeur ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    $s .= '</select>';
    if (!empty($o['aide'])) $s .= '<p class="aide">' . e($o['aide']) . '</p>';
    return $s . '</div>';
}

function champ_case(string $nom, string $libelle, bool $coche, array $o = []): string
{
    $n = nom_champ($o['prefixe'] ?? '', $nom);
    $id = id_unique(id_champ($n));
    return '<div class="champ champ--case"><input type="checkbox" id="' . $id . '" name="' . e($n) . '" value="1"' . ($coche ? ' checked' : '') . '> <label for="' . $id . '">' . e($libelle) . '</label>'
        . (!empty($o['aide']) ? '<p class="aide">' . e($o['aide']) . '</p>' : '') . '</div>';
}

/** Liste de valeurs proposées à la saisie (annuaire…). */
function liste_choix(string $id, array $valeurs): string
{
    $s = '<datalist id="' . e($id) . '">';
    foreach ($valeurs as $v) $s .= '<option value="' . e($v) . '">';
    return $s . '</datalist>';
}

function etiquette_statut(string $statut): string
{
    return '<span class="statut statut--' . e($statut) . '" title="' . e(STATUTS_SENS[$statut] ?? '') . '">' . e(STATUTS[$statut] ?? $statut) . '</span>';
}

/** Boutons d'une ligne de liste ordonnable : monter, descendre, retirer. Utilisables au clavier. */
function boutons_ligne(string $quoi = 'cette ligne'): string
{
    return '<span class="ligne__outils">'
        . '<button type="button" class="outil" data-monter aria-label="Monter ' . e($quoi) . '" title="Monter">' . icone('monter') . '</button>'
        . '<button type="button" class="outil" data-descendre aria-label="Descendre ' . e($quoi) . '" title="Descendre">' . icone('descendre') . '</button>'
        . '<button type="button" class="outil outil--retirer" data-retirer aria-label="Retirer ' . e($quoi) . '" title="Retirer">' . icone('fermer') . '</button>'
        . '</span>';
}

/** Suivi d'une tâche de fond : barre d'avancement tenue à jour par ressources/js/pupitre.js. */
function suivi_tache(array $t): string
{
    $fini = in_array($t['statut'], ['terminee', 'echec'], true);
    return '<div class="tache tache--' . e($t['statut']) . '" data-tache="' . (int) $t['id'] . '"' . ($fini ? ' data-finie' : '') . ' role="status" aria-live="polite">'
        . '<div class="tache__barre"><span style="width:' . (int) $t['avancement'] . '%"></span></div>'
        . '<p class="tache__message">' . e($t['message']) . '</p></div>';
}

/** Bloc d'aperçu : le HTML que le site affichera, dans chaque langue. $par_langue : fr => lignes, en => lignes… */
function apercu(array $par_langue, string $titre = 'Aperçu'): string
{
    $s = '<section class="apercu" aria-label="' . e($titre) . '"><h2>' . e($titre) . '</h2><div class="apercu__langues">';
    $site = site_adresse();
    foreach ($par_langue as $langue => $lignes) {
        // les images du site sont lues sur le site ; un média pas encore publié n'y est pas
        $html = preg_replace('/\ssrc="(?!https?:|\/|data:)/', ' src="' . e($site), implode("\n", $lignes));
        $s .= '<div class="apercu__langue" lang="' . e($langue) . '"><p class="apercu__code">' . strtoupper(e($langue)) . '</p><div class="apercu__site">' . $html . '</div></div>';
    }
    return $s . '</div><p class="aide">L\'aperçu montre le contenu, pas la mise en page du site.</p></section>';
}

/** Lignes du journal, lisibles : qui, quoi, quand, avant, après. */
function journal_afficher(array $lignes, bool $avec_objet = false): string
{
    if (!$lignes) return '<p class="rien">Rien pour l\'instant.</p>';
    $actions = ['creation' => 'Création', 'modification' => 'Modification', 'suppression' => 'Suppression', 'statut' => 'Statut',
        'publication' => 'Publication', 'retour' => 'Retour en arrière', 'validation' => 'Validation', 'note' => 'Note'];
    $s = '<ul class="journal">';
    foreach ($lignes as $l) {
        $avant = json_decode((string) $l['avant'], true);
        $apres = json_decode((string) $l['apres'], true);
        $s .= '<li><strong>' . e($actions[$l['action']] ?? $l['action']) . '</strong>'
            . ($avec_objet ? ' · ' . e($l['objet_type']) : '') . ' · ' . e($l['objet_libelle'])
            . '<br><span class="journal__qui">' . e($l['compte_nom'] ?: '—') . ', ' . e(horodatage_fr($l['cree_le'])) . '</span>';
        $cles = array_unique(array_merge(array_keys(is_array($avant) ? $avant : []), array_keys(is_array($apres) ? $apres : [])));
        if ($cles && $l['action'] !== 'creation') {
            $s .= '<div class="journal__diff">';
            foreach ($cles as $k) {
                $s .= '<div><span class="discret">' . e($k) . ' :</span> ';
                if (is_array($avant) && array_key_exists($k, $avant)) $s .= '<span class="avant">' . e(couper(journal_valeur($avant[$k]), 240)) . '</span> ';
                if (is_array($apres) && array_key_exists($k, $apres)) $s .= '<span class="apres">' . e(couper(journal_valeur($apres[$k]), 240)) . '</span>';
                $s .= '</div>';
            }
            $s .= '</div>';
        }
        $s .= '</li>';
    }
    return $s . '</ul>';
}

/**
 * Formulaire d'une liste ordonnable décrite dans inclus/contenus.php : une ligne par élément, ses
 * champs, les boutons d'ordre, un modèle pour les lignes ajoutées.
 */
function liste_formulaire(array $liste, array $lignes, string $nom = 'lignes', bool $saisie = false): string
{
    $rendre = function (array $l, string $i) use ($liste, $nom, $saisie): string {
        $prefixe = $nom . '[' . $i . ']';
        $s = '<div class="ligne" data-ligne><div class="ligne__tete"><span class="ligne__rang">nº <span data-rang></span></span>' . boutons_ligne() . '</div>';
        $s .= '<input type="hidden" name="' . e($prefixe) . '[id]" value="' . e($l['id'] ?? '') . '">';
        $rang = '';
        foreach ($liste['champs'] as $champ) {
            [$c, $type, $libelle] = $champ;
            $o = ['prefixe' => $prefixe, 'aide' => $champ['aide'] ?? '', 'requis' => !empty($champ['requis']), 'max' => $champ['max'] ?? 0];
            // champ traduisible : la ligne dont on montre l'état des traductions
            $langues = ['objet' => [$liste['table'], $l['id'] ?? null, $c]];
            $ref = '_' . langue_reference();
            // les champs qui portent le même « rang » se suivent sur une ligne
            $nouveau = $champ['rang'] ?? '';
            if ($nouveau !== $rang) {
                if ($rang !== '') $s .= '</div>';
                if ($nouveau !== '') $s .= '<div class="colonnes">';
                $rang = $nouveau;
            }
            switch ($type) {
                case 'bi': $s .= champ_tr($c, $libelle, $l[$c . $ref] ?? '', $o + $langues); break;
                case 'bi-texte': $s .= champ_tr($c, $libelle, $l[$c . $ref] ?? '', $o + $langues + ['texte' => true]); break;
                case 'texte': $s .= champ_texte($c, $libelle, $l[$c] ?? '', $o); break;
                case 'case':
                    // ligne nouvelle : la valeur par défaut de la case ; ligne saisie ou lue : ce qu'elle dit
                    $s .= champ_case($c, $libelle, ($saisie || array_key_exists($c, $l)) ? !empty($l[$c]) : !empty($champ['defaut']), $o);
                    break;
                case 'fiche': case 'media': case 'choix':
                    $choix = isset($champ['vide']) ? $o + ['vide' => $champ['vide']]
                        : (isset($champ['defaut']) ? $o : $o + ['vide' => $type === 'media' ? '— aucun fichier —' : '— choisir —']);
                    $s .= champ_choix($c, $libelle, champ_options($champ), $l[$c] ?? ($champ['defaut'] ?? ''), $choix);
                    break;
                case 'nombre': $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['classe' => 'champ--court']); break;
                case 'decimal':
                    $valeur = (string) ($l[$c] ?? '');
                    if (str_contains($valeur, '.')) $valeur = rtrim(rtrim($valeur, '0'), '.');      // 52.37000 → 52.37
                    $s .= champ($c, $libelle, $valeur, $o + ['classe' => 'champ--court']);
                    break;
                case 'adresse': $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['type' => 'url', 'indication' => 'https://…']); break;
                case 'email': $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['type' => 'email']); break;
                default:
                    $liste_id = '';
                    if (!empty($champ['suggestions'])) { $liste_id = 'sug-' . id_champ($nom . $c); }
                    $s .= champ($c, $libelle, $l[$c] ?? '', $o + ['liste' => $liste_id]);
            }
        }
        if ($rang !== '') $s .= '</div>';
        return $s . '</div>';
    };
    $s = '<div data-liste data-suivant="0"><div class="lignes" data-lignes>';
    foreach (array_values($lignes) as $i => $l) $s .= $rendre($l, (string) $i);
    $s .= '</div><template data-modele>' . $rendre([], '__i__') . '</template>';
    foreach ($liste['champs'] as $champ) {
        if (!empty($champ['suggestions'])) $s .= liste_choix('sug-' . id_champ($nom . $champ[0]), $champ['suggestions']);
    }
    $s .= '<p><button type="button" class="bouton bouton--petit" data-ajouter>' . e($liste['ajout'] ?? 'Ajouter une ligne') . '</button></p></div>';
    return $s;
}

/** Aperçu des zones du site nommées, dans chaque langue des contenus. $zones : repère => intertitre. */
function apercu_zones(array $zones, ?array $exports = null): string
{
    $exports = $exports ?? site_exports();
    $par_langue = [];
    foreach (langues() as $langue) {
        $toutes = repli_zones($exports, $langue);
        $lignes = [];
        foreach ($zones as $nom => $intertitre) {
            if ($intertitre !== '') $lignes[] = '<p class="mention">' . e($intertitre) . '</p>';
            $corps = $toutes[$nom] ?? [];
            $liste = $corps && str_starts_with(ltrim($corps[0]), '<li');
            $lignes[] = $liste ? '<ul>' . implode('', $corps) . '</ul>' : implode("\n", $corps);
            if (!$corps) $lignes[] = '<p class="avenir">vide</p>';
        }
        $par_langue[$langue] = $lignes;
    }
    return apercu($par_langue);
}

// ---------- Composants du design system (montrés dans « Bibliothèque d'interface ») ----------

const MOIS_COURTS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

/**
 * État vide : un pictogramme, un titre, une phrase, et de quoi agir. À employer quand une liste n'a
 * rien à montrer : dire pourquoi, et quoi faire. $action : du HTML déjà échappé (un lien, un bouton).
 */
function etat_vide(string $icone, string $titre, string $texte = '', string $action = ''): string
{
    return '<div class="vide"><div class="vide__icone">' . icone($icone) . '</div><p class="vide__titre">' . e($titre) . '</p>'
        . ($texte !== '' ? '<p>' . e($texte) . '</p>' : '') . ($action !== '' ? '<p>' . $action . '</p>' : '') . '</div>';
}

/** Pastille de date : le jour et le mois abrégé. $classe : pastille-date--jour (aujourd'hui), pastille-date--alerte. */
function pastille_date(string $iso, string $classe = ''): string
{
    $t = strtotime(substr($iso, 0, 10) . ' 12:00');
    if (!$t) return '';
    return '<time class="pastille-date' . ($classe !== '' ? ' ' . e($classe) : '') . '" datetime="' . e(substr($iso, 0, 10)) . '"><strong>' . (int) date('j', $t) . '</strong><span>' . e(MOIS_COURTS[(int) date('n', $t) - 1]) . '</span></time>';
}

/**
 * Indicateur : un libellé, une valeur, une précision. Options : lien (la tuile devient un lien),
 * icone, classe (indicateur--alerte, indicateur--ok). La valeur est un fait lu dans la base, jamais une estimation.
 */
function indicateur(string $libelle, $valeur, string $precision = '', array $o = []): string
{
    $balise = !empty($o['lien']) ? 'a' : 'div';
    return '<' . $balise . ' class="indicateur' . (!empty($o['classe']) ? ' ' . e($o['classe']) : '') . '"' . (!empty($o['lien']) ? ' href="' . e($o['lien']) . '"' : '') . '>'
        . '<span class="indicateur__libelle">' . (!empty($o['icone']) ? icone($o['icone']) : '') . e($libelle) . '</span>'
        . '<span class="indicateur__valeur">' . e((string) $valeur) . '</span>'
        . ($precision !== '' ? '<span class="indicateur__precision">' . e($precision) . '</span>' : '') . '</' . $balise . '>';
}

/**
 * Tuile : un pictogramme dans sa bulle, un titre, un détail, et un chiffre facultatif. Options : lien,
 * chiffre, classe (tuile--vigilance, tuile--actions, tuile--calme, tuile--plein).
 */
function tuile(string $icone, string $titre, string $detail, array $o = []): string
{
    $balise = !empty($o['lien']) ? 'a' : 'div';
    return '<' . $balise . ' class="tuile' . (!empty($o['classe']) ? ' ' . e($o['classe']) : '') . '"' . (!empty($o['lien']) ? ' href="' . e($o['lien']) . '"' : '') . '>'
        . '<span class="tuile__bulle">' . icone($icone) . '</span>'
        . '<span class="tuile__textes"><span class="tuile__titre">' . e($titre) . '</span><span class="tuile__detail">' . e($detail) . '</span></span>'
        . (isset($o['chiffre']) ? '<span class="tuile__chiffre">' . e((string) $o['chiffre']) . '</span>' : '') . '</' . $balise . '>';
}

/** Le canal d'une action de communication : son pictogramme dans une bulle, son nom. */
function canal_afficher(string $canal, bool $nom = true): string
{
    [$libelle, $icone] = CANAUX[$canal] ?? ['Canal inconnu', 'social'];
    return '<span class="canal"><span class="canal__bulle">' . icone($icone) . '</span>' . ($nom ? e($libelle) : '<span class="sr">' . e($libelle) . '</span>') . '</span>';
}

/** Une notification, telle que le centre de notifications et son écran la montrent. $actions : du HTML déjà échappé. */
function notification_afficher(array $n, string $actions = ''): string
{
    $niveaux = ['prioritaire' => 'prioritaire', 'action' => 'à traiter', 'info' => 'information'];
    return '<div class="notif notif--' . e($n['niveau']) . '"><span class="notif__icone">' . icone($n['icone']) . '</span>'
        . '<span class="notif__type">' . e($n['type']) . ' <span class="niveau niveau--' . e($n['niveau']) . '">' . e($niveaux[$n['niveau']] ?? '') . '</span></span>'
        . '<span class="notif__message">' . e($n['message']) . '</span>'
        . ($n['quand'] ? '<span class="notif__quand">' . e(il_y_a($n['quand'])) . '</span>' : '')
        . ($actions !== '' ? '<div class="notif__actions">' . $actions . '</div>' : '') . '</div>';
}

/* ---------- Marque : le logotype et les icônes de Pupitre (ressources/marque/, recopiés de la charte) ---------- */

/** Proportions des fichiers vectoriels (largeur / hauteur), pour réserver la place avant leur chargement. */
const MARQUE_PROPORTIONS = ['logo' => 2.889, 'symbole' => 1.497];

/**
 * Le logotype, en image : il ne se recompose jamais en texte. $variante : principal (fond clair), inverse (fond
 * marine), marine (fond mandarine ou abricot), blanc, noir. $hauteur en pixels (la charte : 34 dans le menu, jamais
 * moins de 24 ; en dessous de 70 px de large, le symbole).
 */
function marque_logo(string $variante = 'principal', int $hauteur = 34, string $classe = 'marque__logo'): string
{
    $variante = in_array($variante, ['principal', 'inverse', 'marine', 'blanc', 'noir'], true) ? $variante : 'principal';
    return '<img class="' . e($classe) . '" src="' . e(chemin_pupitre()) . '/ressources/marque/logo/pupitre-logo-' . $variante . '.svg" alt="Pupitre" width="'
        . (int) round($hauteur * MARQUE_PROPORTIONS['logo']) . '" height="' . $hauteur . '">';
}

/** Le symbole seul (le point d'orgue). $variante : mandarine, marine, cuivre, blanc. Décoratif : le nom est dit ailleurs. */
function marque_symbole(string $variante = 'mandarine', int $hauteur = 26, string $classe = 'marque__symbole'): string
{
    $variante = in_array($variante, ['mandarine', 'marine', 'cuivre', 'blanc'], true) ? $variante : 'mandarine';
    return '<img class="' . e($classe) . '" src="' . e(chemin_pupitre()) . '/ressources/marque/symbole/pupitre-symbole-' . $variante . '.svg" alt="" width="'
        . (int) round($hauteur * MARQUE_PROPORTIONS['symbole']) . '" height="' . $hauteur . '">';
}

/**
 * Ce que la marque demande au <head> de chaque page : icônes, manifeste, couleur du navigateur, polices préchargées
 * et la feuille de style. Les deux polices du premier écran sont préchargées ; les autres graisses arrivent à la demande.
 */
function marque_tete(): string
{
    $base = e(chemin_pupitre());
    $v = e(VERSION_PUPITRE) . '-' . (int) @filemtime(RACINE . '/ressources/css/pupitre.css');
    return '<meta name="theme-color" content="#12263F">' . "\n"
        . '  <link rel="icon" href="' . $base . '/ressources/marque/icones/favicon.ico" sizes="32x32">' . "\n"
        . '  <link rel="icon" href="' . $base . '/ressources/marque/icones/favicon.svg" type="image/svg+xml">' . "\n"
        . '  <link rel="apple-touch-icon" href="' . $base . '/ressources/marque/icones/apple-touch-icon.png">' . "\n"
        . '  <link rel="manifest" href="' . $base . '/ressources/marque/icones/site.webmanifest">' . "\n"
        . '  <link rel="preload" href="' . $base . '/ressources/polices/manrope-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>' . "\n"
        . '  <link rel="preload" href="' . $base . '/ressources/polices/manrope-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>' . "\n"
        . '  <link rel="preload" href="' . $base . '/ressources/polices/outfit-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>' . "\n"
        . '  <link rel="stylesheet" href="' . $base . '/ressources/css/pupitre.css?v=' . $v . '">';
}

/* ---------- Pages hors connexion : procès-verbal d'un aperçu, zone de dépôt ---------- */

/**
 * Début d'une page publique de Pupitre (ouverte par un lien personnel, sans connexion) : le logotype, une carte.
 * $langue : la langue de la page (fr, en). Ces pages ne sont jamais indexées.
 */
function page_publique_tete(string $titre, bool $large = true, string $langue = 'fr'): string
{
    $sous_titre = reglage_sur('sous_titre') ?: reglage_sur('artiste_nom');
    $base = e(chemin_pupitre());
    return '<!doctype html>' . "\n" . '<html lang="' . e($langue) . '">' . "\n<head>\n"
        . '  <meta charset="utf-8">' . "\n" . '  <meta name="viewport" content="width=device-width, initial-scale=1">' . "\n" . '  <meta name="robots" content="noindex, nofollow">' . "\n"
        . '  <title>' . e($titre) . ' · Pupitre</title>' . "\n  " . marque_tete() . "\n"
        . '  <script src="' . $base . '/ressources/js/public.js?v=' . e(VERSION_PUPITRE) . '-' . (int) @filemtime(RACINE . '/ressources/js/public.js') . '" defer></script>' . "\n</head>\n"
        . '<body class="porte">' . "\n" . '<main class="porte__carte' . ($large ? ' porte__carte--large' : '') . '">' . "\n"
        . '  <p class="marque marque--grande">' . marque_logo('principal', 44) . ($sous_titre !== '' ? '<span class="marque__sous-titre">' . e($sous_titre) . '</span>' : '') . "</p>\n";
}

function page_publique_pied(string $mention = ''): string
{
    return ($mention !== '' ? '  <p class="porte__pied">' . e($mention) . "</p>\n" : '') . "</main>\n</body>\n</html>\n";
}

/** Une liste de définitions sobre : [libellé => valeur déjà échappée]. Les valeurs vides sont passées. */
function fiche_definitions(array $lignes): string
{
    $s = '';
    foreach ($lignes as $libelle => $valeur) {
        if ($valeur === '' || $valeur === null) continue;
        $s .= '<div class="definitions__ligne"><dt>' . e($libelle) . '</dt><dd>' . $valeur . '</dd></div>';
    }
    return $s === '' ? '' : '<dl class="definitions">' . $s . '</dl>';
}

/** Ce qu'un aperçu présente, tel que son résumé le garde : rubriques, pages, contenus modifiés, dates annoncées. */
function apercu_resume_afficher(array $a): string
{
    $r = $a['resume'] ?? [];
    $s = '';
    if (trim((string) ($a['note'] ?? '')) !== '') $s .= '<p>' . nl2br(e(trim((string) $a['note']))) . '</p>';
    if (!empty($r['rubriques'])) $s .= '<p><span class="libelle">Rubriques</span><br>' . e(implode(' · ', $r['rubriques'])) . '</p>';
    if (!empty($r['contenus'])) {
        $actions = ['creation' => 'Ajout', 'modification' => 'Modification', 'suppression' => 'Suppression', 'statut' => 'Changement de statut'];
        $s .= '<p class="libelle">Contenus modifiés depuis la dernière publication</p><ul class="items items--serre">';
        foreach ($r['contenus'] as $c) $s .= '<li><span>' . e(($actions[$c['action']] ?? $c['action']) . ' · ' . $c['libelle']) . '</span></li>';
        $s .= '</ul>';
    }
    if (!empty($r['representations'])) $s .= '<p><span class="libelle">Dates qui paraîtront</span><br>' . e(implode(' ; ', $r['representations'])) . '</p>';
    if (!empty($r['medias'])) $s .= '<p class="discret">' . e(pluriel((int) $r['medias'], 'média nouveau', 'médias nouveaux')) . '.</p>';
    if ($s === '') $s = '<p class="rien">Aucune différence avec le site publié au moment de l\'aperçu.</p>';
    return $s;
}

/** Le procès-verbal d'une validation : qui, quoi, quand, d'où. $a : l'aperçu (apercu_lire), $v : sa validation. */
function proces_verbal_afficher(array $a, array $v): string
{
    $valide = $v['decision'] === 'validee';
    return '<div class="pv"><p class="libelle">Procès-verbal de ' . ($valide ? 'validation' : 'demande de corrections') . '</p>'
        . fiche_definitions([
            'Site' => e(rtrim(site_adresse(), '/')),
            'Aperçu' => '« ' . e($a['titre']) . ' », établi le ' . e(horodatage_fr($a['cree_le'])) . ' par ' . e($a['cree_par_nom']),
            'Empreinte de l\'aperçu' => '<code class="insecable">' . e(substr($a['empreinte'], 0, 32)) . '…</code><br><span class="discret">SHA-256 de tout ce qui serait publié : elle désigne cet état du site, à l\'octet près.</span>',
            'Décision' => '<span class="puce ' . DECISIONS_APERCU[$v['decision']][1] . '">' . e(DECISIONS_APERCU[$v['decision']][0]) . '</span>',
            'Par' => e($v['nom']) . ' · ' . e($v['email']),
            'Le' => e(horodatage_fr($v['decide_le'])),
            'Déclaration' => $valide ? e($v['declaration']) : '',
            'Remarques' => trim((string) $v['remarques']) !== '' ? nl2br(e($v['remarques'])) : '',
            'Lien personnel' => 'envoyé le ' . e(horodatage_fr($v['envoye_le'])) . ' à ' . e($v['email']) . ($v['vu_le'] ? ', ouvert le ' . e(horodatage_fr($v['vu_le'])) : ''),
            'Adresse IP' => e($v['ip']),
            'Navigateur' => e($v['agent']),
        ])
        . '<p class="discret">Acceptation donnée en ligne par un lien personnel envoyé à l\'adresse ci-dessus (signature électronique simple) ; Pupitre en garde la trace dans son journal.</p></div>';
}

<?php
/**
 * Préremplir la fiche d'un article à partir de son adresse, ou de son texte collé.
 *
 * Inclus par pages/presse/article.php pour un nouvel article. La lecture est une tâche de fond
 * (inclus/ia_services.php : page récupérée par Pupitre, métadonnées lues sans IA, puis analyse par
 * l'IA) ; son résultat revient ici sous la forme de $propose, que le formulaire de l'article
 * affiche. Rien n'entre dans la revue de presse tant que ce formulaire n'est pas enregistré.
 *
 * Sans IA configurée, la page est tout de même lue : titre, média, auteur, date, langue, adresse
 * canonique sont préremplis ; le reste se saisit à la main. Si la page ne se laisse pas lire
 * (accès réservé, page vide), l'utilisateur colle le texte : aucun mur de paiement n'est contourné.
 */

defined('PUPITRE') or exit;

$GLOBALS['import_article'] = ['tache' => null, 'notes' => [], 'erreur' => '', 'adresse' => '', 'texte' => '', 'doublons' => [], 'lue' => false];
$import = &$GLOBALS['import_article'];
$suite = array_filter(['concert' => recu('concert'), 'disque' => recu('disque')], 'strlen');       // l'article créé depuis un concert ou un album le reste

if (est_post() && recu('action') === 'lire') {
    $import['adresse'] = recu('adresse');
    $import['texte'] = recu('texte_colle');
    if ($import['adresse'] === '' && $import['texte'] === '') {
        $import['erreur'] = 'Donnez l\'adresse de l\'article, ou collez son texte.';
    } elseif ($import['adresse'] !== '' && !preg_match('#^https?://[^\s<>"]+$#i', $import['adresse'])) {
        $import['erreur'] = 'L\'adresse doit être complète : https://…';
    } elseif (mb_strlen($import['texte']) > 200000) {
        $import['erreur'] = 'Ce texte est trop long pour être un article : collez seulement l\'article.';
    } else {
        $tache = tache_creer('article_lecture', ['url' => $import['adresse'], 'texte' => $import['texte']]);
        journaliser('creation', 'article', null, 'Lecture d\'un article demandée', null, ['adresse' => $import['adresse'], 'texte_colle' => $import['texte'] !== '']);
        if (!taches_lancer_en_fond()) message('alerte', 'La lecture attend le prochain passage des tâches de fond (cron) : ce poste ne permet pas de la lancer d\'ici.');
        rediriger(url('article', ['id' => 'nouveau', 'lecture' => $tache] + $suite) . '#lire');
    }
}

$lecture = recu_entier('lecture');
if ($lecture) {
    $t = tache_lire($lecture);
    $moi = compte();
    if ($t && $t['type'] === 'article_lecture' && ((int) $t['cree_par'] === (int) ($moi['id'] ?? 0) || peut('reglages'))) {
        $charge = json_decode((string) $t['charge'], true) ?: [];
        $import['adresse'] = (string) ($charge['url'] ?? '');
        $import['texte'] = (string) ($charge['texte'] ?? '');
        if (in_array($t['statut'], ['en_attente', 'en_cours'], true)) {
            $import['tache'] = $t;
        } else {
            $r = article_lecture_resultat($lecture);
            if (!$r) {
                $import['erreur'] = $t['statut'] === 'echec' ? $t['message'] : 'Le résultat de cette lecture n\'est plus disponible : relancez-la.';
            } else {
                $import['notes'] = array_values(array_filter((array) ($r['notes'] ?? []), 'is_string'));
                if (!empty($r['ok'])) $import['lue'] = true; else $import['erreur'] = (string) ($r['erreur'] ?? 'La lecture n\'a pas abouti.');
                // même après un échec, ce que la page a livré (titre, média, date…) préremplit le formulaire
                if (!empty($r['ok']) || !empty($r['propose']['titre_en'])) {
                    $propose = (array) $r['propose'];
                    $propose['concerts'] = array_values(array_unique(array_merge($propose['concerts'] ?? [], array_filter([recu_entier('concert')]))));
                    $propose['disques'] = array_values(array_unique(array_merge($propose['disques'] ?? [], array_filter([recu_entier('disque')]))));
                    $import['doublons'] = article_doublons($propose);
                }
            }
        }
    }
}
unset($import);

/** La carte « Préremplir à partir de l'article » : adresse, texte collé, suivi de la lecture, remarques. */
function import_article_formulaire(?array $propose): string
{
    $i = $GLOBALS['import_article'];
    $s = '<div class="carte" id="lire"><h2>Préremplir à partir de l\'article</h2>';
    if ($i['tache']) {
        $s .= suivi_tache($i['tache']) . '<p class="aide">La page se rechargera d\'elle-même quand la lecture sera finie. Vous pouvez aussi saisir la fiche à la main, plus bas.</p>';
    }
    if ($i['erreur'] !== '') $s .= '<p class="message message--erreur" role="alert">' . e($i['erreur']) . '</p>';
    if ($i['lue']) $s .= '<p class="message message--ok">Article lu. Le formulaire ci-dessous est prérempli : rien n\'est enregistré tant que vous ne l\'avez pas relu et créé.</p>';
    if ($i['doublons']) {
        $s .= '<p class="message message--alerte">Cet article semble déjà dans la collection : ';
        foreach (array_slice($i['doublons'], 0, 3) as $k => [$b, $raison]) {
            $s .= ($k ? ' ; ' : '') . '<a href="' . e(url('article', ['id' => $b['id']])) . '">' . e(($b['media'] ?? '') . ' « ' . preg_replace('/\*/', '', (string) $b['titre_en']) . ' »') . '</a> (' . e($raison) . ')';
        }
        $s .= '.</p>';
    }
    if ($i['notes']) {
        $s .= '<ul class="remarques">';
        foreach ($i['notes'] as $n) $s .= '<li>' . e($n) . '</li>';
        $s .= '</ul>';
    }
    $suite = array_filter(['concert' => recu('concert'), 'disque' => recu('disque')], 'strlen');
    $s .= '<form method="post" action="' . e(url('article', ['id' => 'nouveau'] + $suite)) . '">' . champ_csrf() . '<input type="hidden" name="action" value="lire">'
        . champ('adresse', 'Adresse de l\'article', $i['adresse'], ['type' => 'url', 'indication' => 'https://…', 'aide' => 'Pupitre lit la page comme un lecteur sans abonnement, relève ce qu\'elle déclare (titre, média, auteur, date, langue), puis '
            . (ia_configuree() ? 'demande à l\'IA un résumé, des citations recopiées mot pour mot et les concerts ou albums concernés.' : 's\'arrête là : l\'IA n\'est pas configurée (Réglages).')])
        . '<details class="repli"' . ($i['texte'] !== '' || $i['erreur'] !== '' ? ' open' : '') . '><summary>La page ne se laisse pas lire ? Collez le texte de l\'article</summary>'
        . champ_texte('texte_colle', 'Texte de l\'article', $i['texte'], ['lignes' => 8, 'aide' => 'Si vous y avez accès : Pupitre ne contourne aucun accès réservé. Le texte collé sert à l\'analyse et au contrôle des citations ; il n\'est jamais publié.'])
        . '</details>'
        . '<div class="actions"><button type="submit" class="bouton">' . ($propose || $i['erreur'] !== '' ? 'Relire l\'article' : 'Lire l\'article') . '</button></div>'
        . '</form></div>';
    return $s;
}

<?php
/**
 * Comptes d'essai pour tests/parcours.py : un pilote, un éditeur, un artiste, à des adresses en
 * « .test » qui ne reçoivent rien. Ils sont créés pour la durée d'un essai, puis retirés.
 *
 *     ESSAI_MDP=… php pupitre/tests/comptes-essai.php creer     (le mot de passe vient de l'environnement)
 *     php pupitre/tests/comptes-essai.php lien                   lien de connexion du compte d'essai « artiste »
 *     php pupitre/tests/comptes-essai.php ia                     « oui » si la clé de l'API est renseignée
 *     php pupitre/tests/comptes-essai.php retirer                retire les comptes et ce qu'ils ont déposé
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../inclus/amorce.php';

const COMPTES_ESSAI = ['pilote' => 'pilote@essai.pupitre.test', 'editeur' => 'editeur@essai.pupitre.test', 'artiste' => 'artiste@essai.pupitre.test'];

$action = $argv[1] ?? '';
if ($action === 'creer') {
    $mdp = (string) getenv('ESSAI_MDP');
    if (strlen($mdp) < 12) { fwrite(STDERR, "ESSAI_MDP : douze caractères au moins.\n"); exit(1); }
    foreach (COMPTES_ESSAI as $role => $email) {
        $donnees = ['nom' => 'Essai ' . $role, 'role' => $role, 'actif' => 1, 'mot_de_passe' => $role === 'artiste' ? null : password_hash($mdp, PASSWORD_DEFAULT)];
        $id = bd_valeur('SELECT id FROM comptes WHERE email = ?', [$email]);
        if ($id) bd_modifier('comptes', $donnees, ['id' => $id]); else bd_inserer('comptes', $donnees + ['email' => $email]);
    }
    bd_requete("DELETE FROM tentatives_connexion WHERE email LIKE '%@essai.pupitre.test'");
    echo "comptes d'essai prêts\n";
} elseif ($action === 'lien') {
    $id = (int) bd_valeur('SELECT id FROM comptes WHERE email = ?', [COMPTES_ESSAI['artiste']]);
    if (!$id) { fwrite(STDERR, "Le compte d'essai n'existe pas.\n"); exit(1); }
    echo lien_creer($id, 'connexion') . "\n";
} elseif ($action === 'ia') {
    echo ia_configuree() ? "oui\n" : "non\n";
} elseif ($action === 'retirer') {
    foreach (COMPTES_ESSAI as $email) {
        $id = (int) bd_valeur('SELECT id FROM comptes WHERE email = ?', [$email]);
        if (!$id) continue;
        // les extraits déposés pendant l'essai, leurs lignes, leurs tâches et leurs fichiers
        foreach (bd_lignes('SELECT * FROM imports WHERE cree_par = ?', [$id]) as $i) {
            @unlink(STOCKAGE . '/imports/' . basename($i['fichier']));
            bd_supprimer('import_lignes', ['import_id' => $i['id']]);
            bd_supprimer('imports', ['id' => $i['id']]);
        }
        bd_supprimer('taches', ['cree_par' => $id]);
        bd_supprimer('notes_artiste', ['compte_id' => $id]);
        bd_supprimer('liens_connexion', ['compte_id' => $id]);
        bd_supprimer('comptes', ['id' => $id]);
    }
    bd_requete("DELETE FROM tentatives_connexion WHERE email LIKE '%@essai.pupitre.test'");
    echo "comptes d'essai retirés\n";
} else {
    fwrite(STDERR, "Usage : comptes-essai.php creer | lien | ia | retirer\n");
    exit(1);
}

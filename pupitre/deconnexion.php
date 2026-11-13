<?php
/** Déconnexion : un envoi de formulaire, avec son jeton. */

require __DIR__ . '/inclus/amorce.php';

if (est_post() && compte()) {
    verifier_csrf();
    fermer_session();
}
rediriger(chemin_pupitre() . '/connexion.php');

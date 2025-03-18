<?php
require_once 'api/config.php';
require_once 'api/auth.php';

// Déconnexion de l'utilisateur
$auth = new Auth();
$auth->logout();

// Redirection vers la page de connexion
header('Location: ' . app_url('index.php'));
exit; 
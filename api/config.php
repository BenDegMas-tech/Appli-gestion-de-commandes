<?php
// Vérification de la version PHP
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    die('PHP 8.2 ou supérieur est requis. Version actuelle : ' . PHP_VERSION);
}

// Configuration des sessions uniquement si aucune session n'est active
if (session_status() === PHP_SESSION_NONE) {
    // Configuration des sessions avant le démarrage
    ini_set('session.cookie_lifetime', 3600); // 1 heure
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_secure', true);
    ini_set('session.cookie_httponly', true);
    
    // Démarrage de la session
    session_start();
}

// Configuration de la base de données
define('DB_HOST', 'localhost');  // Utilisation de localhost au lieu de chambre27-serveur.com pour une connexion locale
define('DB_NAME', 'gestion');
define('DB_USER', 'root');
define('DB_PASS', ';D52UNzg24+K@:m');
define('DB_PORT', '3306');
define('DB_SOCKET', '/var/run/mysqld/mysqld.sock'); // Emplacement standard du socket MariaDB

// Configuration du mode debug
define('DEBUG_MODE', true); // Mode debug activé par défaut
define('DEBUG_LOG_FILE', __DIR__ . '/../logs/debug.log');

// Création du répertoire de logs si nécessaire
if (!is_dir(dirname(DEBUG_LOG_FILE))) {
    mkdir(dirname(DEBUG_LOG_FILE), 0777, true);
}

// Afficher les erreurs en mode debug
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', DEBUG_LOG_FILE);
}

// Configuration de l'application
define('APP_NAME', 'Gestion des Commandes');
define('APP_URL', 'https://chambre27-serveur.com/gestion_commandes');
define('APP_VERSION', '1.0.0');

// Configuration des sessions
define('SESSION_LIFETIME', 3600); // 1 heure
define('SESSION_PATH', '/');
define('SESSION_SECURE', true); // Mettre à true si vous utilisez HTTPS
define('SESSION_HTTPONLY', true);

// Clé secrète pour les sessions et le hachage
define('APP_SECRET', bin2hex(random_bytes(32)));

// Configuration des uploads
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB

// Messages d'erreur
define('ERROR_DB_CONNECTION', 'Erreur de connexion à la base de données');
define('ERROR_INVALID_CREDENTIALS', 'Identifiants invalides');
define('ERROR_UPLOAD_SIZE', 'Le fichier est trop volumineux');
define('ERROR_UPLOAD_TYPE', 'Type de fichier non autorisé');
define('ERROR_PERMISSION', 'Vous n\'avez pas les permissions nécessaires');
define('ERROR_INVALID_TOKEN', 'Token CSRF invalide');

// Types MIME autorisés pour les uploads
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf'
]);

// Configuration de sécurité
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// Configuration des rapports
define('REPORTS_DIR', __DIR__ . '/../reports/');
define('REPORTS_FORMATS', ['pdf', 'csv', 'html']);

// Configuration des emails
// Configuration SendGrid
define('EMAIL_FROM', 'contact@chambre27.com');
define('EMAIL_FROM_NAME', 'Chambre 27 - Gestion des Commandes');
define('SENDGRID_API_KEY', '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');

// Création des répertoires nécessaires s'ils n'existent pas
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if (!is_dir(REPORTS_DIR)) {
    mkdir(REPORTS_DIR, 0755, true);
}

// Fonction de debug
function debug($message, $context = []) {
    if (!DEBUG_MODE) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_PRETTY_PRINT) : '';
    $logMessage = "[$timestamp] $message$contextStr\n";
    
    // Log dans le fichier
    if (file_put_contents(DEBUG_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX) === false) {
        error_log("Erreur d'écriture dans le fichier de debug: " . DEBUG_LOG_FILE);
    }
    
    // Affichage à l'écran si on est admin
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        echo "<div class='debug-message'><pre>$logMessage</pre></div>";
    }
}

// Fonction utilitaire pour générer des URLs complètes
function app_url($path = '') {
    $url = rtrim(APP_URL, '/');
    if (!empty($path)) {
        $path = '/' . ltrim($path, '/');
        $url .= $path;
    }
    return $url;
} 
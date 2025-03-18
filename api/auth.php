<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

/**
 * Classe de gestion de l'authentification
 */
class Auth {
    private $db;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = new Database();
        
        // Démarrer la session si elle n'est pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Vérifie si l'utilisateur est connecté
     * 
     * @return bool
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Vérifie si l'utilisateur est un administrateur
     * 
     * @return bool
     */
    public function isAdmin() {
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Authentifie un utilisateur
     * 
     * @param string $email Email de l'utilisateur
     * @param string $password Mot de passe de l'utilisateur
     * @param bool $remember Se souvenir de l'utilisateur (non implémenté)
     * @return bool|string True si l'authentification réussit, message d'erreur sinon
     */
    public function login($email, $password, $remember = false) {
        debug("Tentative de connexion", ['email' => $email]);

        try {
            $query = "SELECT * FROM users WHERE email = :email";
            debug("Requête SQL", ['query' => $query]);

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            debug("Résultat de la requête", [
                'user_found' => !empty($user),
                'user_data' => $user ? array_diff_key($user, ['password' => '']) : null
            ]);

            if ($user) {
                debug("Vérification du mot de passe", [
                    'password_length' => strlen($password),
                    'hash_length' => strlen($user['password']),
                    'hash_info' => password_get_info($user['password'])
                ]);
                
                if (password_verify($password, $user['password'])) {
                    debug("Mot de passe valide, création de la session");
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_nom'] = $user['nom'];
                    $_SESSION['user_prenom'] = $user['prenom'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_logged_in'] = true;
                    
                    // Mise à jour de la dernière connexion
                    $updateQuery = "UPDATE users SET derniere_connexion = NOW() WHERE id = :id";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->bindParam(':id', $user['id']);
                    $updateStmt->execute();
                    
                    debug("Session créée avec succès", [
                        'user_id' => $_SESSION['user_id'],
                        'user_role' => $_SESSION['user_role']
                    ]);
                    
                    return true;
                } else {
                    debug("Mot de passe invalide", [
                        'password_verify_result' => false,
                        'provided_password_length' => strlen($password)
                    ]);
                }
            } else {
                debug("Utilisateur non trouvé avec cet email");
            }
            
            return false;
        } catch (PDOException $e) {
            debug("Erreur lors de la connexion", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Déconnecte l'utilisateur
     * 
     * @return void
     */
    public function logout() {
        // Détruire toutes les variables de session
        $_SESSION = [];
        
        // Détruire le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Détruire la session
        session_destroy();
    }
    
    /**
     * Récupère les informations de l'utilisateur connecté
     * 
     * @return array|null
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT id, nom, prenom, email, role, date_creation, derniere_connexion FROM users WHERE id = :id",
            ['id' => $_SESSION['user_id']]
        );
    }
    
    /**
     * Récupère l'ID de l'utilisateur connecté
     * 
     * @return int|null
     */
    public function getCurrentUserId() {
        return $this->isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Vérifie si le mot de passe respecte les critères de sécurité
     * 
     * @param string $password Mot de passe à vérifier
     * @return bool
     */
    public function isPasswordValid($password) {
        // Vérifier la longueur minimale
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return false;
        }
        
        // Vérifier la présence d'au moins une lettre majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Vérifier la présence d'au moins une lettre minuscule
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // Vérifier la présence d'au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Génère un token CSRF
     * 
     * @return string
     */
    public function generateCsrfToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Vérifie si le token CSRF est valide
     * 
     * @param string $token Token CSRF à vérifier
     * @return bool
     */
    public function validateCsrfToken($token) {
        if (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Crée un nouvel utilisateur
     * 
     * @param array $userData Données de l'utilisateur
     * @return int|false ID de l'utilisateur créé ou false en cas d'échec
     */
    public function createUser($userData) {
        // Vérifier si l'email existe déjà
        $existingUser = $this->db->fetchOne(
            "SELECT id FROM users WHERE email = :email",
            ['email' => $userData['email']]
        );
        
        if ($existingUser) {
            return false;
        }
        
        // Vérifier si le mot de passe est valide
        if (!$this->isPasswordValid($userData['password'])) {
            return false;
        }
        
        // Hacher le mot de passe
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Ajouter la date de création
        $userData['date_creation'] = date('Y-m-d H:i:s');
        
        // Insérer l'utilisateur dans la base de données
        return $this->db->insert('users', $userData);
    }
    
    /**
     * Met à jour le mot de passe d'un utilisateur
     * 
     * @param int $userId ID de l'utilisateur
     * @param string $currentPassword Mot de passe actuel
     * @param string $newPassword Nouveau mot de passe
     * @return bool
     */
    public function updatePassword($userId, $currentPassword, $newPassword) {
        // Récupérer l'utilisateur
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE id = :id",
            ['id' => $userId]
        );
        
        if (!$user) {
            return false;
        }
        
        // Vérifier le mot de passe actuel
        if (!password_verify($currentPassword, $user['password'])) {
            return false;
        }
        
        // Vérifier si le nouveau mot de passe est valide
        if (!$this->isPasswordValid($newPassword)) {
            return false;
        }
        
        // Hacher le nouveau mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Mettre à jour le mot de passe
        return $this->db->update(
            'users',
            ['password' => $hashedPassword],
            'id = :id',
            ['id' => $userId]
        );
    }
} 
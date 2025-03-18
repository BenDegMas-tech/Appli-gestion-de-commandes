<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrage de la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/config.php';
require_once 'api/auth.php';
require_once 'api/notification.php';

// Redirection si déjà connecté
$auth = new Auth();
if ($auth->isLoggedIn()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

// Traitement du formulaire de connexion
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $result = $auth->login($email, $password, $remember);
        if ($result === true) {
            // Redirection vers le tableau de bord
            header('Location: ' . app_url('dashboard.php'));
            exit;
        } else {
            $error = $result;
        }
    }
}

// Génération du token CSRF
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f5f5f5;
        }
        .form-signin {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }
        .form-signin .form-floating:focus-within {
            z-index: 2;
        }
        .form-signin input[type="email"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }
        .form-signin input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        .logo {
            max-width: 100px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <main class="form-signin">
        <div class="card shadow">
            <div class="card-body">
                <div class="text-center mb-4">
                    <img src="assets/img/logo.png" alt="Logo" class="logo" onerror="this.src='https://via.placeholder.com/100x100?text=Logo'; this.onerror=null;">
                    <h1 class="h3 mb-3 fw-normal"><?php echo APP_NAME; ?></h1>
                    <p class="text-muted">Veuillez vous connecter pour accéder à l'application</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-floating mb-3">
                        <input type="email" class="form-control" id="email" name="email" placeholder="nom@exemple.com" required>
                        <label for="email">Adresse email</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
                        <label for="password">Mot de passe</label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label" for="remember">
                            Se souvenir de moi
                        </label>
                    </div>
                    
                    <button class="w-100 btn btn-lg btn-primary" type="submit">Se connecter</button>
                </form>
                
                <div class="mt-4 text-center">
                    <p class="text-muted">
                        Vous n'avez pas de compte ? Contactez l'administrateur.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center text-muted">
            <p><?php echo APP_NAME; ?> &copy; <?php echo date('Y'); ?></p>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
<?php
require_once 'api/config.php';
require_once 'api/auth.php';
require_once 'api/database.php';

// Vérification de l'authentification
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$db = new Database();
$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION[CSRF_TOKEN_NAME]) {
        $error = ERROR_INVALID_TOKEN;
    } else {
        // Mise à jour des paramètres
        $settings = [
            'app_name' => trim($_POST['app_name'] ?? ''),
            'app_url' => trim($_POST['app_url'] ?? ''),
            'email_from' => trim($_POST['email_from'] ?? ''),
            'email_from_name' => trim($_POST['email_from_name'] ?? '')
        ];

        try {
            // Mise à jour du fichier de configuration
            $configFile = __DIR__ . '/api/config.php';
            $configContent = file_get_contents($configFile);
            
            foreach ($settings as $key => $value) {
                // Échapper les caractères spéciaux dans la valeur
                $value = addslashes($value);
                
                // Pattern plus précis pour correspondre aux constantes
                $pattern = "/define\('" . strtoupper($key) . "',\s*'[^']*'\);/";
                $replacement = "define('" . strtoupper($key) . "', '" . $value . "');";
                
                error_log("Mise à jour du paramètre $key :");
                error_log("Pattern : $pattern");
                error_log("Replacement : $replacement");
                
                $configContent = preg_replace($pattern, $replacement, $configContent);
                
                if ($configContent === null) {
                    throw new Exception("Erreur lors de la mise à jour du paramètre $key");
                }
            }
            
            // Sauvegarder le fichier avec un verrou
            if (file_put_contents($configFile, $configContent, LOCK_EX)) {
                $message = "Paramètres mis à jour avec succès.";
                error_log("Paramètres mis à jour avec succès");
            } else {
                throw new Exception("Impossible d'écrire dans le fichier de configuration");
            }
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
            error_log("Erreur lors de la mise à jour des paramètres : " . $e->getMessage());
        }
    }
}

// Génération du token CSRF
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

include 'includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>Paramètres de l'application</h1>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h5 mb-0">Configuration générale</h2>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                        <div class="mb-3">
                            <label for="app_name" class="form-label">Nom de l'application</label>
                            <input type="text" class="form-control" id="app_name" name="app_name" value="<?php echo APP_NAME; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="app_url" class="form-label">URL de l'application</label>
                            <input type="url" class="form-control" id="app_url" name="app_url" value="<?php echo APP_URL; ?>" required>
                            <div class="form-text">L'URL complète de votre application (ex: https://example.com/app)</div>
                        </div>

                        <div class="mb-3">
                            <label for="email_from" class="form-label">Email d'envoi</label>
                            <input type="email" class="form-control" id="email_from" name="email_from" value="<?php echo defined('EMAIL_FROM') ? EMAIL_FROM : ''; ?>" required>
                            <div class="form-text">L'adresse email utilisée pour envoyer les notifications</div>
                        </div>

                        <div class="mb-3">
                            <label for="email_from_name" class="form-label">Nom de l'expéditeur</label>
                            <input type="text" class="form-control" id="email_from_name" name="email_from_name" value="<?php echo defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : ''; ?>" required>
                            <div class="form-text">Le nom qui apparaîtra comme expéditeur des emails</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Actions rapides</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="email_templates.php" class="btn btn-outline-primary">
                            <i class="bi bi-envelope"></i> Gérer les modèles d'emails
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Informations système</h3>
                </div>
                <div class="card-body">
                    <p><strong>Version PHP :</strong> <?php echo phpversion(); ?></p>
                    <p><strong>Version de l'application :</strong> <?php echo APP_VERSION; ?></p>
                    <p><strong>Serveur Web :</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation des formulaires
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php include 'includes/footer.php'; ?> 
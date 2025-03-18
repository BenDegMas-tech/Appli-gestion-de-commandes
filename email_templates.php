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
        $id = $_POST['id'] ?? null;
        $statut = $_POST['statut'] ?? '';
        $sujet = trim($_POST['sujet'] ?? '');
        $contenu = trim($_POST['contenu'] ?? '');
        
        if (empty($sujet) || empty($contenu)) {
            $error = "Le sujet et le contenu sont obligatoires.";
        } else {
            try {
                if ($id) {
                    // Mise à jour
                    $stmt = $db->prepare("UPDATE email_templates SET sujet = :sujet, contenu = :contenu WHERE id = :id");
                    $stmt->execute([
                        'id' => $id,
                        'sujet' => $sujet,
                        'contenu' => $contenu
                    ]);
                    $message = "Modèle mis à jour avec succès.";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de la sauvegarde : " . $e->getMessage();
            }
        }
    }
}

// Récupération des modèles
try {
    $templates = $db->query("SELECT * FROM email_templates ORDER BY statut")->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des modèles : " . $e->getMessage();
    $templates = [];
}

// Variables disponibles pour les modèles
$variables = [
    '{reference}' => 'Référence de la commande',
    '{client_nom}' => 'Nom du client',
    '{client_prenom}' => 'Prénom du client',
    '{client_email}' => 'Email du client',
    '{date_commande}' => 'Date de la commande',
    '{statut}' => 'Statut de la commande',
    '{app_name}' => 'Nom de l\'application'
];

include 'includes/header.php';
?>

<div class="container mt-4">
    <h1>Gestion des modèles d'e-mails</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h5 mb-0">Variables disponibles</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($variables as $var => $desc): ?>
                    <div class="col-md-4 mb-2">
                        <code><?php echo htmlspecialchars($var); ?></code>: <?php echo htmlspecialchars($desc); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <?php foreach ($templates as $template): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="h5 mb-0">Modèle pour le statut : <?php echo ucfirst(str_replace('_', ' ', $template['statut'])); ?></h3>
            </div>
            <div class="card-body">
                <form method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                    <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                    <input type="hidden" name="statut" value="<?php echo $template['statut']; ?>">
                    
                    <div class="mb-3">
                        <label for="sujet_<?php echo $template['id']; ?>" class="form-label">Sujet</label>
                        <input type="text" class="form-control" id="sujet_<?php echo $template['id']; ?>" name="sujet" value="<?php echo htmlspecialchars($template['sujet']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="contenu_<?php echo $template['id']; ?>" class="form-label">Contenu</label>
                        <textarea class="form-control" id="contenu_<?php echo $template['id']; ?>" name="contenu" rows="5" required><?php echo htmlspecialchars($template['contenu']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
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
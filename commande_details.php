<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrage de la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/auth.php';
require_once 'api/client.php';
require_once 'api/commande.php';
require_once 'api/config.php';

// Vérification de l'authentification
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . app_url('index.php'));
    exit;
}

// Vérification de l'ID de commande
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ' . app_url('commandes.php'));
    exit;
}

$commandeId = $_GET['id'];

// Initialisation des gestionnaires
$commandeManager = new Commande();
$clientManager = new Client();

// Récupération des informations de la commande
$commande = $commandeManager->getCommandeById($commandeId);
if (!$commande) {
    header('Location: commandes.php');
    exit;
}

// Récupération de l'historique des mises à jour
$updates = $commandeManager->getCommandeUpdates($commandeId);

// Génération du token CSRF
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Traitement des actions
$message = '';
$error = '';

// Mise à jour du statut
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $error = ERROR_INVALID_TOKEN;
    } else {
        $nouveauStatut = $_POST['statut'] ?? '';
        $commentaire = trim($_POST['commentaire'] ?? '');
        
        if (empty($nouveauStatut) || !in_array($nouveauStatut, ['en_attente', 'en_cours', 'terminee', 'annulee'])) {
            $error = "Le statut n'est pas valide.";
        } else {
            $updateData = [
                'statut' => $nouveauStatut,
                'commentaire' => $commentaire,
                'created_by' => $_SESSION['user_id']
            ];
            
            if ($commandeManager->updateCommande($commandeId, $updateData)) {
                $message = "Statut mis à jour avec succès.";
                // Rafraîchissement des données
                $commande = $commandeManager->getCommandeById($commandeId);
                $updates = $commandeManager->getCommandeUpdates($commandeId);
            } else {
                $error = "Erreur lors de la mise à jour du statut.";
            }
        }
    }
}

// Fonction pour obtenir le libellé d'un statut
function getStatutLibelle($statut) {
    $libelles = [
        'en_attente' => 'En attente',
        'en_cours' => 'En cours de traitement',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée'
    ];
    
    return $libelles[$statut] ?? $statut;
}

// Fonction pour obtenir la classe CSS d'un statut
function getStatutClass($statut) {
    $classes = [
        'en_attente' => 'warning',
        'en_cours' => 'info',
        'terminee' => 'success',
        'annulee' => 'danger'
    ];
    
    return $classes[$statut] ?? 'secondary';
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Détails de la commande</h1>
            <h2 class="h4 text-muted">Référence: <?php echo htmlspecialchars($commande['reference']); ?></h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="commandes.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Retour aux commandes
            </a>
            <a href="generate_flashcode.php?id=<?php echo $commande['client_id']; ?>" class="btn btn-info">
                <i class="bi bi-qr-code"></i> Flashcode client
            </a>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Informations de la commande -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Détails de la commande</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Référence :</strong> <?php echo htmlspecialchars($commande['reference']); ?></p>
                            <p><strong>Date de commande :</strong> <?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></p>
                            <?php if (!empty($commande['date_livraison_prevue'])): ?>
                                <p><strong>Date de livraison prévue :</strong> <?php echo date('d/m/Y', strtotime($commande['date_livraison_prevue'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <p>
                                <strong>Statut :</strong>
                                <span class="badge bg-<?php echo getStatutClass($commande['statut']); ?>">
                                    <?php echo getStatutLibelle($commande['statut']); ?>
                                </span>
                            </p>
                            <?php if (!empty($commande['description'])): ?>
                                <p><strong>Description :</strong><br><?php echo nl2br(htmlspecialchars($commande['description'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire de mise à jour du statut -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Mettre à jour le statut</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <div class="mb-3">
                            <label for="statut" class="form-label">Nouveau statut</label>
                            <select class="form-select" id="statut" name="statut" required>
                                <option value="">Sélectionnez un statut</option>
                                <option value="en_attente" <?php echo $commande['statut'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="en_cours" <?php echo $commande['statut'] === 'en_cours' ? 'selected' : ''; ?>>En cours de traitement</option>
                                <option value="terminee" <?php echo $commande['statut'] === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                                <option value="annulee" <?php echo $commande['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="commentaire" class="form-label">Commentaire (optionnel)</label>
                            <textarea class="form-control" id="commentaire" name="commentaire" rows="2"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Informations du client et historique -->
        <div class="col-md-6">
            <!-- Informations du client -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Informations du client</h3>
                </div>
                <div class="card-body">
                    <p><strong>Nom:</strong> <?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($commande['email']); ?></p>
                    <?php if (!empty($commande['telephone'])): ?>
                        <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($commande['telephone']); ?></p>
                    <?php endif; ?>
                    <p><strong>ID Flashcode:</strong> <?php echo htmlspecialchars($commande['flashcode_id']); ?></p>
                    
                    <a href="clients.php?action=edit&id=<?php echo $commande['client_id']; ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-pencil"></i> Modifier le client
                    </a>
                </div>
            </div>
            
            <!-- Historique des mises à jour -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Historique des mises à jour</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($updates)): ?>
                        <p class="text-center">Aucune mise à jour enregistrée.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($updates as $update): ?>
                                <div class="timeline-item mb-4">
                                    <div class="d-flex">
                                        <div class="timeline-badge bg-<?php echo getStatutClass($update['nouveau_statut']); ?> me-3">
                                            <i class="bi bi-check-circle"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between">
                                                <h4 class="h6 mb-1">
                                                    <?php echo getStatutLibelle($update['nouveau_statut']); ?>
                                                    <?php if ($update['ancien_statut']): ?>
                                                        <small class="text-muted">(précédemment: <?php echo getStatutLibelle($update['ancien_statut']); ?>)</small>
                                                    <?php endif; ?>
                                                </h4>
                                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($update['created_at'])); ?></small>
                                            </div>
                                            <?php if (!empty($update['commentaire'])): ?>
                                                <p class="mb-1"><?php echo htmlspecialchars($update['commentaire']); ?></p>
                                            <?php endif; ?>
                                            <small class="text-muted">
                                                Par: <?php echo !empty($update['username']) ? htmlspecialchars($update['username']) : 'Client'; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Boutons d'action -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title h5 mb-0">Actions</h3>
                </div>
                <div class="card-body">
                    <div class="btn-group">
                        <a href="commandes.php?action=edit&id=<?php echo $commandeId; ?>" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> Modifier la commande
                        </a>
                        <a href="reports.php?type=commande&id=<?php echo $commandeId; ?>" class="btn btn-success">
                            <i class="bi bi-file-earmark-pdf"></i> Générer un rapport
                        </a>
                        <a href="?id=<?php echo $commandeId; ?>" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Actualiser
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}
</style>

<?php include 'includes/footer.php'; ?> 
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
    header('Location: index.php');
    exit;
}

// Initialisation des gestionnaires
$clientManager = new Client();
$commandeManager = new Commande();

// Génération du token CSRF
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Traitement des actions
$message = '';
$error = '';

// Filtres pour les commandes
$filters = [];
if (isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $filters['client_id'] = $_GET['client_id'];
    $client = $clientManager->getClientById($filters['client_id']);
}

if (isset($_GET['statut']) && in_array($_GET['statut'], ['en_attente', 'en_cours', 'terminee', 'annulee'])) {
    $filters['statut'] = $_GET['statut'];
}

// Suppression d'une commande
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Vérification du token CSRF
    if (!isset($_GET['token']) || $_GET['token'] !== $csrfToken) {
        $error = ERROR_INVALID_TOKEN;
    } else {
        $commandeId = $_GET['id'];
        if ($commandeManager->deleteCommande($commandeId)) {
            $message = "Commande supprimée avec succès.";
        } else {
            $error = "Erreur lors de la suppression de la commande.";
        }
    }
}

// Ajout ou modification d'une commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $error = ERROR_INVALID_TOKEN;
    } else {
        // Validation des données
        $clientId = isset($_POST['client_id']) && is_numeric($_POST['client_id']) ? $_POST['client_id'] : null;
        $description = trim($_POST['description'] ?? '');
        $montant = !empty($_POST['montant']) ? floatval(str_replace(',', '.', $_POST['montant'])) : null;
        $statut = $_POST['statut'] ?? 'en_attente';
        $dateCommande = $_POST['date_commande'] ?? date('Y-m-d');
        $dateLivraisonPrevue = !empty($_POST['date_livraison_prevue']) ? $_POST['date_livraison_prevue'] : null;
        $commentaire = trim($_POST['commentaire'] ?? '');
        
        $errors = [];
        if (empty($clientId)) $errors[] = "Le client est obligatoire.";
        if (!in_array($statut, ['en_attente', 'en_cours', 'terminee', 'annulee'])) $errors[] = "Le statut n'est pas valide.";
        
        if (empty($errors)) {
            $commandeData = [
                'client_id' => $clientId,
                'description' => $description,
                'montant' => $montant,
                'statut' => $statut,
                'date_commande' => $dateCommande,
                'date_livraison_prevue' => $dateLivraisonPrevue,
                'commentaire' => $commentaire,
                'created_by' => $_SESSION['user_id']
            ];
            
            // Modification d'une commande existante
            if (isset($_POST['commande_id']) && is_numeric($_POST['commande_id'])) {
                $commandeId = $_POST['commande_id'];
                if ($commandeManager->updateCommande($commandeId, $commandeData)) {
                    $message = "Commande mise à jour avec succès.";
                    
                    // Envoi de la notification si demandé
                    if (isset($_POST['envoyer_notification']) && $_POST['envoyer_notification'] == 'on') {
                        $notificationManager = new Notification();
                        $emailSent = $notificationManager->sendCommandeNotification(
                            $commandeId,
                            'status_change',
                            [
                                'new_status' => $statut,
                                'commentaire' => $commentaire
                            ]
                        );
                        
                        if ($emailSent) {
                            $message .= " L'email de notification a été envoyé avec succès.";
                        } else {
                            $error .= " L'envoi de l'email de notification a échoué.";
                        }
                    }
                } else {
                    $error = "Erreur lors de la mise à jour de la commande.";
                }
            } 
            // Ajout d'une nouvelle commande
            else {
                $commandeId = $commandeManager->addCommande($commandeData);
                if ($commandeId) {
                    $message = "Commande ajoutée avec succès.";
                } else {
                    $error = "Erreur lors de l'ajout de la commande.";
                }
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// Traitement de la modification de commande
if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['commande_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token CSRF invalide";
    } else {
        $commandeId = (int)$_POST['commande_id'];
        $status = $_POST['status'];
        $commentaire = $_POST['commentaire'];
        $trackingNumber = $_POST['tracking_number'];
        
        try {
            $db->beginTransaction();
            
            // Mise à jour de la commande
            $db->update('commandes', 
                ['status' => $status, 'commentaire' => $commentaire, 'tracking_number' => $trackingNumber],
                ['id' => $commandeId]
            );
            
            // Envoi de notification si demandé
            if (isset($_POST['envoyer_notification'])) {
                $notification = new Notification();
                $notification->sendCommandeNotification($commandeId, 'status_change', [
                    'new_status' => $status,
                    'tracking_number' => $trackingNumber
                ]);
            }
            
            $db->commit();
            $message = "Commande mise à jour avec succès";
            
            // Redirection vers la page des commandes
            header('Location: commandes.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Erreur lors de la mise à jour de la commande";
            error_log($e->getMessage());
        }
    }
}

// Récupération des commandes selon les filtres
$commandes = $commandeManager->getAllCommandes($filters);

// Récupération d'une commande pour modification
$commandeToEdit = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $commandeToEdit = $commandeManager->getCommandeById($_GET['id']);
}

// Récupération de tous les clients pour le formulaire
$allClients = $clientManager->getAllClients();

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
            <h1>
                Gestion des Commandes
                <?php if (isset($client)): ?>
                    <small class="text-muted">pour <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></small>
                <?php endif; ?>
            </h1>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#commandeModal">
                <i class="bi bi-plus-circle"></i> Nouvelle Commande
            </button>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">Filtres</h2>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="client_id" class="form-label">Client</label>
                    <select class="form-select" id="client_id" name="client_id">
                        <option value="">Tous les clients</option>
                        <?php foreach ($allClients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" <?php echo (isset($filters['client_id']) && $filters['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="statut" class="form-label">Statut</label>
                    <select class="form-select" id="statut" name="statut">
                        <option value="">Tous les statuts</option>
                        <option value="en_attente" <?php echo (isset($filters['statut']) && $filters['statut'] === 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                        <option value="en_cours" <?php echo (isset($filters['statut']) && $filters['statut'] === 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                        <option value="terminee" <?php echo (isset($filters['statut']) && $filters['statut'] === 'terminee') ? 'selected' : ''; ?>>Terminée</option>
                        <option value="annulee" <?php echo (isset($filters['statut']) && $filters['statut'] === 'annulee') ? 'selected' : ''; ?>>Annulée</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filtrer</button>
                    <a href="commandes.php" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tableau des commandes -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">Liste des Commandes</h2>
        </div>
        <div class="card-body">
            <?php if (empty($commandes)): ?>
                <p class="text-center">Aucune commande trouvée.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Référence</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandes as $commande): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($commande['reference']); ?></td>
                                    <td><?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatutClass($commande['statut']); ?>">
                                            <?php echo getStatutLibelle($commande['statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?action=edit&id=<?php echo $commande['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </a>
                                            <a href="?action=delete&id=<?php echo $commande['id']; ?>&token=<?php echo $csrfToken; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette commande ?');">
                                                <i class="bi bi-trash"></i> Supprimer
                                            </a>
                                            <a href="commande_details.php?id=<?php echo $commande['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> Détails
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour ajouter/modifier une commande -->
<div class="modal fade" id="commandeModal" tabindex="-1" aria-labelledby="commandeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <?php if ($commandeToEdit): ?>
                    <input type="hidden" name="commande_id" value="<?php echo $commandeToEdit['id']; ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title" id="commandeModalLabel">
                        <?php echo $commandeToEdit ? 'Modifier la commande' : 'Ajouter une commande'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                            <select class="form-select" id="client_id" name="client_id" required>
                                <option value="">Sélectionnez un client</option>
                                <?php foreach ($allClients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo $commandeToEdit && $commandeToEdit['client_id'] === $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="montant" class="form-label">Montant</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control" id="montant" name="montant" 
                                       value="<?php echo $commandeToEdit ? number_format($commandeToEdit['montant'] ?? 0, 2, '.', '') : ''; ?>"
                                       placeholder="0.00">
                                <span class="input-group-text">€</span>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut" required>
                                <option value="">Choisir un statut</option>
                                <option value="en_attente" <?php echo $commandeToEdit && $commandeToEdit['statut'] === 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="en_cours" <?php echo $commandeToEdit && $commandeToEdit['statut'] === 'en_cours' ? 'selected' : ''; ?>>En cours</option>
                                <option value="terminee" <?php echo $commandeToEdit && $commandeToEdit['statut'] === 'terminee' ? 'selected' : ''; ?>>Terminée</option>
                                <option value="annulee" <?php echo $commandeToEdit && $commandeToEdit['statut'] === 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="envoyer_notification" name="envoyer_notification" checked>
                            <label class="form-check-label" for="envoyer_notification">Envoyer une notification par e-mail au client</label>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="date_commande" class="form-label">Date de commande <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="date_commande" name="date_commande" required
                                   value="<?php echo $commandeToEdit ? $commandeToEdit['date_commande'] : date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="date_livraison_prevue" class="form-label">Date de livraison prévue</label>
                            <input type="date" class="form-control" id="date_livraison_prevue" name="date_livraison_prevue"
                                   value="<?php echo $commandeToEdit && $commandeToEdit['date_livraison_prevue'] ? $commandeToEdit['date_livraison_prevue'] : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $commandeToEdit ? htmlspecialchars($commandeToEdit['description'] ?? '') : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="commentaire" class="form-label">Commentaire</label>
                        <textarea class="form-control" id="commentaire" name="commentaire" rows="3"><?php echo $commandeToEdit ? htmlspecialchars($commandeToEdit['commentaire'] ?? '') : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $commandeToEdit ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour ouvrir automatiquement la modal en cas d'édition -->
<?php if ($commandeToEdit): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var commandeModal = new bootstrap.Modal(document.getElementById('commandeModal'));
        commandeModal.show();
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?> 
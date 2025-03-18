<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrage de la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'api/auth.php';
require_once 'api/client.php';
require_once 'api/config.php';

// Vérification de l'authentification
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Initialisation du gestionnaire de clients
$clientManager = new Client();

// Génération du token CSRF
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Traitement des actions
$message = '';
$error = '';

// Suppression d'un client
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Vérification du token CSRF
    if (!isset($_GET['token']) || $_GET['token'] !== $csrfToken) {
        $error = ERROR_INVALID_TOKEN;
    } else {
        $clientId = $_GET['id'];
        if ($clientManager->deleteClient($clientId)) {
            $message = "Client supprimé avec succès.";
        } else {
            $error = "Erreur lors de la suppression du client.";
        }
    }
}

// Ajout ou modification d'un client
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $error = ERROR_INVALID_TOKEN;
    } else {
        // Validation des données
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        
        $errors = [];
        if (empty($nom)) $errors[] = "Le nom est obligatoire.";
        if (empty($prenom)) $errors[] = "Le prénom est obligatoire.";
        if (empty($email)) $errors[] = "L'email est obligatoire.";
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email n'est pas valide.";
        
        if (empty($errors)) {
            $clientData = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone
            ];
            
            // Modification d'un client existant
            if (isset($_POST['client_id']) && is_numeric($_POST['client_id'])) {
                $clientId = $_POST['client_id'];
                if ($clientManager->updateClient($clientId, $clientData)) {
                    $message = "Client mis à jour avec succès.";
                } else {
                    $error = "Erreur lors de la mise à jour du client.";
                }
            } 
            // Ajout d'un nouveau client
            else {
                $clientId = $clientManager->addClient($clientData);
                if ($clientId) {
                    $message = "Client ajouté avec succès.";
                } else {
                    $error = "Erreur lors de l'ajout du client.";
                }
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

// Traitement de la modification de client
if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['client_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token CSRF invalide";
    } else {
        $clientId = (int)$_POST['client_id'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $email = $_POST['email'];
        $telephone = $_POST['telephone'];
        $adresse = $_POST['adresse'];
        $codePostal = $_POST['code_postal'];
        $ville = $_POST['ville'];
        $pays = $_POST['pays'];
        
        try {
            $db->beginTransaction();
            
            // Mise à jour du client
            $db->update('clients', 
                [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'adresse' => $adresse,
                    'code_postal' => $codePostal,
                    'ville' => $ville,
                    'pays' => $pays
                ],
                ['id' => $clientId]
            );
            
            $db->commit();
            $message = "Client mis à jour avec succès";
            
            // Redirection vers la page des clients
            header('Location: clients.php');
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Erreur lors de la mise à jour du client";
            error_log($e->getMessage());
        }
    }
}

// Récupération de tous les clients
$clients = $clientManager->getAllClients();

// Récupération d'un client pour modification
$clientToEdit = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $clientToEdit = $clientManager->getClientById($_GET['id']);
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Gestion des Clients</h1>
        </div>
        <div class="col-md-4 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#clientModal">
                <i class="bi bi-plus-circle"></i> Nouveau Client
            </button>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Tableau des clients -->
    <div class="card mb-4">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">Liste des Clients</h2>
        </div>
        <div class="card-body">
            <?php if (empty($clients)): ?>
                <p class="text-center">Aucun client enregistré.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Flashcode</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['id']); ?></td>
                                    <td><?php echo htmlspecialchars($client['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($client['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo htmlspecialchars($client['telephone'] ?? '-'); ?></td>
                                    <td>
                                        <a href="generate_flashcode.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-qr-code"></i> Voir
                                        </a>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?action=edit&id=<?php echo $client['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </a>
                                            <a href="?action=delete&id=<?php echo $client['id']; ?>&token=<?php echo $csrfToken; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?');">
                                                <i class="bi bi-trash"></i> Supprimer
                                            </a>
                                            <a href="commandes.php?client_id=<?php echo $client['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-list-check"></i> Commandes
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

<!-- Modal pour ajouter/modifier un client -->
<div class="modal fade" id="clientModal" tabindex="-1" aria-labelledby="clientModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <?php if ($clientToEdit): ?>
                    <input type="hidden" name="client_id" value="<?php echo $clientToEdit['id']; ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title" id="clientModalLabel">
                        <?php echo $clientToEdit ? 'Modifier le client' : 'Ajouter un client'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" required
                               value="<?php echo $clientToEdit ? htmlspecialchars($clientToEdit['nom']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="prenom" name="prenom" required
                               value="<?php echo $clientToEdit ? htmlspecialchars($clientToEdit['prenom']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo $clientToEdit ? htmlspecialchars($clientToEdit['email']) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" id="telephone" name="telephone"
                               value="<?php echo $clientToEdit ? htmlspecialchars($clientToEdit['telephone'] ?? '') : ''; ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $clientToEdit ? 'Mettre à jour' : 'Ajouter'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script pour ouvrir automatiquement la modal en cas d'édition -->
<?php if ($clientToEdit): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var clientModal = new bootstrap.Modal(document.getElementById('clientModal'));
        clientModal.show();
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?> 
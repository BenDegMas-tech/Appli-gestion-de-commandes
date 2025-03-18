<?php
require_once 'api/config.php';
require_once 'api/auth.php';
require_once 'api/database.php';
require_once 'includes/functions.php';

// Vérification de l'authentification
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . app_url('index.php'));
    exit;
}

// Récupération des statistiques
try {
    $db = get_db_connection();
    
    // Nombre total de clients
    $stmt = $db->query("SELECT COUNT(*) as total FROM clients");
    $clientsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre total de commandes
    $stmt = $db->query("SELECT COUNT(*) as total FROM commandes");
    $commandesCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Nombre de mails envoyés
    $stmt = $db->query("SELECT COUNT(*) as total FROM notifications WHERE type = 'email' AND statut = 'envoyee'");
    $emailsCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 5 dernières commandes
    $stmt = $db->query("
        SELECT c.*, cl.nom as client_nom, cl.prenom as client_prenom 
        FROM commandes c
        JOIN clients cl ON c.client_id = cl.id
        ORDER BY c.date_commande DESC
        LIMIT 5
    ");
    $dernieresCommandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-4">
        <h1 class="mb-4">Tableau de bord</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- Cartes de statistiques -->
        <div class="row mb-4">
            <!-- Nombre de clients -->
            <div class="col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-people fs-1 text-primary mb-2"></i>
                        <h5 class="card-title">Clients</h5>
                        <p class="card-text display-6"><?php echo number_format($clientsCount, 0, ',', ' '); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Nombre de commandes -->
            <div class="col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-cart fs-1 text-success mb-2"></i>
                        <h5 class="card-title">Commandes</h5>
                        <p class="card-text display-6"><?php echo number_format($commandesCount, 0, ',', ' '); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Nombre d'emails envoyés -->
            <div class="col-md-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-envelope fs-1 text-info mb-2"></i>
                        <h5 class="card-title">Emails envoyés</h5>
                        <p class="card-text display-6"><?php echo number_format($emailsCount, 0, ',', ' '); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dernières commandes -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">5 dernières commandes</h5>
                <a href="commandes.php" class="btn btn-primary btn-sm">Voir toutes les commandes</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
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
                            <?php foreach ($dernieresCommandes as $commande): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($commande['reference']); ?></td>
                                    <td><?php echo htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatutClass($commande['statut']); ?>">
                                            <?php echo getStatutLibelle($commande['statut']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="commande.php?id=<?php echo $commande['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
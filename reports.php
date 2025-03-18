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

// Initialisation des gestionnaires
$clientManager = new Client();
$commandeManager = new Commande();

// Génération du token CSRF
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Traitement des paramètres
$type = isset($_GET['type']) ? $_GET['type'] : 'commandes';
$format = isset($_GET['format']) ? $_GET['format'] : 'html';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$statut = isset($_GET['statut']) ? $_GET['statut'] : null;
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01'); // Premier jour du mois
$dateFin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-t'); // Dernier jour du mois

// Préparation des données pour le rapport
$data = [];
$title = '';

if ($type === 'commande' && $id) {
    // Rapport pour une commande spécifique
    $commande = $commandeManager->getCommandeById($id);
    if (!$commande) {
        header('Location: ' . app_url('commandes.php'));
        exit;
    }
    
    $updates = $commandeManager->getCommandeUpdates($id);
    
    $data = [
        'commande' => $commande,
        'updates' => $updates
    ];
    
    $title = 'Rapport de commande - ' . $commande['reference'];
} elseif ($type === 'client' && $clientId) {
    // Rapport pour un client spécifique
    $client = $clientManager->getClientById($clientId);
    if (!$client) {
        header('Location: ' . app_url('clients.php'));
        exit;
    }
    
    $commandes = $commandeManager->getCommandesByClientId($clientId);
    
    $data = [
        'client' => $client,
        'commandes' => $commandes
    ];
    
    $title = 'Rapport client - ' . $client['nom'] . ' ' . $client['prenom'];
} else {
    // Rapport global des commandes
    $filters = [];
    
    if ($clientId) {
        $filters['client_id'] = $clientId;
    }
    
    if ($statut) {
        $filters['statut'] = $statut;
    }
    
    if ($dateDebut && $dateFin) {
        $filters['date_debut'] = $dateDebut;
        $filters['date_fin'] = $dateFin;
    }
    
    $commandes = $commandeManager->getAllCommandes($filters);
    
    // Statistiques
    $totalCommandes = count($commandes);
    $totalMontant = array_sum(array_column($commandes, 'montant'));
    $statutsCount = array_count_values(array_column($commandes, 'statut'));
    
    $data = [
        'commandes' => $commandes,
        'stats' => [
            'total_commandes' => $totalCommandes,
            'total_montant' => $totalMontant,
            'statuts' => $statutsCount
        ],
        'filters' => [
            'client_id' => $clientId,
            'statut' => $statut,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin
        ]
    ];
    
    $title = 'Rapport des commandes';
}

// Génération du rapport selon le format demandé
if ($format === 'pdf') {
    // Génération du PDF
    // Note: Cette fonctionnalité nécessite l'installation de TCPDF ou FPDF
    // Pour l'instant, nous allons simplement afficher un message d'erreur
    $error = "La génération de PDF n'est pas encore implémentée. Veuillez installer TCPDF ou FPDF.";
    $_SESSION['error'] = $error;
    
    // Redirection vers la page HTML
    header('Location: ' . app_url('reports.php?type=' . $type . '&id=' . $id . '&client_id=' . $clientId));
    exit;
} elseif ($format === 'csv') {
    // Génération du CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitizeFilename($title) . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes CSV selon le type de rapport
    if ($type === 'commande') {
        fputcsv($output, ['Référence', 'Client', 'Date', 'Montant', 'Statut']);
        fputcsv($output, [
            $data['commande']['reference'],
            $data['commande']['nom'] . ' ' . $data['commande']['prenom'],
            $data['commande']['date_commande'],
            $data['commande']['montant'],
            getStatutLibelle($data['commande']['statut'])
        ]);
        
        // Historique des mises à jour
        fputcsv($output, []); // Ligne vide
        fputcsv($output, ['Historique des mises à jour']);
        fputcsv($output, ['Date', 'Ancien statut', 'Nouveau statut', 'Commentaire', 'Utilisateur']);
        
        foreach ($data['updates'] as $update) {
            fputcsv($output, [
                $update['created_at'],
                getStatutLibelle($update['ancien_statut']),
                getStatutLibelle($update['nouveau_statut']),
                $update['commentaire'],
                $update['username'] ?? 'Système'
            ]);
        }
    } elseif ($type === 'client') {
        fputcsv($output, ['Nom', 'Prénom', 'Email', 'Téléphone', 'Flashcode']);
        fputcsv($output, [
            $data['client']['nom'],
            $data['client']['prenom'],
            $data['client']['email'],
            $data['client']['telephone'],
            $data['client']['flashcode_id']
        ]);
        
        // Liste des commandes
        fputcsv($output, []); // Ligne vide
        fputcsv($output, ['Liste des commandes']);
        fputcsv($output, ['Référence', 'Date', 'Montant', 'Statut']);
        
        foreach ($data['commandes'] as $commande) {
            fputcsv($output, [
                $commande['reference'],
                $commande['date_commande'],
                $commande['montant'],
                getStatutLibelle($commande['statut'])
            ]);
        }
    } else {
        // Rapport global
        fputcsv($output, ['Référence', 'Client', 'Date', 'Montant', 'Statut']);
        
        foreach ($data['commandes'] as $commande) {
            fputcsv($output, [
                $commande['reference'],
                $commande['nom'] . ' ' . $commande['prenom'],
                $commande['date_commande'],
                $commande['montant'],
                getStatutLibelle($commande['statut'])
            ]);
        }
        
        // Statistiques
        fputcsv($output, []); // Ligne vide
        fputcsv($output, ['Statistiques']);
        fputcsv($output, ['Total commandes', $data['stats']['total_commandes']]);
        fputcsv($output, ['Montant total', $data['stats']['total_montant']]);
        
        fputcsv($output, []); // Ligne vide
        fputcsv($output, ['Répartition par statut']);
        
        foreach ($data['stats']['statuts'] as $statut => $count) {
            fputcsv($output, [getStatutLibelle($statut), $count]);
        }
    }
    
    fclose($output);
    exit;
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

// Fonction pour assainir un nom de fichier
function sanitizeFilename($filename) {
    // Suppression des caractères spéciaux
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    // Limitation de la longueur
    return substr($filename, 0, 100);
}

// Affichage du rapport au format HTML
include 'includes/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><?php echo htmlspecialchars($title); ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group">
                <a href="reports.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&client_id=<?php echo $clientId; ?>&format=pdf" class="btn btn-primary">
                    <i class="bi bi-file-earmark-pdf"></i> Exporter en PDF
                </a>
                <a href="reports.php?type=<?php echo $type; ?>&id=<?php echo $id; ?>&client_id=<?php echo $clientId; ?>&format=csv" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Exporter en CSV
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($type === 'commande' && isset($data['commande'])): ?>
        <!-- Rapport d'une commande spécifique -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">Informations de la commande</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Référence:</strong> <?php echo htmlspecialchars($data['commande']['reference']); ?></p>
                        <p><strong>Client:</strong> <?php echo htmlspecialchars($data['commande']['nom'] . ' ' . $data['commande']['prenom']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($data['commande']['email']); ?></p>
                        <?php if (!empty($data['commande']['telephone'])): ?>
                            <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($data['commande']['telephone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Date de commande:</strong> <?php echo date('d/m/Y', strtotime($data['commande']['date_commande'])); ?></p>
                        <?php if (!empty($data['commande']['date_livraison_prevue'])): ?>
                            <p><strong>Date de livraison prévue:</strong> <?php echo date('d/m/Y', strtotime($data['commande']['date_livraison_prevue'])); ?></p>
                        <?php endif; ?>
                        <p><strong>Montant:</strong> <?php echo number_format($data['commande']['montant'], 2, ',', ' '); ?> €</p>
                        <p>
                            <strong>Statut:</strong> 
                            <span class="badge bg-<?php echo getStatutClass($data['commande']['statut']); ?>">
                                <?php echo getStatutLibelle($data['commande']['statut']); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($data['commande']['description'])): ?>
                    <div class="mt-3">
                        <h5>Description:</h5>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($data['commande']['description'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Historique des mises à jour -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">Historique des mises à jour</h2>
            </div>
            <div class="card-body">
                <?php if (empty($data['updates'])): ?>
                    <p class="text-center">Aucune mise à jour enregistrée.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Ancien statut</th>
                                    <th>Nouveau statut</th>
                                    <th>Commentaire</th>
                                    <th>Utilisateur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['updates'] as $update): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($update['created_at'])); ?></td>
                                        <td>
                                            <?php if ($update['ancien_statut']): ?>
                                                <span class="badge bg-<?php echo getStatutClass($update['ancien_statut']); ?>">
                                                    <?php echo getStatutLibelle($update['ancien_statut']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatutClass($update['nouveau_statut']); ?>">
                                                <?php echo getStatutLibelle($update['nouveau_statut']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($update['commentaire'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($update['username'] ?? 'Système'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php elseif ($type === 'client' && isset($data['client'])): ?>
        <!-- Rapport d'un client spécifique -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">Informations du client</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nom:</strong> <?php echo htmlspecialchars($data['client']['nom']); ?></p>
                        <p><strong>Prénom:</strong> <?php echo htmlspecialchars($data['client']['prenom']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($data['client']['email']); ?></p>
                        <?php if (!empty($data['client']['telephone'])): ?>
                            <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($data['client']['telephone']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <p><strong>ID Flashcode:</strong> <?php echo htmlspecialchars($data['client']['flashcode_id']); ?></p>
                        <p><strong>Date de création:</strong> <?php echo date('d/m/Y', strtotime($data['client']['created_at'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Liste des commandes du client -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">Commandes du client</h2>
            </div>
            <div class="card-body">
                <?php if (empty($data['commandes'])): ?>
                    <p class="text-center">Aucune commande enregistrée pour ce client.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['commandes'] as $commande): ?>
                                    <tr>
                                        <td>
                                            <a href="commande_details.php?id=<?php echo $commande['id']; ?>">
                                                <?php echo htmlspecialchars($commande['reference']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></td>
                                        <td><?php echo number_format($commande['montant'], 2, ',', ' '); ?> €</td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatutClass($commande['statut']); ?>">
                                                <?php echo getStatutLibelle($commande['statut']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Statistiques des commandes -->
                    <div class="mt-4">
                        <h5>Statistiques</h5>
                        <p><strong>Nombre total de commandes:</strong> <?php echo count($data['commandes']); ?></p>
                        <p><strong>Montant total des commandes:</strong> <?php echo number_format(array_sum(array_column($data['commandes'], 'montant')), 2, ',', ' '); ?> €</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Rapport global des commandes -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">Filtres</h2>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <input type="hidden" name="type" value="<?php echo $type; ?>">
                    
                    <div class="col-md-3">
                        <label for="client_id" class="form-label">Client</label>
                        <select class="form-select" id="client_id" name="client_id">
                            <option value="">Tous les clients</option>
                            <?php foreach ($clientManager->getAllClients() as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo ($clientId == $client['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-select" id="statut" name="statut">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente" <?php echo ($statut === 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                            <option value="en_cours" <?php echo ($statut === 'en_cours') ? 'selected' : ''; ?>>En cours</option>
                            <option value="terminee" <?php echo ($statut === 'terminee') ? 'selected' : ''; ?>>Terminée</option>
                            <option value="annulee" <?php echo ($statut === 'annulee') ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_debut" class="form-label">Date début</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $dateDebut; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="date_fin" class="form-label">Date fin</label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $dateFin; ?>">
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="reports.php" class="btn btn-secondary">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Total des commandes</h5>
                        <p class="card-text display-4"><?php echo $data['stats']['total_commandes']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Montant total</h5>
                        <p class="card-text display-4"><?php echo number_format($data['stats']['total_montant'], 2, ',', ' '); ?> €</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Commandes terminées</h5>
                        <p class="card-text display-4"><?php echo $data['stats']['statuts']['terminee'] ?? 0; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Répartition par statut -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">Répartition par statut</h2>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach (['en_attente', 'en_cours', 'terminee', 'annulee'] as $statut): ?>
                        <div class="col-md-3 text-center">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo getStatutLibelle($statut); ?></h5>
                                    <p class="card-text display-5"><?php echo $data['stats']['statuts'][$statut] ?? 0; ?></p>
                                    <div class="progress">
                                        <div class="progress-bar bg-<?php echo getStatutClass($statut); ?>" role="progressbar" 
                                             style="width: <?php echo ($data['stats']['total_commandes'] > 0) ? (($data['stats']['statuts'][$statut] ?? 0) / $data['stats']['total_commandes'] * 100) : 0; ?>%" 
                                             aria-valuenow="<?php echo $data['stats']['statuts'][$statut] ?? 0; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="<?php echo $data['stats']['total_commandes']; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Liste des commandes -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">Liste des commandes</h2>
            </div>
            <div class="card-body">
                <?php if (empty($data['commandes'])): ?>
                    <p class="text-center">Aucune commande trouvée avec les filtres sélectionnés.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['commandes'] as $commande): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($commande['reference']); ?></td>
                                        <td><?php echo htmlspecialchars($commande['nom'] . ' ' . $commande['prenom']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></td>
                                        <td><?php echo number_format($commande['montant'], 2, ',', ' '); ?> €</td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatutClass($commande['statut']); ?>">
                                                <?php echo getStatutLibelle($commande['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="commande_details.php?id=<?php echo $commande['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="bi bi-eye"></i> Détails
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="text-center mb-4">
        <a href="<?php echo $type === 'commande' ? 'commande_details.php?id=' . $id : ($type === 'client' ? 'clients.php?action=edit&id=' . $clientId : 'commandes.php'); ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'api/config.php';
require_once 'api/client.php';
require_once 'api/commande.php';
require_once 'api/notification.php';

// Initialisation
$db = new Database();
$commandeManager = new Commande();
$notificationManager = new Notification();
$clientManager = new Client();
$message = '';
$error = '';

// Récupération du flashcode
$flashcode = $_GET['code'] ?? '';

if (empty($flashcode)) {
    $error = "Code QR manquant.";
} else {
    try {
        // Récupération des commandes par le flashcode
        $query = "SELECT c.*, cl.nom as client_nom, cl.prenom as client_prenom, cl.email as client_email 
                 FROM commandes c 
                 JOIN clients cl ON c.client_id = cl.id 
                 WHERE cl.flashcode_id = :flashcode 
                 ORDER BY c.date_commande DESC";
        $stmt = $db->prepare($query);
        $stmt->execute(['flashcode' => $flashcode]);
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($commandes)) {
            $error = "Aucune commande trouvée pour ce flashcode.";
            error_log("Aucune commande trouvée pour le flashcode: " . $flashcode);
        } else {
            error_log("Commandes trouvées pour le flashcode: " . $flashcode);
        }

        // Récupération des informations du client
        $client = $clientManager->getClientByFlashcodeId($flashcode);
    } catch (PDOException $e) {
        $error = "Erreur lors de la récupération de la commande.";
        debug("Erreur SQL", ['error' => $e->getMessage()]);
    }
}

// Traitement du formulaire de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($commandes)) {
    $nouveauStatut = $_POST['statut'] ?? '';
    $envoyerNotification = isset($_POST['envoyer_notification']);
    
    if (in_array($nouveauStatut, ['en_attente', 'en_cours', 'terminee', 'annulee'])) {
        try {
            // Mise à jour du statut
            $stmt = $db->prepare("UPDATE commandes SET statut = :statut WHERE id = :id");
            $stmt->execute([
                'statut' => $nouveauStatut,
                'id' => $commandes[0]['id']
            ]);
            
            // Envoi de la notification si demandé
            if ($envoyerNotification) {
                require_once 'includes/NotificationManager.php';
                $notificationManager = new NotificationManager($db);
                $result = $notificationManager->sendStatusNotification(
                    $commandes[0]['client_id'],
                    $commandes[0]['id'],
                    $nouveauStatut
                );
                
                if (!$result['success']) {
                    error_log("Erreur lors de l'envoi de la notification : " . $result['message']);
                }
            }
            
            $message = "Statut mis à jour avec succès.";
            
            // Recharger les commandes pour avoir les informations à jour
            $stmt = $db->prepare($query);
            $stmt->execute(['flashcode' => $flashcode]);
            $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } else {
        $error = "Statut invalide.";
    }
}

// Traitement du formulaire de création de nouvelle commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['description'])) {
    try {
        $db->beginTransaction();

        // Récupération du client
        $client = $clientManager->getClientByFlashcodeId($_POST['flashcode']);
        if (!$client) {
            throw new Exception("Client non trouvé");
        }

        // Préparation des données de la commande
        $commandeData = [
            'client_id' => $client['id'],
            'description' => $_POST['description'],
            'date_commande' => date('Y-m-d'),
            'date_livraison_prevue' => !empty($_POST['date_livraison_prevue']) ? $_POST['date_livraison_prevue'] : null,
            'statut' => 'en_attente'
        ];

        // Création de la commande
        $commandeId = $commandeManager->addCommande($commandeData);
        if (!$commandeId) {
            throw new Exception("Erreur lors de la création de la commande");
        }

        // Envoi de la notification
        $notificationManager->sendCommandeNotification(
            $client['id'],
            $commandeId,
            'new'
        );

        $db->commit();
        $message = "Nouvelle commande créée avec succès.";
        
        // Recharger les commandes
        $stmt = $db->prepare($query);
        $stmt->execute(['flashcode' => $_POST['flashcode']]);
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur lors de la création de la commande : " . $e->getMessage();
    }
}

// Fonction pour obtenir le libellé d'un statut
function getStatutLibelle($statut) {
    $libelles = [
        'en_attente' => 'En attente',
        'en_cours' => 'En cours',
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

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mise à jour de la commande</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        @media (max-width: 576px) {
            .container {
                padding: 10px;
            }
            .table-responsive {
                font-size: 0.9rem;
            }
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
            .card {
                margin-bottom: 1rem;
            }
            .card-header {
                padding: 0.75rem;
            }
            .card-body {
                padding: 0.75rem;
            }
            .form-control {
                font-size: 16px; /* Évite le zoom automatique sur iOS */
            }
            /* Amélioration du bouton d'action */
            .btn-action {
                width: 40px;
                height: 40px;
                padding: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                background-color: #0d6efd;
                color: white;
                border: none;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            }
            .btn-action i {
                font-size: 1.2rem;
            }
            .btn-action:active {
                transform: scale(0.95);
                background-color: #0b5ed7;
            }
        }

        /* Style pour l'impression */
        @media print {
            body * {
                visibility: hidden;
            }
            .print-content, .print-content * {
                visibility: visible;
            }
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
            }
            .etiquette {
                width: 28mm;
                height: 51mm;
                padding: 2mm;
                margin: 0;
                border: 1px solid #000;
                page-break-after: always;
                font-family: Arial, sans-serif;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            .etiquette .code {
                font-size: 14pt;
                font-weight: bold;
                text-align: center;
                margin-bottom: 1mm;
            }
            .etiquette .nom {
                font-size: 10pt;
                text-align: center;
                margin-bottom: 1mm;
            }
            .etiquette .commande {
                font-size: 8pt;
                text-align: center;
            }
            @page {
                size: 28mm 51mm;
                margin: 0;
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-3 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($commandes)): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 h5-md mb-0">Commandes existantes</h2>
                        <button onclick="window.print()" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-printer me-1"></i> Imprimer les étiquettes
                        </button>
                    </div>

                    <!-- Contenu pour l'impression -->
                    <div class="print-content">
                        <?php foreach ($commandes as $commande): ?>
                            <div class="etiquette">
                                <div class="code"><?php echo htmlspecialchars($flashcode); ?></div>
                                <div class="nom"><?php echo htmlspecialchars($commande['client_nom'] . ' ' . $commande['client_prenom']); ?></div>
                                <div class="commande">
                                    <?php echo htmlspecialchars($commande['reference']); ?><br>
                                    <?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Affichage normal -->
                    <div class="card mb-3 mb-md-4 shadow-sm">
                        <div class="card-header bg-white">
                            <h2 class="card-title h6 h5-md mb-0">Commandes existantes</h2>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="py-2">Actions</th>
                                            <th class="py-2">Référence</th>
                                            <th class="py-2">Date</th>
                                            <th class="py-2">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($commandes as $commande): ?>
                                            <tr>
                                                <td class="py-2">
                                                    <a href="commande_details.php?id=<?php echo $commande['id']; ?>" class="btn btn-action">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                                <td class="py-2"><?php echo htmlspecialchars($commande['reference']); ?></td>
                                                <td class="py-2"><?php echo date('d/m/Y', strtotime($commande['date_commande'])); ?></td>
                                                <td class="py-2">
                                                    <span class="badge bg-<?php echo getStatutClass($commande['statut']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $commande['statut'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Formulaire pour créer une nouvelle commande -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h2 class="card-title h6 h5-md mb-0">Créer une nouvelle commande</h2>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" class="needs-validation" novalidate>
                            <input type="hidden" name="flashcode" value="<?php echo htmlspecialchars($flashcode); ?>">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required 
                                    placeholder="Décrivez votre commande ici..."></textarea>
                                <div class="invalid-feedback">
                                    Veuillez entrer une description.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="date_livraison_prevue" class="form-label">Date de livraison prévue</label>
                                <input type="date" class="form-control" id="date_livraison_prevue" name="date_livraison_prevue">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-circle me-1"></i> Créer la commande
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation des formulaires Bootstrap
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html> 
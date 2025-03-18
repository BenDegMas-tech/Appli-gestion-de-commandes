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

// Récupération du client si un ID est spécifié
$client = null;
$flashcodeUrl = '';
$qrCodeImage = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $clientId = $_GET['id'];
    $client = $clientManager->getClientById($clientId);
    
    if ($client) {
        // Génération de l'URL pour le flashcode
        $flashcodeUrl = APP_URL . '/scan.php?code=' . $client['flashcode_id'];
        
        // Génération du QR code
        $qrCodeImage = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($flashcodeUrl);
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h1 class="mb-4">Génération de Flashcode</h1>
            
            <?php if (!$client): ?>
                <div class="alert alert-warning">
                    Veuillez sélectionner un client pour générer son flashcode.
                    <a href="clients.php" class="btn btn-primary ms-3">Liste des clients</a>
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="card-title h5 mb-0">Informations client</h2>
                    </div>
                    <div class="card-body">
                        <p><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></p>
                        <p><strong>Email :</strong> <?php echo htmlspecialchars($client['email']); ?></p>
                        <?php if (!empty($client['telephone'])): ?>
                            <p><strong>Téléphone :</strong> <?php echo htmlspecialchars($client['telephone']); ?></p>
                        <?php endif; ?>
                        <p><strong>ID Flashcode :</strong> <?php echo htmlspecialchars($client['flashcode_id']); ?></p>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="card-title h5 mb-0">Flashcode</h2>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <img src="<?php echo $qrCodeImage; ?>" alt="QR Code" class="img-fluid" style="max-width: 300px;">
                        </div>
                        
                        <div class="mb-3">
                            <p><strong>URL du flashcode :</strong> <a href="<?php echo htmlspecialchars($flashcodeUrl); ?>" target="_blank"><?php echo htmlspecialchars($flashcodeUrl); ?></a></p>
                        </div>
                        
                        <div class="btn-group">
                            <a href="<?php echo $qrCodeImage; ?>" download="flashcode_<?php echo $client['id']; ?>.png" class="btn btn-primary">Télécharger le QR Code</a>
                            <button class="btn btn-success" onclick="printQRCode()">Imprimer</button>
                            <button class="btn btn-info" onclick="printEtiquette()">Imprimer l'étiquette</button>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="card-title h5 mb-0">Instructions</h2>
                    </div>
                    <div class="card-body">
                        <p>Ce flashcode permet à votre client de suivre et mettre à jour ses commandes. Voici comment l'utiliser :</p>
                        <ol>
                            <li>Téléchargez ou imprimez le flashcode ci-dessus</li>
                            <li>Fournissez-le à votre client</li>
                            <li>Le client peut scanner ce code avec son smartphone pour accéder à ses commandes</li>
                            <li>Il pourra alors voir l'état de ses commandes et les mettre à jour si nécessaire</li>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="mt-3">
                <a href="clients.php" class="btn btn-secondary">Retour à la liste des clients</a>
            </div>
        </div>
    </div>
</div>

<script>
function printQRCode() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Flashcode - <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding: 20px;
                }
                .qr-container {
                    margin: 20px auto;
                    max-width: 400px;
                }
                .client-info {
                    margin-bottom: 20px;
                }
                .instructions {
                    text-align: left;
                    margin: 20px auto;
                    max-width: 500px;
                }
            </style>
        </head>
        <body>
            <h1>Flashcode - Suivi de commande</h1>
            
            <div class="client-info">
                <p><strong>Client :</strong> <?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></p>
                <p><strong>ID :</strong> <?php echo htmlspecialchars($client['flashcode_id']); ?></p>
            </div>
            
            <div class="qr-container">
                <img src="<?php echo $qrCodeImage; ?>" alt="QR Code" style="width: 100%; max-width: 300px;">
            </div>
            
            <div class="instructions">
                <h2>Comment utiliser ce flashcode</h2>
                <ol>
                    <li>Scannez ce code avec l'appareil photo de votre smartphone</li>
                    <li>Accédez à la page web qui s'affiche</li>
                    <li>Consultez l'état de vos commandes</li>
                    <li>Mettez à jour le statut si nécessaire</li>
                </ol>
            </div>
            
            <p>Merci de votre confiance !</p>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Lancer l'impression après le chargement de la page
    printWindow.onload = function() {
        printWindow.print();
        // printWindow.close();
    };
}

function printEtiquette() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Étiquette Flashcode</title>
            <style>
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
                    .etiquette .qr-code {
                        width: 20mm;
                        height: 20mm;
                        margin: 0 auto;
                    }
                    @page {
                        size: 28mm 51mm;
                        margin: 0;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-content">
                <div class="etiquette">
                    <div class="code"><?php echo htmlspecialchars($client['flashcode_id']); ?></div>
                    <div class="nom"><?php echo htmlspecialchars($client['nom'] . ' ' . $client['prenom']); ?></div>
                    <img src="<?php echo $qrCodeImage; ?>" alt="QR Code" class="qr-code">
                </div>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Lancer l'impression après le chargement de la page
    printWindow.onload = function() {
        printWindow.print();
        // printWindow.close();
    };
}
</script>

<?php include 'includes/footer.php'; ?> 
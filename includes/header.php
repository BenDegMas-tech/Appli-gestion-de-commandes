<?php
// Vérification que le fichier est inclus et non accédé directement
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Location: ../index.php');
    exit;
}

// Récupération du nom de la page actuelle
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .navbar {
            background-color: #343a40;
            padding: 0.5rem 1rem;
        }
        
        .navbar-brand {
            font-size: 1.25rem;
            font-weight: 500;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.5rem 1rem;
        }
        
        .nav-link:hover {
            color: #fff !important;
        }
        
        .nav-link.active {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            padding: 20px;
            margin-top: 60px;
        }
        
        .dropdown-menu {
            background-color: #343a40;
            border: none;
        }
        
        .dropdown-item {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .dropdown-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background-color: #343a40;
                padding: 1rem;
                margin-top: 0.5rem;
                border-radius: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo app_url('commandes.php'); ?>">
                <i class="bi bi-list-check me-2"></i><?php echo APP_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'commandes.php' ? 'active' : ''; ?>" href="<?php echo app_url('commandes.php'); ?>">
                            <i class="bi bi-list-check me-1"></i> Commandes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'clients.php' ? 'active' : ''; ?>" href="<?php echo app_url('clients.php'); ?>">
                            <i class="bi bi-people me-1"></i> Clients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'scan.php' ? 'active' : ''; ?>" href="<?php echo app_url('scan.php'); ?>">
                            <i class="bi bi-qr-code-scan me-1"></i> Scanner
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" href="<?php echo app_url('reports.php'); ?>">
                            <i class="bi bi-file-earmark-bar-graph me-1"></i> Rapports
                        </a>
                    </li>
                </ul>
                
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear me-1"></i> Administration
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                            <li>
                                <a class="dropdown-item <?php echo $currentPage === 'parametres.php' ? 'active' : ''; ?>" href="<?php echo app_url('parametres.php'); ?>">
                                    <i class="bi bi-gear me-2"></i> Paramètres
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo $currentPage === 'email_templates.php' ? 'active' : ''; ?>" href="<?php echo app_url('email_templates.php'); ?>">
                                    <i class="bi bi-envelope me-2"></i> Modèles d'e-mails
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <?php endif; ?>
                
                <ul class="navbar-nav ms-2">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo app_url('logout.php'); ?>">
                            <i class="bi bi-box-arrow-right me-1"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
        <!-- Le contenu principal sera ici -->
    </div>
</body>
</html> 
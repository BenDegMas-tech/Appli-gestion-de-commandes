-- Structure de la base de données pour l'application de gestion de commandes

-- Table des utilisateurs (administrateurs)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des clients
CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `flashcode_id` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flashcode_id` (`flashcode_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des commandes
CREATE TABLE IF NOT EXISTS `commandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `reference` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('en_attente','en_cours','terminee','annulee') NOT NULL DEFAULT 'en_attente',
  `date_commande` date NOT NULL,
  `date_livraison_prevue` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `client_id` (`client_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des mises à jour de commandes
CREATE TABLE IF NOT EXISTS `commandes_updates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `commande_id` int(11) NOT NULL,
  `ancien_statut` enum('en_attente','en_cours','terminee','annulee') DEFAULT NULL,
  `nouveau_statut` enum('en_attente','en_cours','terminee','annulee') NOT NULL,
  `commentaire` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `commandes_updates_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commandes_updates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `type` enum('email','sms') NOT NULL DEFAULT 'email',
  `statut` enum('en_attente','envoyee','echec') NOT NULL DEFAULT 'en_attente',
  `contenu` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `commande_id` (`commande_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des modèles d'e-mails
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `statut` enum('en_attente','en_cours','terminee','annulee') NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion d'un utilisateur administrateur par défaut (mot de passe: admin123)
INSERT INTO `users` (`nom`, `email`, `password`, `role`) VALUES
('Administrateur', 'admin@example.com', '$2y$10$8MNXAOYLSbWYRGX1jHIrEuGwJWu9LX0jVrGcJ9BmHrGGw9VJUvDUi', 'admin');

-- Insertion des modèles d'e-mails par défaut
INSERT INTO `email_templates` (`statut`, `sujet`, `contenu`) VALUES
('en_attente', 'Votre commande {reference} est en attente', 'Bonjour {client_nom},\n\nVotre commande {reference} a été mise en attente.\n\nCordialement,\nL\'équipe {app_name}'),
('en_cours', 'Votre commande {reference} est en cours de traitement', 'Bonjour {client_nom},\n\nVotre commande {reference} est maintenant en cours de traitement.\n\nCordialement,\nL\'équipe {app_name}'),
('terminee', 'Votre commande {reference} est terminée', 'Bonjour {client_nom},\n\nVotre commande {reference} est maintenant terminée.\n\nCordialement,\nL\'équipe {app_name}'),
('annulee', 'Votre commande {reference} a été annulée', 'Bonjour {client_nom},\n\nVotre commande {reference} a été annulée.\n\nCordialement,\nL\'équipe {app_name}'); 
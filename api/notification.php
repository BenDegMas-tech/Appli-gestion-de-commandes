<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/SendGridMailer.php';

/**
 * Classe de gestion des notifications
 */
class Notification {
    private $db;
    private $mailer;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = new Database();
        $this->mailer = new SendGridMailer(
            $this->db,
            SENDGRID_API_KEY,
            EMAIL_FROM,
            EMAIL_FROM_NAME
        );
    }
    
    /**
     * Envoie une notification pour une commande
     * 
     * @param int $commandeId ID de la commande
     * @param string $type Type de notification (new, status_change)
     * @param array $additionalData Données supplémentaires
     * @return bool
     */
    public function sendCommandeNotification($commandeId, $type, $additionalData = []) {
        try {
            // Récupérer les informations de la commande
            $commande = $this->db->fetchOne(
                "SELECT c.*, cl.nom, cl.prenom, cl.email, cl.telephone 
                FROM commandes c 
                JOIN clients cl ON c.client_id = cl.id 
                WHERE c.id = :id",
                ['id' => $commandeId]
            );
            
            if (!$commande) {
                return false;
            }
            
            // Préparer le contenu de la notification
            $content = $this->prepareNotificationContent($type, $commande, $additionalData);
            
            // Préparation des données pour le template
            $templateData = [
                'reference' => $commande['reference'],
                'client_nom' => $commande['nom'],
                'client_prenom' => $commande['prenom'],
                'client_email' => $commande['email'],
                'date_commande' => date('d/m/Y', strtotime($commande['date_commande'])),
                'statut' => isset($additionalData['new_status']) ? ucfirst(str_replace('_', ' ', $additionalData['new_status'])) : '',
                'app_name' => APP_NAME
            ];

            // Définition du sujet de l'email
            switch ($type) {
                case 'new':
                    $subject = "Confirmation de votre commande {reference}";
                    break;
                case 'status_change':
                    $subject = "Mise à jour du statut de votre commande {reference}";
                    break;
                default:
                    $subject = "Information concernant votre commande {reference}";
            }

            // Remplacer les variables dans le sujet
            foreach ($templateData as $key => $value) {
                $subject = str_replace('{' . $key . '}', $value, $subject);
            }

            // Remplacer les variables dans le contenu
            foreach ($templateData as $key => $value) {
                $content = str_replace('{' . $key . '}', $value, $content);
            }
            
            // Enregistrer la notification dans la base de données
            $notificationData = [
                'commande_id' => $commandeId,
                'client_id' => $commande['client_id'],
                'type' => 'email',
                'statut' => 'en_attente',
                'contenu' => $content,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $notificationId = $this->db->insert('notifications', $notificationData);
            
            if (!$notificationId) {
                error_log("Échec de l'insertion de la notification");
                return false;
            }
            
            // Envoyer un email si l'adresse email est disponible
            if (!empty($commande['email'])) {
                // Validation de l'adresse email
                $email = filter_var($commande['email'], FILTER_SANITIZE_EMAIL);
                error_log("Email original : " . $commande['email']);
                error_log("Email nettoyé : " . $email);
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    error_log("Adresse email invalide pour la commande {$commandeId}: {$commande['email']}");
                    return false;
                }

                // Envoi de l'email
                $emailResult = $this->mailer->sendEmail(
                    $email,
                    $subject,
                    $content,
                    $templateData
                );

                if (!$emailResult['success']) {
                    error_log("Erreur lors de l'envoi de l'email : " . $emailResult['message']);
                    error_log("Détails de l'erreur : " . json_encode($emailResult['error']));
                    return false;
                }
            }
            
            // Mise à jour du statut de la notification
            $stmt = $this->db->prepare("UPDATE notifications SET statut = 'envoyee' WHERE id = :id");
            $stmt->execute(['id' => $notificationId]);

            return [
                'success' => true,
                'message' => 'La commande a été mise à jour avec succès' . ($emailResult['success'] ? ' et l\'email de notification a été envoyé.' : ', mais l\'envoi de l\'email a échoué.')
            ];
        } catch (PDOException $e) {
            error_log('Erreur lors de l\'envoi de notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Prépare le contenu d'une notification
     * 
     * @param string $type Type de notification
     * @param array $commande Données de la commande
     * @param array $additionalData Données supplémentaires
     * @return string
     */
    private function prepareNotificationContent($type, $commande, $additionalData) {
        $content = '';
        
        switch ($type) {
            case 'new':
                $content = "Bonjour {client_prenom} {client_nom},\n\n";
                $content .= "Nous avons bien reçu votre commande (Référence: {reference}).\n";
                $content .= "Nous vous tiendrons informé de l'avancement de votre commande.\n\n";
                $content .= "Merci pour votre confiance.";
                break;
                
            case 'status_change':
                $newStatus = isset($additionalData['new_status']) ? $additionalData['new_status'] : '';
                $statusLabel = '';
                
                switch ($newStatus) {
                    case 'en_preparation':
                        $statusLabel = 'en préparation';
                        break;
                    case 'expedie':
                        $statusLabel = 'expédiée';
                        break;
                    case 'livre':
                        $statusLabel = 'livrée';
                        break;
                    case 'annule':
                        $statusLabel = 'annulée';
                        break;
                    default:
                        $statusLabel = $newStatus;
                }
                
                $content = "Bonjour {client_prenom} {client_nom},\n\n";
                $content .= "Le statut de votre commande (Référence: {reference}) a été mis à jour.\n";
                $content .= "Votre commande est maintenant {statut}.\n\n";
                
                if ($newStatus == 'expedie' && isset($additionalData['tracking_number'])) {
                    $content .= "Numéro de suivi: {$additionalData['tracking_number']}\n\n";
                }
                
                $content .= "Merci pour votre confiance.";
                break;
                
            default:
                $content = "Bonjour {client_prenom} {client_nom},\n\n";
                $content .= "Information concernant votre commande (Référence: {reference}).\n\n";
                $content .= "Merci pour votre confiance.";
        }
        
        return $content;
    }
    
    /**
     * Récupère les notifications d'un client
     * 
     * @param int $clientId ID du client
     * @return array
     */
    public function getClientNotifications($clientId) {
        return $this->db->fetchAll(
            "SELECT * FROM notifications WHERE client_id = :client_id ORDER BY created_at DESC",
            ['client_id' => $clientId]
        );
    }
    
    /**
     * Récupère les notifications d'une commande
     * 
     * @param int $commandeId ID de la commande
     * @return array
     */
    public function getCommandeNotifications($commandeId) {
        return $this->db->fetchAll(
            "SELECT * FROM notifications WHERE commande_id = :commande_id ORDER BY created_at DESC",
            ['commande_id' => $commandeId]
        );
    }
}
?> 
<?php
require_once __DIR__ . '/database.php';
require_once 'config.php';
require_once __DIR__ . '/notification.php';

/**
 * Classe de gestion des commandes
 */
class Commande {
    private $db;
    private $notification;

    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = new Database();
        $this->notification = new Notification();
    }

    /**
     * Récupère toutes les commandes avec filtres optionnels
     * 
     * @param array $filters Filtres (client_id, statut, etc.)
     * @return array
     */
    public function getAllCommandes($filters = []) {
        $query = "
            SELECT c.*, cl.nom, cl.prenom, cl.email, cl.telephone, cl.flashcode_id
            FROM commandes c
            JOIN clients cl ON c.client_id = cl.id
        ";
        
        $params = [];
        $where = [];
        
        if (!empty($filters['client_id'])) {
            $where[] = "c.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }
        
        if (!empty($filters['statut'])) {
            $where[] = "c.statut = :statut";
            $params['statut'] = $filters['statut'];
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        $query .= " ORDER BY c.date_commande DESC, c.id DESC";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Récupère une commande par son ID
     * 
     * @param int $id ID de la commande
     * @return array|false
     */
    public function getCommandeById($id) {
        $query = "
            SELECT c.*, cl.nom, cl.prenom, cl.email, cl.telephone, cl.flashcode_id
            FROM commandes c
            JOIN clients cl ON c.client_id = cl.id
            WHERE c.id = :id
        ";
        
        return $this->db->fetchOne($query, ['id' => $id]);
    }
    
    /**
     * Récupère une commande par sa référence
     * 
     * @param string $reference Référence de la commande
     * @return array|false
     */
    public function getCommandeByReference($reference) {
        $query = "
            SELECT c.*, cl.nom, cl.prenom, cl.email, cl.telephone, cl.flashcode_id
            FROM commandes c
            JOIN clients cl ON c.client_id = cl.id
            WHERE c.reference = :reference
        ";
        
        return $this->db->fetchOne($query, ['reference' => $reference]);
    }
    
    /**
     * Récupère les commandes d'un client
     * 
     * @param int $clientId ID du client
     * @return array
     */
    public function getCommandesByClientId($clientId) {
        $query = "
            SELECT c.*, cl.nom, cl.prenom
            FROM commandes c
            JOIN clients cl ON c.client_id = cl.id
            WHERE c.client_id = :client_id
            ORDER BY c.date_commande DESC, c.id DESC
        ";
        
        return $this->db->fetchAll($query, ['client_id' => $clientId]);
    }
    
    /**
     * Ajoute une nouvelle commande
     * 
     * @param array $data Données de la commande
     * @return int|false ID de la commande ajoutée ou false en cas d'échec
     */
    public function addCommande($data) {
        try {
            debug("Début de l'ajout de la commande", $data);
            
            $this->db->beginTransaction();
            
            // Génération d'une référence unique
            $data['reference'] = $this->generateReference();
            debug("Référence générée", ['reference' => $data['reference']]);
            
            // Préparation des données pour l'insertion
            $commandeData = array_intersect_key($data, array_flip([
                'client_id',
                'reference',
                'description',
                'statut',
                'date_commande',
                'date_livraison_prevue',
                'created_by'
            ]));
            
            // Insertion de la commande
            $commandeId = $this->db->insert('commandes', $commandeData);
            debug("Résultat de l'insertion", ['commande_id' => $commandeId]);
            
            if (!$commandeId) {
                $this->db->rollBack();
                debug("Échec de l'insertion de la commande");
                return false;
            }
            
            // Ajout de l'historique de mise à jour
            $updateData = [
                'commande_id' => $commandeId,
                'ancien_statut' => null,
                'nouveau_statut' => $data['statut'],
                'commentaire' => $data['commentaire'] ?? 'Création de la commande',
                'created_by' => $data['created_by'] ?? null
            ];
            debug("Données de mise à jour", $updateData);
            
            $updateId = $this->db->insert('commandes_updates', $updateData);
            debug("Résultat de l'insertion de la mise à jour", ['update_id' => $updateId]);
            
            if (!$updateId) {
                $this->db->rollBack();
                debug("Échec de l'insertion de la mise à jour");
                return false;
            }
            
            // Envoi d'une notification au client
            $this->notification->sendCommandeNotification(
                $commandeId,
                'new'
            );
            
            $this->db->commit();
            debug("Commande ajoutée avec succès", ['commande_id' => $commandeId]);
            return $commandeId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            debug("Erreur lors de l'ajout de la commande", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            error_log('Erreur lors de l\'ajout de la commande : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Met à jour une commande
     * 
     * @param int $id ID de la commande
     * @param array $data Données de la commande
     * @return bool
     */
    public function updateCommande($id, $data) {
        try {
            $this->db->beginTransaction();
            
            // Récupération de l'ancien statut
            $commande = $this->getCommandeById($id);
            if (!$commande) {
                $this->db->rollBack();
                return false;
            }
            
            $ancienStatut = $commande['statut'];
            $nouveauStatut = $data['statut'] ?? $ancienStatut;
            
            // Données à mettre à jour dans la table commandes
            $commandeData = array_intersect_key($data, array_flip([
                'client_id', 'description', 'statut', 
                'date_commande', 'date_livraison_prevue'
            ]));
            
            // Mise à jour de la commande
            if (!empty($commandeData)) {
                $result = $this->db->update('commandes', $commandeData, 'id = :id', ['id' => $id]);
                if (!$result) {
                    $this->db->rollBack();
                    return false;
                }
            }
            
            // Ajout de l'historique de mise à jour si le statut a changé
            if ($nouveauStatut !== $ancienStatut || !empty($data['commentaire'])) {
                $updateData = [
                    'commande_id' => $id,
                    'ancien_statut' => $ancienStatut,
                    'nouveau_statut' => $nouveauStatut,
                    'commentaire' => $data['commentaire'] ?? 'Mise à jour du statut',
                    'created_by' => $data['updated_by'] ?? null
                ];
                
                $updateId = $this->db->insert('commandes_updates', $updateData);
                
                if (!$updateId) {
                    $this->db->rollBack();
                    return false;
                }
            }
            
            $this->db->commit();
            
            // Envoi de la notification en dehors de la transaction
            if ($nouveauStatut !== $ancienStatut) {
                try {
                    $this->notification->sendCommandeNotification(
                        $id,
                        'status_change',
                        ['new_status' => $nouveauStatut]
                    );
                } catch (Exception $e) {
                    error_log("Erreur lors de l'envoi de la notification : " . $e->getMessage());
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Erreur lors de la mise à jour de la commande : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprime une commande
     * 
     * @param int $id ID de la commande
     * @return bool
     */
    public function deleteCommande($id) {
        return $this->db->delete('commandes', 'id = :id', ['id' => $id]);
    }
    
    /**
     * Récupère l'historique des mises à jour d'une commande
     * 
     * @param int $commandeId ID de la commande
     * @return array
     */
    public function getCommandeUpdates($commandeId) {
        $query = "
            SELECT cu.*, u.nom as user_nom, u.prenom as user_prenom
            FROM commandes_updates cu
            LEFT JOIN users u ON cu.created_by = u.id
            WHERE cu.commande_id = :commande_id
            ORDER BY cu.created_at DESC
        ";
        
        return $this->db->fetchAll($query, ['commande_id' => $commandeId]);
    }
    
    /**
     * Génère une référence unique pour une commande
     * 
     * @return string
     */
    private function generateReference() {
        $prefix = 'CMD';
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return $prefix . $date . $random;
    }

    /**
     * Met à jour le statut d'une commande via un flashcode
     * @param string $flashcodeId ID du flashcode
     * @param int $commandeId ID de la commande
     * @param string $nouveauStatut Nouveau statut
     * @param string $commentaire Commentaire optionnel
     * @return bool Succès de l'opération
     */
    public function updateCommandeViaFlashcode($flashcodeId, $commandeId, $nouveauStatut, $commentaire = null) {
        try {
            $this->db->beginTransaction();
            
            // Vérification que le flashcode correspond bien à un client
            $stmt = $this->db->prepare("
                SELECT c.id, c.client_id, c.statut, cl.flashcode_id 
                FROM commandes c
                JOIN clients cl ON c.client_id = cl.id
                WHERE c.id = ? AND cl.flashcode_id = ?
            ");
            $stmt->execute([$commandeId, $flashcodeId]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$commande) {
                return false;
            }
            
            // Mise à jour du statut
            $stmt = $this->db->prepare("UPDATE commandes SET statut = ? WHERE id = ?");
            $stmt->execute([$nouveauStatut, $commandeId]);
            
            // Enregistrement de la mise à jour
            $this->addCommandeUpdate([
                'commande_id' => $commandeId,
                'ancien_statut' => $commande['statut'],
                'nouveau_statut' => $nouveauStatut,
                'commentaire' => $commentaire ?? 'Mise à jour via flashcode',
                'created_by' => null // Mise à jour par le client
            ]);
            
            // Envoi d'une notification
            $this->notification->sendCommandeNotification(
                $commande['client_id'],
                $commandeId,
                'statut_change_client'
            );
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la mise à jour de la commande via flashcode: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ajoute une mise à jour de commande
     * @param array $data Données de la mise à jour
     * @return int|false ID de la mise à jour créée ou false en cas d'erreur
     */
    public function addCommandeUpdate($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO commandes_updates (
                    commande_id, ancien_statut, nouveau_statut, 
                    commentaire, created_by
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['commande_id'],
                $data['ancien_statut'],
                $data['nouveau_statut'],
                $data['commentaire'] ?? null,
                $data['created_by'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur lors de l'ajout de la mise à jour de commande: " . $e->getMessage());
            return false;
        }
    }
}
?> 
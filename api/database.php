<?php
require_once __DIR__ . '/config.php';

/**
 * Établit et retourne une connexion à la base de données
 * @return PDO Instance de connexion à la base de données
 * @throws PDOException En cas d'erreur de connexion
 */
function get_db_connection() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                DB_HOST,
                DB_NAME
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $db = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Erreur de connexion à la base de données : " . $e->getMessage());
            throw new PDOException("Impossible de se connecter à la base de données. Veuillez réessayer plus tard.");
        }
    }
    
    return $db;
}

/**
 * Classe de gestion de la base de données
 */
class Database {
    private $pdo;
    
    /**
     * Constructeur - établit la connexion à la base de données
     */
    public function __construct() {
        try {
            // Construire le DSN en fonction des paramètres disponibles
            if (defined('DB_SOCKET') && !empty(DB_SOCKET)) {
                // Utiliser le socket Unix
                $dsn = "mysql:unix_socket=" . DB_SOCKET . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            } else {
                // Utiliser host et port
                $port = defined('DB_PORT') ? DB_PORT : '3306';
                $dsn = "mysql:host=" . DB_HOST . ";port=" . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            }
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Définir le jeu de caractères
            $this->pdo->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            // Journaliser l'erreur avec plus de détails
            error_log("Erreur de connexion à la base de données: " . $e->getMessage());
            
            // Afficher un message d'erreur plus convivial
            die(ERROR_DB_CONNECTION . ': Impossible de se connecter à la base de données. Veuillez vérifier vos paramètres de connexion.');
        }
    }
    
    /**
     * Prépare une requête SQL
     * 
     * @param string $query Requête SQL
     * @return PDOStatement
     */
    public function prepare($query) {
        return $this->pdo->prepare($query);
    }
    
    /**
     * Exécute une requête SQL
     * 
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return PDOStatement
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Erreur SQL : ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Récupère une seule ligne de résultat
     * 
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return array|false
     */
    public function fetchOne($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }
    
    /**
     * Récupère toutes les lignes de résultat
     * 
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return array
     */
    public function fetchAll($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insère des données dans une table
     * 
     * @param string $table Nom de la table
     * @param array $data Données à insérer (clé => valeur)
     * @return int|false ID de la dernière insertion ou false en cas d'échec
     */
    public function insert($table, $data) {
        try {
            $columns = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            
            $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
            error_log("Requête SQL générée : " . $query);
            error_log("Données à insérer : " . print_r($data, true));
            
            $stmt = $this->pdo->prepare($query);
            
            foreach ($data as $key => $value) {
                $type = PDO::PARAM_STR;
                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = PDO::PARAM_NULL;
                }
                $stmt->bindValue(':' . $key, $value, $type);
            }
            
            if ($stmt->execute()) {
                $lastId = $this->pdo->lastInsertId();
                error_log("Insertion réussie, dernier ID : " . $lastId);
                return $lastId;
            }
            
            error_log("Échec de l'exécution de la requête");
            return false;
        } catch (PDOException $e) {
            error_log('Erreur d\'insertion : ' . $e->getMessage());
            error_log('Code erreur : ' . $e->getCode());
            error_log('Trace : ' . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Met à jour des données dans une table
     * 
     * @param string $table Nom de la table
     * @param array $data Données à mettre à jour (clé => valeur)
     * @param string $where Condition WHERE
     * @param array $whereParams Paramètres de la condition WHERE
     * @return bool
     */
    public function update($table, $data, $where, $whereParams = []) {
        try {
            $set = [];
            foreach (array_keys($data) as $column) {
                $set[] = "$column = :$column";
            }
            
            $query = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
            $stmt = $this->pdo->prepare($query);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            foreach ($whereParams as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erreur de mise à jour : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Supprime des données d'une table
     * 
     * @param string $table Nom de la table
     * @param string $where Condition WHERE
     * @param array $params Paramètres de la condition WHERE
     * @return bool
     */
    public function delete($table, $where, $params = []) {
        try {
            $query = "DELETE FROM $table WHERE $where";
            $stmt = $this->pdo->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Erreur de suppression : ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère l'ID de la dernière insertion
     * 
     * @param string $name Nom de la séquence (optionnel)
     * @return string
     */
    public function lastInsertId($name = null) {
        return $this->pdo->lastInsertId($name);
    }
    
    /**
     * Commence une transaction
     * 
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Valide une transaction
     * 
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Annule une transaction
     * 
     * @return bool
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
?> 
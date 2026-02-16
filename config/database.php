<?php
/**
 * Configuration de la Base de Données
 * Fichier: config/database.php
 */

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration de la base de données
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'central_reclamation');

// Classe Proxy pour injecter le filtre d'année
class YearAwarePDO {
    private $pdo;
    private $tables = ['tickets', 'historique_actions', 'historique_statut', 'commentaires', 'reponses_fournisseur', 'notes_rfq', 'satisfaction_client', 'pieces_jointes'];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function injectYear($sql) {
        if (empty($sql)) return $sql;
        
        // Récupérer l'année active depuis la base de données (Source Unique de Vérité)
        static $activeYear = null;
        if ($activeYear === null) {
            try {
                // Utiliser la connexion PDO brute pour éviter la récursion
                $stmt = $this->pdo->query("SELECT annee FROM gestion_annees WHERE active = 1 LIMIT 1");
                $bgYear = $stmt->fetchColumn();
                $activeYear = $bgYear ? (int)$bgYear : (int)date('Y');
            } catch (Exception $e) {
                $activeYear = (int)date('Y');
            }
        }
        
        $year = $activeYear;
        
        $tables_regex = implode('|', $this->tables);

        // Ne pas injecter si l'année est déjà présente ou si c'est une requête sur gestion_annees
        if (preg_match("/\bannee\s*=/i", $sql) || preg_match("/\bgestion_annees\b/i", $sql)) {
            return $sql;
        }

        // 1. SELECT, UPDATE, DELETE
        if (preg_match("/\b(SELECT|UPDATE|DELETE)\b/i", $sql) && preg_match("/\b($tables_regex)\b/i", $sql)) {
            if (preg_match("/WHERE/i", $sql)) {
                $sql = preg_replace("/WHERE/i", "WHERE annee = $year AND ", $sql);
            } else {
                if (preg_match("/\b(ORDER|GROUP|LIMIT)\b/i", $sql, $matches, PREG_OFFSET_CAPTURE)) {
                    $pos = $matches[0][1];
                    $sql = substr($sql, 0, $pos) . " WHERE annee = $year " . substr($sql, $pos);
                } else {
                    $sql .= " WHERE annee = $year";
                }
            }
        }

        // 2. INSERT
        if (preg_match("/INSERT\s+INTO\s+($tables_regex)\s*\((.*?)\)\s*VALUES\s*\((.*)\)/is", $sql, $matches)) {
            $table = $matches[1];
            $cols = $matches[2];
            $vals = $matches[3];
            if (strpos($cols, 'annee') === false) {
                $sql = "INSERT INTO $table ($cols, annee) VALUES ($vals, $year)";
            }
        }

        return $sql;
    }

    public function prepare($sql, $options = []) {
        return $this->pdo->prepare($this->injectYear($sql), $options);
    }

    public function query($sql, $fetchMode = null, ...$params) {
        return $this->pdo->query($this->injectYear($sql), $fetchMode, ...$params);
    }

    public function exec($sql) {
        return $this->pdo->exec($this->injectYear($sql));
    }

    public function __call($name, $arguments) {
        return call_user_func_array([$this->pdo, $name], $arguments);
    }
}

// Classe de connexion à la base de données
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET CHARACTER SET utf8mb4");
            
            // On enveloppe PDO
            $this->conn = new YearAwarePDO($pdo);
        } catch(PDOException $e) {
            die("Erreur de connexion : " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
}

// Fonction utilitaire pour obtenir la connexion
function getDB() {
    return Database::getInstance()->getConnection();
}

// Configuration du site
define('SITE_NAME', 'Central de Réclamation');
define('SITE_URL', 'http://localhost/central_reclamation');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Configuration email (à configurer selon votre serveur)
define('MAIL_FROM', 'noreply@reclamation.com');
define('MAIL_FROM_NAME', 'Central de Réclamation');

?>
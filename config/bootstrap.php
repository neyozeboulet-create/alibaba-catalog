<?php
/**
 * Bootstrap principal - Initialisation de l'application
 * Inclus au début de chaque fichier PHP public
 */

// 1. Configuration du fuseau horaire
date_default_timezone_set('Africa/Abidjan');

// 2. Gestion des erreurs selon l'environnement
$config = require __DIR__ . '/database.php';

if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// 3. Connexion PDO (singleton) - DÉFINIE AVANT LES SESSIONS
function getPDO(): PDO
{
    static $pdo = null;
    global $config;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['name'],
            $config['db']['charset']
        );
        
        try {
            $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], $config['db']['options']);
            
            // Auto-migration au premier démarrage (une seule fois par déploiement)
            static $migrated = false;
            if (!$migrated) {
                $migrated = true;
                runMigrations($pdo);
            }
            
        } catch (PDOException $e) {
            // En production, logger l'erreur au lieu de l'afficher
            if ($config['app']['debug']) {
                die('Erreur de connexion à la base de données : ' . $e->getMessage());
            } else {
                error_log('DB Connection Error: ' . $e->getMessage());
                die('Service temporairement indisponible. Veuillez réessayer plus tard.');
            }
        }
    }
    
    return $pdo;
}

// Exécuter les migrations SQL au démarrage
function runMigrations(PDO $pdo): void
{
    $sqlFile = __DIR__ . '/../database.sql';
    
    if (!file_exists($sqlFile)) {
        return;
    }
    
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        return;
    }
    
    try {
        // Extraire le nom de la base depuis la config
        $dbName = $GLOBALS['config']['db']['name'] ?? 'railway';
        
        // Créer la base si elle n'existe pas (Railway peut ne pas créer le nom attendu)
        try {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS \`$dbName\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // Ignorer si la base existe déjà
        }
        
        // Connecter à la bonne base
        $pdo->exec("USE \`$dbName\`");
        
        // Séparer les statements (gestion basique)
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (empty($statement) || str_starts_with($statement, '--')) {
                continue;
            }
            
            // Ignorer CREATE DATABASE et USE (déjà géré)
            if (stripos($statement, 'CREATE DATABASE') !== false || 
                stripos($statement, 'USE ') !== false) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignorer les erreurs "table already exists" (code 42S01)
                if ($e->getCode() !== '42S01' && $e->getCode() !== 'HY000') {
                    error_log('Migration error: ' . $e->getMessage());
                }
            }
        }
        
        error_log('Database migrations executed successfully for database: ' . $dbName);
    } catch (PDOException $e) {
        error_log('Migration setup error: ' . $e->getMessage());
    }
}

// 4. Configuration de session sécurisée (en base de données pour Railway)
// Railway a un système de fichiers éphémère - sessions doivent être en BDD

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->initTable();
    }
    
    private function initTable(): void {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS php_sessions (
                    id VARCHAR(128) NOT NULL PRIMARY KEY,
                    data TEXT NOT NULL,
                    expiry INT UNSIGNED NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            // La table existe déjà, ignorer l'erreur
        }
    }
    
    public function open($savePath, $sessionName): bool {
        return true;
    }
    
    public function close(): bool {
        return true;
    }
    
    public function read($id): string|false {
        $stmt = $this->pdo->prepare("SELECT data FROM php_sessions WHERE id = ? AND expiry > ?");
        $stmt->execute([$id, time()]);
        return $stmt->fetchColumn() ?: '';
    }
    
    public function write($id, $data): bool {
        $expiry = time() + $this->getLifetime();
        $stmt = $this->pdo->prepare("
            INSERT INTO php_sessions (id, data, expiry) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data), expiry = VALUES(expiry)
        ");
        return $stmt->execute([$id, $data, $expiry]);
    }
    
    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function gc($maxLifetime): int|false {
        $stmt = $this->pdo->prepare("DELETE FROM php_sessions WHERE expiry < ?");
        $stmt->execute([time()]);
        return $stmt->rowCount();
    }
    
    private function getLifetime(): int {
        global $config;
        return $config['security']['session_lifetime'] ?? 86400;
    }
}

// Enregistrer le handler de session
$handler = new DatabaseSessionHandler(getPDO());
session_set_save_handler($handler, true);

// Configuration des cookies
session_set_cookie_params([
    'lifetime' => $config['security']['session_lifetime'],
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Démarrer la session
session_start();

// Régénération d'ID de session pour éviter la fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// 5. Fonction helper pour récupérer la config
function config(string $key, $default = null)
{
    global $config;
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

// 6. Protection CSRF simple
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 7. Fonction de redirection
function redirect(string $url, int $statusCode = 302): never
{
    http_response_code($statusCode);
    header('Location: ' . $url);
    exit;
}

// 8. Fonction de flash messages (messages temporaires)
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// 9. Nettoyage de sortie (anti-XSS)
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 10. Vérification si requête AJAX
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// 11. Réponse JSON standardisée
function jsonResponse(array $data, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
<?php
/**
 * Configuration de la base de données - Connexion PDO sécurisée
 * Fichier à ne JAMAIS commiter dans un dépôt public (gitignore)
 */

// Chargement des variables d'environnement (optionnel, pour production)
// require_once __DIR__ . '/../vendor/autoload.php'; // Si utilisation de vlucas/phpdotenv

return [
    // Configuration de la base de données
    // Railway MySQL plugin fournit: MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE
    'db' => [
        'host' => $_ENV['MYSQLHOST'] ?? $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['MYSQLPORT'] ?? $_ENV['DB_PORT'] ?? '3306',
        'name' => $_ENV['MYSQLDATABASE'] ?? $_ENV['DB_NAME'] ?? 'alibaba_catalog',
        'user' => $_ENV['MYSQLUSER'] ?? $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['MYSQLPASSWORD'] ?? $_ENV['DB_PASS'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ],
    ],

    // Configuration de l'application
    'app' => [
        'name' => 'Catalogue Alibaba Import',
        'version' => '1.0.0',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        'timezone' => 'Africa/Abidjan',
        'currency' => 'F CFA',
        'currency_symbol' => 'F CFA',
        'margin_rate' => 0.10, // 10% de marge
    ],

    // Configuration de sécurité
    'security' => [
        // Token secret pour l'API d'importation (OBLIGATOIRE EN PRODUCTION !)
        // Générer avec: openssl rand -hex 32
        'import_api_token' => $_ENV['IMPORT_API_TOKEN'] ?? $_ENV['ALB_IMPORT_TOKEN'] ?? '',
        
        // Clé de session (doit être complexe et unique, 64+ chars)
        // Générer avec: openssl rand -hex 64
        'session_secret' => $_ENV['SESSION_SECRET'] ?? $_ENV['ALB_SESSION_SECRET'] ?? '',
        
        // Durée de session (24h)
        'session_lifetime' => 86400,
        
        // Admin par défaut (username => password_hash)
        // Générer hash avec: php -r "echo password_hash('VOTRE_MDP', PASSWORD_DEFAULT);"
        'admin_users' => [
            'admin' => $_ENV['ADMIN_PASSWORD_HASH'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ],
        
        // Email destinataire pour les notifications de commande
        'admin_email' => $_ENV['ADMIN_EMAIL'] ?? '',
        
        // Configuration email (SMTP requis en production)
        'mail' => [
            'from_email' => $_ENV['MAIL_FROM'] ?? 'noreply@' . ($_ENV['RAILWAY_STATIC_URL'] ?? 'alibaba-catalog.local'),
            'from_name' => 'Catalogue Alibaba Import',
            'smtp_host' => $_ENV['SMTP_HOST'] ?? '',
            'smtp_port' => (int)($_ENV['SMTP_PORT'] ?? 587),
            'smtp_user' => $_ENV['SMTP_USER'] ?? '',
            'smtp_pass' => $_ENV['SMTP_PASS'] ?? '',
            'smtp_secure' => $_ENV['SMTP_SECURE'] ?? 'tls',
        ],
    ],

    // Configuration Mobile Money
    'mobile_money' => [
        'number' => $_ENV['MOBILE_MONEY_NUMBER'] ?? '+225 XX XX XX XX',
        'networks' => ['Moov Money', 'TMoney', 'Orange Money', 'Wave'],
        'instructions' => 'Effectuez le transfert manuel de {montant} {devise} par Mobile Money sur le numéro {numero}. Votre commande sera traitée dès réception de votre transfert.',
    ],
];
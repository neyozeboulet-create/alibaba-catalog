<?php
/**
 * Diagnostic MySQL standalone - NE PAS INCLURE bootstrap.php
 * Teste la connexion MySQL directement
 */

header('Content-Type: application/json; charset=utf-8');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'extensions' => [],
    'environment' => [],
    'connection_test' => null,
    'error' => null
];

// Test 1: Extensions PHP
$results['extensions']['pdo'] = extension_loaded('pdo') ? 'OK' : 'MISSING';
$results['extensions']['pdo_mysql'] = extension_loaded('pdo_mysql') ? 'OK (' . phpversion('pdo_mysql') . ')' : 'MISSING';
$results['extensions']['sockets'] = extension_loaded('sockets') ? 'OK' : 'MISSING';

// Test 2: Variables d'environnement
$envVars = ['MYSQLHOST', 'MYSQLPORT', 'MYSQLUSER', 'MYSQLPASSWORD', 'MYSQLDATABASE'];
foreach ($envVars as $var) {
    $value = getenv($var);
    if ($value) {
        // Masquer le mot de passe
        if ($var === 'MYSQLPASSWORD') {
            $results['environment'][$var] = '***' . substr($value, -4);
        } else {
            $results['environment'][$var] = $value;
        }
    } else {
        $results['environment'][$var] = 'NOT SET';
    }
}

// Test 3: Connexion MySQL
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$port = getenv('MYSQLPORT') ?: '3306';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db = getenv('MYSQLDATABASE') ?: 'railway';

if (empty($pass)) {
    $results['error'] = 'MYSQLPASSWORD not set in environment';
} else {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        
        // Test avec timeout court
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        
        // Test de requête
        $stmt = $pdo->query('SELECT 1 as test');
        $stmt->fetch();
        
        // Lister les tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        $results['connection_test'] = [
            'status' => 'SUCCESS',
            'dsn' => $dsn,
            'tables_found' => count($tables),
            'tables' => $tables
        ];
        
    } catch (PDOException $e) {
        $results['connection_test'] = [
            'status' => 'FAILED',
            'error_code' => $e->getCode(),
            'error_message' => $e->getMessage(),
            'dsn' => "mysql:host={$host};port={$port};dbname={$db}"
        ];
    }
}

// Test 4: Test TCP (optionnel)
if (function_exists('fsockopen')) {
    $sock = @fsockopen($host, (int)$port, $errno, $errstr, 2);
    if ($sock) {
        fclose($sock);
        $results['tcp_test'] = 'PORT OPEN';
    } else {
        $results['tcp_test'] = 'PORT CLOSED: ' . $errno . ' - ' . $errstr;
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
<?php
/**
 * Diagnostic MySQL - Test de connexion détaillé
 */

// Charger la config sans bootstrap
$config = require __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'config' => [
        'host' => $config['db']['host'] ?? 'NOT SET',
        'port' => $config['db']['port'] ?? 'NOT SET',
        'name' => $config['db']['name'] ?? 'NOT SET',
        'user' => $config['db']['user'] ?? 'NOT SET',
        'charset' => $config['db']['charset'] ?? 'utf8mb4',
    ],
    'tests' => [],
    'error' => null
];

// Test 1: Variables d'environnement
$tests = [];

$mysqlHost = getenv('MYSQLHOST') ?: ($config['db']['host'] ?? null);
$mysqlPort = getenv('MYSQLPORT') ?: ($config['db']['port'] ?? null);
$mysqlUser = getenv('MYSQLUSER') ?: ($config['db']['user'] ?? null);
$mysqlPass = getenv('MYSQLPASSWORD') ?: ($config['db']['pass'] ?? null);
$mysqlDb = getenv('MYSQLDATABASE') ?: ($config['db']['name'] ?? null);

$tests['env_mysql_host'] = $mysqlHost ?: 'NOT FOUND';
$tests['env_mysql_port'] = $mysqlPort ?: 'NOT FOUND';
$tests['env_mysql_user'] = $mysqlUser ?: 'NOT FOUND';
$tests['env_mysql_pass'] = $mysqlPass ? 'SET (' . strlen($mysqlPass) . ' chars)' : 'NOT FOUND';
$tests['env_mysql_db'] = $mysqlDb ?: 'NOT FOUND';

// Test 2: Extensions PHP
$tests['pdo_mysql'] = extension_loaded('pdo_mysql') ? 'OK (' . phpversion('pdo_mysql') . ')' : 'MISSING';
$tests['pdo'] = extension_loaded('pdo') ? 'OK' : 'MISSING';

// Test 3: Tentative de connexion
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $mysqlHost, $mysqlPort, $mysqlDb);
    $tests['dsn'] = $dsn;
    
    $pdo = new PDO($dsn, $mysqlUser, $mysqlPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    
    $result = $pdo->query('SELECT 1 as test');
    $tests['connection'] = 'SUCCESS';
    
    // Test 4: Lister les tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $tests['tables_count'] = count($tables);
    $tests['tables'] = $tables;
    
    $response['status'] = 'ok';
    
} catch (PDOException $e) {
    $tests['connection'] = 'FAILED: ' . $e->getCode();
    $tests['connection_error'] = $e->getMessage();
    
    // Tests de debug réseau
    $tests['tcp_test'] = function_exists('fsockopen') ? (@fsockopen($mysqlHost, (int)$mysqlPort, $errno, $errstr, 2) ? 'OPEN' : 'CLOSED: ' . $errno) : 'SKIPPED';
    
    $response['status'] = 'error';
    $response['error'] = $e->getMessage();
}

$response['tests'] = $tests;

echo json_encode($response, JSON_PRETTY_PRINT);
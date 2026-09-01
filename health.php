<?php
/**
 * Endpoint de test de connexion MySQL
 * URL : /health
 * Retourne JSON avec statut de connexion
 */

require_once __DIR__ . '/config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getPDO();
    $result = $pdo->query('SELECT 1 as test');
    if ($result->fetch()) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Database connected successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit(0);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    http_response_code(500);
    exit(1);
}
<?php
/**
 * API d'importation de produits depuis Alibaba
 * Endpoint sécurisé par token pour insertion en base de données
 * 
 * Méthode : POST
 * Headers requis : 
 *   - Content-Type: application/json
 *   - X-API-Token: [token_secret]
 * 
 * Corps JSON attendu :
 * {
 *   "sku": "ALB-001",
 *   "titre": "Nom du produit",
 *   "description": "Description optionnelle",
 *   "prix_origine": 15000.00,
 *   "image_url": "https://exemple.com/image.jpg"
 * }
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérification méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse([
        'success' => false,
        'error' => 'Méthode non autorisée. Utilisez POST.',
        'code' => 'METHOD_NOT_ALLOWED'
    ], 405);
}

// Vérification Content-Type JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    jsonResponse([
        'success' => false,
        'error' => 'Content-Type doit être application/json',
        'code' => 'INVALID_CONTENT_TYPE'
    ], 400);
}

// Récupération du corps JSON
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    jsonResponse([
        'success' => false,
        'error' => 'Corps de la requête vide',
        'code' => 'EMPTY_BODY'
    ], 400);
}

// Décodage JSON
$data = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse([
        'success' => false,
        'error' => 'JSON invalide : ' . json_last_error_msg(),
        'code' => 'INVALID_JSON'
    ], 400);
}

// Vérification du token API
$providedToken = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_X-API-TOKEN'] ?? null;
$expectedToken = config('security.import_api_token');

if (empty($providedToken) || !hash_equals($expectedToken, $providedToken)) {
    // Logger la tentative d'accès non autorisé (sans exposer le token correct)
    error_log('Tentative d\'API import avec token invalide depuis IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue'));
    
    jsonResponse([
        'success' => false,
        'error' => 'Token API invalide ou manquant',
        'code' => 'INVALID_TOKEN'
    ], 401);
}

// =====================================================================
// VALIDATION DES DONNÉES REÇUES
// =====================================================================

$errors = [];

// SKU obligatoire
if (empty($data['sku'])) {
    $errors['sku'] = 'Le SKU est obligatoire';
} elseif (!validerSku($data['sku'])) {
    $errors['sku'] = 'Le SKU doit contenir 3 à 50 caractères alphanumériques, tirets ou underscores';
}

// Titre obligatoire
if (empty($data['titre'])) {
    $errors['titre'] = 'Le titre est obligatoire';
} elseif (mb_strlen($data['titre'], 'UTF-8') > 255) {
    $errors['titre'] = 'Le titre ne doit pas dépasser 255 caractères';
}

// Prix obligatoire et valide
if (!isset($data['prix_origine']) || $data['prix_origine'] === '') {
    $errors['prix_origine'] = 'Le prix d\'origine est obligatoire';
} elseif (!is_numeric($data['prix_origine'])) {
    $errors['prix_origine'] = 'Le prix doit être un nombre';
} elseif (!validerPrix((float)$data['prix_origine'])) {
    $errors['prix_origine'] = 'Le prix doit être positif et inférieur à 10 millions';
}

// Description optionnelle mais longueur max
if (!empty($data['description']) && mb_strlen($data['description'], 'UTF-8') > 5000) {
    $errors['description'] = 'La description ne doit pas dépasser 5000 caractères';
}

// Image URL optionnelle
if (!empty($data['image_url'])) {
    $imageUrl = filter_var($data['image_url'], FILTER_SANITIZE_URL);
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $errors['image_url'] = 'L\'URL de l\'image est invalide';
    }
}

if (!empty($errors)) {
    jsonResponse([
        'success' => false,
        'error' => 'Données invalides',
        'validation_errors' => $errors,
        'code' => 'VALIDATION_ERROR'
    ], 422);
}

// =====================================================================
// INSERTION EN BASE DE DONNÉES
// =====================================================================

try {
    $pdo = getPDO();
    
    // Préparation des données nettoyées
    $produitData = [
        'sku' => sanitizeString($data['sku']),
        'titre' => sanitizeString($data['titre']),
        'description' => !empty($data['description']) ? sanitizeString($data['description'], 5000) : null,
        'prix_origine' => (float)$data['prix_origine'],
        'image_url' => !empty($data['image_url']) ? sanitizeString($data['image_url']) : null,
    ];
    
    // Tentative d'insertion
    $produitId = insererProduit($pdo, $produitData);
    
    if ($produitId === false) {
        // Le SKU existe déjà
        jsonResponse([
            'success' => false,
            'error' => 'Un produit avec ce SKU existe déjà',
            'existing_sku' => $data['sku'],
            'code' => 'DUPLICATE_SKU'
        ], 409);
    }
    
    // Récupérer le produit inséré pour confirmation
    $produit = getProduitById($pdo, $produitId);
    
    // Journalisation (sans exposer les données sensibles en production)
    error_log("Produit importé avec succès: SKU={$produitData['sku']}, ID=$produitId");
    
    // Réponse de succès
    jsonResponse([
        'success' => true,
        'message' => 'Produit importé avec succès',
        'data' => [
            'id' => $produit['id'],
            'sku' => $produit['sku'],
            'titre' => $produit['titre'],
            'prix_origine' => $produit['prix_origine'],
            'prix_client' => $produit['prix_client'],
            'prix_client_formate' => $produit['prix_formate'],
            'date_creation' => $produit['date_creation'],
        ],
        'code' => 'IMPORT_SUCCESS'
    ], 201);
    
} catch (PDOException $e) {
    // Erreur base de données
    error_log('Erreur DB lors de l\'import: ' . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Erreur de base de données. Veuillez réessayer.',
        'code' => 'DATABASE_ERROR'
    ], 500);
    
} catch (Exception $e) {
    // Erreur inattendue
    error_log('Erreur inattendue lors de l\'import: ' . $e->getMessage());
    
    jsonResponse([
        'success' => false,
        'error' => 'Une erreur inattendue s\'est produite.',
        'code' => 'INTERNAL_ERROR'
    ], 500);
}
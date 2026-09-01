<?php
/**
 * Traitement de la commande - Enregistrement en base + Envoi email admin
 * Redirection vers la page de confirmation après traitement
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';

// Vérification méthode POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/panier.php');
}

// Vérification CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');
    redirect('/panier.php');
}

// Récupération du panier
$cart = getCart();

if (empty($cart['items'])) {
    setFlash('error', 'Votre panier est vide.');
    redirect('/panier.php');
}

// =====================================================================
// VALIDATION DES DONNÉES CLIENT
// =====================================================================

$nom = trim($_POST['nom'] ?? '');
$email = trim($_POST['email'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');

$errors = [];

if (empty($nom)) {
    $errors[] = 'Le nom est obligatoire';
} elseif (mb_strlen($nom) > 150) {
    $errors[] = 'Le nom est trop long (max 150 caractères)';
}

if (empty($email) || !validerEmail($email)) {
    $errors[] = 'Adresse e-mail invalide';
}

if (empty($telephone) || !validerTelephone($telephone)) {
    $errors[] = 'Numéro de téléphone Mobile Money invalide';
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        setFlash('error', $error);
    }
    redirect('/panier.php');
}

// =====================================================================
// CRÉATION DE LA COMMANDE
// =====================================================================

$pdo = getPDO();

try {
    // Début transaction
    $pdo->beginTransaction();
    
    // Créer la commande
    $commandeId = creerCommande($pdo, [
        'nom' => $nom,
        'email' => $email,
        'telephone' => $telephone,
    ], $cart);
    
    if (!$commandeId) {
        throw new Exception('Échec de la création de la commande');
    }
    
    // Récupérer la commande complète pour l'email
    $commande = getCommandeWithDetails($pdo, $commandeId);
    
    if (!$commande) {
        throw new Exception('Commande créée mais introuvable');
    }
    
    $pdo->commit();
    
    // =====================================================================
    // ENVOI EMAIL ADMIN
    // =====================================================================
    
    $emailSent = envoyerEmailCommandeAdmin($commande);
    
    if (!$emailSent) {
        // Logger l'erreur mais ne pas bloquer - la commande est en base
        error_log("ERREUR ENVOI EMAIL ADMIN - Commande #{$commande['reference']}");
        // On continue quand même, l'admin pourra voir la commande dans l'admin
    }
    
    // Vider le panier après succès
    clearCart();
    
    // Stocker la référence pour la page de confirmation
    $_SESSION['last_order'] = [
        'reference' => $commande['reference'],
        'total' => $commande['total_ht'],
        'nom' => $commande['nom_client'],
    ];
    
    // Redirection vers confirmation
    redirect('/confirmation.php');
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Erreur traitement commande: ' . $e->getMessage());
    
    setFlash('error', 'Une erreur est survenue lors du traitement. Veuillez réessayer.');
    redirect('/panier.php');
}
<?php
/**
 * Page de connexion administrateur
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';

// Si déjà connecté, rediriger vers le dashboard
if (isAdminLoggedIn()) {
    redirect('/admin/index.php');
}

$errors = [];
$username = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $errors[] = 'Veuillez remplir tous les champs.';
        } elseif (adminLogin($username, $password)) {
            setFlash('success', 'Connexion réussie ! Bienvenue ' . e($username));
            redirect('/admin/index.php');
        } else {
            $errors[] = 'Identifiants incorrects.';
        }
    }
}

// Générer un token CSRF
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion Admin - Catalogue Alibaba Import</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">🛒</div>
                <h1>Administration</h1>
                <p class="login-subtitle">Catalogue Alibaba Import</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?= e($username) ?>" 
                           required 
                           autocomplete="username"
                           placeholder="admin">
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           autocomplete="current-password"
                           placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    Se connecter
                </button>
            </form>
            
            <div class="login-footer">
                <p><strong>Identifiants par défaut :</strong></p>
                <p>Utilisateur : <code>admin</code></p>
                <p>Mot de passe : <code>admin123</code></p>
                <p class="warning">⚠️ Changez le mot de passe immédiatement après la première connexion !</p>
            </div>
        </div>
    </div>
</body>
</html>
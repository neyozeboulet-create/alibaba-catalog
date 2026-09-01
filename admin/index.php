<?php
/**
 * Dashboard d'administration - Liste des produits + Ajout manuel + Import CSV
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/functions.php';

// Protection admin
requireAdmin();

$pdo = getPDO();
$messages = [];

// =====================================================================
// TRAITEMENT DES ACTIONS POST
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $messages[] = ['type' => 'error', 'text' => 'Token de sécurité invalide.'];
    } else {
        $action = $_POST['action'] ?? '';
        
        // --- AJOUT MANUEL ---
        if ($action === 'add_product') {
            $data = [
                'sku' => trim($_POST['sku'] ?? ''),
                'titre' => trim($_POST['titre'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'prix_origine' => (float)($_POST['prix_origine'] ?? 0),
                'image_url' => trim($_POST['image_url'] ?? ''),
            ];
            
            // Validation
            $errors = [];
            if (empty($data['sku']) || !validerSku($data['sku'])) {
                $errors[] = 'SKU invalide (3-50 caractères alphanumériques, tirets, underscores)';
            }
            if (empty($data['titre']) || mb_strlen($data['titre']) > 255) {
                $errors[] = 'Titre obligatoire (max 255 caractères)';
            }
            if (!validerPrix($data['prix_origine'])) {
                $errors[] = 'Prix invalide (doit être > 0 et < 10M)';
            }
            if (!empty($data['image_url']) && !filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'URL d\'image invalide';
            }
            
            if (empty($errors)) {
                $id = insererProduit($pdo, $data);
                if ($id) {
                    $messages[] = ['type' => 'success', 'text' => "Produit '{$data['titre']}' ajouté avec succès (ID: $id)"];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Erreur : SKU déjà existant'];
                }
            } else {
                foreach ($errors as $e) {
                    $messages[] = ['type' => 'error', 'text' => $e];
                }
            }
        }
        
        // --- SUPPRESSION ---
        elseif ($action === 'delete_product') {
            $id = (int)($_POST['produit_id'] ?? 0);
            if ($id > 0) {
                if (deleteProduit($pdo, $id)) {
                    $messages[] = ['type' => 'success', 'text' => 'Produit supprimé avec succès'];
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la suppression'];
                }
            }
        }
        
        // --- IMPORT CSV ---
        elseif ($action === 'import_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $tmpPath = $_FILES['csv_file']['tmp_name'];
                $result = importProduitsFromCSV($pdo, $tmpPath);
                
                if ($result['success'] > 0) {
                    $messages[] = ['type' => 'success', 'text' => "{$result['success']} produit(s) importé(s) avec succès"];
                }
                if ($result['skipped'] > 0) {
                    $messages[] = ['type' => 'warning', 'text' => "{$result['skipped']} produit(s) ignoré(s) (SKU existant)"];
                }
                if (!empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        $messages[] = ['type' => 'error', 'text' => $err];
                    }
                }
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Aucun fichier CSV valide reçu'];
            }
        }
    }
}

// =====================================================================
// RÉCUPÉRATION DES PRODUITS
// =====================================================================

$produits = getAllProduits($pdo);
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Administration - Catalogue Alibaba Import</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-page">
    <header class="admin-header">
        <div class="admin-header-content">
            <h1>🛒 Administration - Catalogue Alibaba Import</h1>
            <div class="admin-user">
                <span>Connecté : <strong><?= e($_SESSION['admin_username']) ?></strong></span>
                <a href="logout.php" class="btn btn-secondary btn-sm">Déconnexion</a>
            </div>
        </div>
    </header>
    
    <div class="admin-container">
        <nav class="admin-nav">
            <a href="index.php" class="active">📦 Produits</a>
            <a href="../index.php" target="_blank">🌐 Voir le site public</a>
        </nav>
        
        <main class="admin-main">
            <!-- Messages flash -->
            <?php foreach (getFlashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            
            <?php foreach ($messages as $msg): ?>
                <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
            <?php endforeach; ?>
            
            <!-- SECTION AJOUT MANUEL -->
            <section class="admin-section">
                <div class="section-header">
                    <h2>➕ Ajouter un produit manuellement</h2>
                    <button type="button" class="btn btn-toggle" onclick="toggleForm('add-form')">Afficher le formulaire</button>
                </div>
                
                <form id="add-form" method="POST" class="admin-form hidden" action="">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="add_product">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sku">SKU (Référence Alibaba) *</label>
                            <input type="text" id="sku" name="sku" required placeholder="ex: ALB-001" pattern="[A-Za-z0-9_\-]{3,50}">
                            <small>Unique, 3-50 caractères (lettres, chiffres, -, _)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="titre">Titre du produit *</label>
                            <input type="text" id="titre" name="titre" required placeholder="ex: Écouteurs Bluetooth Pro" maxlength="255">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prix_origine">Prix d'origine (achat fournisseur) *</label>
                            <input type="number" id="prix_origine" name="prix_origine" required step="0.01" min="0.01" placeholder="ex: 15000">
                            <small>Ce prix n'est JAMAIS affiché au client. Le prix client = prix × 1.10</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="image_url">URL de l'image</label>
                            <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Description optionnelle..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Ajouter le produit</button>
                </form>
            </section>
            
            <!-- SECTION IMPORT CSV -->
            <section class="admin-section">
                <div class="section-header">
                    <h2>📥 Import en masse par CSV</h2>
                    <button type="button" class="btn btn-toggle" onclick="toggleForm('csv-form')">Afficher l'import</button>
                </div>
                
                <form id="csv-form" method="POST" class="admin-form hidden" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="action" value="import_csv">
                    
                    <div class="form-group">
                        <label for="csv_file">Fichier CSV *</label>
                        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    </div>
                    
                    <div class="csv-help">
                        <h4>Format CSV attendu (colonnes) :</h4>
                        <table class="csv-columns">
                            <tr><th>Colonne</th><th>Obligatoire</th><th>Exemple</th></tr>
                            <tr><td>sku / reference</td><td>Oui</td><td>ALB-001</td></tr>
                            <tr><td>titre / nom / produit</td><td>Oui</td><td>Écouteurs Bluetooth</td></tr>
                            <tr><td>prix_origine / prix / price / cout</td><td>Oui</td><td>15000.00</td></tr>
                            <tr><td>description / desc</td><td>Non</td><td>Description du produit</td></tr>
                            <tr><td>image / image_url / photo</td><td>Non</td><td>https://img.com/photo.jpg</td></tr>
                        </table>
                        <p class="csv-note">L'ordre des colonnes n'importe pas. La première ligne doit contenir les en-têtes.</p>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Importer le CSV</button>
                </form>
            </section>
            
            <!-- SECTION LISTE DES PRODUITS -->
            <section class="admin-section">
                <div class="section-header">
                    <h2>📋 Liste des produits (<?= count($produits) ?>)</h2>
                </div>
                
                <?php if (empty($produits)): ?>
                    <div class="empty-state">
                        <p>📭 Aucun produit pour le moment.</p>
                        <p>Utilisez le formulaire ci-dessus ou le bookmarklet pour importer vos premiers produits.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>SKU</th>
                                    <th>Titre</th>
                                    <th>Prix d'origine</th>
                                    <th>Prix client (marge 10%)</th>
                                    <th>Date d'ajout</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produits as $produit): ?>
                                    <tr>
                                        <td>
                                            <?php if ($produit['image_url']): ?>
                                                <img src="<?= e($produit['image_url']) ?>" alt="" class="admin-thumb">
                                            <?php else: ?>
                                                <span class="no-image">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= e($produit['sku']) ?></code></td>
                                        <td><?= e($produit['titre']) ?></td>
                                        <td><?= formaterPrixPrecis($produit['prix_origine']) ?></td>
                                        <td><strong><?= e($produit['prix_formate']) ?></strong></td>
                                        <td><?= date('d/m/Y H:i', strtotime($produit['date_creation'])) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce produit ?')">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="produit_id" value="<?= $produit['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Supprimer">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <script>
        function toggleForm(id) {
            const form = document.getElementById(id);
            const btn = form.previousElementSibling.querySelector('.btn-toggle');
            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                btn.textContent = btn.textContent.replace('Afficher', 'Masquer');
            } else {
                form.classList.add('hidden');
                btn.textContent = btn.textContent.replace('Masquer', 'Afficher');
            }
        }
    </script>
</body>
</html>
<?php
/**
 * Panier de sélection - Gestion par sessions PHP
 * Sans enregistrement bancaire, formulaire client obligatoire
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getPDO();
$cart = getCart();

// Gestion des actions POST (mise à jour quantités, suppression)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Token de sécurité invalide.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_quantity') {
            $sku = $_POST['sku'] ?? '';
            $quantite = (int)($_POST['quantite'] ?? 1);
            
            if (updateCartQuantity($sku, $quantite)) {
                setFlash('success', 'Quantité mise à jour');
            } else {
                setFlash('error', 'Erreur lors de la mise à jour');
            }
            redirect('/panier.php');
            
        } elseif ($action === 'remove_item') {
            $sku = $_POST['sku'] ?? '';
            
            if (removeFromCart($sku)) {
                setFlash('success', 'Produit retiré du panier');
            } else {
                setFlash('error', 'Erreur lors de la suppression');
            }
            redirect('/panier.php');
            
        } elseif ($action === 'clear_cart') {
            clearCart();
            setFlash('success', 'Panier vidé');
            redirect('/panier.php');
        }
    }
}

// Récupération des messages flash
$flashes = getFlashes();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Mon Panier - Catalogue Alibaba Import</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="/">
                        <div class="logo-icon">🛒</div>
                        <div class="logo-text">
                            <h1>Alibaba Import</h1>
                            <p class="logo-subtitle">Catalogue professionnel</p>
                        </div>
                    </a>
                </div>
                
                <nav class="main-nav">
                    <a href="/"><i class="fas fa-home"></i> Accueil</a>
                    <a href="/panier.php" class="active cart-link">
                        <i class="fas fa-shopping-cart"></i> Panier
                        <?php if ($cart['item_count'] > 0): ?>
                            <span class="cart-badge"><?= $cart['item_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin/login.php"><i class="fas fa-lock"></i> Admin</a>
                </nav>
            </div>
        </div>
    </header>
    
    <!-- Contenu principal -->
    <main class="panier-page">
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-shopping-cart"></i> Mon Panier de Sélection</h1>
                <p class="page-subtitle">
                    <?= $cart['produits_count'] ?> produit(s) - 
                    <?= $cart['item_count'] ?> article(s) au total
                </p>
            </div>
            
            <!-- Messages flash -->
            <?php if (!empty($flashes)): ?>
                <div class="flash-messages">
                    <?php foreach ($flashes as $flash): ?>
                        <div class="alert alert-<?= e($flash['type']) ?>">
                            <?= e($flash['message']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($cart['items'])): ?>
                <!-- Panier vide -->
                <div class="empty-cart">
                    <div class="empty-icon">🛒</div>
                    <h2>Votre panier est vide</h2>
                    <p>Ajoutez des produits depuis le catalogue pour commencer votre sélection.</p>
                    <a href="/" class="btn btn-primary btn-lg">
                        <i class="fas fa-arrow-left"></i> Continuer mes achats
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Formulaire panier -->
                <form method="POST" action="valider.php" id="cart-form">
                    <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                    
                    <div class="panier-grid">
                        <!-- Liste des produits -->
                        <div class="panier-items">
                            <div class="panier-header">
                                <h3><i class="fas fa-list"></i> Articles sélectionnés</h3>
                            </div>
                            
                            <div class="items-list">
                                <?php foreach ($cart['items'] as $sku => $item): ?>
                                    <div class="cart-item" data-sku="<?= e($sku) ?>">
                                        <div class="item-image">
                                            <?php if ($item['produit']['image_url']): ?>
                                                <img src="<?= e($item['produit']['image_url']) ?>" 
                                                     alt="<?= e($item['produit']['titre']) ?>">
                                            <?php else: ?>
                                                <div class="no-image-placeholder"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-details">
                                            <h4 class="item-title"><?= e($item['produit']['titre']) ?></h4>
                                            <p class="item-sku"><i class="fas fa-barcode"></i> SKU : <code><?= e($sku) ?></code></p>
                                            <p class="item-unit-price">
                                                Prix unitaire : <strong><?= e($item['prix_unitaire_formate']) ?></strong>
                                            </p>
                                            
                                            <?php if (!empty($item['produit']['description'])): ?>
                                                <p class="item-description"><?= e(mb_substr($item['produit']['description'], 0, 150)) ?>...</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-quantity">
                                            <label for="qty-<?= e($sku) ?>">Quantité</label>
                                            <div class="quantity-controls">
                                                <button type="button" class="qty-btn qty-decrease" data-sku="<?= e($sku) ?>" aria-label="Diminuer">−</button>
                                                <input type="number" 
                                                       id="qty-<?= e($sku) ?>" 
                                                       name="quantites[<?= e($sku) ?>]" 
                                                       value="<?= $item['quantite'] ?>" 
                                                       min="1" 
                                                       max="99"
                                                       class="qty-input"
                                                       data-sku="<?= e($sku) ?>">
                                                <button type="button" class="qty-btn qty-increase" data-sku="<?= e($sku) ?>" aria-label="Augmenter">+</button>
                                            </div>
                                        </div>
                                        
                                        <div class="item-subtotal">
                                            <span class="subtotal-label">Sous-total</span>
                                            <span class="subtotal-amount" data-sku="<?= e($sku) ?>">
                                                <?= e($item['sous_total_formate']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="item-actions">
                                            <button type="button" 
                                                    class="btn btn-danger btn-sm remove-item-btn" 
                                                    data-sku="<?= e($sku) ?>"
                                                    title="Retirer">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                            
                                            <!-- Formulaire caché pour suppression sans JS -->
                                            <form method="POST" action="panier.php" class="remove-form" style="display:none;">
                                                <input type="hidden" name="csrf_token" value="<?= e(generateCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="sku" value="<?= e($sku) ?>">
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Actions globales -->
                            <div class="panier-actions">
                                <button type="button" class="btn btn-secondary" id="clear-cart-btn">
                                    <i class="fas fa-trash"></i> Vider le panier
                                </button>
                                
                                <a href="/" class="btn btn-outline">
                                    <i class="fas fa-arrow-left"></i> Continuer mes achats
                                </a>
                            </div>
                        </div>
                        
                        <!-- Résumé et formulaire client -->
                        <div class="panier-summary">
                            <div class="summary-card">
                                <h3><i class="fas fa-calculator"></i> Récapitulatif</h3>
                                
                                <div class="summary-lines">
                                    <div class="summary-line">
                                        <span>Sous-total (<span id="total-items"><?= $cart['item_count'] ?></span> articles)</span>
                                        <span id="subtotal-amount"><?= e($cart['total_formate']) ?></span>
                                    </div>
                                    
                                    <div class="summary-line summary-total">
                                        <span>Total à payer</span>
                                        <span id="total-amount"><?= e($cart['total_formate']) ?></span>
                                    </div>
                                </div>
                                
                                <div class="summary-note">
                                    <i class="fas fa-info-circle"></i>
                                    Le total inclut la marge commerciale de <?= (getMargeRate() * 100) ?>%.
                                </div>
                                
                                <!-- Formulaire client obligatoire -->
                                <div class="client-form-section">
                                    <h4><i class="fas fa-user"></i> Vos coordonnées (obligatoires)</h4>
                                    <p class="form-note">Ces informations sont nécessaires pour traiter votre commande.</p>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="nom">Nom complet *</label>
                                            <input type="text" 
                                                   id="nom" 
                                                   name="nom" 
                                                   required 
                                                   autocomplete="name"
                                                   placeholder="Votre nom complet"
                                                   maxlength="150">
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="email">Adresse e-mail *</label>
                                            <input type="email" 
                                                   id="email" 
                                                   name="email" 
                                                   required 
                                                   autocomplete="email"
                                                   placeholder="votre@email.com"
                                                   maxlength="150">
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="telephone">Numéro Mobile Money *</label>
                                            <input type="tel" 
                                                   id="telephone" 
                                                   name="telephone" 
                                                   required 
                                                   autocomplete="tel"
                                                   placeholder="+225 XX XX XX XX"
                                                   maxlength="30"
                                                   pattern="^[\+\d\s\-\(\)]{8,30}$">
                                            <small>Numéro pour le paiement Mobile Money (Moov Money, TMoney, Orange Money, Wave)</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Input caché pour stocker le total calculé -->
                                    <input type="hidden" name="total_ht" value="<?= $cart['total'] ?>">
                                    <input type="hidden" name="items_json" value='<?= json_encode($cart['items'], JSON_UNESCAPED_UNICODE) ?>'>
                                    
                                    <button type="submit" class="btn btn-primary btn-lg btn-block" id="validate-btn">
                                        <i class="fas fa-check-circle"></i> Valider ma commande
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Infos paiement -->
                            <div class="payment-info-card">
                                <h4><i class="fas fa-mobile-alt"></i> Paiement Mobile Money</h4>
                                <p>Après validation, vous recevrez les instructions pour payer par Mobile Money :</p>
                                <ul>
                                    <li><i class="fas fa-check"></i> Moov Money</li>
                                    <li><i class="fas fa-check"></i> TMoney</li>
                                    <li><i class="fas fa-check"></i> Orange Money</li>
                                    <li><i class="fas fa-check"></i> Wave</li>
                                </ul>
                                <p class="payment-note">Votre commande sera traitée dès réception de votre transfert.</p>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><i class="fas fa-shopping-cart"></i> Alibaba Import</h4>
                    <p>Catalogue de produits importés directement d'Alibaba.</p>
                </div>
                <div class="footer-section">
                    <h4><i class="fas fa-headset"></i> Contact</h4>
                    <p><i class="fas fa-phone"></i> +225 XX XX XX XX</p>
                    <p><i class="fas fa-envelope"></i> contact@alibaba-import.com</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Alibaba Import - Tous droits réservés.</p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = '<?= e(generateCsrfToken()) ?>';
            
            // Gestion des quantités (+/-)
            document.querySelectorAll('.qty-increase').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.dataset.sku;
                    const input = document.getElementById('qty-' + sku);
                    const current = parseInt(input.value) || 1;
                    if (current < 99) {
                        input.value = current + 1;
                        updateQuantity(sku, current + 1);
                    }
                });
            });
            
            document.querySelectorAll('.qty-decrease').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.dataset.sku;
                    const input = document.getElementById('qty-' + sku);
                    const current = parseInt(input.value) || 1;
                    if (current > 1) {
                        input.value = current - 1;
                        updateQuantity(sku, current - 1);
                    }
                });
            });
            
            // Changement direct dans l'input
            document.querySelectorAll('.qty-input').forEach(input => {
                input.addEventListener('change', function() {
                    const sku = this.dataset.sku;
                    let value = parseInt(this.value) || 1;
                    if (value < 1) value = 1;
                    if (value > 99) value = 99;
                    this.value = value;
                    updateQuantity(sku, value);
                });
            });
            
            // Suppression d'article
            document.querySelectorAll('.remove-item-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Retirer ce produit du panier ?')) {
                        const sku = this.dataset.sku;
                        removeItem(sku);
                    }
                });
            });
            
            // Vider le panier
            document.getElementById('clear-cart-btn')?.addEventListener('click', function() {
                if (confirm('Vider complètement le panier ?')) {
                    clearCart();
                }
            });
            
            // Mise à jour quantité via AJAX
            function updateQuantity(sku, quantite) {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'update_quantity');
                formData.append('sku', sku);
                formData.append('quantite', quantite);
                
                fetch('panier.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Mise à jour des totaux affichés
                    updateTotalsFromPage();
                })
                .catch(err => console.error('Erreur mise à jour:', err));
            }
            
            // Suppression d'article via AJAX
            function removeItem(sku) {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'remove_item');
                formData.append('sku', sku);
                
                fetch('panier.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Recharger la page pour simplicité
                    location.reload();
                })
                .catch(err => console.error('Erreur suppression:', err));
            }
            
            // Vider le panier
            function clearCart() {
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);
                formData.append('action', 'clear_cart');
                
                fetch('panier.php', {
                    method: 'POST',
                    body: formData
                })
                .then(() => location.reload())
                .catch(err => console.error('Erreur vidage:', err));
            }
            
            // Mise à jour des totaux depuis le DOM (fallback sans JS)
            function updateTotalsFromPage() {
                let total = 0;
                let totalItems = 0;
                
                document.querySelectorAll('.cart-item').forEach(item => {
                    const subtotalText = item.querySelector('.subtotal-amount')?.textContent || '';
                    const qtyInput = item.querySelector('.qty-input');
                    const qty = parseInt(qtyInput?.value) || 0;
                    
                    // Extraire le nombre du prix formaté
                    const price = parseFloat(subtotalText.replace(/[^\d,\.]/g, '').replace(',', '.'));
                    if (!isNaN(price)) total += price;
                    totalItems += qty;
                });
                
                // Formatage simple pour affichage
                const formatPrice = (num) => {
                    return new Intl.NumberFormat('fr-FR').format(Math.round(num)) + ' F CFA';
                };
                
                document.getElementById('total-items').textContent = totalItems;
                document.getElementById('subtotal-amount').textContent = formatPrice(total);
                document.getElementById('total-amount').textContent = formatPrice(total);
                
                // Mettre à jour l'input caché
                document.querySelector('input[name="total_ht"]').value = total.toFixed(2);
            }
            
            // Validation formulaire client
            document.getElementById('cart-form')?.addEventListener('submit', function(e) {
                const nom = document.getElementById('nom').value.trim();
                const email = document.getElementById('email').value.trim();
                const telephone = document.getElementById('telephone').value.trim();
                
                let errors = [];
                
                if (!nom) errors.push('Le nom est obligatoire');
                if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    errors.push('Email invalide');
                }
                if (!telephone || telephone.replace(/[\s\-\.\(\)]/g, '').length < 8) {
                    errors.push('Numéro de téléphone invalide');
                }
                
                if (errors.length > 0) {
                    e.preventDefault();
                    alert('Erreurs :\n• ' + errors.join('\n• '));
                    return false;
                }
                
                // Afficher état de chargement
                const btn = document.getElementById('validate-btn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                btn.disabled = true;
            });
        });
    </script>
</body>
</html>
<?php
/**
 * Page d'accueil - Catalogue client
 * Affichage des produits avec design e-commerce moderne
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getPDO();
$produits = getAllProduits($pdo);

// Gestion de l'ajout au panier via AJAX/GET
if (isset($_GET['add_to_cart']) && isset($_GET['sku'])) {
    $sku = $_GET['sku'];
    
    if (addToCart($sku)) {
        setFlash('success', 'Produit ajouté au panier !');
    } else {
        setFlash('error', 'Erreur : produit non trouvé.');
    }
    
    // Redirection sans les paramètres GET
    redirect('/');
}

// Récupération du panier pour l'indicateur
$cart = getCart();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Catalogue de produits importés d'Alibaba - Meilleurs prix, qualité garantie">
    <title>Catalogue Alibaba Import - Produits importés de qualité</title>
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
                    <a href="/" class="active"><i class="fas fa-home"></i> Accueil</a>
                    <a href="/panier.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i> Panier
                        <?php if ($cart['item_count'] > 0): ?>
                            <span class="cart-badge"><?= $cart['item_count'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin/login.php"><i class="fas fa-lock"></i> Admin</a>
                </nav>
                
                <div class="header-contact">
                    <p><i class="fas fa-headset"></i> Service client</p>
                    <p><i class="fas fa-truck"></i> Livraison rapide</p>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h2>Produits importés directement d'Alibaba</h2>
                <p class="hero-subtitle">
                    <i class="fas fa-check-circle"></i> Meilleurs prix fournisseur 
                    <i class="fas fa-check-circle"></i> Qualité garantie 
                    <i class="fas fa-check-circle"></i> Livraison en Côte d'Ivoire
                </p>
                
                <?php $flashes = getFlashes(); ?>
                <?php if (!empty($flashes)): ?>
                    <div class="flash-messages">
                        <?php foreach ($flashes as $flash): ?>
                            <div class="alert alert-<?= e($flash['type']) ?>">
                                <?= e($flash['message']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Catalogue -->
    <main class="catalogue">
        <div class="container">
            <!-- En-tête catalogue -->
            <div class="catalogue-header">
                <div class="catalogue-title">
                    <h2><i class="fas fa-boxes"></i> Notre Sélection</h2>
                    <p class="catalogue-count"><?= count($produits) ?> produits disponibles</p>
                </div>
                
                <div class="catalogue-info">
                    <div class="info-card">
                        <i class="fas fa-tag"></i>
                        <div>
                            <h4>+10% marge inclus</h4>
                            <p>Tous les prix incluent notre marge commerciale</p>
                        </div>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <h4>Garantie SKU Alibaba</h4>
                            <p>Produits identifiables chez le fournisseur</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grille de produits -->
            <?php if (empty($produits)): ?>
                <div class="empty-catalogue">
                    <div class="empty-icon">📭</div>
                    <h3>Catalogue vide</h3>
                    <p>Ajoutez des produits via l'administration ou le bookmarklet pour commencer.</p>
                    <a href="/admin/login.php" class="btn btn-primary">
                        <i class="fas fa-lock"></i> Accéder à l'administration
                    </a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($produits as $produit): ?>
                        <div class="product-card">
                            <!-- Image produit -->
                            <div class="product-image">
                                <?php if ($produit['image_url']): ?>
                                    <img src="<?= e($produit['image_url']) ?>" 
                                         alt="<?= e($produit['titre']) ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="product-image-placeholder">
                                        <i class="fas fa-image"></i>
                                        <span>Image non disponible</span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Badge SKU -->
                                <div class="product-sku-badge">
                                    <i class="fas fa-barcode"></i> <?= e($produit['sku']) ?>
                                </div>
                            </div>
                            
                            <!-- Infos produit -->
                            <div class="product-info">
                                <h3 class="product-title"><?= e($produit['titre']) ?></h3>
                                
                                <?php if (!empty($produit['description'])): ?>
                                    <p class="product-description">
                                        <?= e(mb_substr($produit['description'], 0, 100)) ?>...
                                    </p>
                                <?php endif; ?>
                                
                                <div class="product-price-section">
                                    <div class="price-display">
                                        <span class="price-label">Prix client :</span>
                                        <span class="price-amount"><?= e($produit['prix_formate']) ?></span>
                                    </div>
                                    <div class="price-note">
                                        <i class="fas fa-info-circle"></i> 
                                        Marge commerciale de <?= (getMargeRate() * 100) ?>% incluse
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div class="product-actions">
                                <form method="GET" action="">
                                    <input type="hidden" name="add_to_cart" value="1">
                                    <input type="hidden" name="sku" value="<?= e($produit['sku']) ?>">
                                    <button type="submit" class="btn btn-primary btn-block add-to-cart-btn">
                                        <i class="fas fa-cart-plus"></i> Ajouter à ma sélection
                                    </button>
                                </form>
                                
                                <a href="?add_to_cart=1&sku=<?= urlencode($produit['sku']) ?>&quantity=1" 
                                   class="btn btn-secondary btn-block quick-add-btn">
                                    <i class="fas fa-bolt"></i> Ajouter rapide
                                </a>
                            </div>
                            
                            <!-- Meta infos -->
                            <div class="product-meta">
                                <span><i class="far fa-calendar"></i> Ajouté le <?= date('d/m/Y', strtotime($produit['date_creation'])) ?></span>
                                <?php if ($produit['prix_origine'] > 0): ?>
                                    <span class="supplier-price-hint">
                                        <i class="fas fa-industry"></i> Prix fournisseur confidentiel
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Avis rapide -->
                <div class="catalogue-footer">
                    <div class="testimonial">
                        <i class="fas fa-quote-left"></i>
                        <p>"Commande facile, les SKU Alibaba permettent de retrouver exactement les produits chez le fournisseur."</p>
                        <cite>– Client satisfait</cite>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><i class="fas fa-shopping-cart"></i> Alibaba Import</h4>
                    <p>Catalogue de produits importés directement d'Alibaba avec service personnalisé.</p>
                    <p class="footer-tagline">Le lien direct entre le fournisseur et vous.</p>
                </div>
                
                <div class="footer-section">
                    <h4><i class="fas fa-info-circle"></i> À propos</h4>
                    <ul>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Comment ça marche</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Processus d'importation</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Confidentialité des prix</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4><i class="fas fa-headset"></i> Service client</h4>
                    <ul>
                        <li><i class="fas fa-phone"></i> +225 XX XX XX XX</li>
                        <li><i class="fas fa-envelope"></i> contact@alibaba-import.com</li>
                        <li><i class="fas fa-clock"></i> Lundi-Vendredi, 9h-18h</li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4><i class="fas fa-mobile-alt"></i> Paiement Mobile Money</h4>
                    <p>Paiement sécurisé via :</p>
                    <div class="payment-methods">
                        <span class="payment-method"><i class="fas fa-money-bill-wave"></i> Moov Money</span>
                        <span class="payment-method"><i class="fas fa-money-bill-wave"></i> TMoney</span>
                        <span class="payment-method"><i class="fas fa-money-bill-wave"></i> Orange Money</span>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Alibaba Import - Tous droits réservés.</p>
                <p class="footer-legal">
                    <a href="#">Mentions légales</a> | 
                    <a href="#">CGV</a> | 
                    <a href="#">Politique de confidentialité</a>
                </p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script>
        // Animation pour l'ajout au panier
        document.addEventListener('DOMContentLoaded', function() {
            const cartButtons = document.querySelectorAll('.add-to-cart-btn, .quick-add-btn');
            
            cartButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const form = this.closest('form');
                    const isQuickAdd = this.classList.contains('quick-add-btn');
                    
                    if (!form && !isQuickAdd) {
                        // Pour les liens quick-add
                        return;
                    }
                    
                    // Animation sur le bouton
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
                    this.disabled = true;
                    
                    // Si c'est un formulaire, le soumettre
                    if (form) {
                        form.submit();
                    }
                    
                    // Restaurer le bouton après un délai (au cas où)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 2000);
                });
            });
            
            // Notification toast pour les messages flash
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
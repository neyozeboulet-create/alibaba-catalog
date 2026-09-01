<?php
/**
 * Page de confirmation de commande - Instructions paiement Mobile Money
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';

// Récupérer les infos de la dernière commande
$lastOrder = $_SESSION['last_order'] ?? null;

// Si pas de commande récente, rediriger vers le catalogue
if (!$lastOrder) {
    // Essayer de récupérer depuis un paramètre GET (fallback)
    if (isset($_GET['ref'])) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT reference, total_ht, nom_client FROM commandes WHERE reference = ?');
        $stmt->execute([$_GET['ref']]);
        $order = $stmt->fetch();
        
        if ($order) {
            $lastOrder = [
                'reference' => $order['reference'],
                'total' => $order['total_ht'],
                'nom' => $order['nom_client'],
            ];
        }
    }
    
    if (!$lastOrder) {
        redirect('/');
    }
}

// Infos Mobile Money
$mmInfo = getMobileMoneyInfo($lastOrder['total']);

// Nettoyer la session
unset($_SESSION['last_order']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Confirmation de commande - Catalogue Alibaba Import</title>
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
                    <a href="/panier.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i> Panier
                    </a>
                </nav>
            </div>
        </div>
    </header>
    
    <!-- Contenu principal -->
    <main class="confirmation-page">
        <div class="container">
            <!-- Confirmation succès -->
            <section class="confirmation-hero">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Commande validée avec succès !</h1>
                <p class="confirmation-subtitle">
                    Merci <strong><?= e($lastOrder['nom']) ?></strong>, votre commande a bien été enregistrée.
                </p>
                
                <div class="order-reference">
                    <span class="ref-label">Référence commande :</span>
                    <span class="ref-value"><?= e($lastOrder['reference']) ?></span>
                </div>
            </section>
            
            <!-- Instructions de paiement -->
            <section class="payment-instructions">
                <div class="instruction-card main-instruction">
                    <div class="instruction-header">
                        <i class="fas fa-mobile-alt"></i>
                        <h2>Paiement par Mobile Money</h2>
                    </div>
                    
                    <div class="instruction-content">
                        <p class="instruction-text">
                            Pour valider définitivement votre achat, veuillez effectuer le transfert manuel de 
                        </p>
                        
                        <div class="amount-display">
                            <span class="amount-label">Montant à payer :</span>
                            <span class="amount-value"><?= e($mmInfo['montant_formate']) ?></span>
                        </div>
                        
                        <p class="instruction-text">
                            par Mobile Money sur le numéro suivant :
                        </p>
                        
                        <div class="phone-display">
                            <span class="phone-label">Numéro :</span>
                            <span class="phone-value" id="mm-number"><?= e($mmInfo['numero']) ?></span>
                            <button type="button" class="btn btn-copy" onclick="copyPhoneNumber()" aria-label="Copier le numéro">
                                <i class="fas fa-copy"></i> Copier
                            </button>
                        </div>
                        
                        <div class="networks-display">
                            <span class="networks-label">Réseaux acceptés :</span>
                            <div class="networks-list">
                                <?php foreach ($mmInfo['reseaux'] as $reseau): ?>
                                    <span class="network-badge"><?= e($reseau) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="instruction-note">
                            <i class="fas fa-info-circle"></i>
                            <p>Votre commande sera traitée dès réception de votre transfert.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Récapitulatif commande -->
                <div class="instruction-card summary-card">
                    <div class="instruction-header">
                        <i class="fas fa-receipt"></i>
                        <h3>Récapitulatif de votre commande</h3>
                    </div>
                    
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Référence</span>
                            <span><?= e($lastOrder['reference']) ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Date</span>
                            <span><?= date('d/m/Y à H:i') ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total (marge 10% incluse)</span>
                            <span><?= e($mmInfo['montant_formate']) ?></span>
                        </div>
                    </div>
                    
                    <div class="summary-actions">
                        <a href="/" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Retour au catalogue
                        </a>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimer / Sauvegarder
                        </button>
                    </div>
                </div>
            </section>
            
            <!-- Prochaines étapes -->
            <section class="next-steps">
                <h2><i class="fas fa-list-ol"></i> Prochaines étapes</h2>
                
                <div class="steps-grid">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Effectuez le paiement</h4>
                            <p>Utilisez votre application Mobile Money (Moov Money, TMoney, Orange Money ou Wave) pour transférer le montant exact vers le numéro indiqué.</p>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Conservez la preuve</h4>
                            <p>Gardez le reçu de transaction (SMS de confirmation, capture d'écran) comme justificatif de paiement.</p>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Traitement de la commande</h4>
                            <p>Dès réception de votre paiement, nous passons commande chez le fournisseur Alibaba avec les SKU exacts de vos produits.</p>
                        </div>
                    </div>
                    
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h4>Livraison</h4>
                            <p>Nous vous tiendrons informé(e) par SMS/email de l'avancement jusqu'à la livraison en Côte d'Ivoire.</p>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Contact support -->
            <section class="support-section">
                <div class="support-card">
                    <i class="fas fa-headset"></i>
                    <div>
                        <h3>Besoin d'aide ?</h3>
                        <p>Contactez notre service client :</p>
                        <div class="support-contacts">
                            <a href="tel:+225XXXXXXXXX"><i class="fas fa-phone"></i> +225 XX XX XX XX</a>
                            <a href="mailto:contact@alibaba-import.com"><i class="fas fa-envelope"></i> contact@alibaba-import.com</a>
                        </div>
                        <p class="support-hours">Disponible du Lundi au Vendredi, 9h-18h</p>
                    </div>
                </div>
            </section>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> Alibaba Import - Tous droits réservés.</p>
                <p>Commande #<?= e($lastOrder['reference']) ?> - <?= e($mmInfo['montant_formate']) ?></p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script>
        // Copier le numéro de téléphone
        function copyPhoneNumber() {
            const phoneNumber = document.getElementById('mm-number').textContent;
            const btn = event.currentTarget;
            
            navigator.clipboard.writeText(phoneNumber).then(() => {
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                btn.style.background = '#10b981';
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.style.background = '';
                }, 2000);
            }).catch(() => {
                // Fallback pour navigateurs sans clipboard API
                const textarea = document.createElement('textarea');
                textarea.value = phoneNumber;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                setTimeout(() => btn.innerHTML = originalHTML, 2000);
            });
        }
        
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.instruction-card, .step-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 + index * 150);
            });
            
            // Mise en surbrillance du montant au chargement
            const amount = document.querySelector('.amount-value');
            if (amount) {
                amount.style.animation = 'pulse 2s ease-in-out 3';
            }
        });
    </script>
</body>
</html>
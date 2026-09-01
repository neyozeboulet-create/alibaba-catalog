/**
 * Bookmarklet d'importation Alibaba en 1 clic
 * 
 * Instructions d'installation :
 * 1. Copier tout le code ci-dessous (de "javascript:" à la fin)
 * 2. Créer un nouveau favori dans votre navigateur
 * 3. Coller le code comme URL du favori
 * 4. Nommer le favori "🛒 Importer depuis Alibaba"
 * 
 * Utilisation :
 * 1. Naviguer sur une page produit Alibaba.com
 * 2. Cliquer sur le favori
 * 3. Confirmer l'importation dans la popup
 * 
 * Configuration :
 * - Modifier API_URL avec l'URL de votre serveur
 * - Modifier API_TOKEN avec votre token secret
 * - Modifier DEV_MODE à true/false pour le débogage
 */

(function() {
    // =====================================================================
    // CONFIGURATION - À PERSONNALISER AVANT UTILISATION
    // =====================================================================
    
    // URL de votre API d'importation (sans slash final)
    const API_URL = 'https://votre-domaine.com/api/api_import.php'; // Remplacer par votre URL
    
    // Token secret (doit correspondre à config/security.import_api_token)
    const API_TOKEN = 'ALB_IMPORT_SECRET_TOKEN_2024_CHANGE_ME'; // Remplacer par votre token
    
    // Mode développement (affiche des alertes de débogage)
    const DEV_MODE = true; // Mettre à false en production
    
    // =====================================================================
    // FONCTIONS D'EXTRACTION POUR ALIBABA.COM
    // =====================================================================
    
    /**
     * Extrait le titre du produit
     */
    function extractTitle() {
        // Alibaba.com - Ancien et nouveau design
        const selectors = [
            'h1.product-title',               // Nouveau design
            'h1.pdp-mod-title',               // Design courant
            'h1.product-name',                // Ancien design
            'h1.product-detail-title',        // Alternative
            'h1.pc-detail-title',            // Mobile
            '[data-domkey="product-title"]', // Via data attribute
            '.module_product_title h1',      // Alternative
            '.product-detail h1',            // Dernière tentative
            document.querySelector('meta[property="og:title"]')?.content || '',
            document.querySelector('meta[name="title"]')?.content || '',
            document.title.split('|')[0].trim()
        ];
        
        for (const selector of selectors) {
            if (typeof selector === 'string') {
                const el = document.querySelector(selector);
                if (el && el.textContent.trim()) {
                    return el.textContent.trim().substring(0, 255);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extrait le SKU (référence produit)
     */
    function extractSKU() {
        // Sources possibles pour le SKU sur Alibaba
        const sources = [
            // Via data attributes
            () => {
                const skuEl = document.querySelector('[data-sku-id]');
                return skuEl?.getAttribute('data-sku-id');
            },
            // Dans les métadonnées
            () => {
                const metaSku = document.querySelector('meta[name="sku"]') ||
                               document.querySelector('meta[property="product:sku"]');
                return metaSku?.content;
            },
            // Dans l'URL (pattern: product/Alibaba-SKU-123456789.html)
            () => {
                const urlMatch = window.location.href.match(/product\/([A-Za-z0-9\-_]+)(?=\.html|$)/);
                return urlMatch ? urlMatch[1] : null;
            },
            // Via selecteurs spécifiques
            () => {
                const skuSelectors = [
                    '.sku-code',
                    '.product-sku',
                    '.sku-id',
                    '.model-number',
                    '.product-model',
                    '[data-role="sku-code"]'
                ];
                for (const selector of skuSelectors) {
                    const el = document.querySelector(selector);
                    if (el && el.textContent.trim()) {
                        return el.textContent.trim().replace(/^[^:]*:\s*/i, ''); // Enlever "SKU: "
                    }
                }
                return null;
            },
            // Générer un SKU basé sur le titre si nécessaire
            () => {
                const title = extractTitle();
                if (title) {
                    // Créer un SKU à partir du titre (max 20 caractères)
                    return 'ALB-' + title
                        .toUpperCase()
                        .replace(/[^A-Z0-9]/g, '')
                        .substring(0, 15) + 
                        '-' + Date.now().toString().substr(-5);
                }
                return null;
            }
        ];
        
        for (const source of sources) {
            const sku = source();
            if (sku && sku.length >= 3) {
                // Nettoyer le SKU
                return sku.trim()
                    .replace(/[^\w\-]/g, '_') // Remplacer caractères spéciaux par underscore
                    .substring(0, 100);
            }
        }
        
        return null;
    }
    
    /**
     * Extrait le prix d'origine (prix fournisseur)
     */
    function extractPrice() {
        // Sélecteurs de prix sur Alibaba.com
        const priceSelectors = [
            // Prix principal
            '.price',
            '.product-price',
            '.pdp-price',
            '.pre-inquiry-price',
            '.price-value',
            '.price-amount',
            // Via data attributes
            '[data-product-price]',
            '[data-price]',
            // Métadonnées
            'meta[property="product:price:amount"]',
            'meta[property="og:price:amount"]',
            // Format "US $123.45 - $456.78"
            () => {
                const priceText = document.body.innerText.match(/(?:USD|US\$|EUR|€)\s*[\d,\.]+\s*(?:-\s*[\d,\.]+)?/i);
                if (priceText) {
                    // Extraire le premier prix
                    const match = priceText[0].match(/[\d,\.]+/);
                    if (match) {
                        return parseFloat(match[0].replace(/,/g, ''));
                    }
                }
                return null;
            }
        ];
        
        for (const selector of priceSelectors) {
            let price = null;
            
            if (typeof selector === 'function') {
                price = selector();
            } else {
                const el = document.querySelector(selector);
                if (el) {
                    const text = el.textContent || el.getAttribute('content') || '';
                    const match = text.match(/[\d,\.]+/);
                    if (match) {
                        price = parseFloat(match[0].replace(/,/g, ''));
                    }
                }
            }
            
            if (price && price > 0) {
                return price;
            }
        }
        
        // Si aucun prix trouvé, demander à l'utilisateur
        return promptUserPrice();
    }
    
    /**
     * Demande le prix à l'utilisateur si non détecté automatiquement
     */
    function promptUserPrice() {
        if (!DEV_MODE) return null;
        
        const priceStr = prompt(
            'Prix non détecté automatiquement.\n' +
            'Veuillez entrer le prix d\'achat brut du produit (en USD/EUR sans devise) :',
            '100.00'
        );
        
        if (priceStr) {
            const price = parseFloat(priceStr.replace(/,/g, '.'));
            if (!isNaN(price) && price > 0) {
                return price;
            }
        }
        
        return null;
    }
    
    /**
     * Extrait l'URL de l'image principale
     */
    function extractImageUrl() {
        const imageSelectors = [
            // Image principale
            '.product-image img',
            '.main-image img',
            '.pdp-image img',
            '.product-gallery img',
            '.detail-gallery img',
            // Métadonnées OG
            'meta[property="og:image"]',
            'meta[property="og:image:url"]',
            // Images hi-res
            '[data-zoom-image]',
            '.large-image',
            '.zoom-img'
        ];
        
        for (const selector of imageSelectors) {
            const el = document.querySelector(selector);
            if (el) {
                const src = el.getAttribute('src') || 
                           el.getAttribute('data-src') || 
                           el.getAttribute('data-zoom-image') ||
                           el.getAttribute('content');
                
                if (src && src.startsWith('http')) {
                    // Nettoyer l'URL si nécessaire
                    return src.split('?')[0]; // Enlever les paramètres d'URL
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extrait la description du produit
     */
    function extractDescription() {
        const descSelectors = [
            '.product-description',
            '.description-content',
            '.product-detail-description',
            '.detail-description',
            '[data-description]',
            'meta[property="og:description"]',
            'meta[name="description"]'
        ];
        
        for (const selector of descSelectors) {
            const el = document.querySelector(selector);
            if (el) {
                const content = el.textContent || el.getAttribute('content') || '';
                if (content.trim()) {
                    // Limiter à 5000 caractères
                    return content.trim().substring(0, 5000);
                }
            }
        }
        
        // Essayer de récupérer les spécifications
        const specEls = document.querySelectorAll('.specification-item, .product-attribute, .feature-item');
        if (specEls.length > 0) {
            const specs = [];
            specEls.forEach(el => {
                if (specs.length < 10) { // Limiter à 10 spécifications
                    const text = el.textContent.trim();
                    if (text && !text.includes('undefined')) {
                        specs.push('• ' + text);
                    }
                }
            });
            
            if (specs.length > 0) {
                return specs.join('\n').substring(0, 5000);
            }
        }
        
        return null;
    }
    
    // =====================================================================
    // FONCTIONS D'AFFICHAGE ET UTILITAIRES
    // =====================================================================
    
    /**
     * Affiche un message de statut à l'utilisateur
     */
    function showStatus(message, type = 'info') {
        if (DEV_MODE) {
            alert(`[${type.toUpperCase()}] ${message}`);
        }
        
        // Affichage dans la console pour débogage
        console.log(`Alibaba Importer: ${message}`);
    }
    
    /**
     * Crée et affiche une popup de confirmation
     */
    function showConfirmationPopup(productData) {
        // Créer le contenu HTML de la popup
        const popupHTML = `
            <div style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border: 2px solid #2563eb;
                border-radius: 12px;
                padding: 25px;
                z-index: 10000;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                min-width: 400px;
                max-width: 90vw;
                font-family: Arial, sans-serif;
            ">
                <div style="display: flex; align-items: center; margin-bottom: 20px;">
                    <div style="
                        background: #2563eb;
                        color: white;
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin-right: 15px;
                        font-size: 20px;
                    ">🛒</div>
                    <h2 style="margin: 0; color: #1e40af;">Importer depuis Alibaba</h2>
                </div>
                
                <div style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px;">
                    <h3 style="margin: 0 0 10px 0; color: #374151;">Produit détecté</h3>
                    <div style="margin-bottom: 8px;"><strong>Titre :</strong> ${escapeHTML(productData.titre)}</div>
                    <div style="margin-bottom: 8px;"><strong>SKU :</strong> <code>${escapeHTML(productData.sku)}</code></div>
                    <div style="margin-bottom: 8px;"><strong>Prix d'origine :</strong> ${productData.prix_origine.toFixed(2)}</div>
                    <div style="margin-bottom: 8px;"><strong>Prix client (avec marge 10%) :</strong> <strong>${(productData.prix_origine * 1.10).toFixed(2)}</strong></div>
                </div>
                
                <div id="importer-status" style="
                    margin: 15px 0;
                    padding: 10px;
                    border-radius: 6px;
                    background: #f0f9ff;
                    border: 1px solid #93c5fd;
                    display: none;
                ">
                    <div style="display: flex; align-items: center;">
                        <div id="status-icon" style="margin-right: 10px; font-size: 20px;">⏳</div>
                        <div id="status-text">En cours d'importation...</div>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button id="importer-cancel" style="
                        padding: 10px 20px;
                        background: #f3f4f6;
                        border: 1px solid #d1d5db;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 500;
                        color: #374151;
                    ">Annuler</button>
                    <button id="importer-confirm" style="
                        padding: 10px 20px;
                        background: #2563eb;
                        border: none;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 600;
                        color: white;
                    ">Importer le produit</button>
                </div>
            </div>
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 9999;
            "></div>
        `;
        
        // Créer et insérer la popup
        const popupContainer = document.createElement('div');
        popupContainer.innerHTML = popupHTML;
        document.body.appendChild(popupContainer);
        
        // Gestionnaires d'événements
        document.getElementById('importer-cancel').addEventListener('click', () => {
            document.body.removeChild(popupContainer);
            showStatus('Importation annulée', 'info');
        });
        
        document.getElementById('importer-confirm').addEventListener('click', () => {
            const confirmBtn = document.getElementById('importer-confirm');
            const cancelBtn = document.getElementById('importer-cancel');
            const statusDiv = document.getElementById('importer-status');
            const statusIcon = document.getElementById('status-icon');
            const statusText = document.getElementById('status-text');
            
            // Désactiver les boutons
            confirmBtn.disabled = true;
            cancelBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
            cancelBtn.style.opacity = '0.5';
            
            // Afficher le statut
            statusDiv.style.display = 'block';
            
            // Envoyer les données
            sendImportRequest(productData, statusIcon, statusText, popupContainer);
        });
    }
    
    /**
     * Échappe le HTML pour l'affichage
     */
    function escapeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
    
    /**
     * Envoie la requête d'importation à l'API
     */
    async function sendImportRequest(productData, statusIcon, statusText, popupContainer) {
        try {
            // Mettre à jour le statut
            statusIcon.textContent = '⏳';
            statusText.textContent = 'Envoi vers le catalogue...';
            
            // Préparer les données
            const payload = {
                sku: productData.sku,
                titre: productData.titre,
                description: productData.description,
                prix_origine: productData.prix_origine,
                image_url: productData.image_url
            };
            
            if (DEV_MODE) {
                console.log('Données à envoyer :', payload);
                console.log('Token utilisé :', API_TOKEN.substring(0, 10) + '...');
            }
            
            // Envoyer la requête
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Token': API_TOKEN
                },
                body: JSON.stringify(payload)
            });
            
            // Lire la réponse
            const result = await response.json();
            
            if (DEV_MODE) {
                console.log('Réponse API :', result);
            }
            
            if (response.ok && result.success) {
                // Succès
                statusIcon.textContent = '✅';
                statusText.textContent = 'Produit importé avec succès !';
                
                // Fermer la popup après 2 secondes
                setTimeout(() => {
                    document.body.removeChild(popupContainer);
                    showStatus('Produit importé avec succès !', 'success');
                }, 2000);
                
            } else {
                // Erreur
                statusIcon.textContent = '❌';
                statusText.textContent = result.error || `Erreur ${response.status}`;
                
                // Réactiver les boutons
                const confirmBtn = document.getElementById('importer-confirm');
                const cancelBtn = document.getElementById('importer-cancel');
                confirmBtn.disabled = false;
                cancelBtn.disabled = false;
                confirmBtn.style.opacity = '1';
                cancelBtn.style.opacity = '1';
                
                if (DEV_MODE && result.validation_errors) {
                    console.error('Erreurs de validation :', result.validation_errors);
                }
            }
            
        } catch (error) {
            console.error('Erreur lors de l\'importation :', error);
            
            statusIcon.textContent = '❌';
            statusText.textContent = 'Erreur de connexion. Vérifiez API_URL et API_TOKEN.';
            
            // Réactiver les boutons
            const confirmBtn = document.getElementById('importer-confirm');
            const cancelBtn = document.getElementById('importer-cancel');
            confirmBtn.disabled = false;
            cancelBtn.disabled = false;
            confirmBtn.style.opacity = '1';
            cancelBtn.style.opacity = '1';
        }
    }
    
    // =====================================================================
    // EXÉCUTION PRINCIPALE DU BOOKMARKLET
    // =====================================================================
    
    /**
     * Fonction principale d'exécution
     */
    async function main() {
        showStatus('Extraction des données produit en cours...', 'info');
        
        // Vérifier que nous sommes sur Alibaba.com
        const isAlibaba = window.location.hostname.includes('alibaba.com');
        const isAliexpress = window.location.hostname.includes('aliexpress.com');
        
        if (!isAlibaba && !isAliexpress && DEV_MODE) {
            const proceed = confirm(
                'Cette page ne semble pas être Alibaba.com ou AliExpress.com.\n' +
                'Souhaitez-vous tout de même essayer d\'extraire les données ?'
            );
            if (!proceed) {
                showStatus('Importation annulée - Page non reconnue', 'warning');
                return;
            }
        }
        
        // Vérifier la configuration
        if (API_URL.includes('votre-domaine.com') || API_TOKEN.includes('CHANGE_ME')) {
            showStatus(
                'ERREUR : Veuillez configurer API_URL et API_TOKEN dans le bookmarklet.\n' +
                'Ouvrez les outils de développement (F12) pour voir les lignes à modifier.',
                'error'
            );
            return;
        }
        
        // Extraire les données
        const productData = {
            titre: extractTitle(),
            sku: extractSKU(),
            prix_origine: extractPrice(),
            image_url: extractImageUrl(),
            description: extractDescription()
        };
        
        // Vérifier les données minimales
        if (!productData.titre || !productData.sku || !productData.prix_origine) {
            let missing = [];
            if (!productData.titre) missing.push('titre');
            if (!productData.sku) missing.push('SKU');
            if (!productData.prix_origine) missing.push('prix');
            
            showStatus(
                `Données insuffisantes. Manquant : ${missing.join(', ')}.\n` +
                'Assurez-vous d\'être sur une page produit Alibaba complète.',
                'error'
            );
            return;
        }
        
        if (DEV_MODE) {
            console.log('Données extraites :', productData);
        }
        
        // Afficher la popup de confirmation
        showConfirmationPopup(productData);
    }
    
    // Démarrer l'extraction
    try {
        main();
    } catch (error) {
        console.error('Erreur dans le bookmarklet :', error);
        showStatus('Erreur inattendue : ' + error.message, 'error');
    }
    
})();
<?php
/**
 * Fonctions globales de l'application
 * Calcul de marge, formatage, sécurité, utilitaires
 */

require_once __DIR__ . '/../config/bootstrap.php';

/**
 * =====================================================================
 * CALCUL DE MARGE ET PRIX
 * =====================================================================
 */

/**
 * Calcule le prix client avec marge de 10%
 * NE JAMAIS afficher le prix_origine directement
 * 
 * @param float $prixOrigine Prix d'achat brut fournisseur
 * @return float Prix client (prix_origine * 1.10)
 */
function calculerPrixClient(float $prixOrigine): float
{
    $marge = config('app.margin_rate', 0.10);
    return round($prixOrigine * (1 + $marge), 2);
}

/**
 * Récupère le taux de marge configuré
 * 
 * @return float Taux de marge (ex: 0.10 pour 10%)
 */
function getMargeRate(): float
{
    return config('app.margin_rate', 0.10);
}

/**
 * Formate un prix en F CFA (Franc CFA)
 * 
 * @param float $prix Prix à formater
 * @param bool $avecSymbole Inclure le symbole "F CFA"
 * @return string Prix formaté (ex: "16 500 F CFA")
 */
function formaterPrix(float $prix, bool $avecSymbole = true): string
{
    // Format français : espace comme séparateur de milliers, virgule pour décimales
    $formate = number_format($prix, 0, ',', ' ');
    
    if ($avecSymbole) {
        return $formate . ' ' . config('app.currency_symbol', 'F CFA');
    }
    
    return $formate;
}

/**
 * Formate un prix avec décimales (pour affichage précis si nécessaire)
 * 
 * @param float $prix Prix à formater
 * @param int $decimales Nombre de décimales
 * @return string Prix formaté
 */
function formaterPrixPrecis(float $prix, int $decimales = 2): string
{
    return number_format($prix, $decimales, ',', ' ') . ' ' . config('app.currency_symbol', 'F CFA');
}

/**
 * =====================================================================
 * FONCTIONS PRODUITS
 * =====================================================================
 */

/**
 * Récupère tous les produits actifs
 * 
 * @param PDO $pdo Connexion base de données
 * @return array Liste des produits avec prix client calculé
 */
function getAllProduits(PDO $pdo): array
{
    $stmt = $pdo->query('
        SELECT id, sku, titre, description, prix_origine, image_url, date_creation
        FROM produits
        ORDER BY date_creation DESC
    ');
    
    $produits = $stmt->fetchAll();
    
    // Ajouter le prix client calculé à chaque produit
    foreach ($produits as &$produit) {
        $produit['prix_client'] = calculerPrixClient((float)$produit['prix_origine']);
        $produit['prix_formate'] = formaterPrix($produit['prix_client']);
    }
    
    return $produits;
}

/**
 * Récupère un produit par son ID
 * 
 * @param PDO $pdo Connexion base de données
 * @param int $id ID du produit
 * @return array|false Produit avec prix client ou false si non trouvé
 */
function getProduitById(PDO $pdo, int $id): array|false
{
    $stmt = $pdo->prepare('
        SELECT id, sku, titre, description, prix_origine, image_url, date_creation
        FROM produits
        WHERE id = ?
    ');
    $stmt->execute([$id]);
    $produit = $stmt->fetch();
    
    if ($produit) {
        $produit['prix_client'] = calculerPrixClient((float)$produit['prix_origine']);
        $produit['prix_formate'] = formaterPrix($produit['prix_client']);
    }
    
    return $produit ?: false;
}

/**
 * Récupère un produit par son SKU
 * 
 * @param PDO $pdo Connexion base de données
 * @param string $sku SKU du produit
 * @return array|false Produit avec prix client ou false si non trouvé
 */
function getProduitBySku(PDO $pdo, string $sku): array|false
{
    $stmt = $pdo->prepare('
        SELECT id, sku, titre, description, prix_origine, image_url, date_creation
        FROM produits
        WHERE sku = ?
    ');
    $stmt->execute([$sku]);
    $produit = $stmt->fetch();
    
    if ($produit) {
        $produit['prix_client'] = calculerPrixClient((float)$produit['prix_origine']);
        $produit['prix_formate'] = formaterPrix($produit['prix_client']);
    }
    
    return $produit ?: false;
}

/**
 * Insère un nouveau produit (utilisé par l'API d'importation et l'admin)
 * 
 * @param PDO $pdo Connexion base de données
 * @param array $data Données du produit (sku, titre, description, prix_origine, image_url)
 * @return int|false ID du produit inséré ou false en cas d'erreur
 */
function insererProduit(PDO $pdo, array $data): int|false
{
    // Vérifier si le SKU existe déjà
    $existing = getProduitBySku($pdo, $data['sku']);
    if ($existing) {
        return false; // SKU déjà existant
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO produits (sku, titre, description, prix_origine, image_url)
        VALUES (?, ?, ?, ?, ?)
    ');
    
    $result = $stmt->execute([
        $data['sku'],
        $data['titre'],
        $data['description'] ?? null,
        $data['prix_origine'],
        $data['image_url'] ?? null,
    ]);
    
    return $result ? (int)$pdo->lastInsertId() : false;
}

/**
 * Met à jour un produit existant
 * 
 * @param PDO $pdo Connexion base de données
 * @param int $id ID du produit
 * @param array $data Données à mettre à jour
 * @return bool Succès de la mise à jour
 */
function updateProduit(PDO $pdo, int $id, array $data): bool
{
    $allowedFields = ['titre', 'description', 'prix_origine', 'image_url'];
    $setParts = [];
    $values = [];
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $setParts[] = "`$field` = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($setParts)) {
        return false;
    }
    
    $values[] = $id;
    $sql = 'UPDATE produits SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($values);
}

/**
 * Supprime un produit
 * 
 * @param PDO $pdo Connexion base de données
 * @param int $id ID du produit
 * @return bool Succès de la suppression
 */
function deleteProduit(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM produits WHERE id = ?');
    return $stmt->execute([$id]);
}

/**
 * Import CSV de produits
 * 
 * @param PDO $pdo Connexion base de données
 * @param string $csvPath Chemin vers le fichier CSV
 * @return array Résultat : ['success' => int, 'errors' => array, 'skipped' => int]
 */
function importProduitsFromCSV(PDO $pdo, string $csvPath): array
{
    $result = ['success' => 0, 'errors' => [], 'skipped' => 0];
    
    if (!file_exists($csvPath) || !is_readable($csvPath)) {
        $result['errors'][] = 'Fichier CSV introuvable ou illisible';
        return $result;
    }
    
    $handle = fopen($csvPath, 'r');
    if (!$handle) {
        $result['errors'][] = 'Impossible d\'ouvrir le fichier CSV';
        return $result;
    }
    
    // Lire l'en-tête
    $headers = fgetcsv($handle, 1000, ',', '"', '\\');
    if (!$headers) {
        fclose($handle);
        $result['errors'][] = 'Fichier CSV vide';
        return $result;
    }
    
    // Normaliser les en-têtes (minuscules, sans accents)
    $headerMap = [];
    foreach ($headers as $i => $h) {
        $key = strtolower(trim($h));
        $key = str_replace(['é', 'è', 'ê', 'à', 'ù', 'ô', 'î', 'ç'], 
                          ['e', 'e', 'e', 'a', 'u', 'o', 'i', 'c'], $key);
        $headerMap[$key] = $i;
    }
    
    // Colonnes requises
    $required = ['sku', 'titre', 'prix', 'prix_origine'];
    $prixColumn = null;
    
    foreach (['prix_origine', 'prix', 'price', 'cout', 'coût'] as $col) {
        if (isset($headerMap[$col])) {
            $prixColumn = $headerMap[$col];
            break;
        }
    }
    
    if (!$prixColumn) {
        fclose($handle);
        $result['errors'][] = 'Colonne prix introuvable (attendu: prix_origine, prix, price, cout ou coût)';
        return $result;
    }
    
    $skuColumn = $headerMap['sku'] ?? $headerMap['reference'] ?? $headerMap['ref'] ?? null;
    $titreColumn = $headerMap['titre'] ?? $headerMap['title'] ?? $headerMap['nom'] ?? $headerMap['produit'] ?? null;
    $descColumn = $headerMap['description'] ?? $headerMap['desc'] ?? null;
    $imageColumn = $headerMap['image'] ?? $headerMap['image_url'] ?? $headerMap['url_image'] ?? $headerMap['photo'] ?? null;
    
    if ($skuColumn === null || $titreColumn === null) {
        fclose($handle);
        $result['errors'][] = 'Colonnes obligatoires manquantes (sku, titre)';
        return $result;
    }
    
    $rowNum = 1;
    while (($row = fgetcsv($handle, 1000, ',', '"', '\\')) !== false) {
        $rowNum++;
        
        // Ignorer les lignes vides
        if (array_filter($row) === []) {
            continue;
        }
        
        $sku = trim($row[$skuColumn] ?? '');
        $titre = trim($row[$titreColumn] ?? '');
        $prixOrigine = (float)str_replace([',', ' '], ['.', ''], $row[$prixColumn] ?? '0');
        
        if (empty($sku) || empty($titre) || $prixOrigine <= 0) {
            $result['errors'][] = "Ligne $rowNum : Données invalides (SKU: $sku, Titre: $titre, Prix: $prixOrigine)";
            continue;
        }
        
        $data = [
            'sku' => $sku,
            'titre' => $titre,
            'description' => $descColumn !== null ? trim($row[$descColumn] ?? '') : null,
            'prix_origine' => $prixOrigine,
            'image_url' => $imageColumn !== null ? trim($row[$imageColumn] ?? '') : null,
        ];
        
        $insertId = insererProduit($pdo, $data);
        if ($insertId) {
            $result['success']++;
        } else {
            $result['skipped']++;
            $result['errors'][] = "Ligne $rowNum : SKU '$sku' déjà existant";
        }
    }
    
    fclose($handle);
    return $result;
}

/**
 * =====================================================================
 * GESTION DU PANIER (SESSION)
 * =====================================================================
 */

const CART_SESSION_KEY = 'alibaba_cart';

/**
 * Récupère le panier actuel
 * 
 * @return array Panier avec produits et totaux calculés
 */
function getCart(): array
{
    $cart = $_SESSION[CART_SESSION_KEY] ?? [];
    $pdo = getPDO();
    $enrichedCart = [];
    $total = 0.0;
    $itemCount = 0;
    
    foreach ($cart as $sku => $item) {
        $produit = getProduitBySku($pdo, $sku);
        if ($produit) {
            $quantite = (int)($item['quantite'] ?? 1);
            $prixUnitaire = $produit['prix_client'];
            $sousTotal = $prixUnitaire * $quantite;
            
            $enrichedCart[$sku] = [
                'produit' => $produit,
                'quantite' => $quantite,
                'prix_unitaire' => $prixUnitaire,
                'prix_unitaire_formate' => formaterPrix($prixUnitaire),
                'sous_total' => $sousTotal,
                'sous_total_formate' => formaterPrix($sousTotal),
            ];
            
            $total += $sousTotal;
            $itemCount += $quantite;
        }
    }
    
    return [
        'items' => $enrichedCart,
        'total' => $total,
        'total_formate' => formaterPrix($total),
        'item_count' => $itemCount,
        'produits_count' => count($enrichedCart),
    ];
}

/**
 * Ajoute un produit au panier
 * 
 * @param string $sku SKU du produit
 * @param int $quantite Quantité à ajouter (défaut: 1)
 * @return bool Succès de l'ajout
 */
function addToCart(string $sku, int $quantite = 1): bool
{
    $pdo = getPDO();
    $produit = getProduitBySku($pdo, $sku);
    
    if (!$produit) {
        return false;
    }
    
    if (!isset($_SESSION[CART_SESSION_KEY])) {
        $_SESSION[CART_SESSION_KEY] = [];
    }
    
    $cart = &$_SESSION[CART_SESSION_KEY];
    
    if (isset($cart[$sku])) {
        $cart[$sku]['quantite'] += $quantite;
    } else {
        $cart[$sku] = ['quantite' => $quantite];
    }
    
    return true;
}

/**
 * Met à jour la quantité d'un produit dans le panier
 * 
 * @param string $sku SKU du produit
 * @param int $quantite Nouvelle quantité (0 pour supprimer)
 * @return bool Succès de la mise à jour
 */
function updateCartQuantity(string $sku, int $quantite): bool
{
    if (!isset($_SESSION[CART_SESSION_KEY][$sku])) {
        return false;
    }
    
    if ($quantite <= 0) {
        unset($_SESSION[CART_SESSION_KEY][$sku]);
    } else {
        $_SESSION[CART_SESSION_KEY][$sku]['quantite'] = $quantite;
    }
    
    return true;
}

/**
 * Supprime un produit du panier
 * 
 * @param string $sku SKU du produit
 * @return bool Succès de la suppression
 */
function removeFromCart(string $sku): bool
{
    if (isset($_SESSION[CART_SESSION_KEY][$sku])) {
        unset($_SESSION[CART_SESSION_KEY][$sku]);
        return true;
    }
    return false;
}

/**
 * Vide complètement le panier
 */
function clearCart(): void
{
    unset($_SESSION[CART_SESSION_KEY]);
}

/**
 * =====================================================================
 * GESTION DES COMMANDES
 * =====================================================================
 */

/**
 * Génère une référence de commande unique
 * 
 * @return string Référence au format CMD-YYYYMMDD-XXXXXX
 */
function genererReferenceCommande(): string
{
    $date = date('Ymd');
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    return "CMD-$date-$random";
}

/**
 * Crée une commande en base de données
 * 
 * @param PDO $pdo Connexion base de données
 * @param array $clientInfo Infos client (nom, email, telephone)
 * @param array $cart Panier (retour de getCart())
 * @return int|false ID de la commande créée ou false en cas d'erreur
 */
function creerCommande(PDO $pdo, array $clientInfo, array $cart): int|false
{
    $reference = genererReferenceCommande();
    $total = $cart['total'];
    
    // Début transaction
    $pdo->beginTransaction();
    
    try {
        // 1. Insérer la commande
        $stmt = $pdo->prepare('
            INSERT INTO commandes (reference, nom_client, email_client, telephone_client, total_ht, statut)
            VALUES (?, ?, ?, ?, ?, "en_attente")
        ');
        $stmt->execute([
            $reference,
            $clientInfo['nom'],
            $clientInfo['email'],
            $clientInfo['telephone'],
            $total,
        ]);
        
        $commandeId = (int)$pdo->lastInsertId();
        
        // 2. Insérer les détails de commande
        $stmtDetail = $pdo->prepare('
            INSERT INTO details_commandes (commande_id, produit_id, sku_produit, titre_produit, prix_unitaire, quantite, sous_total)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        
        foreach ($cart['items'] as $sku => $item) {
            $stmtDetail->execute([
                $commandeId,
                $item['produit']['id'],
                $sku,
                $item['produit']['titre'],
                $item['prix_unitaire'],
                $item['quantite'],
                $item['sous_total'],
            ]);
        }
        
        $pdo->commit();
        return $commandeId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Erreur création commande: ' . $e->getMessage());
        return false;
    }
}

/**
 * Récupère une commande avec ses détails
 * 
 * @param PDO $pdo Connexion base de données
 * @param int $commandeId ID de la commande
 * @return array|false Commande avec détails ou false
 */
function getCommandeWithDetails(PDO $pdo, int $commandeId): array|false
{
    $stmt = $pdo->prepare('
        SELECT c.*, 
               dc.sku_produit, dc.titre_produit, dc.prix_unitaire, dc.quantite, dc.sous_total
        FROM commandes c
        LEFT JOIN details_commandes dc ON dc.commande_id = c.id
        WHERE c.id = ?
    ');
    $stmt->execute([$commandeId]);
    $rows = $stmt->fetchAll();
    
    if (empty($rows)) {
        return false;
    }
    
    $commande = $rows[0];
    $commande['details'] = [];
    $commande['total_formate'] = formaterPrix($commande['total_ht']);
    
    foreach ($rows as $row) {
        if ($row['sku_produit']) {
            $commande['details'][] = [
                'sku' => $row['sku_produit'],
                'titre' => $row['titre_produit'],
                'prix_unitaire' => $row['prix_unitaire'],
                'prix_unitaire_formate' => formaterPrix($row['prix_unitaire']),
                'quantite' => $row['quantite'],
                'sous_total' => $row['sous_total'],
                'sous_total_formate' => formaterPrix($row['sous_total']),
            ];
        }
    }
    
    return $commande;
}

/**
 * =====================================================================
 * ENVOI D'EMAIL
 * =====================================================================
 */

/**
 * Envoie un email de notification de commande à l'admin
 * 
 * @param array $commande Commande avec détails (retour de getCommandeWithDetails)
 * @return bool Succès de l'envoi
 */
function envoyerEmailCommandeAdmin(array $commande): bool
{
    $config = config('security');
    $to = $config['admin_email'];
    $subject = "Nouvelle commande #{$commande['reference']} - {$commande['nom_client']}";
    
    // Construire le corps de l'email en HTML
    $htmlBody = "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2563eb; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
            .footer { background: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
            th { background: #f3f4f6; font-weight: 600; }
            .total-row { background: #fef3c7; font-weight: bold; }
            .info-row { background: white; }
            .label { font-weight: 600; color: #374151; width: 180px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0;'>🛒 Nouvelle commande reçue</h1>
                <p style='margin: 5px 0 0;'>Référence : <strong>{$commande['reference']}</strong></p>
            </div>
            <div class='content'>
                <h2>Informations client</h2>
                <table>
                    <tr class='info-row'><td class='label'>Nom :</td><td>{$commande['nom_client']}</td></tr>
                    <tr class='info-row'><td class='label'>Email :</td><td>{$commande['email_client']}</td></tr>
                    <tr class='info-row'><td class='label'>Téléphone (Mobile Money) :</td><td>{$commande['telephone_client']}</td></tr>
                    <tr class='info-row'><td class='label'>Date :</td><td>" . date('d/m/Y à H:i', strtotime($commande['date_creation'])) . "</td></tr>
                </table>
                
                <h2>Détail de la commande</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>SKU Alibaba</th>
                            <th style='text-align: right;'>Prix unitaire</th>
                            <th style='text-align: center;'>Qté</th>
                            <th style='text-align: right;'>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($commande['details'] as $detail) {
        $htmlBody .= "
                        <tr>
                            <td>{$detail['titre']}</td>
                            <td><strong>{$detail['sku']}</strong></td>
                            <td style='text-align: right;'>{$detail['prix_unitaire_formate']}</td>
                            <td style='text-align: center;'>{$detail['quantite']}</td>
                            <td style='text-align: right;'>{$detail['sous_total_formate']}</td>
                        </tr>";
    }
    
    $htmlBody .= "
                        <tr class='total-row'>
                            <td colspan='4' style='text-align: right;'><strong>TOTAL</strong></td>
                            <td style='text-align: right;'><strong>{$commande['total_formate']}</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <p><strong>⚠️ Action requise :</strong> Commandez les produits ci-dessus chez le fournisseur Alibaba en utilisant les codes SKU fournis.</p>
            </div>
            <div class='footer'>
                <p>Catalogue Alibaba Import - Système automatisé de notification</p>
                <p>Ne pas répondre à cet email automatique.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Version texte pour compatibilité
    $textBody = "Nouvelle commande #{$commande['reference']}\n\n";
    $textBody .= "Client : {$commande['nom_client']}\n";
    $textBody .= "Email : {$commande['email_client']}\n";
    $textBody .= "Téléphone : {$commande['telephone_client']}\n";
    $textBody .= "Date : " . date('d/m/Y à H:i', strtotime($commande['date_creation'])) . "\n\n";
    $textBody .= "DÉTAILS :\n";
    
    foreach ($commande['details'] as $detail) {
        $textBody .= "- {$detail['titre']} (SKU: {$detail['sku']}) x{$detail['quantite']} = {$detail['sous_total_formate']}\n";
    }
    
    $textBody .= "\nTOTAL : {$commande['total_formate']}\n\n";
    $textBody .= "Commandez ces produits chez le fournisseur Alibaba avec les SKU indiqués.\n";
    
    // Envoi via la fonction mail() native (à remplacer par PHPMailer/SMTP en production)
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . config('security.mail.from_name') . ' <' . config('security.mail.from_email') . '>',
        'Reply-To: ' . config('security.mail.from_email'),
        'X-Mailer: PHP/' . phpversion(),
    ];
    
    // Si configuration SMTP disponible, utiliser une bibliothèque comme PHPMailer
    // Pour l'instant, on utilise mail() native
    return mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * =====================================================================
 * VALIDATION ET SÉCURITÉ
 * =====================================================================
 */

/**
 * Valide un email
 */
function validerEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valide un numéro de téléphone (format international basique)
 */
function validerTelephone(string $telephone): bool
{
    // Format : +225 XX XX XX XX ou 0X XX XX XX XX ou XX XX XX XX
    $clean = preg_replace('/[\s\-\.\(\)]/', '', $telephone);
    return preg_match('/^(\+?\d{1,3})?\d{8,15}$/', $clean) === 1;
}

/**
 * Valide un SKU (alphanumérique, tirets, underscores)
 */
function validerSku(string $sku): bool
{
    return preg_match('/^[A-Za-z0-9_\-]{3,50}$/', $sku) === 1;
}

/**
 * Valide un prix (positif, max 2 décimales)
 */
function validerPrix(float $prix): bool
{
    return $prix > 0 && $prix < 10000000; // Max 10 millions
}

/**
 * Nettoie une chaîne pour insertion en base (protection complémentaire)
 * Note : Les requêtes préparées protègent déjà contre l'injection SQL
 */
function sanitizeString(string $input, int $maxLength = 255): string
{
    $clean = trim($input);
    $clean = strip_tags($clean);
    $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
    return $clean;
}

/**
 * Génère un slug URL-friendly
 */
function slugify(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');
    return $text;
}

/**
 * =====================================================================
 * AUTHENTIFICATION ADMIN
 * =====================================================================
 */

/**
 * Vérifie si l'utilisateur est connecté en admin
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Connecte un admin
 */
function adminLogin(string $username, string $password): bool
{
    $config = config('security');
    $adminUsers = $config['admin_users'] ?? [];
    
    if (isset($adminUsers[$username]) && password_verify($password, $adminUsers[$username])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        return true;
    }
    
    return false;
}

/**
 * Déconnecte l'admin
 */
function adminLogout(): void
{
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['admin_login_time']);
}

/**
 * Exige une connexion admin (redirige si non connecté)
 */
function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        redirect('/admin/login.php');
    }
}

/**
 * =====================================================================
 * MOBILE MONEY
 * =====================================================================
 */

/**
 * Récupère les infos Mobile Money pour la page de confirmation
 */
function getMobileMoneyInfo(float $montant): array
{
    $config = config('mobile_money');
    $instruction = str_replace(
        ['{montant}', '{devise}', '{numero}'],
        [formaterPrix($montant, false), config('app.currency_symbol'), $config['number']],
        $config['instructions']
    );
    
    return [
        'numero' => $config['number'],
        'reseaux' => $config['networks'],
        'montant' => $montant,
        'montant_formate' => formaterPrix($montant),
        'instruction' => $instruction,
    ];
}
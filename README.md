# Catalogue Alibaba Import - Système Complet

Site web complet pour catalogue de produits importés d'Alibaba, développé en **PHP natif**, **MySQL**, **HTML/CSS/JavaScript** sans framework lourd.

---

## 📋 Fonctionnalités

| Module | Description |
|--------|-------------|
| **Catalogue client** | Grille de produits moderne avec prix marge 10% incluse |
| **Panier session** | Gestion quantités, total dynamique, formulaire client obligatoire |
| **Admin protégé** | Connexion sécurisée, CRUD produits, import CSV |
| **API Import** | Endpoint sécurisé par token pour insertion programmatique |
| **Bookmarklet 1-clic** | Extraction auto depuis Alibaba.com → votre base |
| **Commande + Email** | Enregistrement BDD + email admin avec SKU Alibaba |
| **Confirmation Mobile Money** | Instructions paiement manuel (Moov, TMoney, Orange, Wave) |

---

## 🏗 Architecture des dossiers

```
alibaba-catalog/
├── admin/                    # Administration
│   ├── index.php            # Dashboard produits (CRUD + CSV)
│   ├── login.php            # Connexion admin
│   └── logout.php           # Déconnexion
├── api/
│   └── api_import.php       # API import sécurisée (POST JSON + token)
├── assets/
│   ├── css/
│   │   └── style.css        # Design complet responsive
│   ├── js/
│   │   ├── bookmarklet.js       # Version lisible (dev)
│   │   └── bookmarklet-minified.js  # Version one-liner (favori)
│   └── images/
├── config/
│   ├── bootstrap.php        # Initialisation (session, PDO, helpers)
│   └── database.php         # Configuration (DB, sécurité, mail, MM)
├── includes/
│   └── functions.php        # Logique métier (marge, panier, commandes, email)
├── index.php                # Catalogue client
├── panier.php               # Panier + formulaire client
├── valider.php              # Traitement commande + email
├── confirmation.php         # Page instructions Mobile Money
├── database.sql             # Script création BDD + tables + données test
└── README.md                # Ce fichier
```

---

## 🚀 Installation

### 1. Prérequis
- **PHP 8.1+** (extensions : pdo_mysql, mbstring, json)
- **MySQL 5.7+ / MariaDB 10.3+**
- Serveur web (Apache/Nginx) avec `mod_rewrite` ou équivalent
- HTTPS recommandé pour la sécurité (token API, sessions)

### 2. Base de données
```bash
# Créer la base et les tables
mysql -u root -p < database.sql
```
Ou importer `database.sql` via phpMyAdmin / Adminer.

### 3. Configuration
Éditer `config/database.php` avec vos identifiants :

```php
'db' => [
    'host' => 'localhost',
    'port' => '3306',
    'name' => 'alibaba_catalog',
    'user' => 'votre_user',
    'pass' => 'votre_mot_de_passe',
],

'security' => [
    // ⚠️ OBLIGATOIRE : Changez ces valeurs en production !
    'import_api_token' => 'VOTRE_TOKEN_SECRET_UNIQUE_ICI',
    'session_secret'   => 'CHAINE_ALEATOIRE_LONGUE_ET_UNIQUE',
    
    // Email admin pour notifications commande
    'admin_email' => 'votre@email.com',
    
    // Configuration SMTP (optionnel - utilise mail() natif sinon)
    'mail' => [
        'from_email' => 'noreply@votre-domaine.com',
        'from_name'  => 'Votre Catalogue',
        'smtp_host'  => 'smtp.votre-hebergeur.com',
        'smtp_port'  => 587,
        'smtp_user'  => 'votre@email.com',
        'smtp_pass'  => 'votre_mot_de_passe_smtp',
        'smtp_secure' => 'tls',
    ],
],

'mobile_money' => [
    'number' => '+225 XX XX XX XX',  // Votre numéro Mobile Money
],
```

> 💡 **Astuce** : Pour ne pas commiter les secrets, utilisez des variables d'environnement (voir commentaires dans `database.php`).

### 4. Permissions
```bash
# Dossiers accessibles en écriture si upload CSV
chmod 755 admin/ api/ assets/
```

### 5. Virtual Host (Apache)
```apache
<VirtualHost *:80>
    ServerName alibaba-import.local
    DocumentRoot /chemin/vers/alibaba-catalog
    
    <Directory /chemin/vers/alibaba-catalog>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Sécurité : interdire l'accès direct aux fichiers sensibles
    <FilesMatch "\.(sql|php)$">
        Require all denied
    </FilesMatch>
    
    # Autoriser les fichiers publics
    <FilesMatch "^(index|panier|valider|confirmation)\.php$">
        Require all granted
    </FilesMatch>
    
    # API
    <Files "api_import.php">
        Require all granted
    </FilesMatch>
    
    # Admin
    <Directory /chemin/vers/alibaba-catalog/admin>
        <Files "login.php">
            Require all granted
        </Files>
        <Files "index.php">
            Require all granted
        </Files>
    </Directory>
</VirtualHost>
```

### 6. Premier accès
1. Ouvrir `https://votre-domaine.com/admin/login.php`
2. Connectez-vous : **admin** / **admin123**
3. **⚠️ Changez immédiatement le mot de passe** dans `config/database.php` (générez un hash avec `password_hash('nouveau_mdp', PASSWORD_DEFAULT)`)

---

## 🔧 Utilisation

### 📦 Catalogue Client (Public)
- **Accueil** : `https://votre-domaine.com/`
- Grille de produits responsive
- Bouton "Ajouter à ma sélection" → ajoute au panier (session)

### 🛒 Panier
- **URL** : `https://votre-domaine.com/panier.php`
- Modifier quantités (+/- / input direct)
- Formulaire obligatoire : Nom, Email, Téléphone Mobile Money
- Clic "Valider ma commande" → `valider.php`

### ✅ Validation & Email
`valider.php` :
1. Vérifie CSRF + données client
2. Crée commande en BDD (tables `commandes` + `details_commandes`)
3. Envoie email **HTML + texte** à l'admin avec :
   - Coordonnées client
   - Tableau détaillé : Produit, **SKU Alibaba**, Quantité, Prix unitaire (marge), Sous-total
   - Total général
4. Vide le panier
5. Redirige vers `confirmation.php`

### 📱 Page Confirmation
Affiche :
- Référence commande
- **Montant exact à payer** (avec marge 10%)
- **Numéro Mobile Money** (copiable)
- Réseaux acceptés : Moov Money, TMoney, Orange Money, Wave
- Étapes suivantes

---

## ⚡ Import 1-clic depuis Alibaba (Bookmarklet)

### Installation du bouton
1. Ouvrez `assets/js/bookmarklet-minified.js`
2. **Modifiez les 2 premières lignes** :
   ```javascript
   const API_URL = 'https://VOTRE-DOMAINE.COM/api/api_import.php';
   const API_TOKEN = 'VOTRE_TOKEN_SECRET_IDENTIQUE_A_CONFIG';
   ```
3. Copiez **tout le contenu** (une seule ligne commençant par `javascript:`)
4. Créez un **nouveau favori** dans votre navigateur :
   - **Nom** : `🛒 Importer Alibaba`
   - **URL** : *collez le code copié*
5. Placez le favori dans la barre des favoris

### Utilisation
1. Allez sur une **fiche produit Alibaba.com** (ex: `detail.1688.com/...` ou `fr.alibaba.com/product/...`)
2. Cliquez sur le favori **🛒 Importer Alibaba**
3. Une popup affiche les données extraites (titre, SKU, prix, image, description)
4. Vérifiez → Cliquez **"Importer le produit"**
5. Confirmation → Le produit apparaît dans votre admin !

> ⚠️ **Note** : Les sélecteurs CSS dans le bookmarklet ciblent les structures HTML courantes d'Alibaba. Si l'extraction échoue, vous pouvez modifier les sélecteurs dans `assets/js/bookmarklet.js` (version lisible) puis re-minifier.

---

## 📥 Import CSV (Admin)

Dans l'admin (`/admin/index.php`) → Section "Import en masse par CSV"

**Format attendu** (ordre des colonnes libre, en-têtes obligatoires) :

| Colonne (aliases acceptés) | Obligatoire | Exemple |
|---|---|---|
| `sku` / `reference` / `ref` | ✅ | `ALB-001` |
| `titre` / `nom` / `produit` / `title` | ✅ | `Écouteurs Bluetooth Pro` |
| `prix_origine` / `prix` / `price` / `cout` / `coût` | ✅ | `15000.00` |
| `description` / `desc` | ❌ | `Réduction bruit active...` |
| `image` / `image_url` / `photo` / `url_image` | ❌ | `https://img.com/photo.jpg` |

Exemple CSV :
```csv
sku,titre,prix_origine,description,image
ALB-100,Montre Connectée GPS,45000,Montre sport étanche 50m,https://img.com/montre.jpg
ALB-101,Chargeur USB-C 65W,12000,Chargeur GaN compact,https://img.com/chargeur.jpg
```

---

## 🔐 Sécurité implémentée

| Protection | Implémentation |
|---|---|
| **Injection SQL** | Requêtes préparées PDO partout (`?` binding) |
| **XSS** | `htmlspecialchars()` via helper `e()` sur toutes sorties |
| **CSRF** | Token par session (`generateCsrfToken()` / `verifyCsrfToken()`) |
| **Session** | `httponly`, `secure` (HTTPS), `samesite=Lax`, régénération ID |
| **API Token** | Vérification `hash_equals()` (timing-safe) + header `X-API-Token` |
| **Admin Auth** | `password_hash` / `password_verify` (bcrypt) |
| **Validation** | Côté serveur stricte (types, longueurs, formats) |

---

## 🧪 Tests rapides

```bash
# 1. Test BDD
php -r "require 'config/bootstrap.php'; \$pdo = getPDO(); echo 'DB OK: ' . \$pdo->query('SELECT 1')->fetchColumn();"

# 2. Test fonction marge
php -r "require 'config/bootstrap.php'; require 'includes/functions.php'; echo '15000 * 1.10 = ' . calculerPrixClient(15000) . ' => ' . formaterPrix(calculerPrixClient(15000));"

# 3. Test API (depuis terminal avec curl)
curl -X POST https://votre-domaine.com/api/api_import.php \
  -H "Content-Type: application/json" \
  -H "X-API-Token: VOTRE_TOKEN" \
  -d '{"sku":"TEST-001","titre":"Produit Test","prix_origine":10000,"image_url":"","description":"Test API"}'
```

---

## 📧 Configuration Email (Production)

La fonction `envoyerEmailCommandeAdmin()` utilise `mail()` native PHP.

**Pour production**, remplacez par **PHPMailer** ou **Symfony Mailer** :

```bash
composer require phpmailer/phpmailer
```

Puis dans `includes/functions.php`, fonction `envoyerEmailCommandeAdmin()` :
```php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = config('security.mail.smtp_host');
$mail->SMTPAuth   = true;
$mail->Username   = config('security.mail.smtp_user');
$mail->Password   = config('security.mail.smtp_pass');
$mail->SMTPSecure = config('security.mail.smtp_secure');
$mail->Port       = config('security.mail.smtp_port');

$mail->setFrom(config('security.mail.from_email'), config('security.mail.from_name'));
$mail->addAddress(config('security.admin_email'));
$mail->isHTML(true);
$mail->Subject = $subject;
$mail->Body    = $htmlBody;
$mail->AltBody = $textBody;
$mail->send();
```

---

## 🎨 Personnalisation

### Couleurs (CSS Variables)
Éditez `:root` dans `assets/css/style.css` :
```css
:root {
    --color-primary: #VOTRE_COULEUR;
    --color-primary-dark: #VOTRE_COULEUR_FONCEE;
}
```

### Taux de marge
Dans `config/database.php` :
```php
'app' => [
    'margin_rate' => 0.15,  // 15% au lieu de 10%
],
```

### Devise
```php
'currency' => 'FCFA',
'currency_symbol' => 'F CFA',
```

---

## 📝 Licence & Auteur

Développé sur mesure pour import Alibaba → Catalogue e-commerce local.
Code propriétaire - Usage interne uniquement.

---

## 🆘 Dépannage

| Problème | Solution |
|---|---|
| Page blanche | Activer `display_errors` dans `bootstrap.php` (debug=true) |
| Erreur 500 API | Vérifier `error_log` + token exact dans config + header `X-API-Token` |
| Email non reçu | Vérifier `mail.log` + config SMTP + spam + `sendmail_path` php.ini |
| Bookmarklet n'extrait rien | Ouvrir console F12 sur Alibaba → adapter sélecteurs dans `bookmarklet.js` |
| CSS/JS non chargé | Vérifier chemins relatifs + virtual host `DocumentRoot` |
| Session perdue | Vérifier `session.save_path` writable + HTTPS + `session.cookie_secure` |

---

## 📞 Support

Pour toute question technique sur l'installation ou la personnalisation, consultez le code source commenté ou ouvrez une issue sur votre dépôt privé.

**Bon développement ! 🚀**
# 🚀 Guide de Déploiement Railway (Step-by-Step)

## 📋 Récapitulatif - Ce qui a été préparé

✅ **Fichiers créés/modifiés pour Railway** :
- `composer.json` - Dépendances PHP
- `nixpacks.toml` - Configuration Nixpacks (build Railway)
- `railway.json` - Configuration Railway
- `config/database.php` - Lira automatiquement les variables Railway
- `config/bootstrap.php` - Sessions en BDD (nécessaire pour Railway)
- `.gitignore` - Exclut fichiers sensibles

✅ **Repo Git initialisé** : `6db03ef`

---

## 🔧 Étape 1 : Créer le repo GitHub (5 minutes)

### 1. Ouvrir https://github.com/new

### 2. Remplir le formulaire :

| Champ | Valeur à utiliser |
|-------|-------------------|
| **Owner** | `neyozeboulet-create` |
| **Repository name** | `alibaba-catalog` (ou un nom de ton choix) |
| **Description** | `Catalogue de produits importés d'Alibaba - PHP/MySQL` |
| **Public** | ✅ Coché (ou non selon besoin) |
| **Add README** | ✅ Coché (facultatif) |
| **.gitignore** | Sélectionner `PHP` |
| **License** | Aucun ou MIT |

### 3. Click "Create repository"

---

## 🔄 Étape 2 : Pousser le code vers GitHub (2 minutes)

### 1. Ajouter le remote GitHub
```bash
cd C:\Users\Admin\Documents\Dev\alibaba-catalog
git remote add origin https://github.com/neyozeboulet-create/alibaba-catalog.git
# Remplace l'URL ci-dessus par le tien si différent
```

### 2. Pousser le code
```bash
git branch -M main
git push -u origin main
```

> ⚠️ **Si demande de token** : Crée un **Personal Access Token** sur GitHub :
> 1. GitHub → Settings → Developer settings → Personal access tokens
> 2. Click "Generate new token" → "Fine-grained"
> 3. Permissions : `public_repo` (ou `private_repo` si repo privé)
> 4. Expires : "No expiration" ou 1 an
> 5. Copier le token et utilise-le comme mot de passe git

---

## 🚂 Étape 3 : Déployer sur Railway (5 minutes)

### 1. Ouvrir https://railway.app

### 2. Click "Start a New Project"

### 3. "Deploy from GitHub repo"

### 4. Sélectionner ton repo : `neyozeboulet-create/alibaba-catalog`

### 5. Railway détectera automatiquement :
- ✅ `composer.json` → installera PHP + dépendances
- ✅ `nixpacks.toml` → configura le build

### 6. Click "Deploy"

---

## 🗄️ Étape 4 : Ajouter MySQL (2 minutes)

### 1. Dans Railway, click "MySQL" dans la sidebar

### 2. Click "+ Add Database"

### 3. Railway crée automatiquement les variables :
- `MYSQLHOST`
- `MYSQLPORT`
- `MYSQLUSER`
- `MYSQLPASSWORD`
- `MYSQLDATABASE`

### 4. Click "Deploy" (Railway redéploie)

---

## ⚙️ Étape 5 : Configurer les variables d'environnement (5 minutes)

### 1. Dans Railway, click "Variables" (sidebar)

### 2. Ajouter les variables suivantes :

| Variable | Description | Exemple |
|----------|-------------|---------|
| `IMPORT_API_TOKEN` | Token pour l'API d'import (généré avec `openssl rand -hex 32`) | `a1b2c3d4e5f6...` |
| `SESSION_SECRET` | Clé de session (généré avec `openssl rand -hex 64`) | `x1y2z3a4b5c6...` |
| `ADMIN_PASSWORD_HASH` | Hash du mot de passe admin | `password_hash('mon_mdp', PASSWORD_DEFAULT)` |
| `ADMIN_EMAIL` | Email pour recevoir les commandes | `ton@email.com` |
| `MOBILE_MONEY_NUMBER` | Ton numéro Mobile Money | `+225 XX XX XX XX` |
| `SMTP_HOST` | Serveur SMTP (optionnel) | `smtp.sendgrid.net` |
| `SMTP_USER` | Utilisateur SMTP | `apikey` |
| `SMTP_PASS` | Mot de passe SMTP | `SG.xxxxxx` |
| `SMTP_SECURE` | TLS ou SSL | `tls` |

### 3. Click "Save"

### 4. Click "Deploy" (Railway redéploie)

---

## 🎯 Étape 6 : Initialiser la base de données (1 minute)

Railway exécutera automatiquement `database.sql` au premier déploiement grâce au code dans `bootstrap.php`.

Vérifier dans les logs Railway :
```
✅ Database migrations executed successfully
```

---

## 🌐 Étape 7 : Configurer le domaine personnalisé (facultatif)

### 1. Dans Railway → Settings → Domains → Add Custom Domain

### 2. Ajouter `catalogue.votredomaine.com`

### 3. Chez ton registrar DNS, ajouter CNAME :
```
Type: CNAME
Name: catalogue
Value: ton-projet.up.railway.app
```

---

## 🧪 Étape 8 : Tester

### 1. Accéder à :
- **Site public** : `https://ton-projet.up.railway.app`
- **Admin** : `https://ton-projet.up.railway.app/admin/login.php`
  - Username : `admin`
  - Password : `admin123`

### 2. Modifier le mot de passe immédiatement dans `config/database.php` → `ADMIN_PASSWORD_HASH`

---

## 🛠️ Dépannage

| Problème | Solution |
|----------|----------|
| Erreur "Table php_sessions doesn't exist" | Vérifier logs Railway, la migration devrait s'exécuter automatiquement |
| Erreur "No route matches" | Ajouter `.htaccess` à la racine (déjà fait) |
| Erreur 500 | Vérifier variables d'environnement, logs Railway |
| Email non envoyé | Configurer SMTP (SendGrid/Resend) dans les variables |

---

## 📚 Commandes utiles

```bash
# Vérifier la config locale
php -r "require 'config/bootstrap.php'; print_r(config('db'));"

# Générer un token secret
openssl rand -hex 32

# Générer un secret de session
openssl rand -hex 64

# Générer un hash de mot de passe
php -r "echo password_hash('admin123', PASSWORD_DEFAULT);"

# Lancer le serveur local pour test
php -S localhost:8000
```

---

## 📖 Documentation Railway

- [Nixpacks PHP](https://nixpacks.com/docs/language-guides/php/)
- [Railway Variables](https://docs.railway.app/deploy/variables)
- [Railway MySQL](https://docs.railway.app/guides/mysql)

---

## 🆘 Besoin d'aide ?

Si tu bloques à une étape, copie-colle l'erreur ici et je t'aide !
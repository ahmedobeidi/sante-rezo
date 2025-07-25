# SantéRézo 🏥

Une plateforme web de gestion de rendez-vous médicaux. Les patients peuvent prendre rendez-vous avec des médecins spécialisés facilement !

## 🎯 À propos du projet

SantéRézo est une app web qui simplifie la prise de rendez-vous médicaux. Plus besoin d'appeler ! Tout se fait en ligne :

- **Patients** : Chercher des médecins, voir leurs dispos et réserver
- **Médecins** : Gérer leur planning et suivre leurs patients  
- **Admins** : Superviser la plateforme

## 🛠️ Technologies utilisées

### Backend
- **PHP 8.2+**
- **Symfony 7.2** - Le framework principal
- **Doctrine ORM** - Pour la base de données
- **Twig** - Templates HTML
- **Symfony Security** - Connexion et sécurité
- **Symfony Mailer** - Envoi d'emails

### Frontend  
- **Tailwind CSS** - Framework CSS
- **JavaScript** - Un peu de JavaScript
- **Font Awesome** - Icônes sympas

### Base de données
- **MySQL** - Base de données
- **Doctrine Migrations** - Gestion des changements DB

## 🚀 Installation

### Ce qu'il faut avoir
- PHP 8.2+
- Composer
- Node.js et npm
- MySQL
- Git

### Comment installer

1. **Récupérer le code**
   ```bash
   git clone https://github.com/votre-username/sante-rezo.git
   cd sante-rezo
   ```

2. **Installer les trucs PHP**
   ```bash
   composer install
   ```

3. **Installer les trucs JavaScript**
   ```bash
   npm install
   ```

4. **Config**
   ```bash
   cp .env .env.local
   ```
   Modifier `.env.local` avec vos paramètres de DB et email.

5. **Créer la base de données**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

6. **Ajouter des données de test**
   ```bash
   php bin/console doctrine:fixtures:load
   ```

7. **Compiler les CSS/JS**
   ```bash
   npm run build
   ```

8. **Lancer le serveur**
   ```bash
   symfony server:start
   ```

## ⚙️ Configuration

### Variables importantes dans .env.local

```env
# Base de données
DATABASE_URL="mysql://username:password@127.0.0.1:3306/sante_rezo"

# Email
MAILER_DSN=smtp

# Environnement
APP_ENV=dev
```

### Uploads
Les photos sont dans `public/uploads/profiles/`

## 📖 Utilisation

### Côté Patient
1. **S'inscrire** sur `/register` 
2. **Compléter son profil** 
3. **Chercher des médecins** sur la page d'accueil
4. **Réserver** en cliquant sur un créneau
5. **Gérer ses RDV** dans son espace

### Côté Médecin  
1. **S'inscrire** comme médecin
2. **Remplir son profil pro**
3. **Ajouter des créneaux** dispo
4. **Gérer son planning**
5. **Voir ses disponibilités**

### Admin
Interface admin sur `/admin` pour tout gérer.

## 📁 Structure du projet

```
sante-rezo/
├── src/
│   ├── Controller/      # Logique des pages
│   ├── Entity/          # Modèles de données  
│   ├── Form/            # Formulaires
│   └── Repository/      # Requêtes DB
├── templates/           # Pages HTML
│   ├── home/            # Accueil
│   ├── doctor/          # Interface médecin
│   ├── patient/         # Interface patient
│   └── security/        # Connexion
├── public/              # Fichiers publics
└── config/              # Configuration
```

## 👥 Rôles utilisateurs

- **ROLE_USER** - Base pour tous
- **ROLE_PATIENT** - Peut réserver des RDV
- **ROLE_DOCTOR** - Peut gérer son planning  
- **ROLE_ADMIN** - Accès à tout

## 🛣️ Routes principales

### Public
- `/` - Accueil
- `/login` - Connexion
- `/register` - Inscription

### Patient
- `/patient` - Profil
- `/patient/appointments/available` - RDV dispos
- `/patient/appointments/upcoming` - Mes RDV

### Médecin
- `/doctor` - Profil
- `/doctor/appointments/upcoming` - Mes RDV
- `/doctor/appointments/add` - Ajouter créneaux

**Fait avec Symfony et beaucoup de café ☕**
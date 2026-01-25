# Swapapp

Un projet Symfony pour une application de swap/echange d'articles.

## Prérequis

Avant d'installer le projet, assurez-vous d'avoir les outils suivants installés :

- **PHP 8.1 ou supérieur** : [Télécharger PHP](https://www.php.net/downloads)
- **Composer** : Gestionnaire de dépendances PHP. [Télécharger Composer](https://getcomposer.org/download/)
- **Node.js et npm** : Pour la compilation des assets. [Télécharger Node.js](https://nodejs.org/)
- **MySQL** : Base de données. Recommandé avec Laragon pour un environnement local facile.
- **Laragon** (optionnel mais recommandé) : Environnement de développement local incluant Apache, MySQL et PHP. [Télécharger Laragon](https://laragon.org/download/)

## Installation

### 1. Cloner le repository

```bash
git clone https://github.com/Aydikoussay/swapapp.git
cd swapapp
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer l'environnement

Copiez le fichier `.env` et ajustez les variables si nécessaire :

```bash
cp .env .env.local
```

Modifiez `.env.local` pour configurer la base de données :

```dotenv
DATABASE_URL="mysql://root:@127.0.0.1:3306/swapapps?serverVersion=8.0&charset=utf8mb4"
```

- Remplacez `root:` par votre utilisateur MySQL si différent.
- Assurez-vous que MySQL est démarré.

### 4. Créer la base de données

```bash
php bin/console doctrine:database:create
```

### 5. Créer les tables

```bash
php bin/console doctrine:schema:update --force
```

### 6. Installer les dépendances JavaScript

```bash
npm install
```

### 7. Compiler les assets

```bash
npm run dev
```

Pour le développement avec rechargement automatique :

```bash
npm run watch
```

### 8. Démarrer le serveur

#### Option 1 : Avec le serveur Symfony (recommandé pour le développement)

```bash
php bin/console server:start
```

Accédez à `http://127.0.0.1:8000`

#### Option 2 : Avec Laragon

- Ouvrez Laragon et démarrez Apache et MySQL.
- Placez le projet dans le dossier `www` de Laragon.
- Accédez à `http://localhost/swapapp/public/`

### 9. Créer un utilisateur administrateur

Pour créer un utilisateur :

```bash
php bin/console make:user
```

Suivez les prompts pour entrer l'email et le mot de passe.

Ou utilisez le formulaire d'inscription sur `/register`.

## Utilisation

- **Connexion** : Allez sur `/login` et utilisez les identifiants créés.
- **Dashboard** : Accessible après connexion pour les admins.
- **Articles** : Gérez les articles via `/article`.
- **Requêtes** : Gérez les échanges via `/request`.

## Structure du projet

- `src/Controller/` : Contrôleurs Symfony
- `src/Entity/` : Entités Doctrine
- `src/Form/` : Formulaires
- `templates/` : Templates Twig
- `assets/` : Assets JavaScript/CSS
- `migrations/` : Migrations Doctrine

## Commandes utiles

- `php bin/console cache:clear` : Vider le cache
- `php bin/console doctrine:migrations:migrate` : Exécuter les migrations
- `php bin/console debug:router` : Lister les routes
- `npm run build` : Compiler les assets pour la production

## Tests

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:update --force --env=test
php bin/phpunit
```

## Déploiement

Pour la production, configurez les variables d'environnement, compilez les assets avec `npm run build`, et utilisez un serveur web comme Apache ou Nginx.


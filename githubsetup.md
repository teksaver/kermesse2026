# Configuration du Déploiement GitHub Actions via SFTP

Ce guide explique comment configurer votre dépôt GitHub pour que le déploiement automatique via SFTP fonctionne correctement vers votre hébergement.

## 1. Récupérer vos identifiants d'hébergement
Vous aurez besoin des informations fournies par votre hébergeur (généralement visibles depuis l'espace client ou le panel d'administration web type cPanel) :
- **L'adresse du serveur** (Hôte) : par exemple `ftp.votre-domaine.com` ou une adresse IP.
- **Votre identifiant** (Nom d'utilisateur).
- **Votre mot de passe SFTP** pour vous connecter au serveur.

## 2. Ajouter vos secrets sur GitHub
Pour des raisons de sécurité évidentes, vous ne devez **jamais** inscrire vos identifiants en clair dans les fichiers (`deploy.yml`). Pour cela, nous utilisons les "Secrets GitHub" :

1. Allez sur la page principale de votre dépôt sur le site GitHub.
2. Cliquez sur l'onglet **Settings** ⚙️ (Paramètres).
3. Dans la barre latérale de gauche, descendez dans la section "Security" et ouvrez **Secrets and variables**, puis cliquez sur **Actions**.
4. Cliquez sur le bouton vert **New repository secret** pour ajouter vos différents secrets (le nom doit être rigoureusement exact) :

### Les 3 secrets pour le déploiement SFTP :
- **Nom**: `SFTP_SERVER`
  - *Valeur*: L'adresse host de votre serveur
- **Nom**: `SFTP_USERNAME`
  - *Valeur*: L'identifiant SFTP fourni par votre hébergeur
- **Nom**: `SFTP_PASSWORD` 
  - *Valeur*: Votre mot de passe de connexion SFTP. 

### Les 4 secrets pour la connexion à la Base de Données (MySQL) :
*Ces variables seront utilisées de manière invisible par le script de déploiement sur GitHub pour recréer proprement votre fichier de configuration* (`.env`).
- **Nom**: `DB_HOST`
  - *Valeur*: L'adresse de connexion à MySQL (souvent `localhost` ou `127.0.0.1` chez la plupart des hébergeurs)
- **Nom**: `DB_NAME`
  - *Valeur*: Le nom de votre base de données SQL
- **Nom**: `DB_USER`
  - *Valeur*: Votre identifiant MySQL
- **Nom**: `DB_PASS`
  - *Valeur*: Le mot de passe associé à la base de données 

### Les secrets applicatifs supplementaires :
- **Nom**: `APP_SECRET`
  - *Valeur*: Une chaine aleatoire longue pour Symfony
- **Nom**: `AUTH_JWT_SECRET`
  - *Valeur*: Une seconde chaine aleatoire longue dediee a la signature des JWT
- **Nom**: `MAILER_DSN`
  - *Valeur*: Le DSN de votre transport e-mail SMTP
- **Nom**: `MAILER_FROM`
  - *Valeur*: L'adresse expediteur utilisee pour les liens de connexion
- **Nom**: `SITE_URL`
  - *Valeur*: Votre nom de domaine public, sans `https://`
- **Nom**: `WEBHOOK_SECRET`
  - *Valeur*: Un secret partage pour declencher la migration distante en securite

## 3. Le dossier distant et le port
Le fichier `.github/workflows/deploy.yml` a été pré-configuré avec vos paramètres spécifiques. Vous n'avez donc, à priori, plus rien à y modifier. 

Notez que ces options spécifiques à votre hébergement y ont été explicitement intégrées :
- Le **port SFTP 115** (`port: 115`) car il diffère du port standard (22).
- Le **dossier cible `/httpdocs`** (`remote_path: '/httpdocs'`) qui correspond à la racine web de votre serveur.

## 4. Testez !
Une fois les configurations faites et le dernier `push` de `deploy.yml` réalisé vers votre branche `main` :
1. Allez dans l'onglet **Actions** de votre dépôt GitHub.
2. Vous verrez l'exécution de "Deploy via SFTP".
3. Cliquez dessus pour voir la progression : si tout est vert, les fichiers ont ete copies puis la route `/webhook/migrations` a execute `doctrine:migrations:migrate` sur l'hebergement.

## 5. Pourquoi les migrations passent par GitHub Actions ?
Ouvaton n'expose pas de GUI pour lancer des commandes Doctrine apres le transfert SFTP. Le workflow de deploiement contourne proprement cette limitation :

1. GitHub Actions prepare `.env.local` avec les secrets de production.
2. Les fichiers sont envoyes par SFTP dans `httpdocs`.
3. GitHub appelle ensuite le webhook Symfony protege par `WEBHOOK_SECRET`.
4. Ce webhook execute `doctrine:migrations:migrate --no-interaction --allow-no-migration` directement sur le serveur.

Ainsi, les migrations restent versionnees dans Git, rejouables, et ne dependent d'aucune intervention manuelle dans l'hebergeur.

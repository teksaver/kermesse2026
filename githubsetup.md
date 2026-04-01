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
4. Cliquez sur le bouton vert **New repository secret** et ajoutez ces 3 secrets (le nom doit être rigoureusement exact) :

### Les 3 secrets à créer :
- **Nom**: `SFTP_SERVER`
  - *Valeur*: L'adresse host de votre hébergeur
- **Nom**: `SFTP_USERNAME`
  - *Valeur*: L'identifiant SFTP fourni par votre hébergeur
- **Nom**: `SFTP_PASSWORD` 
  - *Valeur*: Votre mot de passe de connexion SFTP. 

## 3. Le dossier distant et le port
Le fichier `.github/workflows/deploy.yml` a été pré-configuré avec vos paramètres spécifiques. Vous n'avez donc, à priori, plus rien à y modifier. 

Notez que ces options spécifiques à votre hébergement y ont été explicitement intégrées :
- Le **port SFTP 115** (`port: 115`) car il diffère du port standard (22).
- Le **dossier cible `/httpdocs`** (`remote_path: '/httpdocs'`) qui correspond à la racine web de votre serveur.

## 4. Testez !
Une fois les configurations faites et le dernier `push` de `deploy.yml` réalisé vers votre branche `main` :
1. Allez dans l'onglet **Actions** de votre dépôt GitHub.
2. Vous verrez l'exécution de "Deploy via SFTP".
3. Cliquez dessus pour voir la progression : si tout est vert, les fichiers ont été copiés de succès vers votre hébergement ! Allez visiter l'adresse de votre site web pour découvrir l'interface `index.php`.

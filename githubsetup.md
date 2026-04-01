# Configuration du Déploiement GitHub Actions via SFTP

Ce guide explique comment configurer votre dépôt GitHub pour que le déploiement automatique via SFTP fonctionne correctement vers votre hébergement.

## 1. Récupérer vos identifiants d'hébergement
Vous aurez besoin des informations fournies par votre hébergeur (généralement visibles depuis l'espace client ou le panel d'administration web type cPanel) :
- **L'adresse du serveur** (Hôte) : par exemple `ftp.votre-domaine.com` ou une adresse IP.
- **Votre identifiant** (Nom d'utilisateur).
- **Votre clé privée SSH** (recommandé et plus sécurisé) OU **votre mot de passe**. Dans le cas d'une clé, votre hébergeur doit autoriser les accès SSH/SFTP par clés.

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
- **Nom**: `SFTP_PRIVATE_KEY` 
  - *Valeur*: Le contenu texte complet de votre clé privée (qui commence de manière classique par `-----BEGIN OPENSSH PRIVATE KEY-----` ou `-----BEGIN RSA PRIVATE KEY-----`). 

> [!CAUTION]  
> **Si vous n'avez pas de clé privée et utilisez uniquement un mot de passe :**
> - Au lieu de créer le secret avec la clé, créez un secret nommé **`SFTP_PASSWORD`** (avec votre mot de passe).
> - Ensuite, modifiez le fichier de votre code situé dans `.github/workflows/deploy.yml` pour remplacer la propriété `ssh_private_key: ${{ secrets.SFTP_PRIVATE_KEY }}` par `password: ${{ secrets.SFTP_PASSWORD }}`.

## 3. Ajuster le dossier distant et le port
Ouvrez le fichier local `.github/workflows/deploy.yml` ou modifiez-le directement sur GitHub.

Notez que le **port SFTP 115** a été explicitement ajouté à la configuration (`port: 115`) car il diffère du port standard (22).

Cherchez la ligne ci-dessous dans la configuration :
```yaml
remote_path: '/chemin/vers/votre/public_html'
```
Il faut la remplacer par le chemin exact du répertoire racine web sur votre serveur distant.
Chez de nombreux hébergeurs mutualisés classiques, ce dossier s'appelle généralement :
- `/public_html/`
- `/www/`
- `/htdocs/`
- Ou parfois juste `/` si votre compte FTP est déjà limité au dossier web.

## 4. Testez !
Une fois les configurations faites et le dernier `push` de `deploy.yml` réalisé vers votre branche `main` :
1. Allez dans l'onglet **Actions** de votre dépôt GitHub.
2. Vous verrez l'exécution de "Deploy via SFTP".
3. Cliquez dessus pour voir la progression : si tout est vert, les fichiers ont été copiés de succès vers votre hébergement ! Allez visiter l'adresse de votre site web pour découvrir l'interface `index.php`.

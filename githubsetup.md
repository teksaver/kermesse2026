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
- **Nom**: `MAILER_FROM`
  - *Valeur*: L'adresse expediteur visible par les destinataires, par exemple `no-reply@votredomaine.fr`
- **Nom**: `MAILER_FROM_NAME`
  - *Valeur*: Le nom visible de l'expediteur, par exemple `Kermesse 2026`
- **Nom**: `MAILER_ENVELOPE_SENDER`
  - *Valeur*: L'adresse de retour technique utilisee par SMTP pour les rebonds, par exemple `bounces@votredomaine.fr`
- **Nom**: `SITE_URL`
  - *Valeur*: Votre nom de domaine public, sans `https://`
- **Nom**: `WEBHOOK_SECRET`
  - *Valeur*: Un secret partage pour declencher la migration distante en securite

### Difference entre `MAILER_FROM` et `MAILER_ENVELOPE_SENDER`
- `MAILER_FROM` correspond a l'adresse visible dans le champ "De:" du message. C'est celle que l'utilisateur voit dans sa boite mail.
- `MAILER_ENVELOPE_SENDER` correspond a l'adresse technique utilisee pendant l'envoi SMTP. C'est elle qui recoit les rebonds et qui sert souvent de reference pour les controles anti-spam.
- Dans la plupart des cas, il est preferable que ces deux adresses soient sur le meme domaine.
- Elles peuvent etre identiques si vous voulez rester simple.
- Une configuration propre et classique est par exemple :
  - `MAILER_FROM=no-reply@votredomaine.fr`
  - `MAILER_ENVELOPE_SENDER=bounces@votredomaine.fr`

### Les secrets SMTP Ouvaton :
- **Nom**: `SMTP_HOST`
  - *Valeur*: L'hote SMTP fourni par Ouvaton
- **Nom**: `SMTP_USERNAME`
  - *Valeur*: L'identifiant SMTP fourni par Ouvaton
- **Nom**: `SMTP_PASSWORD`
  - *Valeur*: Le mot de passe SMTP fourni par Ouvaton

Le workflow utilise directement le port `587` avec `STARTTLS` via `encryption=tls`. Cela evite d'avoir a gerer plusieurs variantes de configuration.

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

## 6. Delivrabilite e-mail et anti-spam
Le workflow peut injecter correctement les identifiants SMTP, mais la delivrabilite depend aussi de la configuration DNS de votre domaine. Pour limiter fortement le risque de spam :

1. Utilisez une adresse `MAILER_FROM` sur votre propre domaine, pas une adresse Gmail/Outlook.
2. Faites pointer `MAILER_ENVELOPE_SENDER` sur le meme domaine que `MAILER_FROM`.
3. Activez le SPF du domaine pour autoriser les serveurs SMTP Ouvaton a envoyer vos e-mails.
4. Activez le DKIM fourni par Ouvaton dans la zone DNS du domaine.
5. Ajoutez une politique DMARC simple, par exemple en mode observation au debut.
6. Assurez-vous que le domaine du site (`SITE_URL`) et le domaine expediteur restent coherents.

Le DKIM ne se configure pas dans `deploy.yml` ni dans Symfony : il doit etre publie dans le DNS du domaine avec les valeurs remises par Ouvaton. Sans SPF/DKIM/DMARC alignes, meme un SMTP valide risque d'etre classe en spam.

Le choix retenu ici est le port `587` avec `STARTTLS`, qui est en general le plus simple et le plus interoperable pour un envoi applicatif moderne.

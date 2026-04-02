# Configuration du Déploiement GitHub Actions via SFTP

Ce guide explique comment configurer votre dépôt GitHub pour que le déploiement automatique via SFTP fonctionne correctement vers votre hébergement.

## 1. Récupérer vos identifiants d'hébergement
Vous aurez besoin des informations fournies par votre hébergeur (généralement visibles depuis l'espace client ou le panel d'administration web type cPanel) :
- **L'adresse du serveur** (Hôte) : par exemple `ftp.votre-domaine.com` ou une adresse IP.
- **Votre identifiant** (Nom d'utilisateur).
- **Votre mot de passe SFTP** pour vous connecter au serveur.

## 2. Ajouter les secrets et variables sur GitHub
Le workflow utilise maintenant deux types de configuration GitHub :

- les **Secrets GitHub Actions** pour les valeurs sensibles
- les **Repository Variables** pour les valeurs non sensibles

Pour les configurer :

1. Allez sur la page principale de votre dépôt sur le site GitHub.
2. Cliquez sur l'onglet **Settings** ⚙️ (Paramètres).
3. Dans la barre latérale de gauche, descendez dans la section "Security" et ouvrez **Secrets and variables**, puis cliquez sur **Actions**.
4. Utilisez **Secrets** pour les valeurs sensibles et **Variables** pour les autres.

### Les secrets GitHub Actions :

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
- **Nom**: `WEBHOOK_SECRET`
  - *Valeur*: Un secret partage pour declencher la migration distante en securite

### Les repository variables GitHub :
- **Nom**: `SITE_URL`
  - *Valeur attendue*: uniquement le nom de domaine public, sans `http://` ni `https://`
  - *Exemple*: `kermesse2026.fr`
- **Nom**: `MAILER_FROM`
  - *Valeur attendue*: l'adresse expediteur visible par les destinataires
  - *Exemple*: `no-reply@kermesse2026.fr`
- **Nom**: `MAILER_FROM_NAME`
  - *Valeur attendue*: le nom visible de l'expediteur
  - *Exemple*: `Kermesse 2026`
- **Nom**: `MAILER_ENVELOPE_SENDER`
  - *Valeur attendue*: l'adresse technique de retour SMTP pour les rebonds
  - *Exemple*: `bounces@kermesse2026.fr`

### Difference entre `MAILER_FROM` et `MAILER_ENVELOPE_SENDER`
- `MAILER_FROM` correspond a l'adresse visible dans le champ "De:" du message. C'est celle que l'utilisateur voit dans sa boite mail.
- `MAILER_ENVELOPE_SENDER` correspond a l'adresse technique utilisee pendant l'envoi SMTP. C'est elle qui recoit les rebonds et qui sert souvent de reference pour les controles anti-spam.
- Dans la plupart des cas, il est preferable que ces deux adresses soient sur le meme domaine.
- Elles peuvent etre identiques si vous voulez rester simple.
- Une configuration propre et classique est par exemple :
  - `MAILER_FROM=no-reply@votredomaine.fr`
  - `MAILER_ENVELOPE_SENDER=bounces@votredomaine.fr`

### Valeurs attendues en pratique
- `APP_SECRET`
  - chaine aleatoire longue pour Symfony
  - exemple : `4mYx7pQ2nL9sV3kT8rH1cD6fB0wZ5jUa`
- `AUTH_JWT_SECRET`
  - chaine aleatoire longue distincte de `APP_SECRET`
  - exemple : `8dR4vN1qT7mK2xP9sC5hL3zF6bW0yJae`
- `WEBHOOK_SECRET`
  - chaine aleatoire longue choisie par vous
  - exemple : `7x3VnP2qL9sK4mT8wZ1rH6cY5uB0eF3j`
- `SITE_URL`
  - seulement le domaine
  - exemple correct : `kermesse2026.fr`
  - exemple incorrect : `https://kermesse2026.fr`

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
3. Cliquez dessus pour voir la progression : le workflow commence par envoyer un petit bootstrap applicatif, recharge le cache Symfony via `/index.php/webhook/migrations`, puis envoie `deploy-package.zip` et appelle `/index.php/webhook/deploy` pour decompresser l'archive et executer les migrations.

## 5. Pourquoi les migrations passent par GitHub Actions ?
Ouvaton n'expose pas de GUI pour lancer des commandes Doctrine apres le transfert SFTP. Le workflow de deploiement contourne proprement cette limitation :

1. GitHub Actions prepare `.env.local` avec les secrets de production.
2. GitHub construit un bundle de deploiement et un bootstrap applicatif leger.
3. Le bootstrap est envoye une premiere fois par SFTP pour mettre a jour les routes et les webhooks.
4. GitHub appelle `/index.php/webhook/migrations`, qui vide et rechauffe le cache Symfony puis lance les migrations.
5. GitHub construit ensuite l'archive `deploy-package.zip` et l'envoie par SFTP.
6. GitHub appelle `/index.php/webhook/deploy`.
7. Le webhook decompresse l'archive directement sur le serveur puis execute les migrations.

Ainsi, les migrations restent versionnees dans Git, rejouables, et ne dependent d'aucune intervention manuelle dans l'hebergeur.

## 5.bis Premiere mise en place du deploiement zip
Le workflow gere maintenant automatiquement la premiere mise en place :

- il pousse d'abord un bootstrap leger pour rendre les nouveaux webhooks disponibles
- il recharge ensuite le cache Symfony
- puis il bascule sur le mode zip

Le passage par `index.php` est volontaire : il permet de ne pas dependre de la reecriture d'URL du serveur web, ce qui rend le deploiement plus fiable sur un hebergement mutualise comme Ouvaton.

Vous n'avez donc pas besoin d'un deploiement manuel intermediaire.

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

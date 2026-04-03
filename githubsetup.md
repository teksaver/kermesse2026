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
- **Nom**: `LOG_VIEWER_TOKEN`
  - *Valeur*: Un token secret dedie a l'affichage des logs runtime dans le navigateur

### Regles de format recommandees pour chaque secret
- `APP_SECRET`
  - longueur minimale recommandee: `32` caracteres (mieux: `48` a `64`)
  - contenu: chaine aleatoire forte
  - caracteres conseilles: lettres maj/min, chiffres
  - caracteres speciaux: autorises, mais pas necessaires
  - contrainte: ne pas reutiliser cette valeur pour un autre secret

- `AUTH_JWT_SECRET`
  - longueur minimale recommandee: `32` caracteres (mieux: `64`)
  - contenu: chaine aleatoire forte, differente de `APP_SECRET`
  - caracteres conseilles: lettres maj/min, chiffres
  - caracteres speciaux: autorises, mais pas necessaires
  - contrainte: valeur stable tant que vous ne souhaitez pas invalider toutes les sessions JWT

- `WEBHOOK_SECRET`
  - longueur minimale recommandee: `32` caracteres (mieux: `48` a `64`)
  - contenu: chaine aleatoire forte
  - caracteres conseilles: lettres maj/min, chiffres
  - caracteres speciaux: possibles, mais evitez espaces, guillemets et `&` pour simplifier l'usage dans les requetes HTTP
  - contrainte: garder la meme valeur cote serveur et GitHub Secrets

- `LOG_VIEWER_TOKEN`
  - longueur minimale recommandee: `32` caracteres
  - contenu: chaine aleatoire forte
  - caracteres conseilles: lettres maj/min, chiffres
  - caracteres speciaux: possibles, mais evitez ceux qui doivent etre echappes dans une URL (espace, `?`, `&`, `#`, `%`)
  - contrainte: ce token transite dans l'URL du visualiseur, donc privilegiez un format URL-friendly

### Format simple conseille pour tous les secrets
Pour limiter les erreurs de quoting/URL, utilisez ce format unique partout :
- uniquement `[A-Za-z0-9]`
- longueur `48` ou `64`
- exemple de style: `7x3VnP2qL9sK4mT8wZ1rH6cY5uB0eF3jQ4nM8tR2yK6pD9s`

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
- `LOG_VIEWER_TOKEN`
  - chaine aleatoire longue choisie par vous
  - exemple : `n4R6vP8qT1mK3xS7bC0hL5zD9fW2yJae`
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
- Le **dossier de déploiement SFTP `/`** (`remote_path: '/'`) qui correspond a la racine du projet.
- Le **dossier web public `/httpdocs`** reste la racine servie par l'hebergement.

Le point important est le suivant :
- le projet Symfony complet est deploie a la racine SFTP `/`
- le fichier public reste `httpdocs/index.php`
- ce fichier charge le projet depuis son dossier parent

Il ne faut donc pas deployer tout le projet directement dans `/httpdocs`, sinon `index.php` ne retrouvera plus correctement `vendor/`, `config/` et le reste du projet.

## 4. Testez !
Une fois les configurations faites et le dernier `push` de `deploy.yml` réalisé vers votre branche `main` :
1. Allez dans l'onglet **Actions** de votre dépôt GitHub.
2. Vous verrez l'exécution de "Deploy via SFTP".
3. Cliquez dessus pour voir la progression : le workflow commence par envoyer un petit bootstrap applicatif, verifie l'environnement via `/deploy_probe.php`, puis envoie `deploy-package.zip` et appelle `/deploy_release.php` pour decompresser l'archive et executer les commandes de maintenance.

Si le deploiement echoue, le workflow appelle automatiquement `/deploy_logs.php` et affiche les logs serveur recents dans la sortie GitHub Actions.

## 5. Pourquoi les migrations passent par GitHub Actions ?
Ouvaton n'expose pas de GUI pour lancer des commandes Doctrine apres le transfert SFTP. Le workflow de deploiement contourne proprement cette limitation :

1. GitHub Actions prepare `.env.local` avec les secrets de production.
2. GitHub construit un bundle de deploiement et un bootstrap applicatif leger.
3. Le bootstrap est envoye une premiere fois par SFTP pour mettre a jour les routes et les webhooks.
4. GitHub verifie l'etat du serveur avec `/deploy_probe.php`.
5. GitHub construit ensuite l'archive `deploy-package.zip` et l'envoie par SFTP.
6. GitHub appelle `/deploy_release.php`.
7. Ce script decompresse l'archive directement sur le serveur, puis lance `cache:clear`, `asset-map:compile`, `cache:warmup` et `doctrine:migrations:migrate`.

Ainsi, les migrations restent versionnees dans Git, rejouables, et ne dependent d'aucune intervention manuelle dans l'hebergeur.

## 5.bis Premiere mise en place du deploiement zip
Le workflow gere maintenant automatiquement la premiere mise en place :

- il pousse d'abord un bootstrap leger pour rendre les scripts de deploiement disponibles
- il verifie ensuite l'environnement reel du serveur
- puis il bascule sur le mode zip

Le passage par des scripts PHP publics dedies est volontaire : il permet de ne pas dependre du boot complet de Symfony avant que `vendor/` ne soit en place, ce qui rend le premier deploiement bien plus fiable sur un hebergement mutualise comme Ouvaton.

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

## 6.bis Envoi e-mail sans worker (important sur hebergement mutualise)
Le projet est configure pour envoyer les e-mails en mode synchrone (`sync`) pour `SendEmailMessage`.
Consequence :
- pas de dependance a une file Messenger en base
- pas besoin de table `messenger_messages`
- pas besoin de worker Messenger en tache de fond

Cette configuration evite l'erreur :
`Table ... messenger_messages doesn't exist`

Si vous voulez revenir plus tard a un mode asynchrone :
1. remettre le routage mail vers `async`
2. configurer un transport de queue (Doctrine/Redis/AMQP)
3. creer les tables necessaires
4. lancer un worker en continu

## 7. Remplacer un secret fuité (Rotation des clés)
Si vous soupçonnez qu'un secret a fuité (comme le `WEBHOOK_SECRET`, le `DB_PASS`, ou l'`APP_SECRET`), inutile d'intervenir manuellement sur votre hébergement. Le processus de déploiement gère automatiquement la rotation des clés :

1. Allez sur GitHub, dans **Settings > Secrets and variables > Actions**.
2. Cliquez sur l'icône de modification ✏️ à côté du secret compromis et entrez la nouvelle valeur.
3. Allez dans l'onglet **Actions** et relancez le workflow "Deploy via SFTP" (bouton **Run workflow**), ou faites simplement un nouveau `git push`.

Le workflow générera le nouveau fichier `.env.local` avec les nouveaux secrets et l'enverra sur le serveur OUVATON par SFTP lors de la phase de "Bootstrap". Ainsi, votre serveur utilisera instantanément le nouveau secret pour les prochaines étapes de déploiement et pour l'application elle-même. C'est le moyen le plus sûr de corriger une fuite.

## 8. Acceder aux logs et diagnostiquer les erreurs runtime
### Où lire les logs
Vous avez deux voies simples :

1. Dans GitHub Actions :
- en cas d'echec, l'etape `Diagnostic logs serveur (si échec)` affiche le resultat de `/deploy_logs.php`
- vous y verrez les fins de fichiers de logs Symfony et PHP

2. En SFTP :
- logs Symfony production : `var/log/prod.log`
- logs Symfony development : `var/log/dev.log`
- logs PHP potentiels selon l'hebergement : `error_log` et `httpdocs/error_log`

### Visualiseur de logs runtime (sans CLI)
Une page dediee est disponible pour consulter les logs directement depuis le navigateur :
- URL : `https://<SITE_URL>/index.php/admin/logs?token=<LOG_VIEWER_TOKEN>`

Fonctionnement :
- la page affiche les dernieres lignes de `var/log/prod.log`, `var/log/dev.log`, `error_log`, `httpdocs/error_log`
- le token est obligatoire (secret `LOG_VIEWER_TOKEN`)
- vous pouvez ajuster le nombre de lignes avec `&lines=200` (entre 20 et 500)

Exemple :
`https://kermesse.ouvaton.org/index.php/admin/logs?token=VOTRE_TOKEN`

Securite :
- ne partagez pas cette URL publiquement
- si besoin, changez `LOG_VIEWER_TOKEN` dans GitHub Secrets puis redeployez

### Endpoints de diagnostic utiles
- `https://<SITE_URL>/deploy_probe.php`
- `https://<SITE_URL>/deploy_logs.php`
- `https://<SITE_URL>/deploy_release.php`

Ces endpoints exigent `WEBHOOK_SECRET` (en POST ou query string) et servent uniquement au deploiement.

### Procedure recommandee en cas d'erreur 500
Quand vous voyez `Oops! An Error Occurred` en production :
1. Ouvrez `https://<SITE_URL>/index.php/admin/logs?token=<LOG_VIEWER_TOKEN>`
2. Augmentez le volume si besoin avec `&lines=300`
3. Analysez d'abord `var/log/prod.log`, puis `error_log` et `httpdocs/error_log`
4. Si la page de logs ne repond pas, utilisez `https://<SITE_URL>/deploy_logs.php?secret=<WEBHOOK_SECRET>`

## 9. Politique des fichiers .env (securite)
### Quels fichiers peuvent etre commit ?
- `.env` : oui, pour des valeurs par defaut non sensibles et des placeholders
- `.env.dev` : oui eventuellement, uniquement pour des valeurs de dev non sensibles
- `.env.test` : oui, pour les valeurs de test non sensibles

### Quels fichiers ne doivent jamais etre commit ?
- `.env.local`
- `.env.dev.local`
- `.env.test.local`
- tout fichier contenant de vrais mots de passe, tokens, DSN de production, secrets webhook

Le `.gitignore` du projet ignore deja correctement les variantes `*.local`.

### Ou stocker vos secrets locaux (non commits) ?
- usage general local : `.env.local`
- specificite dev locale : `.env.dev.local`
- specificite tests locaux : `.env.test.local`

Exemple typique en local :
- creer `.env.local`
- y mettre vos valeurs reelles (`DB_PASS`, `WEBHOOK_SECRET`, `AUTH_JWT_SECRET`, `LOG_VIEWER_TOKEN`, etc.)
- ne jamais pousser ce fichier

### Alerte importante
Si un secret a deja ete commit dans un fichier suivi (exemple: ancien `.env.dev`), il faut le considerer comme divulgue :
1. Regenerer ce secret
2. Mettre la nouvelle valeur dans GitHub Secrets/Variables
3. Redeployer

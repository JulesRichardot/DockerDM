# Rapport de Réalisation - Exercice 2 : Virtualisation avec Docker

## Objectif

L'exercice consistait à mettre en place une architecture Docker avec deux réseaux isolés reliés par un routeur. Le réseau `front` (170.20.0.0/16) héberge le serveur web et Adminer, tandis que le réseau `back` (170.21.0.0/16) contient uniquement la base de données MariaDB. La communication entre les réseaux est assurée par un conteneur routeur personnalisé qui active le forwarding IP.

## Architecture

L'architecture comprend quatre services principaux. Le serveur web (`web`) écoute sur le port 80 et sert une application PHP. Adminer (`adminer`) est accessible sur le port 8080 pour la gestion de la base de données. Le routeur (`routeur`) est connecté aux deux réseaux avec des IP fixes : 170.20.0.2 sur le réseau front et 170.21.0.2 sur le réseau back. Enfin, la base de données MariaDB (`db`) est configurée avec l'IP fixe 170.21.0.10 et n'expose aucun port vers l'extérieur, garantissant qu'elle n'est accessible que depuis les autres conteneurs via le routage.

## Configuration du routeur

Le routeur est basé sur Alpine Linux et utilise iproute2 pour gérer le routage. Le script d'entrypoint est minimal et ne fait qu'activer le forwarding IP :

```bash
#!/bin/sh
sysctl -w net.ipv4.ip_forward=1 || echo 1 > /proc/sys/net/ipv4/ip_forward || true
tail -f /dev/null
```

Le conteneur doit être en mode `privileged: true` pour pouvoir modifier les paramètres système nécessaires au forwarding IP. La capacité `NET_ADMIN` est également requise.

## Configuration des services web

Les conteneurs `web` et `adminer` doivent configurer manuellement une route vers le réseau back car Docker ne crée pas automatiquement cette route. Un script d'entrypoint ajoute cette route au démarrage :

```bash
#!/bin/sh
ip route add 170.21.0.0/16 via 170.20.0.2 dev eth0 2>/dev/null || true
exec httpd -D FOREGROUND
```

Cette route indique que tout le trafic destiné au réseau 170.21.0.0/16 doit passer par le routeur situé à l'adresse 170.20.0.2. Les conteneurs nécessitent la capacité `NET_ADMIN` pour pouvoir modifier leur table de routage.

## Résolution DNS

Un problème important rencontré est que les services sur le réseau `front` ne peuvent pas résoudre les noms DNS des services sur le réseau `back` car Docker isole les réseaux au niveau DNS. Pour permettre au serveur web de se connecter à la base de données en utilisant le nom `db`, la configuration `extra_hosts` est utilisée dans docker-compose.yml pour mapper le nom `db` vers l'IP fixe 170.21.0.10.

## Configuration de MariaDB

La configuration de MariaDB a été l'un des aspects les plus complexes de l'exercice. Un Dockerfile personnalisé a été créé pour gérer l'initialisation de la base de données. Le script `entrypoint.sh` vérifie si la base de données est déjà initialisée en examinant la présence des tables système. Si ce n'est pas le cas, il :

1. Initialise la base de données avec `mysql_install_db`
2. Démarre temporairement MySQL en mode `--skip-networking` (socket Unix uniquement)
3. Attend que MySQL soit prêt en vérifiant le socket et en utilisant `mysqladmin ping`
4. Exécute le script d'initialisation SQL (`init.sql`) pour créer la base de données, l'utilisateur et définir les mots de passe
5. Arrête proprement le processus temporaire
6. Démarre le serveur MySQL final qui écoute sur le réseau

Le fichier de configuration `server.cnf` définit les paramètres essentiels pour que MariaDB écoute sur le réseau :
- `port=3306` : port standard MySQL
- `bind-address=0.0.0.0` : écoute sur toutes les interfaces
- `skip-networking=0` : active explicitement l'écoute réseau (critique pour résoudre les problèmes de connexion)

## Problèmes rencontrés et solutions

### 1. Activation du forwarding IP

**Problème :** Le conteneur routeur ne pouvait pas activer le forwarding IP, générant l'erreur "Read-only file system" lors de l'exécution de `sysctl -w net.ipv4.ip_forward=1`.

**Solution :** Ajout de `privileged: true` au service `routeur` dans `docker-compose.yml`. Cette option donne au conteneur les privilèges nécessaires pour modifier les paramètres système du noyau.

### 2. Configuration des routes statiques

**Problème :** Les conteneurs `web` et `adminer` ne pouvaient pas ajouter de routes vers le réseau `back`, générant l'erreur "Operation not permitted".

**Solution :** Ajout de la capacité `NET_ADMIN` aux services `web` et `adminer`, et création de scripts d'entrypoint qui ajoutent automatiquement la route `170.21.0.0/16 via 170.20.0.2` au démarrage.

### 3. Conflits d'adresses IP

**Problème :** L'utilisation de l'adresse `.1` pour le routeur causait des conflits car cette adresse est réservée aux gateways Docker par défaut.

**Solution :** Utilisation de l'adresse `.2` pour le routeur sur les deux réseaux (170.20.0.2 et 170.21.0.2), évitant ainsi les conflits avec les gateways automatiques.

### 4. Résolution DNS inter-réseaux

**Problème :** Les services sur le réseau `front` ne pouvaient pas résoudre le nom `db` vers son adresse IP car Docker isole les réseaux au niveau DNS.

**Solution :** Utilisation de `extra_hosts` dans `docker-compose.yml` pour mapper explicitement le nom `db` vers l'adresse IP fixe 170.21.0.10.

### 5. Permissions de la base de données MariaDB

**Problème :** Lors du premier démarrage, MariaDB générait des erreurs de permissions sur `/var/lib/mysql` et `/run/mysqld`, empêchant l'écriture des fichiers de données.

**Solution :** Ajout de commandes `chown` dans le Dockerfile et dans l'entrypoint pour s'assurer que l'utilisateur `mysql` possède les répertoires nécessaires, même après le montage des volumes Docker.

### 6. Initialisation de MariaDB et verrouillage des fichiers

**Problème :** Le processus d'initialisation nécessitait de démarrer temporairement MySQL pour exécuter le script SQL, puis de l'arrêter avant de démarrer le serveur final. Les processus MySQL temporaires ne se terminaient pas complètement, causant des erreurs de verrouillage de fichiers ("Can't lock aria control file", "Unable to lock ./ibdata1") lors du démarrage du serveur final.

**Solution :** 
- Implémentation d'une logique d'arrêt robuste dans `entrypoint.sh` :
  - Tentative d'arrêt propre avec `mysqladmin shutdown`
  - Boucle d'attente pour vérifier la fin des processus avec `pgrep`
  - Arrêt forcé avec `pkill -9` si nécessaire
  - Nettoyage des fichiers de verrouillage (socket, fichiers PID)
- Utilisation de `--skip-networking` pour le processus temporaire afin d'éviter les conflits de port

### 7. MariaDB n'écoute pas sur le port réseau

**Problème :** Même avec `port=3306` et `bind-address=0.0.0.0` dans la configuration, MariaDB affichait `port: 0` dans les logs et n'acceptait pas les connexions TCP, seulement les connexions via socket Unix.

**Solution :** Ajout explicite de `skip-networking=0` dans `server.cnf`. Cette option était nécessaire car MariaDB peut avoir des comportements par défaut qui désactivent l'écoute réseau. En l'explicitant à `0`, on force l'activation de l'écoute réseau TCP.

### 8. Simplification de la configuration

**Problème :** La configuration initiale contenait des options redondantes et des tentatives multiples de connexion/arrêt qui alourdissaient le code.

**Solution :** 
- **server.cnf** : Suppression des options redondantes (`user`, `datadir`, `socket` qui sont des valeurs par défaut), conservation uniquement des options essentielles pour l'écoute réseau
- **entrypoint.sh** : Simplification de la logique d'arrêt (suppression des tentatives multiples redondantes, réduction de la boucle d'attente de 15 à 10 itérations, suppression de la double tentative de connexion MySQL)

### 9. Configuration du port d'Adminer

**Problème :** Adminer n'était plus accessible. Apache dans le conteneur Adminer écoutait par défaut sur le port 80, alors que le mapping de ports dans `docker-compose.yml` était configuré pour `8080:8080`.

**Solution :** Modification du mapping de ports dans `docker-compose.yml` de `"8080:8080"` à `"8080:80"`. Cela permet à Apache d'écouter sur son port par défaut (80) à l'intérieur du conteneur, tout en étant accessible depuis l'hôte sur le port 8080. Le `EXPOSE 8080` dans le Dockerfile est uniquement informatif et ne force pas Apache à écouter sur ce port.

### 10. Problèmes d'accès à Adminer via l'URL racine

**Problème :** Lors de l'accès à `http://localhost:8080`, la page par défaut d'Apache ("It works!") était affichée au lieu de l'interface d'Adminer.

**Causes identifiées :**
- Un fichier `index.html` par défaut était présent dans le répertoire `/var/www/localhost/htdocs/` et était servi en priorité par Apache (configuration `DirectoryIndex index.html`).
- Le téléchargement initial d'Adminer via `curl` sans l'option `-L` récupérait une page de redirection HTML au lieu du fichier PHP complet d'Adminer.

**Solutions appliquées :**
1. **Correction du téléchargement d'Adminer :**
   - Modification du `Dockerfile` d'Adminer pour utiliser `curl -L` afin de suivre les redirections et télécharger le fichier `adminer.php` complet.
   - Le fichier est maintenant téléchargé directement sous le nom `adminer.php` dans `/var/www/localhost/htdocs/`.

2. **Suppression du fichier `index.html` par défaut :**
   - Ajout de `RUN rm -f /var/www/localhost/htdocs/index.html` dans le `Dockerfile` pour supprimer le fichier par défaut d'Apache.

3. **Solution finale :**
   - Après plusieurs tentatives de configuration de `DirectoryIndex` via `sed` ou des fichiers `.htaccess` (non préférés par l'utilisateur), la solution finale adoptée est de ne pas modifier la configuration d'Apache pour l'index par défaut.
   - Adminer est désormais accessible directement via `http://localhost:8080/adminer.php`. La page `http://localhost:8080` affiche toujours "It works!", ce qui est acceptable pour l'utilisateur.

**Identifiants de connexion Adminer :**

Pour se connecter à la base de données `mydb` via Adminer, les identifiants configurés dans `MariaDB/init.sql` sont les suivants :

- **Système** : MySQL
- **Serveur** : `db` (nom du service Docker, résolu en `170.21.0.10` grâce à `extra_hosts`)
- **Utilisateur** : `user`
- **Mot de passe** : `passwd`
- **Base de données** : `mydb`

Pour l'utilisateur `root` :
- **Utilisateur** : `root`
- **Mot de passe** : `rootpasswd`

## Validation de l'application Web

L'image ci-dessous illustre le bon fonctionnement de l'application de messagerie hébergée par le service `web`. Elle confirme la connexion réussie à la base de données MariaDB et la capacité à afficher et ajouter des messages.

![Interface de l'application de messagerie](image.png)

**Description détaillée de l'image :**

L'image présente une interface web simple intitulée "Messagerie", affichant le statut de la connexion à la base de données et une liste de messages récents, ainsi qu'un formulaire pour ajouter de nouveaux messages.

**Description générale :**
L'interface est épurée, avec un fond blanc et du texte noir. Le titre principal "Messagerie" est centré en haut de la page.

**Détails du contenu :**

1. **Statut de la connexion :**
   - Juste en dessous du titre, on peut lire "Connexion PDO OK à mydb via db:3306", indiquant une connexion réussie à la base de données `mydb` via le service `db` sur le port 3306.
   - En vert, un message de succès "Message ajouté avec succès!" est affiché, suggérant qu'une opération d'ajout de message vient d'être effectuée avec succès.

2. **Messages récents :**
   - Un titre "Messages récents:" est affiché.
   - En dessous, un cadre blanc contient un message unique :
     - "Hello World!" (le contenu du message)
     - "2025-11-05 22:45:01" (la date et l'heure d'envoi du message)

3. **Formulaire d'ajout de message :**
   - Un titre "Ajouter un message" est présent.
   - Un champ de texte (`textarea`) est visible avec le placeholder "Tapez votre message ici...".
   - Un bouton "Envoyer" est situé en dessous du champ de texte.

4. **Message d'accueil :**
   - En bas de la page, un message d'accueil est affiché : "Bienvenue sur la messagerie. Ajoutez un message et retrouvez-le ci-dessus."

**Pertinence pour le rapport :**
Cette image démontre le bon fonctionnement de l'application web (`web_container`), sa capacité à se connecter à la base de données MariaDB (`mariadb_container`) via le réseau routé, et la persistance des données (affichage du message "Hello World!"). Le message de succès "Message ajouté avec succès!" confirme qu'une interaction complète avec la base de données (insertion) a été réalisée avec succès.

## Validation de l'interface Adminer

L'image ci-dessous montre l'interface d'Adminer après une connexion réussie à la base de données `mydb`. Elle confirme que la base de données est accessible via le routage inter-réseaux et que la table `messages` a été créée et est visible.

![Interface Adminer connectée à mydb](image.png)

**Description détaillée de l'image :**

L'image affiche l'interface de gestion de base de données Adminer, connectée à une base de données MariaDB nommée "mydb". L'interface est dominée par des tons de bleu foncé avec du texte blanc et bleu clair.

**Barre supérieure :**
- Un menu déroulant "Langue: Français" indique que l'interface est en français.
- "Adminer 5.4.1" est affiché, indiquant la version de l'outil.
- Le titre principal "MariaDB » db » Base de données: mydb" confirme le type de base de données (MariaDB) et la base sélectionnée (mydb).
- Une barre de navigation contient plusieurs onglets : "Modifier la base de données", "Schéma de la base de données", "Privilèges", "Routines" et "Évènements". L'onglet "Base de données: mydb" est actif.

**Sidebar gauche :**
- Une section "Adminer 5.4.1" en haut.
- Un menu déroulant affichant "mydb" sélectionné, permettant de basculer entre différentes bases de données.
- Des liens pour "Requête SQL", "Importer", "Exporter" et "Créer une table".

**Zone principale (Tables et vues) :**
- Un champ de recherche "Rechercher dans les tables (1)" avec un bouton "Rechercher".
- Un tableau listant les objets de la base de données avec les colonnes suivantes : case à cocher, "Table", "Moteur?", "Interclassement?", "Longueur des données?", "Longueur de l'index?", "Espace inutilisé?", "Incrément automatique?", "Lignes?" et "Commentaire?".
- Une table nommée "messages" est listée avec les détails suivants :
  - Moteur : "InnoDB"
  - Interclassement : "utf8mb4_unicode_ci"
  - Longueur des données : "16,384"
  - Longueur de l'index : "0"
  - Espace inutilisé : "0"
  - Incrément automatique : "2"
  - Lignes : "~1" (environ 1)
  - Commentaire : (vide)
- Une ligne "1 au total" résume ces informations.
- Des actions sont disponibles pour les éléments sélectionnés : "Analyser", "Optimiser", "Vérifier", "Réparer", "Tronquer" et "Supprimer".
- Une option "Déplacer vers une autre base de données" avec un menu déroulant et des boutons "Déplacer" et "Copier".
- Des liens pour "Créer une table" et "Créer une vue".

**Sections Routines et Évènements :**
- Dans "Routines", des liens pour "Créer une procédure" et "Créer une fonction".
- Dans "Évènements", un lien pour "Créer un évènement".

**Pertinence pour le rapport :**
Cette image valide visuellement que la configuration d'Adminer fonctionne correctement, que la connexion à la base de données MariaDB via le routage inter-réseaux est établie avec succès, et que la base de données `mydb` avec sa table `messages` est bien en place et accessible. Elle démontre également que le service `adminer_container` sur le réseau `front` peut effectivement communiquer avec le service `mariadb_container` sur le réseau `back` via le routeur.

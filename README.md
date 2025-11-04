# Exercice 2 - Virtualisation avec Docker

Architecture Docker avec deux réseaux isolés (`front` et `back`) reliés par un routeur personnalisé.

## Architecture

- **Réseau front (170.20.0.0/16)** : Serveur web et Adminer
- **Réseau back (170.21.0.0/16)** : Base de données MariaDB
- **Routeur** : Conteneur assurant le routage entre les deux réseaux

## Démarrage

```bash
docker-compose up -d --build
```

## Accès aux services

- **Serveur web** : http://localhost
- **Adminer** : http://localhost:8080
  - Serveur : `db`
  - Utilisateur : `user`
  - Mot de passe : `passwd`
  - Base de données : `mydb`

## Arrêt

```bash
docker-compose down
```

## Structure

```
exo2/
├── docker-compose.yml
├── routeur/          # Conteneur routeur
├── serveurWeb/       # Serveur web PHP
├── adminer/          # Interface Adminer
└── MariaDB/          # Configuration MariaDB (non utilisée)
```

## Documentation

Voir `RAPPORT_REALISATION.md` pour les détails techniques.

## Création d'un dépôt distant (GitHub/GitLab)

Si vous souhaitez pousser ce projet sur un dépôt distant :

1. **Créer un nouveau dépôt sur GitHub/GitLab** (sans initialiser avec README)

2. **Ajouter le remote :**
   ```bash
   git remote add origin <URL_du_repo>
   ```

3. **Pousser le code :**
   ```bash
   git push -u origin master
   ```

Le dépôt local est déjà initialisé avec tous les fichiers du projet.


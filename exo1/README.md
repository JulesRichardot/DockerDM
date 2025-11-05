# Exercice 1 - Docker

## Description

Cet exercice consiste à créer une image Docker pour compiler et exécuter le projet `couleurs` depuis le dépôt GitLab de Sorbonne Paris Nord.

## Structure

- `Dockerfile` : Configuration de l'image Docker
- `Rapport exo1.md` : Rapport détaillé de réalisation

## Construction de l'image

Pour construire l'image Docker :

```bash
docker build -t couleurs .
```

## Exécution

Pour exécuter le conteneur :

```bash
docker run couleurs
```

## Technologies utilisées

- **Base image** : Alpine Linux 3.16
- **Outils** : git, make, gcc, musl-dev
- **Projet** : [couleurs](https://gitlab.sorbonne-paris-nord.fr/franck.butelle/couleurs.git)


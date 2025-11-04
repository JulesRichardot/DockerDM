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
set -e
ip route add 170.21.0.0/16 via 170.20.0.2 dev eth0 2>/dev/null || true
exec httpd -D FOREGROUND
```

Cette route indique que tout le trafic destiné au réseau 170.21.0.0/16 doit passer par le routeur situé à l'adresse 170.20.0.2. Les conteneurs nécessitent la capacité `NET_ADMIN` pour pouvoir modifier leur table de routage.

## Résolution DNS

Un problème important rencontré est que les services sur le réseau `front` ne peuvent pas résoudre les noms DNS des services sur le réseau `back` car Docker isole les réseaux au niveau DNS. Pour permettre au serveur web de se connecter à la base de données en utilisant le nom `db`, la configuration `extra_hosts` est utilisée dans docker-compose.yml pour mapper le nom `db` vers l'IP fixe 170.21.0.10.

## Problèmes rencontrés

Le premier problème était l'activation du forwarding IP. Sans les privilèges appropriés, le conteneur routeur ne pouvait pas modifier le paramètre système, générant une erreur "Read-only file system". La solution a été d'ajouter `privileged: true` au conteneur routeur.

Le second problème concernait la configuration des routes. Les conteneurs `web` et `adminer` ne pouvaient pas ajouter de routes sans la capacité `NET_ADMIN`. Cette capacité a été ajoutée et des scripts d'entrypoint ont été créés pour configurer automatiquement les routes au démarrage.

Enfin, l'attribution d'IP fixes a nécessité quelques ajustements. L'utilisation de l'adresse .1 était problématique car elle est réservée aux gateways Docker. L'utilisation de .2 pour le routeur a résolu les conflits.

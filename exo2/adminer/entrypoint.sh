#!/bin/sh
set -e

# Ajouter la route vers le réseau back via le routeur
ip route add 170.21.0.0/16 via 170.20.0.2 dev eth0 2>/dev/null || true

# Démarrer Apache
exec httpd -D FOREGROUND

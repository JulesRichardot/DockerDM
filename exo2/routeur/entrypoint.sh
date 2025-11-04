#!/bin/sh

# Activer le forwarding IP
sysctl -w net.ipv4.ip_forward=1 || echo 1 > /proc/sys/net/ipv4/ip_forward || true

# Garder le conteneur en vie
tail -f /dev/null

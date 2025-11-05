#!/bin/sh
set -e

# Permissions
chown -R mysql:mysql /var/lib/mysql /run/mysqld 2>/dev/null || true
mkdir -p /run/mysqld
chown mysql:mysql /run/mysqld

# Initialisation si nécessaire
if [ ! -d /var/lib/mysql/mysql ] || [ ! -f /var/lib/mysql/mysql/user.MYI ]; then
    echo 'Initialisation de la base de données...'
    rm -rf /var/lib/mysql/*
    mysql_install_db --user=mysql --datadir=/var/lib/mysql --skip-name-resolve
    
    echo 'Démarrage temporaire de MySQL...'
    mysqld_safe --user=mysql --datadir=/var/lib/mysql --socket=/run/mysqld/mysqld.sock --skip-networking &
    MYSQL_PID=$!
    
    # Attendre que MySQL soit prêt
    for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
        sleep 1
        if [ -S /run/mysqld/mysqld.sock ] && mysqladmin --socket=/run/mysqld/mysqld.sock ping >/dev/null 2>&1; then
            break
        fi
    done
    
    echo 'Exécution du script d''initialisation...'
    mysql -u root --socket=/run/mysqld/mysqld.sock < /docker-entrypoint-initdb.d/init.sql
    
    echo 'Arrêt de MySQL...'
    mysqladmin --socket=/run/mysqld/mysqld.sock shutdown 2>/dev/null || kill -TERM $MYSQL_PID 2>/dev/null || true
    
    # Attendre que les processus se terminent
    for i in 1 2 3 4 5 6 7 8 9 10; do
        sleep 1
        if ! pgrep -f 'mysqld_safe|mariadbd' >/dev/null 2>&1; then
            break
        fi
    done
    
    # Forcer l'arrêt si nécessaire
    pkill -9 -f 'mysqld_safe|mariadbd' 2>/dev/null || true
    sleep 2
    
    # Nettoyer
    rm -f /run/mysqld/mysqld.sock /var/lib/mysql/*.pid 2>/dev/null || true
fi

echo 'Démarrage de MySQL...'
exec mysqld --user=mysql --datadir=/var/lib/mysql

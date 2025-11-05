FROM alpine:3.16

# Installer MariaDB
RUN apk add --no-cache mariadb mariadb-client

# Répertoires requis
RUN mkdir -p /run/mysqld /var/lib/mysql && \
    chown -R mysql:mysql /run/mysqld /var/lib/mysql

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY ./init.sql /docker-entrypoint-initdb.d/init.sql
COPY ./server.cnf /etc/my.cnf.d/server.cnf

VOLUME ["/var/lib/mysql"]

EXPOSE 3306

# Init si besoin, puis démarrage (UN SEUL processus)
CMD ["./entrypoint.sh"]

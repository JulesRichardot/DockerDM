FROM alpine:3.16

RUN apk update && apk add php8 php8-mysqli curl php8-apache2 php8-pdo_mysql php8-session iproute2
RUN curl -o /var/www/localhost/htdocs/index.php https://www.adminer.org/latest.php

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

CMD ["/entrypoint.sh"]

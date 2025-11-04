FROM alpine:3.16

RUN apk update && apk add php8-apache2 php8-mysqli php8-pdo_mysql iproute2

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
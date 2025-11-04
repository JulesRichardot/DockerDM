FROM alpine:3.16

RUN apk update && apk add iproute2

COPY ./entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]
FROM alpine:3.16

WORKDIR /couleurs

RUN apk update && apk add git make gcc musl-dev

RUN git clone https://gitlab.sorbonne-paris-nord.fr/franck.butelle/couleurs.git .
RUN ./configure && make && make install

ENTRYPOINT ["mescouleurs"]
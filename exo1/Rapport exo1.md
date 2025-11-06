# Rapport Exercice 1

Au départ, j'ai effectué le travail sur la machine virtuelle Debian 12, comme j'avais l'habitude de le faire en TP.

Plusieurs difficultés sont apparues au cours de l'exercice.

D'abord, la compilation échouait dans le conteneur Alpine car il manquait certains outils de développement (gcc, make, musl-dev). J'ai pu logiquement les ajouter, en observant les messages d'erreur et en me renseignant sur le net.

J'ai ensuite découvert que la compilation échouait parce que make essayait de régénérer certains fichiers automake (comme aclocal.m4), alors qu'il suffisait en réalité d'utiliser directement le Makefile déjà présent dans le dépôt.

La solution a été d'ajouter les paquets automake, autoconf, m4 et perl pour permettre au projet de se compiler correctement.

Cette fois plus d'erreur, j'obtenais le résultat attendu.

Cependant, j'ai constaté que j'avais besoin de beaucoup de dépendances supplémentaires pour réussir la compilation, y compris certaines qui semblaient déjà présentes dans le dépôt GitLab d'origine (notamment automake et autoconf).

Cette situation m'a paru anormale et m'a conduit à penser que mon environnement virtuel Debian introduisait trop de décalages avec celui attendu dans le sujet.

J'ai donc décidé de migrer vers Docker Desktop, pour pouvoir utiliser docker directement sur mon environnement personnel.

J'ai pu reconstruire l'image avec beaucoup moins de dépendances manuelles (uniquement git, gcc, make, et musl-dev).

La construction et l'exécution via PowerShell se sont révélées plus simples et beaucoup plus rapides, tout en gardant le même comportement que sur Linux.

<img width="317" height="125" alt="alpine-couleurs" src="https://github.com/user-attachments/assets/67d642c3-c5d3-4712-a870-b71bba79e3ed" />

# Utilise une image officielle PHP avec Apache
FROM php:8.2-apache

# Copie tout le code dans le dossier du serveur web
COPY . /var/www/html/

# Expose le port sur lequel Apache écoute
EXPOSE 80

# Commande de démarrage (par défaut Apache démarre automatiquement)
CMD ["apache2-foreground"]

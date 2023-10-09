FROM mcr.microsoft.com/devcontainers/php:1-8.2-bookworm

# # Allow devcontainer/Codespace use www-data as the remote user instead of root.
# RUN usermod --shell /bin/bash www-data
# RUN touch /var/www/.bashrc
# RUN chown -R www-data: /var/www/

# Install git & zip
RUN apt-get update && \
    apt-get upgrade -y && \
    apt-get install -y git libzip-dev zip

# Install PHP ZIP
RUN docker-php-ext-install zip

#Install Xdebug
#RUN pecl install "xdebug" || true \
#    && docker-php-ext-enable xdebug
#RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
#RUN echo "xdebug.log=/var/www/html/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install Composer
# RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install WP CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && \
        chmod +x wp-cli.phar && \
        mv wp-cli.phar /usr/local/bin/wp

# Allow www-data user to use sudo without password
# RUN adduser www-data sudo
# RUN echo '%sudo ALL=(ALL) NOPASSWD:ALL' >> /etc/sudoers
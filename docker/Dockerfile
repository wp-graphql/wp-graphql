###############################################################################
# Pre-configured WordPress Installation w/ WPGraphQL #
# For testing only, use in production not recommended. #
###############################################################################

# Use build args to get the right wordpress + php image
ARG WP_VERSION
ARG PHP_VERSION

FROM wordpress:${WP_VERSION:-6.8}-php${PHP_VERSION:-8.3} AS app
LABEL author=jasonbahl
LABEL author_uri=https://github.com/jasonbahl
WORKDIR /var/www/html

# Needed to specify the build args again after the FROM command.
ARG WP_VERSION
ARG PHP_VERSION

# Save the build args for use by the runtime environment
ENV WP_VERSION=${WP_VERSION}
ENV PHP_VERSION=${PHP_VERSION}

SHELL [ "/bin/bash", "-c" ]

# Install system packages
RUN apt-get update && apt-get -y install mariadb-client

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Set project environmental variables
ENV WP_ROOT_FOLDER="/var/www/html"
ENV PLUGINS_DIR="${WP_ROOT_FOLDER}/wp-content/plugins"
ENV PROJECT_DIR="${PLUGINS_DIR}/wp-graphql"
ENV DATA_DUMP_DIR="${PROJECT_DIR}/tests/_data"

# Remove exec statement from base entrypoint script.
RUN sed -i '$d' /usr/local/bin/docker-entrypoint.sh

# Set up Apache catch all name
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Custom PHP settings
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/custom.ini \
    ;

# Install XDebug 3. If PHP version 7, use last supported version
RUN echo "Installing XDebug 3 version $XDEBUG_VERSION (in disabled state)" \
    && if [[ $PHP_VERSION == 7* ]] ; then pecl install xdebug-3.1.5 ; else pecl install xdebug ; fi \
    && mkdir -p /usr/local/etc/php/conf.d/disabled \
    && echo "zend_extension=xdebug" > /usr/local/etc/php/conf.d/disabled/docker-php-ext-xdebug.ini \
    && echo "xdebug.mode=develop,debug,coverage" >> /usr/local/etc/php/conf.d/disabled/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/disabled/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=$(ip route | awk '/default/ { print $3 }')" >> /usr/local/etc/php/conf.d/disabled/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/disabled/docker-php-ext-xdebug.ini \
    && echo "xdebug.max_nesting_level=512" >> /usr/local/etc/php/conf.d/disabled/docker-php-ext-xdebug.ini \
    ;

# Set xdebug configuration off by default. See the entrypoint.sh.
ENV USING_XDEBUG=0

# Set up entrypoint
WORKDIR    /var/www/html
COPY       docker/app.setup.sh /usr/local/bin/app-setup.sh
COPY       docker/app.post-setup.sh /usr/local/bin/app-post-setup.sh
COPY       docker/app.entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN        chmod 755 /usr/local/bin/app-entrypoint.sh
ENTRYPOINT ["app-entrypoint.sh"]
CMD ["apache2-foreground"]

FROM app AS testing
WORKDIR    /var/www/html
# Install php extensions
RUN apt-get update && apt-get -y install mariadb-client zip unzip \
    && pecl install pcov \
    && docker-php-ext-install pdo_mysql

ENV COVERAGE=
ENV SUITES=${SUITES:-}

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

# Configure php
RUN echo "date.timezone = UTC" >> /usr/local/etc/php/php.ini

# Set up entrypoint
WORKDIR    /var/www/html
COPY       docker/testing.entrypoint.sh /usr/local/bin/testing-entrypoint.sh
RUN        chmod 755 /usr/local/bin/testing-entrypoint.sh
ENTRYPOINT ["testing-entrypoint.sh"]

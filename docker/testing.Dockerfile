############################################################################
# Container for running Codeception tests on a WPGraphQL Docker instance. #
############################################################################

ARG WP_VERSION
ARG PHP_VERSION
ARG DOCKER_REGISTRY

FROM ${DOCKER_REGISTRY:-}wp-graphql:latest-wp${WP_VERSION}-php${PHP_VERSION}

LABEL author=jasonbahl
LABEL author_uri=https://github.com/jasonbahl

SHELL [ "/bin/bash", "-c" ]

# Install php extensions
RUN docker-php-ext-install pdo_mysql

# Install PCOV
# This is needed for Codeception / PHPUnit to track code coverage
RUN apt-get install zip unzip -y \
    && pecl install pcov

ENV COVERAGE=
ENV SUITES=${SUITES:-}

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | php -- \
    --filename=composer \
    --install-dir=/usr/local/bin

# Add composer global binaries to PATH
ENV PATH "$PATH:~/.composer/vendor/bin"

# Configure php
RUN echo "date.timezone = UTC" >> /usr/local/etc/php/php.ini

# Set up entrypoint
WORKDIR    /var/www/html
COPY       docker/testing.entrypoint.sh /usr/local/bin/testing-entrypoint.sh
RUN        chmod 755 /usr/local/bin/testing-entrypoint.sh
ENTRYPOINT ["testing-entrypoint.sh"]

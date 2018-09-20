ARG WP_DOCKER_IMAGE
FROM ${WP_DOCKER_IMAGE}

# Install PHP Composeer, WP-CLI, xdebug, PHP MySQL driver, etc
RUN apt-get update -y \
  && apt-get install --no-install-recommends -y g++ git make mysql-client subversion unzip zip zlib1g-dev \
  && rm -rf /var/lib/apt/lists/* \
  && pecl install xdebug \
  && docker-php-ext-enable xdebug \
  && docker-php-ext-install pdo_mysql zip \
  && echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini \
  && curl -Ls 'https://raw.githubusercontent.com/composer/getcomposer.org/4d2ef40109bfbec0f9b8b39f12f260fb6e80befa/web/installer' | php -- --quiet \
  && chmod +x composer.phar \
  && mv composer.phar /usr/local/bin/composer \
  && curl -O 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar' \
  && chmod +x wp-cli.phar \
  && mv wp-cli.phar /usr/local/bin/wp \
  && mkdir -p /project/src /project/vendor

# First copy the files needed for php composer install so that the Docker build only re-executes the install when those
# files change.
COPY composer.json composer.lock /project/
COPY src/ /project/src/
COPY vendor/ /project/vendor/
RUN chown -R www-data:www-data /project
USER www-data
RUN cd /project \
  && composer install

# Copy in all other files from repo, but preserve the files used by/modified by composer install.
# Also copy in the "c3.php" needed for remote Codeception code coverage. https://github.com/Codeception/c3
USER root
RUN mkdir /tmp/project
COPY . /tmp/project/
RUN rm -rf /tmp/project/composer.* /tmp/project/vendor \
  && cp -a /tmp/project/* /project/ \
  && curl -L 'https://raw.github.com/Codeception/c3/2.0/c3.php' > /project/c3.php \
  && chown -R www-data:www-data /project \
  && rm -rf /tmp/project

# Copy docker-entrypoints to a directory that's already in the environment PATH
COPY docker-entrypoints/docker-entrypoint*.sh /usr/local/bin/

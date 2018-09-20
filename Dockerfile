ARG WP_DOCKER_IMAGE
FROM ${WP_DOCKER_IMAGE} as base-environment

USER root

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
  && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
  && chmod +x wp-cli.phar \
  && mv wp-cli.phar /usr/local/bin/wp

WORKDIR /project

# First copy the files needed for php composer install so that the Docker build only re-executes the install when those
# files change.
RUN mkdir /project/src /project/vendor && chown -R www-data:www-data /project
COPY --chown=www-data:www-data composer.json composer.lock /project/
COPY --chown=www-data:www-data src/ /project/src/
COPY --chown=www-data:www-data vendor/ /project/vendor/
#RUN chown -R www-data:www-data /project
USER www-data
RUN composer install

# Copy in everything else, but don't clobber the php composer files or the 'vendor' directory
USER root
RUN mkdir /tmp/project && chown -R www-data:www-data /tmp/project
COPY --chown=www-data:www-data . /tmp/project/
RUN rm -rf /tmp/project/composer.* /tmp/project/vendor && cp -a /tmp/project/* /project/
#RUN chown -R www-data:www-data /project

# Copy docker-entrypoints to a place that's already in the environment PATH
COPY docker-entrypoints/docker-entrypoint*.sh /usr/local/bin/

RUN rm -rf /tmp/project && mkdir /tmp/project
COPY --chown=www-data:www-data . /tmp/project/

WORKDIR /var/www/html


# Don't need 'root' privileges anymore, so don't run as 'root'.
#USER www-data

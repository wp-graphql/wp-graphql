ARG PHP_VERSION
FROM wordpress:cli-php${PHP_VERSION}

USER root

RUN apk add --no-cache autoconf bash g++ git make mysql-client subversion zip

RUN curl -Ls 'https://raw.githubusercontent.com/composer/getcomposer.org/4d2ef40109bfbec0f9b8b39f12f260fb6e80befa/web/installer' | php -- --quiet \
  && chmod +x composer.phar \
  && mv composer.phar /usr/local/bin/composer \
  && composer --version

RUN pecl install xdebug \
  && docker-php-ext-enable xdebug \
  && docker-php-ext-install pdo_mysql \
  && echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /project

# First copy the files needed for php composer install so that the Docker build only re-executes the install when those
# files change.
RUN mkdir /project/src /project/vendor
COPY composer.json composer.lock /project/
COPY src/ /project/src/
COPY vendor/ /project/vendor/
RUN chown -R www-data:www-data /project
USER www-data
RUN composer install


# Copy in everything else, but don't clobber the php composer files or the 'vendor' directory
USER root
RUN mkdir /tmp/project
COPY . /tmp/project
RUN rm -rf /tmp/project/composer.* /tmp/project/vendor && cp -a /tmp/project/* /project/
RUN chown -R www-data:www-data /project

# Copy docker-endpoints to a place that's already in the environment PATH
COPY docker-endpoints/docker-endpoint*.sh /usr/local/bin/

# Don't need 'root' privileges anymore, so don't run as 'root'.
USER www-data

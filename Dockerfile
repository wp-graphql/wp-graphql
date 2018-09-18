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

# First copy the files needed for composer install so that the Docker build only re-executes the install when those
# files change.
RUN mkdir /project/src

COPY composer.json composer.lock /project/
COPY src/ /project/src/

RUN composer install --ignore-platform-reqs

COPY docker-endpoints/docker-endpoint*.sh /usr/local/bin/

# Copy in everything else
COPY . /project/

#RUN find && false

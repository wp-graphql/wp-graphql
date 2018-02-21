ARG PHP_VERSION

FROM wordpress:cli-php${PHP_VERSION}

USER root

RUN apk --no-cache --update add bash subversion sudo

RUN docker-php-ext-install pdo_mysql

RUN echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini

RUN pecl install xdebug-2.5.5 && docker-php-ext-enable xdebug
RUN echo 'zend_extension="/usr/local/lib/php/extensions/no-debug-non-zts-20151012/xdebug.so"' >> /usr/local/etc/php/php.ini
RUN echo 'xdebug.remote_port=9000' >> /usr/local/etc/php/php.ini
RUN echo 'xdebug.remote_enable=1' >> /usr/local/etc/php/php.ini
RUN echo 'xdebug.remote_connect_back=1' >> /usr/local/etc/php/php.ini

ENV WP_CLI_ARGS="--allow-root"

WORKDIR /project

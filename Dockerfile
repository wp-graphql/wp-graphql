# This Dockerfile assumes BASE_DOCKER_IMAGE refers to a Debian+Apache variant of WordPress.
ARG BASE_DOCKER_IMAGE
FROM ${BASE_DOCKER_IMAGE}

# Install PHP Composer, WP-CLI, xdebug (only for PHP 7.X), PHP MySQL driver, etc
RUN echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini \
  && apt-get update -y \
  && apt-get install --no-install-recommends -y g++ git make mysql-client subversion unzip zip zlib1g-dev \
  && rm -rf /var/lib/apt/lists/* \
  && if echo "${PHP_VERSION}" | grep '^7.'; then pecl install xdebug; docker-php-ext-enable xdebug; fi \
  && docker-php-ext-install pdo_mysql zip \
  && curl -Ls 'https://raw.githubusercontent.com/composer/getcomposer.org/4d2ef40109bfbec0f9b8b39f12f260fb6e80befa/web/installer' | php -- --quiet \
  && chmod +x composer.phar \
  && mv composer.phar /usr/local/bin/composer \
  && curl -O 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar' \
  && chmod +x wp-cli.phar \
  && mv wp-cli.phar /usr/local/bin/wp

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
COPY . /tmp/project/
RUN rm -rf /tmp/project/composer.* /tmp/project/vendor \
  && cp -a /tmp/project/* /project/ \
  && rm -rf /tmp/project \
  && curl -L 'https://raw.github.com/Codeception/c3/2.0/c3.php' > /project/c3.php \
  && chown -R www-data:www-data /project \
  && cp -a /project /usr/src/wordpress/wp-content/plugins/wp-graphql

ENV PRISTINE_WP_DIR=/usr/src/wordpress/ \
  WP_TEST_CORE_DIR=/tmp/dfmedia-wordpress/ \
  WP_TESTS_DIR=/tmp/wordpress-tests-lib/ \
  WP_TESTS_TAG=tags/$WORDPRESS_VERSION

# Install WP test framework
RUN cp -a "${PRISTINE_WP_DIR}" "${WP_TEST_CORE_DIR}" \
  && mkdir -p "${WP_TESTS_DIR}" \
  && svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "${WP_TESTS_DIR}/includes" \
  && svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "${WP_TESTS_DIR}/data" \
  && curl -Lsv "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" > "${WP_TESTS_DIR}/wp-tests-config.php" \
  && curl -Ls 'https://raw.github.com/markoheijnen/wp-mysqli/master/db.php' > "${WP_TEST_CORE_DIR}/wp-content/db.php"

# Copy docker-entrypoints to a directory that's already in the environment PATH
COPY docker-entrypoints/*.sh /usr/local/bin/

RUN cp -a /usr/src/wordpress/ /tmp/wordpress

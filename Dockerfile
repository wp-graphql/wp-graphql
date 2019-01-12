# Using the 'DESIRED_' prefix to avoid confusion with environment variables of the same name.
ARG DESIRED_WP_VERSION
ARG DESIRED_PHP_VERSION
ARG OFFICIAL_WORDPRESS_DOCKER_IMAGE="wordpress:${DESIRED_WP_VERSION}-php${DESIRED_PHP_VERSION}-apache"


# --------------------- STAGE -----------------------
# Sets timezone to UTC and install XDebug on top of official WordPress image
FROM ${OFFICIAL_WORDPRESS_DOCKER_IMAGE} as wordpress-utc-xdebug

# Set timezone to UTC and install XDebug for PHP 7.X.
RUN echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini \
  && if echo "${PHP_VERSION}" | grep '^7.'; then pecl install xdebug; docker-php-ext-enable xdebug; fi


# --------------------- STAGE -----------------------
# This runs PHP Composer install on the project so it can be used by the SUT and tester Docker images
FROM ${OFFICIAL_WORDPRESS_DOCKER_IMAGE} as wp-graphql-composer-dependencies

# Install PHP composer
RUN curl -Ls 'https://raw.githubusercontent.com/composer/getcomposer.org/d3e09029468023aa4e9dcd165e9b6f43df0a9999/web/installer' | php -- --quiet \
  && chmod +x composer.phar \
  && mv composer.phar /usr/local/bin/composer

ENV PROJECT_DIR=/tmp/wp-graphql/

# First copy the files needed for PHP composer install so that the Docker build only re-executes the install when those
# files change.
COPY --chown='www-data:www-data' composer.json composer.lock "${PROJECT_DIR}"/
COPY --chown='www-data:www-data' src/ "${PROJECT_DIR}/src/"
COPY --chown='www-data:www-data' vendor/ "${PROJECT_DIR}/vendor/"

# Run PHP Composer install so that Codeception dependencies are available
USER www-data
RUN cd "${PROJECT_DIR}" \
  && composer install

# Copy in all other files from repo, but preserve the files used by/modified by composer install.
USER root
COPY --chown='www-data:www-data' . /tmp/project/
RUN rm -rf /tmp/project/composer.* /tmp/project/vendor \
  && cp -a /tmp/project/* "${PROJECT_DIR}" \
  && rm -rf /tmp/project


# --------------------- STAGE -----------------------
# Creates Wordpress image + wp-graphql system-under-test (SUT)
FROM ${OFFICIAL_WORDPRESS_DOCKER_IMAGE} as wordpress-wp-graphql-sut

# Install XDebug and WP-CLI
RUN echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini \
  && if echo "${PHP_VERSION}" | grep '^7.'; then pecl install 'xdebug' && docker-php-ext-enable xdebug; fi \
  && curl -O 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar' \
  && chmod +x wp-cli.phar \
  && mv wp-cli.phar /usr/local/bin/wp

# Add WP-CLI config for flushing rewrite rules: https://developer.wordpress.org/cli/commands/rewrite/flush/
COPY --chown='www-data:www-data' docker/wp-cli.yml /var/www/html/

# Add plugin code
ENV PROJECT_DIR=/usr/src/wordpress/wp-content/plugins/wp-graphql/
RUN mkdir "${PROJECT_DIR}"

# Install code coverage support
RUN curl -L 'https://raw.github.com/Codeception/c3/2.0/c3.php' > "${PROJECT_DIR}/c3.php"

COPY --chown='www-data:www-data' --from='wp-graphql-composer-dependencies' /tmp/wp-graphql/ "${PROJECT_DIR}"/

# Add Docker entrypoint script
COPY docker/docker-entrypoint.sut.sh /usr/local/bin/

ENTRYPOINT [ "docker-entrypoint.sut.sh" ]


# --------------------- STAGE -----------------------
# Creates image from which plugin tests are invoked
FROM ${OFFICIAL_WORDPRESS_DOCKER_IMAGE} as wordpress-wp-graphql-tester

# Install mysql client, pdo_mysql, subversion
RUN echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini \
  && apt-get update -y \
  && apt-get install --no-install-recommends -y mysql-client subversion \
  && rm -rf /var/lib/apt/lists/* \
  && docker-php-ext-install pdo_mysql

ENV WP_TEST_CORE_DIR=/tmp/wordpress/ \
  PROJECT_DIR=/tmp/wordpress/wp-content/plugins/wp-graphql/ \
  PRISTINE_WP_DIR=/usr/src/wordpress/ \
  WP_TESTS_DIR=/tmp/wordpress-tests-lib/ \
  WP_TESTS_TAG=tags/$WORDPRESS_VERSION

# Install WP test framework
RUN mkdir -p "${WP_TESTS_DIR}" \
  && svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "${WP_TESTS_DIR}/includes" \
  && svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "${WP_TESTS_DIR}/data" \
  && curl -Lsv "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" > "${WP_TESTS_DIR}/wp-tests-config.php" \
  && chown -R 'www-data:www-data' "${WP_TESTS_DIR}"

# Move core WordPress files to core test directory
RUN mv "${PRISTINE_WP_DIR}" "${WP_TEST_CORE_DIR}"

# Add db configuration to core test directory
RUN curl -Ls 'https://raw.github.com/markoheijnen/wp-mysqli/master/db.php' > "${WP_TEST_CORE_DIR}/wp-content/db.php"

COPY --chown='www-data:www-data' --from='wp-graphql-composer-dependencies' /tmp/wp-graphql/ "${PROJECT_DIR}"/

COPY docker/edit-wp-test-suite-db-config.sh docker/docker-entrypoint.tester.sh /usr/local/bin/

WORKDIR /tmp/wordpress/wp-content/plugins/wp-graphql

ENTRYPOINT [ "docker-entrypoint.tester.sh" ]


# --------------------- STAGE -----------------------
# Allows developer to log into full-provisioned Docker container to run tests
FROM wordpress-wp-graphql-tester as wordpress-wp-graphql-tester-shell

COPY docker/docker-entrypoint.tester-shell.sh /usr/local/bin/

ENTRYPOINT [ "docker-entrypoint.tester-shell.sh" ]

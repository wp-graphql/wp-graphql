# -------------------- STAGE ---------------
# Using the 'DESIRED_' prefix to avoid confusion with environment variables of the same name.
ARG DESIRED_WP_VERSION
ARG DESIRED_PHP_VERSION
ARG BASE_DOCKER_IMAGE="wordpress:${DESIRED_WP_VERSION}-php${DESIRED_PHP_VERSION}-apache"
FROM ${BASE_DOCKER_IMAGE} as project-files

# Install composer
RUN curl -Ls 'https://raw.githubusercontent.com/composer/getcomposer.org/4d2ef40109bfbec0f9b8b39f12f260fb6e80befa/web/installer' | php -- --quiet \
  && chmod +x composer.phar \
  && mv composer.phar /usr/local/bin/composer \
  && mkdir /project

# First copy the files needed for php composer install so that the Docker build only re-executes the install when those
# files change. Then actually run composer.
COPY --chown=www-data:www-data composer.json composer.lock /project/
COPY --chown=www-data:www-data src/ /project/src/
COPY --chown=www-data:www-data vendor/ /project/vendor/
USER www-data
RUN cd /project \
  && composer install

# Copy in all other files from repo, but preserve the files used by/modified by composer install.
USER root
COPY --chown=www-data:www-data . /tmp/project/
RUN rm -rf /tmp/project/composer.* /tmp/project/vendor \
  && cp -a /tmp/project/* /project/ \
  && rm -rf /tmp/project

# -------------------- STAGE ---------------
FROM ${BASE_DOCKER_IMAGE} as wordpress-sut-environment

COPY --chown=www-data:www-data --from='project-files' /project/ /usr/src/wordpress/wp-content/plugins/wp-graphql/

# Install xdebug and code coverage support
RUN if echo "${PHP_VERSION}" | grep '^7.'; then pecl install xdebug; docker-php-ext-enable xdebug; fi \
  && curl -L 'https://raw.github.com/Codeception/c3/2.0/c3.php' > /usr/src/wordpress/wp-content/plugins/wp-graphql/c3.php

# -------------------- STAGE ---------------
FROM ${BASE_DOCKER_IMAGE} as base-tester-environment

ENV PRISTINE_WP_DIR=/usr/src/wordpress/ \
  WP_TEST_CORE_DIR=/tmp/wordpress/ \
  WP_TESTS_DIR=/tmp/wordpress-tests-lib/ \
  WP_TESTS_TAG=tags/$WORDPRESS_VERSION

# Install wp-cli and pdo_mysql
RUN echo 'date.timezone = "UTC"' > /usr/local/etc/php/conf.d/timezone.ini \
  && curl -O 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar' \
  && chmod +x wp-cli.phar \
  && mv wp-cli.phar /usr/local/bin/wp \
  && apt-get update -y \
  && apt-get install --no-install-recommends -y mysql-client subversion \
  && rm -rf /var/lib/apt/lists/* \
  && docker-php-ext-install pdo_mysql

# Install WordPress test framework
RUN cp -a "${PRISTINE_WP_DIR}" "${WP_TEST_CORE_DIR}" \
  && curl -Ls 'https://raw.github.com/markoheijnen/wp-mysqli/master/db.php' > "${WP_TEST_CORE_DIR}/wp-content/db.php" \
  && mkdir -p "${WP_TESTS_DIR}" \
  && svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "${WP_TESTS_DIR}/includes" \
  && svn co --quiet "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "${WP_TESTS_DIR}/data" \
  && curl -Lsv "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" > "${WP_TESTS_DIR}/wp-tests-config.php" \
  && chown -R www-data:www-data "${WP_TESTS_DIR}"

# Copy docker-entrypoints to a directory that's already in the environment PATH
COPY docker-entrypoints/*.sh /usr/local/bin/

USER www-data

WORKDIR "${WP_TEST_CORE_DIR}/wp-content/plugins/wp-graphql"


# -------------------- STAGE ---------------
FROM base-tester-environment as tester-environment

# Add plugin code to the WordPress test framework
COPY --chown=www-data:www-data --from='project-files' /project/ "${WP_TEST_CORE_DIR}/wp-content/plugins/wp-graphql"

# -------------------- STAGE ---------------
FROM base-tester-environment as tester-shell-environment
ARG CONTAINER_USER_ID
ARG CONTAINER_GROUP_ID

# Create a user inside of the Docker image that has the same user and group id as the user invoking the Docker task.
# This is for when developers log into a running container (as this user) to run the tests. This is ensures new files
# are owned by the user invoking the Docker task.
USER root
RUN groupadd --gid "${CONTAINER_USER_ID}" tester \
  && useradd --uid "${CONTAINER_GROUP_ID}" --gid tester --shell /bin/bash --create-home tester \
  && chown -R "${CONTAINER_USER_ID}:${CONTAINER_GROUP_ID}" "${WP_TESTS_DIR}" "${WP_TEST_CORE_DIR}" \
  && echo 'composer install' >> /home/tester/.bashrc \
  && echo 'initialize-wp-test-environment.sh' >> /home/tester/.bashrc

USER tester

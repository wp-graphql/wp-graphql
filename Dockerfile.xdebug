# This Docker image adds XDebug to the official WordPress Docker image so that developers can step through WordPress
# plugin execution with a tool that supports XDebug.

# Using the 'DESIRED_' prefix to avoid confusion with environment variables of the same name.
ARG DESIRED_WP_VERSION
ARG DESIRED_PHP_VERSION
ARG OFFICIAL_WORDPRESS_DOCKER_IMAGE="wordpress:${DESIRED_WP_VERSION}-php${DESIRED_PHP_VERSION}-apache"


# --------------------- STAGE -----------------------
# Sets timezone to UTC and install XDebug on top of official WordPress image
FROM ${OFFICIAL_WORDPRESS_DOCKER_IMAGE}

# Install XDebug for PHP 7.X.
RUN if echo "${PHP_VERSION}" | grep '^7'; then \
      pecl install xdebug; \
      docker-php-ext-enable xdebug; \
    fi

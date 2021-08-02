#!/bin/bash

echo "WordPress: ${WP_VERSION} PHP: ${PHP_VERSION}"

# Processes parameters and runs Codeception.
run_tests() {
    if [[ -n "$COVERAGE" ]]; then
        local coverage="--coverage --coverage-xml"
    fi
    if [[ -n "$DEBUG" ]]; then
        local debug="--debug"
    fi

    local suites=$1
    if [[ -z "$suites" ]]; then
        echo "No test suites specified. Must specify variable SUITES."
        exit 1
    fi

    # If maintenance mode is active, de-activate it
    if $( wp maintenance-mode is-active --allow-root ); then
      echo "Deactivating maintenance mode"
      wp maintenance-mode deactivate --allow-root
    fi


    # Suites is the comma separated list of suites/tests to run.
    echo "Running Test Suite $suites"
    vendor/bin/codecept run -c codeception.dist.yml "${suites}" ${coverage:-} ${debug:-} --no-exit
}

# Exits with a status of 0 (true) if provided version number is higher than proceeding numbers.
version_gt() {
    test "$(printf '%s\n' "$@" | sort -V | head -n 1)" != "$1";
}

write_htaccess() {
    echo "<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
SetEnvIf Authorization \"(.*)\" HTTP_AUTHORIZATION=\$1
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>" >> ${WP_ROOT_FOLDER}/.htaccess
}

# Move to WordPress root folder
workdir="$PWD"
echo "Moving to WordPress root directory ${WP_ROOT_FOLDER}."
cd ${WP_ROOT_FOLDER}

# Because we are starting apache independetly of the docker image,
# we set WORDPRESS environment variables so apache see them and used in the wp-config.php
echo "export WORDPRESS_DB_HOST=${WORDPRESS_DB_HOST}" >> /etc/apache2/envvars
echo "export WORDPRESS_DB_USER=${WORDPRESS_DB_USER}" >> /etc/apache2/envvars
echo "export WORDPRESS_DB_PASSWORD=${WORDPRESS_DB_PASSWORD}" >> /etc/apache2/envvars
echo "export WORDPRESS_DB_NAME=${WORDPRESS_DB_NAME}" >> /etc/apache2/envvars

# Run app setup scripts.
. app-setup.sh
. app-post-setup.sh

write_htaccess

# Return to PWD.
echo "Moving back to project working directory ${PROJECT_DIR}"
cd ${PROJECT_DIR}

# Ensure Apache is running
service apache2 start

# Ensure everything is loaded
dockerize \
    -wait tcp://${DB_HOST}:${DB_HOST_PORT:-3306} \
    -wait ${WP_URL} \
    -timeout 1m

# Download c3 for testing.
if [ ! -f "$PROJECT_DIR/c3.php" ]; then
    echo "Downloading Codeception's c3.php"
    curl -L 'https://raw.github.com/Codeception/c3/2.0/c3.php' > "$PROJECT_DIR/c3.php"
fi

# Install dependencies
echo "Running composer update"
COMPOSER_MEMORY_LIMIT=-1 composer update
echo "Running composer install"
COMPOSER_MEMORY_LIMIT=-1 composer install --no-interaction

# Install pcov/clobber if PHP7.1+
if version_gt $PHP_VERSION 7.0 && [[ -n "$COVERAGE" ]] && [[ -z "$USING_XDEBUG" ]]; then
    echo "Using pcov/clobber for codecoverage"
    docker-php-ext-enable pcov
    echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
    echo "pcov.directory = ${PROJECT_DIR}" >> /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
    COMPOSER_MEMORY_LIMIT=-1 composer require pcov/clobber --dev
    vendor/bin/pcov clobber
elif [[ -n "$COVERAGE" ]] && [[ -n "$USING_XDEBUG" ]]; then
    echo "Using XDebug for codecoverage"
fi

# Set output permission
echo "Setting Codeception output directory permissions"
chmod 777 ${TESTS_OUTPUT}

# Run tests
run_tests "${SUITES}"

# Remove c3.php
if [ -f "$PROJECT_DIR/c3.php" ] && [ "$SKIP_TESTS_CLEANUP" != "1" ]; then
    echo "Removing Codeception's c3.php"
    rm -rf "$PROJECT_DIR/c3.php"
fi

# Clean coverage.xml and clean up PCOV configurations.
if [ -f "${TESTS_OUTPUT}/coverage.xml" ] && [[ -n "$COVERAGE" ]]; then
    echo 'Cleaning coverage.xml for deployment'.
    pattern="$PROJECT_DIR/"
    sed -i "s~$pattern~~g" "$TESTS_OUTPUT"/coverage.xml

    # Remove pcov/clobber
    if version_gt $PHP_VERSION 7.0 && [[ -z "$SKIP_TESTS_CLEANUP" ]] && [[ -z "$USING_XDEBUG" ]]; then
        echo 'Removing pcov/clobber.'
        vendor/bin/pcov unclobber
        COMPOSER_MEMORY_LIMIT=-1 composer remove --dev pcov/clobber
        rm /usr/local/etc/php/conf.d/docker-php-ext-pcov.ini
    fi

fi

if [[ -z "$SKIP_TESTS_CLEANUP" ]]; then
    echo 'Changing composer configuration in container.'
    composer config --global discard-changes true

    echo 'Removing devDependencies.'
    composer install --no-dev -n

    echo 'Removing composer.lock'
    rm composer.lock
fi

# Set public test result files permissions.
if [ -n "$(ls "$TESTS_OUTPUT")" ]; then
    echo 'Setting result files permissions'.
    chmod 777 -R "$TESTS_OUTPUT"/*
fi


# Check results and exit accordingly.
if [ -f "${TESTS_OUTPUT}/failed" ]; then
    echo "Uh oh, something went wrong."
    exit 1
else
    echo "Woohoo! It's working!"
fi

#!/bin/bash

# Processes parameters and runs Codeception.
run_tests() {
    echo "Running Tests"
    if [[ -n "$COVERAGE" ]]; then
        local coverage="--coverage --coverage-xml"
    fi
    if [[ -n "$DEBUG" ]]; then
        local debug="--debug"
    fi

    local suites=${1:-" ;"}
    IFS=';' read -ra target_suites <<< "$suites"
    for suite in "${target_suites[@]}"; do
        vendor/bin/codecept run -c codeception.dist.yml ${suite} ${coverage:-} ${debug:-} --no-exit
    done
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
echo "Moving to WordPress root directory."
cd ${WP_ROOT_FOLDER}

# Run app entrypoint script.
. app-entrypoint.sh

write_htaccess

# Return to PWD.
echo "Moving back to project working directory."
cd ${workdir}

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

if [[ -n "$LOWEST" ]]; then
    PREFER_LOWEST="--prefer-source"
fi

# Install dependencies
COMPOSER_MEMORY_LIMIT=-1 composer update --prefer-source ${PREFER_LOWEST}
COMPOSER_MEMORY_LIMIT=-1 composer install --prefer-source --no-interaction

# Install pcov/clobber if PHP7.1+
if version_gt $PHP_VERSION 7.0 && [[ -n "$COVERAGE" ]] && [[ -z "$USING_XDEBUG" ]]; then
    echo "Installing pcov/clobber"
    COMPOSER_MEMORY_LIMIT=-1 composer require --dev pcov/clobber
    vendor/bin/pcov clobber
elif [[ -n "$COVERAGE" ]]; then
    echo "Using XDebug for codecoverage"
fi

# Set output permission
echo "Setting Codeception output directory permissions"
chmod 777 ${TESTS_OUTPUT}

# Run tests
run_tests ${SUITES}

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
    echo "Uh oh, some went wrong."
    exit 1
else
    echo "Woohoo! It's working!"
    exit 0
fi

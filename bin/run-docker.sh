#!/usr/bin/env bash

set -eu

##
# Use this script through Composer scripts in the package.json.
# To quickly build and run the docker-compose scripts for an app or automated testing
# run the command below after run `composer install --no-dev` with the respectively
# flag for what you need.
##
print_usage_instructions() {
    echo "Usage: composer build-and-run -- [-a|-t]";
    echo "       -a  Spin up a WordPress installation.";
    echo "       -t  Run the automated tests.";
    echo "Example use:";
    echo "  composer build-app";
    echo "  composer run-app";
    echo "";
    echo "  WP_VERSION=5.5.3 PHP_VERSION=7.4 composer build-app";
    echo "  WP_VERSION=5.5.3 PHP_VERSION=7.4 composer run-app";
    echo "";
    echo "  WP_VERSION=5.5.3 PHP_VERSION=7.4  bin/run-docker.sh build -a";
    echo "  WP_VERSION=5.5.3 PHP_VERSION=7.4  bin/run-docker.sh run -a";
    exit 1
}

if [ $# -eq 0 ]; then
    print_usage_instructions
fi

TAG=${TAG-latest}
WP_VERSION=${WP_VERSION-5.6}
PHP_VERSION=${PHP_VERSION-7.4}

subcommand=$1; shift
case "$subcommand" in
    "build" )
        while getopts ":at" opt; do
            case ${opt} in
                a )
                docker build -f docker/app.Dockerfile \
                    -t wp-graphql:${TAG}-wp${WP_VERSION}-php${PHP_VERSION} \
                    --build-arg WP_VERSION=${WP_VERSION} \
                    --build-arg PHP_VERSION=${PHP_VERSION} \
                    .
                    ;;
                t )
                docker build -f docker/app.Dockerfile \
                    -t wp-graphql:${TAG}-wp${WP_VERSION}-php${PHP_VERSION} \
                    --build-arg WP_VERSION=${WP_VERSION} \
                    --build-arg PHP_VERSION=${PHP_VERSION} \
                    .

                docker build -f docker/testing.Dockerfile \
                    -t wp-graphql-testing:${TAG}-wp${WP_VERSION}-php${PHP_VERSION} \
                    --build-arg WP_VERSION=${WP_VERSION} \
                    --build-arg PHP_VERSION=${PHP_VERSION} \
                    .
                    ;;
                \? ) print_usage_instructions;;
                * ) print_usage_instructions;;
            esac
        done
        shift $((OPTIND -1))
        ;;
    "run" )
        while getopts "e:at" opt; do
            case ${opt} in
                a )
                docker compose up --scale testing=0
                    -e WP_VERSION=${WP_VERSION} \
                    -e PHP_VERSION=${PHP_VERSION} \
                    ;;
                t )
                docker compose run --rm \
                    -e COVERAGE=${COVERAGE-} \
                    -e USING_XDEBUG=${USING_XDEBUG-} \
                    -e DEBUG=${DEBUG-} \
                    -e WP_VERSION=${WP_VERSION} \
                    -e PHP_VERSION=${PHP_VERSION} \
                    testing --scale app=0
                    ;;
                \? ) print_usage_instructions;;
                * ) print_usage_instructions;;
            esac
        done
        shift $((OPTIND -1))
        ;;

    \? ) print_usage_instructions;;
    * ) print_usage_instructions;;
esac

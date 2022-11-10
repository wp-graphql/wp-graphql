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
    echo "  WP_VERSION=6.1 PHP_VERSION=8.1 composer build-app";
    echo "  WP_VERSION=6.1 PHP_VERSION=8.1 composer run-app";
    echo "";
    echo "  WP_VERSION=6.1 PHP_VERSION=8.1  bin/run-docker.sh build -a";
    echo "  WP_VERSION=6.1 PHP_VERSION=8.1  bin/run-docker.sh run -a";
    exit 1
}

if [ $# -eq 0 ]; then
    print_usage_instructions
fi

TAG=${TAG-latest}
WP_VERSION=${WP_VERSION-6.1}
PHP_VERSION=${PHP_VERSION-8.1}

BUILD_NO_CACHE=${BUILD_NO_CACHE-}

if [[ ! -f ".env" ]]; then
  echo "No .env file was detected. .env.dist has been copied to .env"
  echo "Open the .env file and enter values to match your local environment"
  cp .env.dist .env
fi

subcommand=$1; shift
case "$subcommand" in
    "build" )
        while getopts ":cat" opt; do
            case ${opt} in
                c )
                    echo "Build without cache"
                    BUILD_NO_CACHE=--no-cache
                    ;;
                a )
                docker build $BUILD_NO_CACHE -f docker/app.Dockerfile \
                    -t wp-graphql:${TAG}-wp${WP_VERSION}-php${PHP_VERSION} \
                    --build-arg WP_VERSION=${WP_VERSION} \
                    --build-arg PHP_VERSION=${PHP_VERSION} \
                    .
                    ;;
                t )
                docker build $BUILD_NO_CACHE -f docker/app.Dockerfile \
                    -t wp-graphql:${TAG}-wp${WP_VERSION}-php${PHP_VERSION} \
                    --build-arg WP_VERSION=${WP_VERSION} \
                    --build-arg PHP_VERSION=${PHP_VERSION} \
                    .

                docker build $BUILD_NO_CACHE -f docker/testing.Dockerfile \
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
                WP_VERSION=${WP_VERSION} PHP_VERSION=${PHP_VERSION} docker compose up app
                    ;;
                t )
                docker-compose run --rm \
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

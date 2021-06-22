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
	exit 1
}

if [ -z "$1" ]; then
	print_usage_instructions
fi

BUILD_NO_CACHE=

env_file=".env.dist";

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
                    -t wpgraphql-app:latest \
                    --build-arg WP_VERSION=${WP_VERSION-5.4} \
                    --build-arg PHP_VERSION=${PHP_VERSION-7.4} \
                    .
                    ;;
                t )
                docker build $BUILD_NO_CACHE -f docker/app.Dockerfile \
                    -t wpgraphql-app:latest \
                    --build-arg WP_VERSION=${WP_VERSION-5.4} \
                    --build-arg PHP_VERSION=${PHP_VERSION-7.4} \
                    .

                docker build $BUILD_NO_CACHE -f docker/testing.Dockerfile \
                    -t wpgraphql-testing:latest \
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
				e )
				env_file=${OPTARG};
				if [ ! -f $env_file ]; then
					echo "No file found at $env_file"
				fi
				;;
                a ) docker-compose up --scale testing=0;;
                t )
                docker-compose run --rm \
                    -e COVERAGE=${COVERAGE-} \
                    -e USING_XDEBUG=${USING_XDEBUG-} \
                    -e DEBUG=${DEBUG-} \
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

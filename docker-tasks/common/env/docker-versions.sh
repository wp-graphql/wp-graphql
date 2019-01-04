#!/usr/bin/env bash

# Docker image settings to be shared by various Docker tasks.
export MYSQL_DOCKER_IMAGE='mariadb:10.2.20-bionic'
export WP_VERSION="${WP_VERSION:-5.0.2}"
export PHP_VERSION="${PHP_VERSION:-7.2}"

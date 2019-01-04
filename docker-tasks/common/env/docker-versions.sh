#!/usr/bin/env bash

# TODO: Verify this!
# Docker image settings to be shared by various Docker tasks. These values can be overridden with shell values.
export MYSQL_DOCKER_IMAGE='mariadb:10.2.20-bionic'
export WP_VERSION='5.0.2'
export PHP_VERSION='7.2'

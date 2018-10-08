#!/usr/bin/env bash

set -e

install_wpgraphql_plugin_into_wp() {
  cp -a /project /usr/src/wordpress/wp-content/plugins/wp-graphql
}

run_wp() {
  # Now run what the Wordpress Docker image would have run by default
  docker-entrypoint.sh 'apache2-foreground'
}

main() {
  install_wpgraphql_plugin_into_wp
  run_wp
}

main

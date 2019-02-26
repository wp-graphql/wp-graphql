#!/usr/bin/env bash

set -eu

wait_for_someone_to_login() {
  sleep '9999d'

}
main() {
  edit-wp-test-suite-db-config.sh
  wait_for_someone_to_login
}

main

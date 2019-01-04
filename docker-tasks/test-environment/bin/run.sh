#!/usr/bin/env bash
set -eu

cd_to_task_dir() {
  cd "$( dirname "${BASH_SOURCE[0]}" )/.." >/dev/null
}

run_test_environment() {
  ../common/bin/docker-compose-up-wrapper.sh docker-compose-files/docker-compose.yml
}

main() {
  cd_to_task_dir
  run_test_environment
}

main

#!/usr/bin/env bash
set -eu

cd_to_task_dir() {
  cd "$( dirname "${BASH_SOURCE[0]}" )/.." >/dev/null
}

run_app() {
  ../common/bin/docker-compose-up-wrapper.sh docker-compose-files/docker-compose.yml
}

main() {
  cd_to_task_dir
  run_app
}

main

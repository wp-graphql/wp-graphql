#!/usr/bin/env bash

set -eu

readonly DOCKER_COMPOSE_FILES="${1:?Comma-delimited list of Docker Compose file(s) required.}"
readonly EXIT_CODE_CONTAINER="${2:-}"
readonly CONTAINER_DATA_PATH="${3:-}"
readonly HOST_DATA_PATH="${4:-}"
readonly DOCKER_COMPOSE_UP_FILE_FLAGS="-f ${DOCKER_COMPOSE_FILES//,/ -f }"

validate_parameters() {
  # If only one of the container and host paths are specified, show usage and exit
  if [[ -z "${CONTAINER_DATA_PATH:-}" && ! -z "${HOST_DATA_PATH:-}" ]] \
    || [[ ! -z "${CONTAINER_DATA_PATH:-}" && -z "${HOST_DATA_PATH:-}" ]]; then
    echo 'Usage: docker-compose-up-wrapper.sh <commad-delimited docker compose file(s)> <exit code container> <container data path> <host data path>' >&2
    exit 1
  fi
}

normalize_directory_name_for_docker() {
  echo "$(basename $(pwd))" | sed -e 's/[^a-zA-Z0-9_.-]\+/-/g'
}

build_docker_name() {
  local -r prefix="${1}"
  echo "${prefix}-$(normalize_directory_name_for_docker)-${RANDOM}"
}

generate_unique_names() {
  readonly UNIQUE_DOCKER_COMPOSE_PROJECT_NAME="$(build_docker_name 'docker-compose')"
  readonly UNIQUE_DATA_CONTAINER_NAME="$(build_docker_name 'container')"
}

build_docker_compose_up_exit_code_flag() {
  if [[ -z "${EXIT_CODE_CONTAINER:-}" ]]; then
    echo ''
  else
    echo "--abort-on-container-exit --exit-code-from ${EXIT_CODE_CONTAINER}"
  fi
}
run_docker_compose_up() {
  env UNIQUE_DATA_CONTAINER_NAME="${UNIQUE_DATA_CONTAINER_NAME}" docker-compose ${DOCKER_COMPOSE_UP_FILE_FLAGS} -p "${UNIQUE_DOCKER_COMPOSE_PROJECT_NAME}" up --build $(build_docker_compose_up_exit_code_flag)
}

build_container_data_path() {
  if [[ -z "${CONTAINER_DATA_PATH:-}" ]]; then
    echo ''
  elif [[ "${CONTAINER_DATA_PATH/:/}" ==  "${CONTAINER_DATA_PATH}" ]]; then
    echo "${UNIQUE_DATA_CONTAINER_NAME}:${CONTAINER_DATA_PATH}"
  else
    echo "${CONTAINER_DATA_PATH}"
  fi
}

copy_data_from_docker_container_to_host_if_requested() {
  if [[ ! -z "${CONTAINER_DATA_PATH:-}" && ! -z "${HOST_DATA_PATH:-}" ]]; then
    docker cp "$(build_container_data_path)" "${HOST_DATA_PATH}"
  fi
}

cleanup_docker_artifacts() {
  docker-compose ${DOCKER_COMPOSE_UP_FILE_FLAGS} -p "${UNIQUE_DOCKER_COMPOSE_PROJECT_NAME}" down -v --rmi local 2> /dev/null
}

main() {
  validate_parameters
  generate_unique_names

  trap cleanup_docker_artifacts EXIT

  set +e
  run_docker_compose_up
  local -r exit_code=$?
  set -e

  copy_data_from_docker_container_to_host_if_requested

  exit $((exit_code))
}

main

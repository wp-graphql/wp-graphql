#!/usr/bin/env bash

set -e

show_usage() {
 echo "The following environment variables need to be set:" >&2
 echo "DOCKER_TASK - desired Docker compose file (required)" >&2
 echo "CONTAINER_DATA_PATH - container path from which to copy data (optional, but if used, HOST_DATA_PATH must be specified as well)" >&2
 echo "HOST_DATA_PATH - host path to which to copy container data (optional, but if used, CONTAINER_DATA_PATH must be specified as well)" >&2
 exit 1
}


normalize_directory_name_for_docker() {
  echo "$(basename $(pwd))" | sed -e 's/[^a-zA-Z0-9_.-]\+/-/g'
}

build_docker_name() {
  local -r prefix="${1}"
  echo "${prefix}-$(normalize_directory_name_for_docker)-${RANDOM}"
}


initialize_env_var() {
  readonly DOCKER_COMPOSE_FILE="docker-tasks/${DOCKER_TASK}/docker-compose.yml"

  if [[ ! -f "${DOCKER_COMPOSE_FILE}" ]]; then
    echo "${DOCKER_COMPOSE_FILE} is not a regular file!" >&2
    show_usage
  fi

  # If only one of the container and host paths are specified, show usage and exit
  if [[ -z "${CONTAINER_DATA_PATH}" && ! -z "${HOST_DATA_PATH}" ]] \
    || [[ ! -z "${CONTAINER_DATA_PATH}" && -z "${HOST_DATA_PATH}" ]]; then
    show_usage
  fi

  readonly DOCKER_COMPOSE_PROJECT_NAME="$(build_docker_name 'docker-compose')"
  readonly CONTAINER_NAME="$(build_docker_name 'container')"
}


run_compose() {
  env CONTAINER_NAME="${CONTAINER_NAME}" docker-compose -f "${DOCKER_COMPOSE_FILE}" -p "${DOCKER_COMPOSE_PROJECT_NAME}" up "$@"
}

copy_data_from_docker_container_to_host() {
  if [[ ! -z "${CONTAINER_DATA_PATH}" && ! -z "${HOST_DATA_PATH}" ]]; then
    docker cp "${CONTAINER_NAME}:${CONTAINER_DATA_PATH}" "${HOST_DATA_PATH}"
  fi
}

cleanup() {
  echo "Cleaning up Docker artifacts..."
  env CONTAINER_NAME="${CONTAINER_NAME}" docker-compose -p "${DOCKER_COMPOSE_PROJECT_NAME}" -f "${DOCKER_COMPOSE_FILE}" down -v --rmi local
}

main() {
  initialize_env_var "$@"
  trap cleanup EXIT

  set +e
  run_compose "$@"
  readonly exit_code=$?
  set -e

  copy_data_from_docker_container_to_host

  exit $((exit_code))
}

main "$@"

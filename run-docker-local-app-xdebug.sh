#!/usr/bin/env bash
set -eu

get_docker_host_os_ip() {
  if [[ "$(uname -s)" == 'Linux' ]]; then
    # HACK: For Linux, there's not a simple way to reliably get the IP of the host OS. For dev environments, the
    # simplest approach is to assume the container can reach the Docker host at IP "172.17.0.1".
    # https://github.com/docker/for-linux/issues/264#issuecomment-431300555
    echo '172.17.0.1'
  else
    echo 'host.docker.internal'
  fi
}

run_app_xdebug() {
  env DOCKER_HOST_IP="$(get_docker_host_os_ip)" docker-compose -f docker/docker-compose.local-app.yml -f docker/docker-compose.local-app-xdebug.yml up --build
}

cleanup_docker_artifacts() {
  docker-compose -f docker/docker-compose.local-app.yml -f docker/docker-compose.local-app-xdebug.yml down -v --rmi local 2> /dev/null
}

main() {
  trap cleanup_docker_artifacts EXIT
  run_app_xdebug
}

main

#!/usr/bin/env bash
set -eu

cd_to_task_dir() {
  cd "$( dirname "${BASH_SOURCE[0]}" )/.." >/dev/null
}

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

run_app() {
  env DOCKER_HOST_IP="$(get_docker_host_os_ip)" ../common/bin/docker-compose-up-wrapper.sh 'docker-compose-files/docker-compose.yml,docker-compose-files/docker-compose.xdebug.yml'
}

main() {
  cd_to_task_dir
  run_app
}

main

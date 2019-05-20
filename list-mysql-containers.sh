#!/usr/bin/env bash
set -eu

main() {
  docker ps | grep 3306
}

main

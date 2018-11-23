#!/usr/bin/env bash
set -e

sync_files_from_bind_mounts() {
  rsync -av --delete /project/ /tester-shell-dir/
}

restore_vendor_files() {
  rsync -av --delete /pristine-tester-plugin/vendor/ /tester-shell-dir/vendor/
}

run_codeception() {
  ./vendor/bin/codecept "$@"
}

main() {
  sync_files_from_bind_mounts
  restore_vendor_files
  run_codeception "$@"
}

main "$@"

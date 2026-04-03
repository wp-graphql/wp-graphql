#!/usr/bin/env bash
# Run k6 persisted-GET load against WordPress origin only (default http://localhost:8888).
# Safe to run from any working directory — resolves paths from this script location.
#
# Environment (all optional):
#   BASE_URL       default http://localhost:8888
#   URLS_FILE      default urls.txt (in benchmark/k6)
#   DURATION       default 2m
#   VUS            default 10
#
# Do not set REQUIRE_X_CACHE or MIN_HIT_RATE — origin responses typically lack Varnish X-Cache.
#
# Example:
#   DURATION=30s VUS=5 ./plugins/wp-graphql-pqu/benchmark/scripts/run-k6-origin.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../k6" || {
	echo "error: expected ../k6 next to $SCRIPT_DIR" >&2
	exit 1
}

export BASE_URL="${BASE_URL:-http://localhost:8888}"
export URLS_FILE="${URLS_FILE:-urls.txt}"
export DURATION="${DURATION:-2m}"
export VUS="${VUS:-10}"

exec k6 run pqu-persisted-get.js

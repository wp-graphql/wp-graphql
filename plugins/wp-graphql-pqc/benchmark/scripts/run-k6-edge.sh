#!/usr/bin/env bash
# Run k6 persisted-GET load against the Varnish edge (default http://localhost:8081).
# Safe to run from any working directory — resolves paths from this script location.
#
# Environment (all optional):
#   BASE_URL       default http://localhost:8081
#   URLS_FILE      default urls.txt (in benchmark/k6)
#   DURATION       default 2m
#   VUS            default 10
#   REQUIRE_X_CACHE  set to 1 to require X-Cache header
#   MIN_HIT_RATE   e.g. 0.85 for threshold on warm edge
#
# Examples:
#   ./plugins/wp-graphql-pqc/benchmark/scripts/run-k6-edge.sh
#   DURATION=30s VUS=5 REQUIRE_X_CACHE=1 ./plugins/wp-graphql-pqc/benchmark/scripts/run-k6-edge.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/../k6" || {
	echo "error: expected ../k6 next to $SCRIPT_DIR" >&2
	exit 1
}

export BASE_URL="${BASE_URL:-http://localhost:8081}"
export URLS_FILE="${URLS_FILE:-urls.txt}"
export DURATION="${DURATION:-2m}"
export VUS="${VUS:-10}"

exec k6 run pqc-persisted-get.js

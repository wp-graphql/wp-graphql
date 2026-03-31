#!/usr/bin/env bash
# Edge load (k6) + periodic post updates (WP-CLI) to trigger Smart Cache purge → edge MISS/HIT cycles.
# Run from repo root or anywhere; resolves k6 cwd from this script location.
#
# Requires: k6 on PATH; WP_BIN must target the same WordPress site Varnish uses as backend.
#
# Environment (optional):
#   WP_BIN            default wp — e.g. npm run wp-env -- run cli -- wp
#   WP_WORKDIR        directory to run WP_BIN from (default: monorepo root with package.json + wp-env)
#   POST_ID           post to touch (default 10)
#   CHURN_INTERVAL    seconds between updates (default 30)
#   BASE_URL, URLS_FILE, DURATION, VUS — same as run-k6-edge.sh (edge defaults)
#
# Examples (script path is relative to your shell cwd):
#   ./plugins/wp-graphql-pqc/benchmark/scripts/k6-with-churn.sh   # from monorepo root
#   ../scripts/k6-with-churn.sh                                   # from benchmark/k6
#
# Ctrl+C stops k6 and exits.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
K6_DIR="$(cd "$SCRIPT_DIR/../k6" && pwd)" || exit 1

# npm run wp-env must run from the monorepo root (package.json). k6 runs from K6_DIR, so churn uses WP_WORKDIR.
detect_wp_workdir() {
	local d="$SCRIPT_DIR"
	local i
	for i in 1 2 3 4 5 6 7 8 9 10; do
		if [[ -f "$d/package.json" ]] && grep -q 'wp-env' "$d/package.json" 2>/dev/null; then
			echo "$d"
			return 0
		fi
		d="$(dirname "$d")"
	done
	return 1
}

POST_ID="${POST_ID:-10}"
CHURN_INTERVAL="${CHURN_INTERVAL:-30}"
WP_BIN="${WP_BIN:-wp}"

if [[ -n "${WP_WORKDIR:-}" ]]; then
	WP_WORKDIR="$(cd "$WP_WORKDIR" && pwd)"
elif WP_WORKDIR="$(detect_wp_workdir)"; then
	:
else
	WP_WORKDIR=""
fi

export BASE_URL="${BASE_URL:-http://localhost:8081}"
export URLS_FILE="${URLS_FILE:-urls.txt}"
export DURATION="${DURATION:-2m}"
export VUS="${VUS:-10}"

cleanup() {
	if [[ -n "${K6_PID:-}" ]] && kill -0 "$K6_PID" 2>/dev/null; then
		kill "$K6_PID" 2>/dev/null || true
		wait "$K6_PID" 2>/dev/null || true
	fi
}
trap cleanup EXIT INT TERM

cd "$K6_DIR" || exit 1

k6 run pqc-persisted-get.js &
K6_PID=$!

echo "k6 pid=${K6_PID} (edge ${BASE_URL}); churning post ${POST_ID} every ${CHURN_INTERVAL}s via: ${WP_BIN}"
if [[ -n "$WP_WORKDIR" ]]; then
	echo "WP_WORKDIR=${WP_WORKDIR} (npm/wp-env commands run here)"
else
	echo "WP_WORKDIR=(unset — running WP_BIN from current directory at churn time)"
fi
if [[ "$WP_BIN" == *npm* ]] && [[ -z "$WP_WORKDIR" ]]; then
	echo "warning: WP_BIN looks like npm but no wp-env package.json found upward from script; set WP_WORKDIR to monorepo root" >&2
fi
echo "Expect pqc_x_cache_misses to bump after each save when URLs in urls.txt tag that post; Ctrl+C stops both."

while kill -0 "$K6_PID" 2>/dev/null; do
	sleep "$CHURN_INTERVAL"
	if ! kill -0 "$K6_PID" 2>/dev/null; then
		break
	fi
	ts="$(date "+%Y-%m-%d %H:%M:%S")"
	title="churn-$(date +%s)"
	echo "[$ts] churn: post update ${POST_ID}"
	if [[ -n "$WP_WORKDIR" ]]; then
		if ! ( cd "$WP_WORKDIR" && $WP_BIN post update "$POST_ID" --post_title="$title" ); then
			echo "[$ts] churn: wp post update failed (WP_WORKDIR=${WP_WORKDIR} POST_ID=${POST_ID} WP_BIN=${WP_BIN})" >&2
		fi
	else
		if ! $WP_BIN post update "$POST_ID" --post_title="$title"; then
			echo "[$ts] churn: wp post update failed (POST_ID=${POST_ID} WP_BIN=${WP_BIN})" >&2
		fi
	fi
done

wait "$K6_PID" 2>/dev/null || true

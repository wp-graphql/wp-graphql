#!/usr/bin/env bash
# k6 edge load + rotating churn: new posts, random updates, taxonomy flips, menu items.
# Does not delete posts (would break singular URLs in urls-headless-day.txt).
#
# Run from repo root:
#   URLS_FILE=urls-headless-day.txt ./plugins/wp-graphql-pqc/benchmark/scripts/k6-with-realistic-churn.sh
#
# Environment:
#   CHURN_CYCLE_SEC   base sleep between actions (default 40); jitter adds 0–25s
#   WP_ENV            default cli (development WordPress / :8888)
#   WP_WORKDIR        monorepo root (auto-detected)
#   BASE_URL, URLS_FILE, DURATION, VUS — same as run-k6-edge.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
K6_DIR="$(cd "$SCRIPT_DIR/../k6" && pwd)" || exit 1

has_wp_env_npm_script() {
	local f="$1/package.json"
	[[ -f "$f" ]] || return 1
	grep -qE '"wp-env"[[:space:]]*:[[:space:]]*"wp-env"' "$f" 2>/dev/null
}

detect_wp_workdir() {
	local d="$SCRIPT_DIR"
	local i
	for i in $(seq 1 16); do
		if has_wp_env_npm_script "$d"; then
			echo "$d"
			return 0
		fi
		d="$(dirname "$d")"
	done
	return 1
}

CHURN_CYCLE_SEC="${CHURN_CYCLE_SEC:-40}"
WP_ENV="${WP_ENV:-cli}"

if [[ -n "${WP_WORKDIR:-}" ]]; then
	WP_WORKDIR="$(cd "$WP_WORKDIR" && pwd)"
elif WP_WORKDIR="$(detect_wp_workdir)"; then
	:
else
	WP_WORKDIR=""
fi

wp_churn() {
	if [[ -n "$WP_WORKDIR" ]]; then
		( cd "$WP_WORKDIR" && npm run wp-env -- run "$WP_ENV" -- wp "$@" )
	else
		wp "$@"
	fi
}

export BASE_URL="${BASE_URL:-http://localhost:8081}"
export URLS_FILE="${URLS_FILE:-urls-headless-day.txt}"
export DURATION="${DURATION:-2m}"
export VUS="${VUS:-10}"

random_post_id() {
	wp_churn post list --post_type=post --post_status=publish --format=ids --posts_per_page=500 2>/dev/null | tr ' ' '\n' | grep -E '^[0-9]+$' | shuf | head -1
}

random_category_id() {
	wp_churn term list category --format=ids --number=200 2>/dev/null | tr ' ' '\n' | grep -E '^[0-9]+$' | shuf | head -1
}

random_tag_id() {
	wp_churn term list post_tag --format=ids --number=200 2>/dev/null | tr ' ' '\n' | grep -E '^[0-9]+$' | shuf | head -1
}

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

echo "k6 pid=${K6_PID} edge=${BASE_URL} urls=${URLS_FILE}; realistic churn every ~${CHURN_CYCLE_SEC}s (+jitter)"
echo "WP_WORKDIR=${WP_WORKDIR:-"(unset — using host wp)"} WP_ENV=${WP_ENV}"

while kill -0 "$K6_PID" 2>/dev/null; do
	jit=$(( RANDOM % 26 ))
	sleep $(( CHURN_CYCLE_SEC + jit ))
	if ! kill -0 "$K6_PID" 2>/dev/null; then
		break
	fi
	ts="$(date "+%Y-%m-%d %H:%M:%S")"
	roll=$(( RANDOM % 5 ))
	case "$roll" in
		0)
			title="bench-new-$(date +%s)-$RANDOM"
			echo "[$ts] churn: post create (publish) $title"
			wp_churn post create --post_type=post --post_status=publish --post_title="$title" || true
			;;
		1)
			pid="$(random_post_id)"
			if [[ -n "$pid" ]]; then
				title="bench-touch-$(date +%s)"
				echo "[$ts] churn: post update $pid"
				wp_churn post update "$pid" --post_title="$title" || true
			fi
			;;
		2)
			pid="$(random_post_id)"
			cid="$(random_category_id)"
			if [[ -n "$pid" && -n "$cid" ]]; then
				echo "[$ts] churn: post $pid set category term $cid"
				wp_churn post term set "$pid" category "$cid" || true
			fi
			;;
		3)
			pid="$(random_post_id)"
			tid="$(random_tag_id)"
			if [[ -n "$pid" && -n "$tid" ]]; then
				echo "[$ts] churn: post $pid set post_tag term $tid"
				wp_churn post term set "$pid" post_tag "$tid" || true
			fi
			;;
		4)
			label="churn-$(date +%s)"
			echo "[$ts] churn: menu item Primary Nav -> $label"
			wp_churn menu item add-custom "Primary Nav" "$label" "/" || true
			;;
	esac
done

wait "$K6_PID" 2>/dev/null || true

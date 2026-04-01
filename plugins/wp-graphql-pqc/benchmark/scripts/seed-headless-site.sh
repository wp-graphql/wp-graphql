#!/usr/bin/env bash
# Seed many posts, categories, tags, authors, Primary Nav menu, and assign taxonomy/authors.
# Intended for local wp-env (development WordPress). Run from repo root:
#   ./plugins/wp-graphql-pqc/benchmark/scripts/seed-headless-site.sh
#
# Options:
#   --force   Run even if benchmark_headless_seed_done=1 (does not delete existing content).
#
# Environment (optional):
#   WP_ENV              wp-env target (default: cli) — maps to localhost:8888 dev site.
#   BENCH_POST_COUNT    default 1000
#   BENCH_CAT_COUNT     default 40
#   BENCH_TAG_COUNT     default 40
#   BENCH_AUTHOR_COUNT  default 40
#   WP_WORKDIR          monorepo root (auto-detected if unset)

set -euo pipefail

FORCE=0
for a in "$@"; do
	if [[ "$a" == "--force" ]]; then
		FORCE=1
	fi
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

has_wp_env_npm_script() {
	local f="$1/package.json"
	[[ -f "$f" ]] || return 1
	grep -qE '"wp-env"[[:space:]]*:[[:space:]]*"wp-env"' "$f" 2>/dev/null
}

detect_wp_workdir() {
	local d="$SCRIPT_DIR"
	local i
	for i in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
		if has_wp_env_npm_script "$d"; then
			echo "$d"
			return 0
		fi
		d="$(dirname "$d")"
	done
	return 1
}

if [[ -n "${WP_WORKDIR:-}" ]]; then
	WP_WORKDIR="$(cd "$WP_WORKDIR" && pwd)"
elif WP_WORKDIR="$(detect_wp_workdir)"; then
	:
else
	echo "error: could not find monorepo root (package.json with \"wp-env\": \"wp-env\"). Set WP_WORKDIR." >&2
	exit 1
fi

WP_ENV="${WP_ENV:-cli}"
POST_COUNT="${BENCH_POST_COUNT:-1000}"
CAT_COUNT="${BENCH_CAT_COUNT:-40}"
TAG_COUNT="${BENCH_TAG_COUNT:-40}"
AUTHOR_COUNT="${BENCH_AUTHOR_COUNT:-40}"

run_wp() {
	( cd "$WP_WORKDIR" && npm run wp-env -- run "$WP_ENV" -- wp "$@" )
}

if [[ "$FORCE" -eq 0 ]]; then
	done_flag="$(run_wp option get benchmark_headless_seed_done 2>/dev/null || true)"
	if [[ "${done_flag:-}" == "1" ]]; then
		echo "Seed already applied (option benchmark_headless_seed_done=1). Use --force to run again anyway."
		exit 0
	fi
fi

echo "Using WP_WORKDIR=$WP_WORKDIR WP_ENV=$WP_ENV"
echo "Generating ${CAT_COUNT} categories, ${TAG_COUNT} tags, ${AUTHOR_COUNT} authors, ${POST_COUNT} posts..."

run_wp plugin activate wp-graphql 2>/dev/null || true
run_wp plugin activate wp-graphql-smart-cache 2>/dev/null || true
run_wp plugin activate wp-graphql-pqc 2>/dev/null || true

run_wp rewrite structure /%postname%/ --hard 2>/dev/null || true
run_wp rewrite flush --hard 2>/dev/null || true

run_wp term generate category --count="$CAT_COUNT"
run_wp term generate post_tag --count="$TAG_COUNT"
run_wp user generate --count="$AUTHOR_COUNT" --role=author
run_wp post generate --count="$POST_COUNT" --post_status=publish

CONTAINER_BENCH="/var/www/html/wp-content/plugins/wp-graphql-pqc/benchmark"
run_wp eval-file "$CONTAINER_BENCH/scripts/php/bench-assign-posts.php"

if run_wp term list nav_menu --slug=primary-nav --format=ids 2>/dev/null | grep -qE '^[0-9]+$'; then
	echo "Menu Primary Nav (slug primary-nav) already exists."
else
	run_wp menu create "Primary Nav"
	run_wp menu item add-custom "Primary Nav" "Home" "/"
	run_wp menu item add-custom "Primary Nav" "Blog" "/"
fi

run_wp option update benchmark_headless_seed_done 1

echo "Done. Next: ./plugins/wp-graphql-pqc/benchmark/scripts/build-persisted-urls.sh"

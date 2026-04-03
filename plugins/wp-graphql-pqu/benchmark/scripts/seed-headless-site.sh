#!/usr/bin/env bash
# Seed many posts, categories, tags, authors, Primary Nav menu, and assign taxonomy/authors.
# Intended for local wp-env (development WordPress). Run from repo root:
#   ./plugins/wp-graphql-pqu/benchmark/scripts/seed-headless-site.sh
#
# Idempotent: lists current counts and only runs wp * generate for the shortfall. Skips
# assignment unless something was generated or you pass --assign (or BENCH_FORCE_ASSIGN=1).
#
# Options:
#   --force   Do not fast-exit when counts, menu, and prior assign already satisfy targets.
#   --assign  Always run bench-assign-posts.php (even if no generate step ran).
#
# Environment (optional):
#   WP_ENV              wp-env target (default: cli) — maps to localhost:8888 dev site.
#   BENCH_POST_COUNT    default 1000
#   BENCH_CAT_COUNT     default 40
#   BENCH_TAG_COUNT     default 40
#   BENCH_AUTHOR_COUNT  default 40
#   BENCH_FORCE_ASSIGN  set to 1 to always run assignment (same as --assign)
#   WP_WORKDIR          monorepo root (auto-detected if unset)

set -euo pipefail

FORCE=0
FORCE_ASSIGN=0
for a in "$@"; do
	case "$a" in
		--force) FORCE=1 ;;
		--assign) FORCE_ASSIGN=1 ;;
	esac
done

if [[ "${BENCH_FORCE_ASSIGN:-0}" == "1" ]]; then
	FORCE_ASSIGN=1
fi

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

# Last line numeric only (avoids noise if WP-CLI prints notices).
wp_count() {
	local out
	out="$(run_wp "$@" 2>/dev/null | tail -1 | tr -d '[:space:]')"
	if [[ "$out" =~ ^[0-9]+$ ]]; then
		echo "$out"
	else
		echo 0
	fi
}

menu_primary_nav_exists() {
	run_wp term list nav_menu --slug=primary-nav --format=ids 2>/dev/null | grep -qE '^[0-9]+$'
}

assigned_flag() {
	# wp option get prints a trailing newline; strip so [[ "$(assigned_flag)" == "1" ]] works.
	run_wp option get benchmark_headless_assigned 2>/dev/null | tr -d '\r\n[:space:]' || true
}

echo "Using WP_WORKDIR=$WP_WORKDIR WP_ENV=$WP_ENV"
echo "Targets: posts>=${POST_COUNT}, categories>=${CAT_COUNT}, tags>=${TAG_COUNT}, authors(author)>=${AUTHOR_COUNT}"

CUR_POSTS="$(wp_count post list --post_type=post --post_status=publish --format=count)"
CUR_CATS="$(wp_count term list category --format=count)"
CUR_TAGS="$(wp_count term list post_tag --format=count)"
CUR_AUTHORS="$(wp_count user list --role=author --format=count)"

echo "Current: posts=${CUR_POSTS} categories=${CUR_CATS} tags=${CUR_TAGS} authors=${CUR_AUTHORS}"

NEED_CAT=$(( CAT_COUNT > CUR_CATS ? CAT_COUNT - CUR_CATS : 0 ))
NEED_TAG=$(( TAG_COUNT > CUR_TAGS ? TAG_COUNT - CUR_TAGS : 0 ))
NEED_AUTHOR=$(( AUTHOR_COUNT > CUR_AUTHORS ? AUTHOR_COUNT - CUR_AUTHORS : 0 ))
NEED_POST=$(( POST_COUNT > CUR_POSTS ? POST_COUNT - CUR_POSTS : 0 ))

DID_GENERATE=0

if [[ "$FORCE" -eq 0 ]] && [[ "$FORCE_ASSIGN" -eq 0 ]]; then
	MENU_OK=0
	if menu_primary_nav_exists; then
		MENU_OK=1
	fi
	ASSIGNED_OK=0
	if [[ "$(assigned_flag)" == "1" ]]; then
		ASSIGNED_OK=1
	fi
	if [[ "$NEED_CAT" -eq 0 && "$NEED_TAG" -eq 0 && "$NEED_AUTHOR" -eq 0 && "$NEED_POST" -eq 0 && "$MENU_OK" -eq 1 && "$ASSIGNED_OK" -eq 1 ]]; then
		echo "Nothing to do: counts meet targets, Primary Nav exists, assignment already ran (benchmark_headless_assigned=1)."
		echo "Use --force to bypass this check, --assign to re-run assignment only, or adjust BENCH_*_COUNT."
		exit 0
	fi
fi

run_wp plugin activate wp-graphql 2>/dev/null || true
run_wp plugin activate wp-graphql-smart-cache 2>/dev/null || true
run_wp plugin activate wp-graphql-pqu 2>/dev/null || true

run_wp rewrite structure /%postname%/ --hard 2>/dev/null || true
run_wp rewrite flush --hard 2>/dev/null || true

if [[ "$NEED_CAT" -gt 0 ]]; then
	echo "Generating ${NEED_CAT} new categories (have ${CUR_CATS}, target ${CAT_COUNT})..."
	run_wp term generate category --count="$NEED_CAT"
	DID_GENERATE=1
else
	echo "Skip category generate (already have ${CUR_CATS} >= ${CAT_COUNT})."
fi

if [[ "$NEED_TAG" -gt 0 ]]; then
	echo "Generating ${NEED_TAG} new post_tags (have ${CUR_TAGS}, target ${TAG_COUNT})..."
	run_wp term generate post_tag --count="$NEED_TAG"
	DID_GENERATE=1
else
	echo "Skip post_tag generate (already have ${CUR_TAGS} >= ${TAG_COUNT})."
fi

if [[ "$NEED_AUTHOR" -gt 0 ]]; then
	echo "Generating ${NEED_AUTHOR} new authors (have ${CUR_AUTHORS}, target ${AUTHOR_COUNT})..."
	run_wp user generate --count="$NEED_AUTHOR" --role=author
	DID_GENERATE=1
else
	echo "Skip user generate (already have ${CUR_AUTHORS} authors >= ${AUTHOR_COUNT})."
fi

if [[ "$NEED_POST" -gt 0 ]]; then
	echo "Generating ${NEED_POST} new posts (have ${CUR_POSTS}, target ${POST_COUNT})..."
	run_wp post generate --count="$NEED_POST" --post_status=publish
	DID_GENERATE=1
else
	echo "Skip post generate (already have ${CUR_POSTS} >= ${POST_COUNT})."
fi

CONTAINER_BENCH="/var/www/html/wp-content/plugins/wp-graphql-pqu/benchmark"
if [[ "$DID_GENERATE" -eq 1 ]] || [[ "$FORCE_ASSIGN" -eq 1 ]] || [[ "$(assigned_flag)" != "1" ]]; then
	echo "Running taxonomy + author assignment for published posts..."
	run_wp eval-file "$CONTAINER_BENCH/scripts/php/bench-assign-posts.php"
	run_wp option update benchmark_headless_assigned 1
else
	echo "Skip bench-assign-posts (benchmark_headless_assigned=1 and no new content; use --assign to re-run)."
fi

if menu_primary_nav_exists; then
	echo "Menu Primary Nav (slug primary-nav) already exists."
else
	run_wp menu create "Primary Nav"
	run_wp menu item add-custom "Primary Nav" "Home" "/"
	run_wp menu item add-custom "Primary Nav" "Blog" "/"
fi

run_wp option update benchmark_headless_seed_done 1

echo "Done. Next: ./plugins/wp-graphql-pqu/benchmark/scripts/build-persisted-urls.sh"

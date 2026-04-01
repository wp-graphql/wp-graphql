#!/usr/bin/env bash
# Emit variables JSONL + bulk-register each Faust-shaped template; merge paths for k6.
# Run from monorepo root (WP_WORKDIR auto-detected):
#   ./plugins/wp-graphql-pqc/benchmark/scripts/build-persisted-urls.sh
#
# Environment (optional):
#   WP_ENV              default cli
#   EDGE_BASE           e.g. http://localhost:8081 — passed to bulk-register for sample line
#   BENCH_* limits      see emit-headless-variables.php (BENCH_CAT_LIMIT, BENCH_URI_LIMIT, …)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BENCH_HOST="$(cd "$SCRIPT_DIR/.." && pwd)"

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

if [[ -n "${WP_WORKDIR:-}" ]]; then
	WP_WORKDIR="$(cd "$WP_WORKDIR" && pwd)"
elif WP_WORKDIR="$(detect_wp_workdir)"; then
	:
else
	echo "error: set WP_WORKDIR to monorepo root" >&2
	exit 1
fi

WP_ENV="${WP_ENV:-cli}"
CONTAINER_BENCH="/var/www/html/wp-content/plugins/wp-graphql-pqc/benchmark"
GEN_REL="k6/generated"
GEN_HOST="$BENCH_HOST/$GEN_REL"
mkdir -p "$GEN_HOST"

run_wp() {
	( cd "$WP_WORKDIR" && npm run wp-env -- run "$WP_ENV" -- wp "$@" )
}

emit() {
	local env_emit="$1"
	local outfile="$2"
	local raw n
	raw="$(mktemp)"
	(
		cd "$WP_WORKDIR"
		# npm prints "> wp-env" lines to stdout; only JSON lines belong in variables-jsonl.
		NPM_CONFIG_LOGLEVEL=silent npm run wp-env -- run "$WP_ENV" -- bash -lc '
			export BENCH_EMIT="'"$env_emit"'"
			export BENCH_POSTS_FIRST="'"${BENCH_POSTS_FIRST:-100}"'"
			export BENCH_CAT_LIMIT="'"${BENCH_CAT_LIMIT:-50}"'"
			export BENCH_TAG_LIMIT="'"${BENCH_TAG_LIMIT:-50}"'"
			export BENCH_USER_LIMIT="'"${BENCH_USER_LIMIT:-40}"'"
			export BENCH_URI_LIMIT="'"${BENCH_URI_LIMIT:-500}"'"
			wp eval-file "'"$CONTAINER_BENCH"'/scripts/php/emit-headless-variables.php"
		'
	) >"$raw"
	grep -E '^[[:space:]]*[\{\[]' "$raw" >"$GEN_HOST/$outfile" || true
	rm -f "$raw"
	n="$(wc -l < "$GEN_HOST/$outfile" | tr -d "[:space:]")"
	if [[ "${n:-0}" -eq 0 ]]; then
		echo "error: emit ${env_emit} produced no JSON lines in ${GEN_HOST}/${outfile} (empty taxonomy? wp-env failed?)." >&2
		exit 1
	fi
	echo "Wrote $GEN_HOST/$outfile ($n JSON line(s))"
}

emit front front.jsonl
emit blog blog.jsonl
emit category category.jsonl
emit tag tag.jsonl
emit user user.jsonl
emit uri uri.jsonl

bulk() {
	local gql_file="$1"
	local jsonl="$2"
	local urls_out="$3"
	local -a extra=( )
	if [[ -n "${EDGE_BASE:-}" ]]; then
		extra=( --edge-base="$EDGE_BASE" )
	fi
	# With set -u, "${extra[@]}" is unbound when extra is empty; use ${arr[@]+...} guard.
	run_wp graphql-pqc bulk-register "$CONTAINER_BENCH/graphql/$gql_file" \
		--variables-jsonl="$CONTAINER_BENCH/$GEN_REL/$jsonl" \
		--urls-out="$CONTAINER_BENCH/$GEN_REL/$urls_out" \
		${extra[@]+"${extra[@]}"}
}

bulk front-page-nav.graphql front.jsonl urls-step-front.txt
bulk posts-blog.graphql blog.jsonl urls-step-blog.txt
bulk category-archive.graphql category.jsonl urls-step-category.txt
bulk tag-archive.graphql tag.jsonl urls-step-tag.txt
bulk author-archive.graphql user.jsonl urls-step-author.txt
bulk singular-by-uri.graphql uri.jsonl urls-step-singular.txt

OUT_LIST="$BENCH_HOST/k6/urls-headless-day.txt"
: >"$OUT_LIST"
shopt -s nullglob
for f in "$GEN_HOST"/urls-step-*.txt; do
	[[ -f "$f" ]] || continue
	cat "$f" >>"$OUT_LIST"
done
shopt -u nullglob

if ! [[ -s "$OUT_LIST" ]]; then
	echo "error: no URLs collected; check bulk-register output and JSONL files." >&2
	exit 1
fi

TMP_SORT="$(mktemp)"
sort -R "$OUT_LIST" >"$TMP_SORT" && mv "$TMP_SORT" "$OUT_LIST"
LINE_COUNT="$(grep -cve '^[[:space:]]*$' "$OUT_LIST" || true)"

MANIFEST="$GEN_HOST/build-manifest.json"
GIT_SHA="$(git -C "$WP_WORKDIR" rev-parse --short HEAD 2>/dev/null || echo unknown)"
cat >"$MANIFEST" <<JSON
{
  "generated_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "git_commit": "$GIT_SHA",
  "urls_file": "k6/urls-headless-day.txt",
  "approx_line_count": $LINE_COUNT,
  "variables_jsonl": ["front.jsonl", "blog.jsonl", "category.jsonl", "tag.jsonl", "user.jsonl", "uri.jsonl"],
  "templates": [
    "front-page-nav.graphql",
    "posts-blog.graphql",
    "category-archive.graphql",
    "tag-archive.graphql",
    "author-archive.graphql",
    "singular-by-uri.graphql"
  ]
}
JSON

echo "Merged shuffled URLs -> $OUT_LIST ($LINE_COUNT paths). Manifest: $MANIFEST"

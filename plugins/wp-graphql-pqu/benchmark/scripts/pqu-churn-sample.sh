#!/usr/bin/env bash
# Sample churn loop: touch a post (Smart Cache purge) and HEAD a persisted URL on the edge.
# Run from a context where `wp` hits your bench WordPress (e.g. inside wp-env cli).
#
# Usage:
#   ./pqu-churn-sample.sh [EDGE_BASE] [PERSISTED_PATH] [POST_ID] [SLEEP_SEC]
#
# Examples (host has curl; WordPress via wp-env from repo root):
#   export WP_BIN='npm run wp-env -- run cli -- wp'
#   ./plugins/wp-graphql-pqu/benchmark/scripts/pqu-churn-sample.sh \
#     http://localhost:8081 \
#     /graphql/persisted/YOUR_HASH/variables/VARS_HASH \
#     10 \
#     15
#
# If WP_BIN is unset, uses `wp` (must be on PATH).

set -euo pipefail

EDGE_BASE="${1:-http://localhost:8081}"
PERSISTED_PATH="${2:?Usage: $0 EDGE_BASE PERSISTED_PATH POST_ID [SLEEP_SEC]}"
POST_ID="${3:?}"
SLEEP_SEC="${4:-20}"

WP_BIN="${WP_BIN:-wp}"

edge_url="${EDGE_BASE%/}${PERSISTED_PATH}"

echo "Edge:    $edge_url"
echo "Post:    $POST_ID"
echo "WP:      $WP_BIN"
echo "Sleep:   ${SLEEP_SEC}s between rounds (Ctrl+C to stop)"
echo ""

round=0
while true; do
  round=$((round + 1))
  echo "=== Round $round ==="

  # Bump modified time so save hooks run (title tweak keeps it obvious in logs).
  $WP_BIN post update "$POST_ID" --post_title="churn-${round}-$(date +%s)" >/dev/null

  sleep 2

  echo "HEAD (expect X-Cache: MISS or low hit count right after purge):"
  curl -sI "$edge_url" | grep -iE '^(HTTP/|x-cache)' || true

  echo ""
  sleep "$SLEEP_SEC"
done

#!/usr/bin/env bash
# =============================================================================
# LOCAL ONLY: Run wp-graphql-acf JS E2E tests the same way CI does
# =============================================================================
#
# This script is for local development and debugging only. It is NOT used by
# GitHub Actions; CI runs the same steps via .github/workflows/js-e2e-tests.yml
# and js-e2e-tests-reusable.yml.
#
# What it does (from repo root):
#   1. npm ci
#   2. Build wp-graphql and plugins
#   3. Start wp-env (dev :8888, test :8889)
#   4. Wait for test site at localhost:8889
#   5. Install ACF/Extended via install-test-deps (respects env below)
#   6. Run Playwright E2E for wp-graphql-acf
#   7. Stop wp-env
#
# Environment (optional; controls ACF variant and test skips):
#   INSTALL_ACF_PRO         'true' = ACF Pro, 'false' = ACF Free (default: unset)
#   INSTALL_ACF_EXTENDED_PRO 'true' = ACF Extended Pro when using ACF Pro (default: unset)
#   ACF_LICENSE_KEY         From env or plugins/wp-graphql-acf/.env (needed for Pro)
#   ACF_EXTENDED_LICENSE_KEY From env or .env (needed for Extended Pro)
#
# Examples (run from repo root):
#   ./bin/run-acf-e2e-local.sh
#   INSTALL_ACF_PRO=false INSTALL_ACF_EXTENDED_PRO=false ./bin/run-acf-e2e-local.sh   # ACF Free
#   INSTALL_ACF_PRO=true  INSTALL_ACF_EXTENDED_PRO=false ./bin/run-acf-e2e-local.sh   # ACF Pro + Extended Free
#   INSTALL_ACF_PRO=true  INSTALL_ACF_EXTENDED_PRO=true  ./bin/run-acf-e2e-local.sh   # ACF Pro + Extended Pro
# =============================================================================

set -e

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

echo "=== 1. npm ci ==="
npm ci

echo "=== 2. Build wp-graphql ==="
npm run build -w @wpgraphql/wp-graphql

echo "=== 3. Build plugins ==="
npm run build -- --filter='./plugins/*'

echo "=== 4. Start wp-env ==="
npm run wp-env start

echo "=== 5. Wait for WordPress test site to be ready (localhost:8889) ==="
for i in $(seq 1 30); do
  if curl -f -s "http://localhost:8889/wp-admin/install.php" > /dev/null 2>&1 || curl -f -s "http://localhost:8889/wp-admin" > /dev/null 2>&1; then
    echo "WordPress test site is ready."
    break
  fi
  echo "Waiting for WordPress test site... ($i/30)"
  sleep 2
done
curl -f -s "http://localhost:8889/wp-admin/install.php" > /dev/null || curl -f -s "http://localhost:8889/wp-admin" > /dev/null || { echo "WordPress test site did not become ready at :8889."; exit 1; }

echo "=== 6. Install plugin test deps (ACF/Extended) ==="
( cd plugins/wp-graphql-acf && npm run install-test-deps )

echo "=== 7. Run wp-graphql-acf E2E tests ==="
npm run -w @wpgraphql/wp-graphql-acf test:e2e

echo "=== 8. Stop wp-env ==="
npm run wp-env stop

echo "Done."

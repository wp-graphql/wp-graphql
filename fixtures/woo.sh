#!/bin/zsh
# Group D fixtures: WooCommerce 10.4.3 + WooGraphQL 1.0.3. Run through bin/setup.sh woo.
# Creates the products with WooCommerce's PHP API (fixtures/woo-products.php) and writes their IDs
# to fixtures/woo.ids.
source "$(dirname "$0")/../bin/lib.sh"
F=$ROOT/fixtures
wp_php "$F/woo-products.php" | grep -E '^[A-Z0-9_]+=[0-9]+$' > "$F/woo.ids"
cat "$F/woo.ids"

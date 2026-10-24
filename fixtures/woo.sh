#!/bin/zsh
# Group D fixtures: WooCommerce 10.4.3 + WooGraphQL 1.0.3.
# Note: on our site `wp wc product create --name=...` left every product titled "Product" (and the
# variations unnamed), so we renamed them afterwards to the names below. Do the same if you compare titles.
# WP-CLI against the test site. Set WP to override (it can include arguments), e.g. WP="wp --path=/srv/site".
W=(${=WP:-wp}); F=$(cd "$(dirname "$0")" && pwd); IDS=$F/woo.ids; : > $IDS
rec() { echo "$1=$3" >> $IDS; echo "$2 $3 woo:$1" >> $F/created-ids.txt; }
P2=$($W wc product create --user=1 --name="[ext-matrix] Probe Target P2" --type=simple --regular_price=20 --status=publish --porcelain); rec P2 post $P2
P1=$($W wc product create --user=1 --name="[ext-matrix] Shortcode Host P1" --type=simple --regular_price=10 --status=publish \
  --description="<!-- wp:post-title {\"level\":2} /-->
[product_page id=\"$P2\"]" --porcelain); rec P1 post $P1
P3=$($W wc product create --user=1 --name="[ext-matrix] Loop Host P3" --type=simple --regular_price=30 --status=publish \
  --description="[products ids=\"$P2\"]
<!-- wp:post-title {\"level\":2} /-->" --porcelain); rec P3 post $P3
ATTR=$($W wc product_attribute create --user=1 --name="[ext-matrix] Color" --slug=extmx-color --porcelain); rec ATTR wc_attribute $ATTR
$W wc product_attribute_term create "$ATTR" --user=1 --name=Red
$W wc product_attribute_term create "$ATTR" --user=1 --name=Blue
V=$($W wc product create --user=1 --name="[ext-matrix] Variable V" --type=variable --status=publish \
  --description='<!-- wp:post-title {"level":2} /-->' \
  --attributes="[{\"id\":$ATTR,\"variation\":true,\"visible\":true,\"options\":[\"Red\",\"Blue\"]}]" --porcelain); rec V post $V
V1=$($W wc product_variation create "$V" --user=1 --regular_price=11 --attributes="[{\"id\":$ATTR,\"option\":\"Red\"}]" --porcelain); rec V1 post $V1
V2=$($W wc product_variation create "$V" --user=1 --regular_price=12 --attributes="[{\"id\":$ATTR,\"option\":\"Blue\"}]" --porcelain); rec V2 post $V2
$W wc product update "$V" --user=1 --upsell_ids="[$P2]"
cat $IDS
# (added) the `wp wc product_attribute create` route 404'd on this site, so the attribute, its two
# terms, V's attribute and the variations' attributes were set with woo-attr.php instead:
#   cp woo-attr.php into wp-content, then
#   wp-env run cli -- env EXTMX_V=$V EXTMX_V1=$V1 EXTMX_V2=$V2 wp eval-file /var/www/html/wp-content/extmx-woo-attr.php
#   (prints ATTR=1 RED=252 BLUE=253), then remove the copied file.

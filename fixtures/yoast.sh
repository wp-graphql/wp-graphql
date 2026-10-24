#!/bin/zsh
# Group B fixtures: Yoast SEO + WPGraphQL for Yoast. CPTs book/movie + taxonomy genre are registered by the probe mu-plugin.
# WP-CLI against the test site. Set WP to override (it can include arguments), e.g. WP="wp --path=/srv/site".
W=(${=WP:-wp}); F=$(cd "$(dirname "$0")" && pwd); IDS=$F/yoast.ids; : > $IDS
rec() { echo "$1=$3" >> $IDS; echo "$2 $3 yoast:$1" >> $F/created-ids.txt; }
ALICE=$($W user create extmx-alice extmx-alice@example.com --role=author --display_name="[ext-matrix] Alice Author" --porcelain); rec ALICE user $ALICE
BOB=$($W user create extmx-bob extmx-bob@example.com --role=author --display_name="[ext-matrix] Bob Builder" --porcelain); rec BOB user $BOB
IMG1=$($W media import /tmp/extmx.png --title="[ext-matrix] Image One" --porcelain); rec IMG1 post $IMG1
IMG2=$($W media import /tmp/extmx.png --title="[ext-matrix] Image Two" --porcelain); rec IMG2 post $IMG2
CAT=$($W term create category "[ext-matrix] News Y" --slug=extmx-news-y --porcelain); rec CAT term $CAT
CAT2=$($W term create category "[ext-matrix] Sports Y" --slug=extmx-sports-y --porcelain); rec CAT2 term $CAT2
G1=$($W term create genre "[ext-matrix] Fantasy" --slug=extmx-fantasy --porcelain); rec G1 term $G1
P1=$($W post create --post_type=post --post_title="[ext-matrix] Alice Post" --post_author=$ALICE --post_status=publish --porcelain); rec P1 post $P1
P2=$($W post create --post_type=post --post_title="[ext-matrix] Bob Post" --post_author=$BOB --post_status=publish --porcelain); rec P2 post $P2
$W post meta set $P1 _thumbnail_id $IMG1
$W post meta set $P2 _thumbnail_id $IMG2
$W post term set $P1 category $CAT $CAT2 --by=id
$W post meta set $P1 _yoast_wpseo_primary_category $CAT2
BOOK1=$($W post create --post_type=book --post_title="[ext-matrix] Book One" --post_author=$ALICE --post_status=publish --porcelain); rec BOOK1 post $BOOK1
MOVIE1=$($W post create --post_type=movie --post_title="[ext-matrix] Movie One" --post_author=$BOB --post_status=publish --porcelain); rec MOVIE1 post $MOVIE1
$W post meta set $BOOK1 _thumbnail_id $IMG2
$W post term set $BOOK1 genre $G1 --by=id
FRONT=$($W post create --post_type=page --post_title="[ext-matrix] Home Page" --post_status=publish --porcelain); rec FRONT post $FRONT
BLOG=$($W post create --post_type=page --post_title="[ext-matrix] Blog" --post_status=publish --porcelain); rec BLOG post $BLOG
PARENT=$($W post create --post_type=page --post_title="[ext-matrix] Parent" --post_status=publish --porcelain); rec PARENT post $PARENT
CHILD=$($W post create --post_type=page --post_title="[ext-matrix] Child" --post_parent=$PARENT --post_status=publish --porcelain); rec CHILD post $CHILD
# Reading settings: restore from reading-settings.orig at the end (show_on_front=posts, page_on_front=0, page_for_posts=0)
$W option update show_on_front page
$W option update page_on_front $FRONT
$W option update page_for_posts $BLOG
$W option get wpseo_titles --format=json > $F/wpseo_titles.orig.json
$W option patch update wpseo_titles breadcrumbs-enable true --format=json
$W option patch update wpseo_titles breadcrumbs-display-blog-page true --format=json
$W rewrite flush
$W yoast index --reindex --skip-confirmation
cat $IDS

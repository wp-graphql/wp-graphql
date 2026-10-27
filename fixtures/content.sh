#!/bin/zsh
# Group A fixtures: Content Blocks + Faust + ACF canaries. Run through bin/setup.sh.
# WP-CLI against the test site. Set WP to override (it can include arguments), e.g. WP="wp --path=/srv/site".
W=(${=WP:-wp})
F=$(cd "$(dirname "$0")" && pwd)
IDS=$F/content.ids
: > $IDS
rec() { echo "$1=$3" >> $IDS; }

# ---- Content Blocks ----
HOSTCAT=$($W term create category "[ext-matrix] Host Cat" --slug=extmx-host-cat --porcelain); rec HOSTCAT term $HOSTCAT
LOOPCAT=$($W term create category "[ext-matrix] Loop Cat" --slug=extmx-loop-cat --porcelain); rec LOOPCAT term $LOOPCAT
CAT2=$($W term create category "[ext-matrix] Cat Two" --slug=extmx-cat-two --porcelain); rec CAT2 term $CAT2
CAT3=$($W term create category "[ext-matrix] Cat Three" --slug=extmx-cat-three --porcelain); rec CAT3 term $CAT3
LA=$($W post create --post_title="[ext-matrix] Loop A" --post_status=publish --post_date="2000-01-01 00:00:00" --post_category=$LOOPCAT --porcelain); rec LA post $LA
LB=$($W post create --post_title="[ext-matrix] Loop B" --post_status=publish --post_date="2000-01-02 00:00:00" --post_category=$LOOPCAT --porcelain); rec LB post $LB
SYN=$($W post create --post_type=wp_block --post_title="[ext-matrix] Synced Title Pattern" --post_status=publish --post_content='<!-- wp:post-title {"level":5} /-->' --porcelain); rec SYN post $SYN
P1C=$(cat <<HTML
<!-- wp:post-title {"level":2} /-->
<!-- wp:post-terms {"term":"category"} /-->
<!-- wp:query {"queryId":1,"query":{"perPage":2,"pages":0,"offset":0,"postType":"post","order":"asc","orderBy":"date","inherit":false}} -->
<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div>
<!-- /wp:query -->
<!-- wp:post-title {"level":3} /-->
<!-- wp:post-terms {"term":"category"} /-->
<!-- wp:block {"ref":$SYN} /-->
<!-- wp:latest-posts {"postsToShow":2} /-->
<!-- wp:post-title {"level":4} /-->
HTML
)
P1=$($W post create --post_title="[ext-matrix] Host P1" --post_status=publish --post_date="2001-01-01 00:00:00" --post_category=$HOSTCAT --post_content="$P1C" --porcelain); rec P1 post $P1
P2=$($W post create --post_title="[ext-matrix] Second P2" --post_status=publish --post_date="2001-01-02 00:00:00" --post_category=$CAT2 --post_content='<!-- wp:post-title {"level":2} /--><!-- wp:post-terms {"term":"category"} /-->' --porcelain); rec P2 post $P2
P3=$($W post create --post_title="[ext-matrix] Third P3" --post_status=publish --post_date="2001-01-03 00:00:00" --post_category=$CAT3 --post_content='<!-- wp:post-title {"level":2} /--><!-- wp:post-terms {"term":"category"} /-->' --porcelain); rec P3 post $P3

# ---- Faust ----
FU=$($W user create extmx-fauthor extmx-fauthor@example.com --role=author --display_name="[ext-matrix] F Author" --porcelain); rec FU user $FU
FA=$($W post create --post_title='[ext-matrix] Alpha' --post_name=extmx-alpha --post_status=publish --post_author=$FU --post_content='<a href="http://localhost:8888/extmx-beta/">beta</a> <img src="http://localhost:8888/wp-content/uploads/x.png">' --porcelain); rec FA post $FA
FB=$($W post create --post_title='[ext-matrix] Beta' --post_name=extmx-beta --post_status=publish --porcelain); rec FB post $FB
FC=$($W post create --post_title='[ext-matrix] Gamma' --post_name=extmx-gamma --post_status=publish --porcelain); rec FC post $FC
FPAGE=$($W post create --post_type=page --post_title='[ext-matrix] About' --post_name=extmx-about --post_status=publish --porcelain); rec FPAGE post $FPAGE
FCAT1=$($W term create category "[ext-matrix] News" --slug=extmx-news --porcelain); rec FCAT1 term $FCAT1
FCAT2=$($W term create category "[ext-matrix] Events" --slug=extmx-events --porcelain); rec FCAT2 term $FCAT2
FCAT3=$($W term create category "[ext-matrix] Guides" --slug=extmx-guides --porcelain); rec FCAT3 term $FCAT3
$W post term set $FA category $FCAT1 $FCAT2 $FCAT3 --by=id
FMEDIA=$($W media import /tmp/extmx.png --post_id=$FA --title='[ext-matrix] Alpha image' --featured_image --porcelain); rec FMEDIA post $FMEDIA
$W option update sticky_posts "[$FB]" --format=json
$W option patch insert faustwp_settings frontend_uri http://localhost:3000

# ---- ACF canaries (field groups are local, in the probe mu-plugin) ----
AP1=$($W post create --post_title='[ext-matrix] ACF Post One' --post_status=publish --post_date="2001-02-01 00:00:00" --porcelain); rec AP1 post $AP1
AP2=$($W post create --post_title='[ext-matrix] ACF Post Two' --post_status=publish --post_date="2001-02-02 00:00:00" --porcelain); rec AP2 post $AP2
AP3=$($W post create --post_title='[ext-matrix] ACF Post Three (no images)' --post_status=publish --post_date="2001-02-03 00:00:00" --porcelain); rec AP3 post $AP3
AA1=$($W media import /tmp/extmx.png --post_id=$AP1 --title='[ext-matrix] P1 image A' --porcelain); rec AA1 post $AA1
AA2=$($W media import /tmp/extmx.png --post_id=$AP1 --title='[ext-matrix] P1 image B' --porcelain); rec AA2 post $AA2
AA3=$($W media import /tmp/extmx.png --post_id=$AP2 --title='[ext-matrix] P2 image' --porcelain); rec AA3 post $AA3
AAU=$($W media import /tmp/extmx.png --title='[ext-matrix] Unattached image' --porcelain); rec AAU post $AAU
ACAT=$($W term create category '[ext-matrix] ACF Cat' --slug=extmx-acf-cat --porcelain); rec ACAT term $ACAT
$W post term set $AP1 category $ACAT --by=id
$W post term set $AP2 category $ACAT --by=id
AU=$($W user create extmx-acfauthor extmx-acfauthor@example.com --role=author --display_name="[ext-matrix] ACF Author" --porcelain); rec AU user $AU
$W post update $AP1 --post_author=$AU
for P in $AP1 $AP2 $AP3; do
  $W eval "update_field( 'field_extmx_story_body', \"<p>Post $P</p>\n[gallery]\", $P ); update_field( 'field_extmx_story_rows', [ [ 'field_extmx_story_row_body' => '[gallery]' ] ], $P );"
done
$W eval "update_field( 'field_extmx_term_body', '[gallery]', 'term_$ACAT' ); update_field( 'field_extmx_user_body', '[gallery]', 'user_$AU' );"
$W eval "update_field( 'field_extmx_site_body', \"<p>Options</p>\n[gallery]\", 'options' );"
cat $IDS
# (added) give the Faust media item a description so MediaItem.description renders through the_content
$W post update $FMEDIA --post_content='[ext-matrix] media description'

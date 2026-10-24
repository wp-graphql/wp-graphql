#!/bin/zsh
# Group C fixtures: Rank Math SEO 1.0.280 + WPGraphQL for Rank Math 0.3.4 (Redirections module stays OFF).
# WP-CLI against the test site. Set WP to override (it can include arguments), e.g. WP="wp --path=/srv/site".
W=(${=WP:-wp}); F=$(cd "$(dirname "$0")" && pwd); IDS=$F/rankmath.ids; : > $IDS
rec() { echo "$1=$3" >> $IDS; echo "$2 $3 rankmath:$1" >> $F/created-ids.txt; }
$W option add rank_math_registration_skip 1   # same as the wizard "skip" button; without it Rank Math never builds its variables manager
$W option get rank-math-options-general --format=json > $F/rank-math-options-general.orig.json
$W option get rank_math_modules --format=json > $F/rank_math_modules.orig.json
$W option patch update rank-math-options-general breadcrumbs on
$W eval 'update_option("rank_math_modules", array_values(array_diff(array_unique(array_merge((array) get_option("rank_math_modules", []), ["rich-snippet"])), ["redirections"])));'
C0=$($W term create category "[ext-matrix] Parent Cat" --slug=extmx-parent-cat --porcelain); rec C0 term $C0
C1=$($W term create category "[ext-matrix] Child Cat" --slug=extmx-child-cat --parent=$C0 --porcelain); rec C1 term $C1
$W term meta update $C1 rank_math_title "[ext-matrix] Child Cat SEO Title"
$W term meta update $C1 rank_math_description "Child cat description"
U1=$($W user create extmx-rmauthor extmx-rmauthor@example.com --role=author --display_name="[ext-matrix] RM Author" --porcelain); rec U1 user $U1
$W user meta update $U1 rank_math_title "[ext-matrix] RM Author SEO Title"
$W user meta update $U1 twitter extmxrmauthor
P1=$($W post create --post_title="[ext-matrix] RM Alpha" --post_status=publish --post_author=$U1 --post_category=$C1 --post_content='<!-- wp:paragraph --><p>Alpha body</p><!-- /wp:paragraph -->' --porcelain); rec P1 post $P1
P2=$($W post create --post_title="[ext-matrix] RM Beta" --post_status=publish --post_author=1 --post_content='<p>Beta body</p>' --porcelain); rec P2 post $P2
P3=$($W post create --post_title="[ext-matrix] RM Gamma" --post_status=publish --post_author=1 --post_content='<p>Gamma body</p>' --porcelain); rec P3 post $P3
for X in "$P1:Alpha:81" "$P2:Beta:42" "$P3:Gamma:12"; do
  ID=${X%%:*}; R=${X#*:}; T=${R%%:*}; SC=${R#*:}
  $W post meta update $ID rank_math_title "[ext-matrix] $T SEO Title"
  $W post meta update $ID rank_math_description "$T meta description"
  $W post meta update $ID rank_math_focus_keyword "$(echo $T | tr A-Z a-z),kw"
  $W post meta update $ID rank_math_breadcrumb_title "$T Crumb"
  $W post meta update $ID rank_math_seo_score $SC
done
$W post meta update $P1 rank_math_pillar_content on
PGP=$($W post create --post_type=page --post_title="[ext-matrix] RM Parent Page" --post_status=publish --porcelain); rec PGP post $PGP
PGC=$($W post create --post_type=page --post_title="[ext-matrix] RM Child Page" --post_parent=$PGP --post_status=publish --porcelain); rec PGC post $PGC
$W rewrite flush
cat $IDS

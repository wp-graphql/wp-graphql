#!/bin/zsh
# Group F (smoke) fixtures: Polylang 3.9.1 + wp-graphql-polylang 0.7.1 (stable branch zip).
# Reduced vs the matrix file: no theme switch, no static front page, no menus (site settings stay untouched),
# no createPost mutation. Adding languages makes Polylang create per-language default categories;
# those are recorded by diffing term ids before/after.
# WP-CLI against the test site. Set WP to override (it can include arguments), e.g. WP="wp --path=/srv/site".
W=(${=WP:-wp}); F=$(cd "$(dirname "$0")" && pwd); IDS=$F/polylang.ids; : > $IDS
rec() { echo "$1=$3" >> $IDS; echo "$2 $3 polylang:$1" >> $F/created-ids.txt; }
$W eval 'foreach ([["en_US","en","English",0],["fi","fi","Suomi",1],["de_DE","de","Deutsch",2]] as $l) { $r = PLL()->model->languages->add(["locale"=>$l[0],"slug"=>$l[1],"name"=>$l[2],"term_group"=>$l[3]]); if (is_wp_error($r)) { echo $r->get_error_message(), "\n"; } }'
for N in 1 2 3; do
  EN=$($W post create --post_title="[ext-matrix] PLL Post $N EN" --post_status=publish --porcelain); rec EN$N post $EN
  FI=$($W post create --post_title="[ext-matrix] PLL Post $N FI" --post_status=publish --porcelain); rec FI$N post $FI
  DE=$($W post create --post_title="[ext-matrix] PLL Post $N DE" --post_status=publish --porcelain); rec DE$N post $DE
  $W eval "pll_set_post_language($EN,'en'); pll_set_post_language($FI,'fi'); pll_set_post_language($DE,'de'); pll_save_post_translations(['en'=>$EN,'fi'=>$FI,'de'=>$DE]);"
done
CAT_EN=$($W term create category "[ext-matrix] News PLL" --slug=extmx-news-pll --porcelain); rec CAT_EN term $CAT_EN
CAT_FI=$($W term create category "[ext-matrix] Uutiset PLL" --slug=extmx-uutiset-pll --porcelain); rec CAT_FI term $CAT_FI
$W eval "pll_set_term_language($CAT_EN,'en'); pll_set_term_language($CAT_FI,'fi'); pll_save_term_translations(['en'=>$CAT_EN,'fi'=>$CAT_FI]);"
EN1=$(grep '^EN1=' $IDS | cut -d= -f2); FI1=$(grep '^FI1=' $IDS | cut -d= -f2)
$W post term set "$EN1" category "$CAT_EN" --by=id
$W post term set "$FI1" category "$CAT_FI" --by=id
$W eval '$mo = new PLL_MO(); $mo->add_entry(new Translation_Entry(["singular"=>"Read more","translations"=>["Lue lisää"]])); $mo->export_to_db(PLL()->model->get_language("fi"));'
cat $IDS

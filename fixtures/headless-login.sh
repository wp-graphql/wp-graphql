#!/bin/zsh
# Group G (smoke) fixtures: WPGraphQL Headless Login 0.4.4, Password provider enabled.
# WP-CLI against the test site. Set WP to override (it can include arguments), e.g. WP="wp --path=/srv/site".
W=(${=WP:-wp}); F=$(cd "$(dirname "$0")" && pwd); IDS=$F/headless-login.ids; : > $IDS
rec() { echo "$1=$3" >> $IDS; echo "$2 $3 headless-login:$1" >> $F/created-ids.txt; }
$W option update wpgraphql_login_provider_password '{"name":"Password","order":0,"isEnabled":true,"clientOptions":[],"loginOptions":[]}' --format=json   # delete at the end (did not exist before)
U=$($W user create extmx-hluser extmx-hluser@example.com --user_pass='hl-pass-123' --role=author --display_name="[ext-matrix] HL User" --porcelain); rec U user $U
P1=$($W post create --post_title='[ext-matrix] HL post' --post_status=publish --post_author=$U --porcelain); rec P1 post $P1
cat $IDS

#!/bin/zsh
# Shared helpers for setup.sh, teardown.sh and run.sh. Sourced, not run directly.
#
# Everything talks to the test site through WP-CLI. Set WP to the command that runs WP-CLI
# against it (it can include arguments), for example:
#   WP="wp --path=/srv/site"
#   WP="npx wp-env run cli -- wp"
# Files are placed on the site with WP-CLI too, so this works when WP-CLI runs in a container.

ROOT=$(cd "$(dirname "$0")/.." && pwd)
W=(${=WP:-wp})
STATE=$ROOT/.state
mkdir -p $STATE

# The plugins each group activates: "slug|source", where source is a wp.org version or a zip URL.
typeset -A GROUP_PLUGINS
GROUP_PLUGINS[content]="wp-graphql-content-blocks|https://github.com/wpengine/wp-graphql-content-blocks/releases/download/v4.8.9/wp-graphql-content-blocks.zip faustwp|1.9.0"
GROUP_PLUGINS[yoast]="wordpress-seo|28.7 add-wpgraphql-seo|5.1.0"
GROUP_PLUGINS[rankmath]="seo-by-rank-math|1.0.280 wp-graphql-rank-math|https://github.com/AxeWP/wp-graphql-rank-math/releases/download/0.3.4/wp-graphql-rank-math.zip"
GROUP_PLUGINS[woo]="woocommerce|10.4.3 wp-graphql-woocommerce|https://github.com/wp-graphql/wp-graphql-woocommerce/releases/download/v1.0.3/wp-graphql-woocommerce.zip"
GROUP_PLUGINS[polylang]="polylang|3.9.1 wp-graphql-polylang|https://github.com/valu-digital/wp-graphql-polylang/archive/refs/heads/stable.zip"
GROUP_PLUGINS[headless-login]="wp-graphql-headless-login|https://github.com/AxeWP/wp-graphql-headless-login/releases/download/0.4.4/wp-graphql-headless-login.zip"

# The options each group's fixtures change, saved before and restored after.
typeset -A GROUP_OPTIONS
GROUP_OPTIONS[content]="sticky_posts faustwp_settings options_extmx_site_body _options_extmx_site_body"
GROUP_OPTIONS[yoast]="show_on_front page_on_front page_for_posts wpseo_titles"
GROUP_OPTIONS[rankmath]="rank_math_registration_skip rank-math-options-general rank_math_modules"
GROUP_OPTIONS[woo]=""
GROUP_OPTIONS[polylang]=""
GROUP_OPTIONS[headless-login]="wpgraphql_login_provider_password"

# The query directories each group's fixtures serve, and the ids file they use.
typeset -A GROUP_QUERIES
GROUP_QUERIES[content]="content-blocks faust acf"
GROUP_QUERIES[yoast]="yoast"
GROUP_QUERIES[rankmath]="rankmath"
GROUP_QUERIES[woo]="woo"
GROUP_QUERIES[polylang]="polylang"
GROUP_QUERIES[headless-login]="headless-login"

# Groups that can't be active together (both register the same fields).
typeset -A GROUP_CONFLICTS
GROUP_CONFLICTS[yoast]="seo-by-rank-math"
GROUP_CONFLICTS[rankmath]="wordpress-seo"

require_group() {
	if [[ -z "$1" || -z "${GROUP_QUERIES[$1]+set}" ]]; then
		echo "Unknown group '$1'. Groups: ${(k)GROUP_QUERIES}" >&2
		exit 1
	fi
}

# Runs a PHP file (without its opening <?php line) on the site, with an optional PHP prefix that
# defines variables for it.
wp_php() {
	local file=$1 prefix=${2:-}
	$W eval "${prefix} eval( base64_decode( '$(sed '1d' $file | base64 | tr -d '\n')' ) );"
}

install_probe() {
	$W eval "wp_mkdir_p( WPMU_PLUGIN_DIR ); file_put_contents( WPMU_PLUGIN_DIR . '/zz-ext-matrix-probe.php', base64_decode( '$(base64 < $ROOT/probe/zz-ext-matrix-probe.php | tr -d '\n')' ) ); echo \"probe installed\\n\";"
}

remove_probe() {
	$W eval "@unlink( WPMU_PLUGIN_DIR . '/zz-ext-matrix-probe.php' ); @unlink( '/tmp/extmx.png' ); echo \"probe removed\\n\";"
}

# Saves an option's current value (or that it doesn't exist) so restore_option can put it back.
save_option() {
	local group=$1 name=$2 file=$STATE/$1.$2.json
	[[ -f $file ]] && return 0 # Keep the first saved value if setup runs twice.
	if $W option get "$name" --format=json > $file.tmp 2>/dev/null; then
		mv $file.tmp $file
	else
		rm -f $file.tmp
		echo "__missing__" > $file
	fi
}

restore_option() {
	local group=$1 name=$2 file=$STATE/$1.$2.json
	[[ -f $file ]] || return 0
	if [[ "$(cat $file)" == "__missing__" ]]; then
		$W option delete "$name" >/dev/null 2>&1
	else
		$W option update "$name" "$(cat $file)" --format=json >/dev/null
	fi
	rm -f $file
}

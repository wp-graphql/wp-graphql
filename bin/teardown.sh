#!/bin/zsh
# Usage: WP="<wp-cli command>" zsh bin/teardown.sh <group> [--keep-plugins]
#
# Undoes bin/setup.sh <group>: deletes the fixtures, restores the options the fixtures changed,
# removes the probe mu-plugin, and deactivates the group's plugins (pass --keep-plugins to leave
# them active).
#
# Fixtures are found by their markers, never by ID, so this only deletes content setup created:
# posts, media and products titled "[ext-matrix] ...", terms named "[ext-matrix] ..." or with an
# "extmx-" slug, and users whose login starts with "extmx-". Content plugins create on their own
# (WooCommerce's shop pages, Polylang's languages and translations, plugin options and tables) is
# left in place and listed at the end; use a disposable site if you need a clean slate.

source "$(dirname "$0")/lib.sh"
GROUP=$1
require_group "$GROUP"
KEEP_PLUGINS=${2:-}

$W eval '
$deleted = [ "posts" => 0, "terms" => 0, "users" => 0 ];

// Posts of every type, including media, products and variations.
$ids = get_posts( [
	"post_type"        => array_values( get_post_types() ),
	"post_status"      => "any",
	"posts_per_page"   => -1,
	"fields"           => "ids",
	"suppress_filters" => true,
] );
foreach ( $ids as $id ) {
	if ( 0 === strpos( get_the_title( $id ), "[ext-matrix]" ) || 0 === strpos( (string) get_post_field( "post_title", $id ), "[ext-matrix]" ) ) {
		if ( "attachment" === get_post_type( $id ) ) {
			wp_delete_attachment( $id, true );
		} else {
			wp_delete_post( $id, true );
		}
		++$deleted["posts"];
	}
}

// Terms in every taxonomy.
foreach ( get_taxonomies() as $taxonomy ) {
	$terms = get_terms( [ "taxonomy" => $taxonomy, "hide_empty" => false ] );
	if ( is_wp_error( $terms ) ) {
		continue;
	}
	foreach ( $terms as $term ) {
		if ( 0 === strpos( $term->name, "[ext-matrix]" ) || 0 === strpos( $term->slug, "extmx-" ) ) {
			wp_delete_term( $term->term_id, $taxonomy );
			++$deleted["terms"];
		}
	}
}

// Users.
require_once ABSPATH . "wp-admin/includes/user.php";
foreach ( get_users( [ "search" => "extmx-*", "search_columns" => [ "user_login" ] ] ) as $user ) {
	wp_delete_user( $user->ID );
	++$deleted["users"];
}

// The WooCommerce attribute the woo fixtures register.
if ( function_exists( "wc_attribute_taxonomy_id_by_name" ) && ( $attribute = wc_attribute_taxonomy_id_by_name( "extmx-color" ) ) ) {
	wc_delete_attribute( $attribute );
}

printf( "Deleted %d posts, %d terms and %d users.\n", $deleted["posts"], $deleted["terms"], $deleted["users"] );
'

remove_probe

if [[ $KEEP_PLUGINS != --keep-plugins ]]; then
	for spec in ${=GROUP_PLUGINS[$GROUP]}; do
		slug=${spec%%|*}
		$W plugin deactivate "$slug" >/dev/null 2>&1
		# A copy installed from a zip into a renamed folder ("<slug>-AbC123") counts too.
		for copy in $($W plugin list --status=active --field=name 2>/dev/null | grep -E "^${slug}-[A-Za-z0-9]{6}\$"); do
			$W plugin deactivate "$copy" >/dev/null 2>&1
		done
	done
	echo "Deactivated the $GROUP group's plugins (still installed)."
fi

# Restore options after deactivating plugins, so a still-active plugin can't write its defaults back.
for option in ${=GROUP_OPTIONS[$GROUP]}; do
	restore_option "$GROUP" "$option"
done
rm -f "$ROOT/fixtures/$GROUP.ids"

$W rewrite flush >/dev/null 2>&1
$W cache flush >/dev/null

case $GROUP in
	woo) echo "Left in place: WooCommerce's own pages, options, tables and sessions." ;;
	polylang) echo "Left in place: Polylang's languages, their translation terms, its options and string translations." ;;
	yoast) echo "Left in place: Yoast SEO's indexables and options." ;;
	rankmath) echo "Left in place: Rank Math's other options and tables." ;;
esac

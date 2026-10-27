#!/bin/zsh
# Usage: WP="<wp-cli command>" zsh bin/setup.sh <group>
#
# Prepares a test site for one group of queries: installs and activates the group's plugins at the
# versions we tested, adds the test-only probe mu-plugin, saves the options the fixtures change,
# and creates the fixtures (fixtures/<group>.sh, which writes fixtures/<group>.ids).
# Run it on a disposable test site with WPGraphQL active. Undo it with bin/teardown.sh <group>.
#
# Groups: content (Content Blocks, Faust and the ACF canaries), yoast, rankmath, woo, polylang,
# headless-login.

source "$(dirname "$0")/lib.sh"
GROUP=$1
require_group "$GROUP"

for conflict in ${=GROUP_CONFLICTS[$GROUP]}; do
	if $W plugin is-active "$conflict" 2>/dev/null; then
		echo "Deactivate $conflict first: it can't be active alongside the $GROUP group." >&2
		exit 1
	fi
done

if ! $W plugin is-active wp-graphql 2>/dev/null; then
	echo "Activate WPGraphQL (the build you want to test) first." >&2
	exit 1
fi

# The ACF canaries need ACF (or Secure Custom Fields) and WPGraphQL for ACF.
if [[ $GROUP == content ]]; then
	if [[ "$($W eval 'echo function_exists( "acf_get_field_groups" ) ? 1 : 0;')" != 1 ]]; then
		$W plugin install secure-custom-fields --activate
	fi
	if [[ "$($W eval 'echo class_exists( "WPGraphQLAcf" ) ? 1 : 0;')" != 1 ]]; then
		$W plugin install wpgraphql-acf --activate
	fi
fi

for spec in ${=GROUP_PLUGINS[$GROUP]}; do
	slug=${spec%%|*}
	source=${spec#*|}
	if [[ $source == http* ]]; then
		# Install from the release zip only if it isn't installed yet: reinstalling a zip over an
		# existing copy can leave a second copy in a renamed folder.
		$W plugin is-installed "$slug" 2>/dev/null || $W plugin install "$source"
	elif [[ "$($W plugin get "$slug" --field=version 2>/dev/null)" != "$source" ]]; then
		$W plugin install "$slug" --version="$source" --force
	fi
	$W plugin activate "$slug"
done

install_probe

# A small placeholder image the content and yoast fixtures import as media.
$W eval '$image = imagecreatetruecolor( 40, 30 ); imagefill( $image, 0, 0, imagecolorallocate( $image, 30, 90, 160 ) ); imagepng( $image, "/tmp/extmx.png" );'

for option in ${=GROUP_OPTIONS[$GROUP]}; do
	save_option "$GROUP" "$option"
done

zsh "$ROOT/fixtures/$GROUP.sh"
$W cache flush >/dev/null

echo
echo "Ready. Run a group's queries with: WP=\"$WP\" zsh bin/run.sh $GROUP <label> <graphql-endpoint>"

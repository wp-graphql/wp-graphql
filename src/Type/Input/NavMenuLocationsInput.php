<?php
namespace WPGraphQL\Type;

use WPGraphQL\Data\DataSource;

function get_fields() {
	$locations = DataSource::get_registered_nav_menu_locations();
	$fields = [];
	if ( ! empty( $locations ) ) {
		foreach( $locations as $location ) {
			$fields[ $location ] = [
				'type' => 'ID',
				'description' => __( 'The WP ID of the nav menu to be assigned to %s', 'wp-graphql', $location ),
			];
		}
	}

	return $fields;
}


register_graphql_input_type( 'NavMenuLocationsInput', [
	'description' => __( 'Nav menu location values' ),
	'fields' => get_fields(),
] );
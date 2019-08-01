<?php
namespace WPGraphQL\Type;

use WPGraphQL\Data\DataSource;

$values = [];

$locations = DataSource::get_registered_nav_menu_locations();

if ( ! empty( $locations ) && is_array( $locations ) ) {
	foreach ( $locations as $location ) {
		$values[ WPEnumType::get_safe_name( $location ) ] = [
			'value' => $location,
		];
	}
}

if ( empty( $values ) ) {
	$values['EMPTY'] = [
		'value' => 'Empty menu location',
	];
}

register_graphql_enum_type(
	'MenuLocationEnum',
	[
		'description' => __( 'Registered menu locations', 'wp-graphql' ),
		'values'      => $values,
	]
);

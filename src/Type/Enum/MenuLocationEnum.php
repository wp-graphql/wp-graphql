<?php
namespace WPGraphQL\Type;

$values = [];

$locations = array_keys( get_nav_menu_locations() );

if ( ! empty( $locations ) && is_array( $locations ) ) {
	foreach ( array_keys( get_nav_menu_locations() ) as $location ) {
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

register_graphql_enum_type( 'MenuLocationEnum', [
	'description' => __( 'Registered menu locations', 'wp-graphql' ),
	'values' => $values
] );

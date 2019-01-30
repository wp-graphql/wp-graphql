<?php

namespace WPGraphQL\Type;

if ( ! function_exists( 'wp_get_available_translations' ) ) {
	require_once ABSPATH . '/wp-admin/includes/translation-install.php';
}

$values       = [];
$translations = wp_get_available_translations();

$values[ WPEnumType::get_safe_name( 'en_US' ) ] = [
	'value'       => 'en_US',
	'description' => _x( 'English (United States)', 'wp-graphql' ),
];

if ( ! empty( $translations ) && is_array( $translations ) ) {
	foreach ( $translations as $key => $value ) {
		$values[ WPEnumType::get_safe_name( $key ) ] = [
			'value'       => $key,
			'description' => $value['english_name']
		];
	}
}

register_graphql_enum_type( 'AvailableLocalesEnum', [
	'description' => __( 'Available translations for the site', 'wp-graphql' ),
	'values'      => $values
] );

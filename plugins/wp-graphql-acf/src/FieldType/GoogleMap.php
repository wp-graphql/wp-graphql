<?php
namespace WPGraphQL\Acf\FieldType;

class GoogleMap {

	/**
	 * Register support for the "google_map" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'google_map',
			[
				'graphql_type' => 'AcfGoogleMap',
			]
		);
	}
}

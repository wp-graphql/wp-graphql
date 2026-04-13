<?php

/**
 * @param string                $acf_field_type
 * @param array<mixed>|callable $config
 */
function register_graphql_acf_field_type( string $acf_field_type, $config = [] ): void {
	add_action(
		'wpgraphql/acf/register_field_types',
		static function ( \WPGraphQL\Acf\FieldTypeRegistry $registry ) use ( $acf_field_type, $config ) {
			$registry->register_field_type( $acf_field_type, $config );
		}
	);
}

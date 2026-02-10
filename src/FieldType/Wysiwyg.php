<?php
namespace WPGraphQL\Acf\FieldType;

class Wysiwyg {

	/**
	 * Register support for the "wysiwyg" ACF field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'wysiwyg',
			[
				'graphql_type'  => 'String',
				'prepare_value' => static function ( $value, $root, $node_id, array $acf_field_config ) {

					// @todo: This was ported over, but I'm not 💯 sure what this is solving and
					// why it's only applied on options pages and not other pages 🤔
					if ( is_array( $root ) && ! ( ! empty( $root['type'] ) && 'options_page' === $root['type'] ) && isset( $root[ $acf_field_config['key'] ] ) ) {
						$value = $root[ $acf_field_config['key'] ];
						if ( 'wysiwyg' === $acf_field_config['type'] ) {
							$value = apply_filters( 'the_content', $value );
						}
					}

					return $value;
				},
			]
		);
	}
}

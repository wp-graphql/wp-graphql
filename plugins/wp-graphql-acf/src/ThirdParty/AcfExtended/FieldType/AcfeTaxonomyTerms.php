<?php

namespace WPGraphQL\Acf\ThirdParty\AcfExtended\FieldType;

use GraphQL\Deferred;

class AcfeTaxonomyTerms {

	/**
	 * Register support for the ACF Extended acfe_taxonomy_terms field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'acfe_taxonomy_terms',
			[
				'graphql_type' => [ 'list_of' => 'TermNode' ],
				'resolve'      => static function ( $root, $args, $context, $info, $field_type, $field_config ) {
					$value = $field_config->resolve_field( $root, $args, $context, $info );
					if ( empty( $value ) ) {
						return null;
					}

					if ( ! is_array( $value ) ) {
						$value = [ $value ];
					}

					return new Deferred(
						static function () use ( $value, $context ) {
							return array_filter(
								array_map(
									static function ( $id ) use ( $context ) {
										return $context->get_loader( 'term' )->load( (int) $id );
									},
									$value
								)
							);
						}
					);
				},
			]
		);
	}
}

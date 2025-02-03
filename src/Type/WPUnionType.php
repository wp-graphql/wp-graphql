<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPUnionType
 *
 * @phpstan-import-type UnionConfig from \GraphQL\Type\Definition\UnionType
 *
 * Union Types should extend this class to take advantage of the helper methods
 * and consistent filters.
 *
 * @package WPGraphQL\Type\Union
 * @since   0.0.30
 */
class WPUnionType extends UnionType {

	/**
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	public $type_registry;

	/**
	 * WPUnionType constructor.
	 *
	 * @param array<string,mixed>              $config The Config to set up a Union Type
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @phpstan-param UnionConfig|array{typeNames?:array<string>} $config
	 *
	 * @since 0.0.30
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$this->type_registry = $type_registry;

		$name           = isset( $config['name'] ) ? ucfirst( $config['name'] ) : $this->inferName();
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );

		$config['types'] = function () use ( $config ): array {
			$prepared_types = [];
			if ( ! empty( $config['typeNames'] ) && is_array( $config['typeNames'] ) ) {
				foreach ( $config['typeNames'] as $type_name ) {
					if ( in_array( strtolower( $type_name ), $this->type_registry->get_excluded_types(), true ) ) {
						continue;
					}
					$type = $this->type_registry->get_type( $type_name );
					if ( $type instanceof ObjectType ) {
						$prepared_types[] = $type;
					}
				}
			}
			return $prepared_types;
		};

		$config['resolveType'] = function ( $obj, $context, $info ) use ( $config ) {
			$type = null;
			if ( isset( $config['resolveType'] ) && is_callable( $config['resolveType'] ) ) {
				$type = call_user_func( $config['resolveType'], $obj, $context, $info );
			}

			return apply_filters( 'graphql_union_resolve_type', $type, $obj, $this );
		};

		$types           = apply_filters( 'graphql_union_possible_types', $config['types'], $config, $this );
		$config['types'] = $types;

		/**
		 * Filter the config of WPUnionType
		 *
		 * @param UnionConfig                 $config Array of configuration options passed to the WPUnionType when instantiating a new type
		 * @param \WPGraphQL\Type\WPUnionType $instance The instance of the WPUnionType class
		 */
		$config = apply_filters( 'graphql_wp_union_type_config', $config, $this );

		do_action( 'graphql_wp_union_type', $config, $this );

		parent::__construct( $config );
	}
}

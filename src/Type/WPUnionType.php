<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPUnionType
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
	 * @since 0.0.30
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$this->type_registry = $type_registry;

		$name           = ucfirst( $config['name'] );
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

		$config['resolveType'] = function ( $obj ) use ( $config ): ?ObjectType {
			$type = null;
			if ( is_callable( $config['resolveType'] ) ) {
				$type = call_user_func( $config['resolveType'], $obj );
			}

			return apply_filters( 'graphql_union_resolve_type', $type, $obj, $this );
		};

		/** @var callable(): array<\GraphQL\Type\Definition\ObjectType> $types */
		$types           = apply_filters( 'graphql_union_possible_types', $config['types'], $config, $this );
		$config['types'] = $types;

		/** @var array{
		 *     name?: string|null,
		 *     description?: string|null,
		 *     types: callable(): array<\GraphQL\Type\Definition\ObjectType>,
		 *     resolveType?: callable(mixed, mixed, \GraphQL\Type\Definition\ResolveInfo): (\GraphQL\Type\Definition\ObjectType|string|null)|null
		 * } */
		$config = apply_filters( 'graphql_wp_union_type_config', $config, $this );

		do_action( 'graphql_wp_union_type', $config, $this );

		parent::__construct( $config );
	}
}

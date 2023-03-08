<?php

namespace WPGraphQL\Type;

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
	 * @param array        $config The Config to setup a Union Type
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @since 0.0.30
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {

		$this->type_registry = $type_registry;

		/**
		 * Set the Types to start with capitals
		 */
		$name           = ucfirst( $config['name'] );
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );

		$config['types'] = function () use ( $config ) {
			$prepared_types = [];
			if ( ! empty( $config['typeNames'] ) && is_array( $config['typeNames'] ) ) {
				$prepared_types = [];
				foreach ( $config['typeNames'] as $type_name ) {
					/**
					 * Skip if the type is excluded from the schema.
					 */
					if ( in_array( strtolower( $type_name ), $this->type_registry->get_excluded_types(), true ) ) {
						continue;
					}

					$prepared_types[] = $this->type_registry->get_type( $type_name );
				}
			}

			return $prepared_types;
		};

		$config['resolveType'] = function ( $object ) use ( $config ) {
			$type = null;
			if ( is_callable( $config['resolveType'] ) ) {
				$type = call_user_func( $config['resolveType'], $object );
			}

			/**
			 * Filter the resolve type method for all unions
			 *
			 * @param mixed       $type          The Type to resolve to, based on the object being resolved
			 * @param mixed       $object        The Object being resolved
			 * @param \WPGraphQL\Type\WPUnionType $wp_union_type The WPUnionType instance
			 */
			return apply_filters( 'graphql_union_resolve_type', $type, $object, $this );
		};

		/**
		 * Filter the possible_types to allow systems to add to the possible resolveTypes.
		 *
		 * @param mixed       $types         The possible types for the Union
		 * @param array       $config        The config for the Union Type
		 * @param \WPGraphQL\Type\WPUnionType $wp_union_type The WPUnionType instance
		 *
		 * @return mixed|array
		 */
		$config['types'] = apply_filters( 'graphql_union_possible_types', $config['types'], $config, $this );

		/**
		 * Filter the config of WPUnionType
		 *
		 * @param array       $config        Array of configuration options passed to the WPUnionType when instantiating a new type
		 * @param \WPGraphQL\Type\WPUnionType $wp_union_type The instance of the WPUnionType class
		 *
		 * @since 0.0.30
		 */
		$config = apply_filters( 'graphql_wp_union_type_config', $config, $this );

		/**
		 * Run an action when the WPUnionType is instantiating
		 *
		 * @param array       $config        Array of configuration options passed to the WPUnionType when instantiating a new type
		 * @param \WPGraphQL\Type\WPUnionType $wp_union_type The instance of the WPUnionType class
		 */
		do_action( 'graphql_wp_union_type', $config, $this );

		parent::__construct( $config );
	}
}

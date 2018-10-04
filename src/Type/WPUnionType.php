<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\UnionType;
use WPGraphQL\TypeRegistry;

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
	 * WPUnionType constructor.
	 *
	 * @since 0.0.30
	 */
	public function __construct( $config ) {
		/**
		 * Set the Types to start with capitals
		 */
		$config['name'] = ucfirst( $config['name'] );

		if ( ! empty( $config['typeNames'] ) && is_array( $config['typeNames'] ) ) {
			$config['types'] = self::prepare_types( $config['typeNames'], $config );
		}

		/**
		 * Filter the possible_types to allow systems to add to the possible resolveTypes.
		 *
		 * @since 0.0.30
		 * @return array
		 */
		$config['types'] = apply_filters( "graphql_{$config['name']}_possible_types", $config['types'] );

		/**
		 * Filter the config of WPUnionType
		 *
		 * @param array       $config Array of configuration options passed to the WPUnionType when instantiating a new type
		 * @param WPUnionType $this   The instance of the WPObjectType class
		 *
		 * @since 0.0.30
		 */
		$config = apply_filters( 'graphql_wp_union_type_config', $config, $this );

		/**
		 * Run an action when the WPObjectType is instantiating
		 *
		 * @param array       $config Array of configuration options passed to the WPUnionType when instantiating a new type
		 * @param WPUnionType $this   The instance of the WPObjectType class
		 */
		do_action( 'graphql_wp_union_type', $config, $this );

		parent::__construct( $config );
	}

	protected static function prepare_types( $type_names, $config ) {
		$prepared_types = [];
		foreach ( $type_names as $type ) {
			$prepared_types[] = TypeRegistry::get_type( $type );
		}
		return $prepared_types;
	}
}

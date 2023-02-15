<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InputObjectType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPInputObjectType
 *
 * Input types should extend this class to take advantage of the helper methods for formatting
 * and adding consistent filters.
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class WPInputObjectType extends InputObjectType {

	/**
	 * WPInputObjectType constructor.
	 *
	 * @param array        $config
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$name           = $config['name'];
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );

		/**
		 * Setup the fields
		 *
		 * @return array|mixed
		 */
		if ( ! empty( $config['fields'] ) && is_array( $config['fields'] ) ) {
			$config['fields'] = function () use ( $config, $type_registry ) {
				$fields = $this->prepare_fields( $config['fields'], $config['name'], $config, $type_registry );
				$fields = $type_registry->prepare_fields( $fields, $config['name'] );

				return $fields;
			};
		}

		parent::__construct( $config );
	}

	/**
	 * Prepare_fields
	 *
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array        $fields
	 * @param string       $type_name
	 * @param array        $config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 * @return mixed
	 * @since 0.0.5
	 */
	public function prepare_fields( array $fields, string $type_name, array $config, TypeRegistry $type_registry ) {

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array  $fields    The array of fields for the object config
		 * @param string $type_name The name of the object type
		 * @param array  $config    The type config
		 *
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The TypeRegistry instance
		 */
		$fields = apply_filters( 'graphql_input_fields', $fields, $type_name, $config, $type_registry );

		/**
		 * Filter once with lowercase, once with uppercase for Back Compat.
		 */
		$lc_type_name = lcfirst( $type_name );
		$uc_type_name = ucfirst( $type_name );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array $fields The array of fields for the object config
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The TypeRegistry instance
		 */
		$fields = apply_filters( "graphql_{$lc_type_name}_fields", $fields, $type_registry );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array $fields The array of fields for the object config
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The TypeRegistry instance
		 */
		$fields = apply_filters( "graphql_{$uc_type_name}_fields", $fields, $type_registry );

		/**
		 * Sort the fields alphabetically by key. This makes reading through docs much easier
		 *
		 * @since 0.0.2
		 */
		ksort( $fields );

		/**
		 * Return the filtered, sorted $fields
		 *
		 * @since 0.0.5
		 */
		return $fields;
	}
}

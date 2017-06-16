<?php
namespace WPGraphQL;

use GraphQL\Schema;

/**
 * Class WPSchema
 *
 * Extends the Schema to make some properties accessible via hooks/filters
 *
 * @package WPGraphQL
 */
class WPSchema extends Schema {

	/**
	 * Holds the $filterable_config which allows WordPress access to modifying the
	 * $config that gets passed down to the Executable Schema
	 *
	 * @var array|null
	 * @since 0.0.9
	 */
	public $filterable_config;

	/**
	 * WPSchema constructor.
	 *
	 * @param array|null $config
	 * @since 0.0.9
	 */
	public function __construct( $config ) {

		/**
		 * Set the $filterable_config as the $config that was passed to the WPSchema when instantiated
		 * @since 0.0.9
		 */
		$this->filterable_config = apply_filters( 'graphql_schema_config', $config );

		parent::__construct( $this->filterable_config );
	}

	/**
	 * This takes in the Schema and escapes it before it's returned to the executor.
	 *
	 * @param \WPGraphQL\WPSchema $schema
	 *
	 * @return mixed
	 */
	public static function sanitize_schema( \WPGraphQL\WPSchema $schema ) {

		/**
		 * Get the prepared TypeMap
		 */
		$types = $schema->getTypeMap();

		/**
		 * Ensure there are types
		 */
		if ( ! empty( $types ) && is_array( $types ) ) {

			/**
			 * Loop through the types
			 */
			foreach ( $types as $type_name => $type_object ) {

				/**
				 * esc the values
				 */
				$sanitized_types[ $type_name ] = $type_object;
				$sanitized_types[ $type_name ]->name = esc_html( $type_object->name );
				$sanitized_types[ $type_name ]->description = esc_html( $type_object->description );
				$sanitized_types[ $type_name ]->deprecationReason = esc_html( $type_object->description );
			}
		}

		/**
		 * Ensure there are $sanitized_types, and set the config's types as the sanitized types
		 */
		if ( ! empty( $sanitized_types ) && is_array( $sanitized_types ) ) {
			$schema->filterable_config['types'] = $sanitized_types;
		}

		/**
		 * Return the $schema with the sanitized types
		 */
		return $schema;

	}
}

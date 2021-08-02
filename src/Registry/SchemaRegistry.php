<?php

namespace WPGraphQL\Registry;

use GraphQL\Type\SchemaConfig;
use WPGraphQL\WPSchema;

/**
 * Class SchemaRegistry
 *
 * @package WPGraphQL\Registry
 */
class SchemaRegistry {

	/**
	 * @var TypeRegistry
	 */
	protected $type_registry;

	/**
	 * SchemaRegistry constructor.
	 *
	 * @throws \Exception
	 */
	public function __construct() {
		$this->type_registry = \WPGraphQL::get_type_registry();
	}

	/**
	 * Returns the Schema to use for execution of the GraphQL Request
	 *
	 * @return WPSchema
	 * @throws \Exception
	 */
	public function get_schema() {

		$this->type_registry->init();

		$schema_config             = new SchemaConfig();
		$schema_config->query      = $this->type_registry->get_type( 'RootQuery' );
		$schema_config->mutation   = $this->type_registry->get_type( 'RootMutation' );
		$schema_config->typeLoader = function ( $type ) {
			return $this->type_registry->get_type( $type );
		};
		$schema_config->types      = $this->type_registry->get_types();

		/**
		 * Create a new instance of the Schema
		 */
		$schema = new WPSchema( $schema_config );

		/**
		 * Filter the Schema
		 *
		 * @param WPSchema       $schema The generated Schema
		 * @param SchemaRegistry $this   The Schema Registry Instance
		 */
		return apply_filters( 'graphql_schema', $schema, $this );

	}


}

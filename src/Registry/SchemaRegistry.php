<?php

namespace WPGraphQL\Registry;

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

		/**
		 * Create a new instance of the Schema
		 */
		$schema = new WPSchema(
			[
				'query'      => $this->type_registry->get_type( 'RootQuery' ),
				'mutation'   => $this->type_registry->get_type( 'RootMutation' ),
				'typeLoader' => function( $type ) {
					return $this->type_registry->get_type( $type );
				},
				'types'      => $this->type_registry->get_types(),
			]
		);

		/**
		 * Filter the Schema
		 *
		 * @param WPSchema       $schema The generated Schema
		 * @param SchemaRegistry $this   The Schema Registry Instance
		 */
		return apply_filters( 'graphql_schema', $schema, $this );

	}


}

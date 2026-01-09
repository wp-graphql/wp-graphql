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
	 * @var \WPGraphQL\Registry\TypeRegistry
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
	 * @return \WPGraphQL\WPSchema
	 * @throws \Exception
	 */
	public function get_schema() {
		$this->type_registry->init();

		$schema_config = SchemaConfig::create()
			->setQuery(
				function () {
					/**
					 * @var ?\GraphQL\Type\Definition\ObjectType $type
					 */
					$type = $this->type_registry->get_type( 'RootQuery' );

					return $type;
				}
			)->setMutation(
				function () {
					/**
					 * @var ?\GraphQL\Type\Definition\ObjectType $type
					 */
					$type = $this->type_registry->get_type( 'RootMutation' );

					return $type;
				}
			)->setTypeLoader(
				function ( $type_name ) {
					/**
					 * @var (\GraphQL\Type\Definition\Type&\GraphQL\Type\Definition\NamedType)|null $type
					 */
					$type = $this->type_registry->get_type( $type_name );

					return $type;
				}
			)
			->setTypes( fn() => $this->type_registry->get_types() );

		/**
		 * Create a new instance of the Schema
		 */
		$schema = new WPSchema( $schema_config, $this->type_registry );

		/**
		 * Filter the Schema
		 *
		 * @param \WPGraphQL\WPSchema $schema The generated Schema
		 * @param \WPGraphQL\Registry\SchemaRegistry $registry The Schema Registry Instance
		 */
		return apply_filters( 'graphql_schema', $schema, $this );
	}
}

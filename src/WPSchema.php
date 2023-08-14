<?php

namespace WPGraphQL;

use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPSchema
 *
 * Extends the Schema to make some properties accessible via hooks/filters
 *
 * @package WPGraphQL
 */
class WPSchema extends Schema {

	/**
	 * @var \GraphQL\Type\SchemaConfig
	 */
	public $config;

	/**
	 * Holds the $filterable_config which allows WordPress access to modifying the
	 * $config that gets passed down to the Executable Schema
	 *
	 * @var \GraphQL\Type\SchemaConfig|null
	 * @since 0.0.9
	 */
	public $filterable_config;

	/**
	 * WPSchema constructor.
	 *
	 * @param \GraphQL\Type\SchemaConfig $config The config for the Schema.
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @since 0.0.9
	 */
	public function __construct( SchemaConfig $config, TypeRegistry $type_registry ) {

		$this->config = $config;

		/**
		 * Set the $filterable_config as the $config that was passed to the WPSchema when instantiated
		 *
		 * @param \GraphQL\Type\SchemaConfig $config The config for the Schema.
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The WPGraphQL type registry.
		 *
		 * @since 0.0.9
		 */
		$this->filterable_config = apply_filters( 'graphql_schema_config', $config, $type_registry );
		parent::__construct( $this->filterable_config );
	}

}

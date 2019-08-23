<?php
namespace WPGraphQL\Registry;

use WPGraphQL\WPSchema;

class SchemaRegistry {

	protected $type_registry;

	public function __construct( TypeRegistry $type_registry ) {
		$this->type_registry = $type_registry;
	}

	/**
	 * @return WPSchema
	 * @throws \Exception
	 */
	public function get_schema() {

		$this->type_registry->init();

		$schema =  new WPSchema([
			'query' => $this->type_registry->get_type( 'RootQuery' ),
			'mutation' => $this->type_registry->get_type( 'RootMutation' ),
			'typeLoader' => function( $type ) {
				return $this->type_registry->get_type( $type );;
			}
		]);

		/// $schema->assertValid();

		return $schema;

	}



}

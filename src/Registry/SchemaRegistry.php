<?php
namespace WPGraphQL\Registry;

class SchemaRegistry {

	protected $type_registry;

	public function __construct( TypeRegistry $type_registry ) {
		$this->type_registry = $type_registry;
	}



}

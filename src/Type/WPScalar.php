<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\CustomScalarType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPScalar
 *
 * @package WPGraphQL\Type
 */
class WPScalar extends CustomScalarType {

	/**
	 * WPScalar constructor.
	 *
	 * @param array        $config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {

		$name           = $config['name'];
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );
		$config         = apply_filters( 'graphql_custom_scalar_config', $config, $type_registry );

		parent::__construct( $config );
	}

}

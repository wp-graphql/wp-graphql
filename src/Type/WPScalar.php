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
	 * @param TypeRegistry $type_registry
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$config = apply_filters( 'graphql_custom_scalar_config', $config, $type_registry );
		parent::__construct( $config );

	}

}

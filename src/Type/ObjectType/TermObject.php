<?php

namespace WPGraphQL\Type\ObjectType;

use Exception;
use WP_Taxonomy;
use WPGraphQL\Data\TermObjectType;

/**
 * Class TermObject
 *
 * @package WPGraphQL\Type\Object
 * @deprecated @todo
 */
class TermObject {

	/**
	 * Register the Type for each kind of Taxonomy
	 *
	 * @param WP_Taxonomy $tax_object The taxonomy being registered
	 *
	 * @return void
	 * @throws Exception
	 * @deprecated @todo
	 */
	public static function register_taxonomy_object_type( WP_Taxonomy $tax_object ) {
		_deprecated_function( __FUNCTION__, '@todo', esc_attr( \WPGraphQL\Registry\Utils\TermObject::class ) . '::register_types()' );

		\WPGraphQL\Registry\Utils\TermObject::register_types( $tax_object );
	}

}

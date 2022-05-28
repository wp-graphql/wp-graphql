<?php

namespace WPGraphQL\Type\ObjectType;

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
	 * @deprecated @todo
	 *
	 * @return void
	 */
	public static function register_taxonomy_object_type( WP_Taxonomy $tax_object ) {
		_deprecated_function( __FUNCTION__, '@todo', TermObjectType::class . '::register_term_object_types()' ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		TermObjectType::register_term_object_types( $tax_object );
	}

}

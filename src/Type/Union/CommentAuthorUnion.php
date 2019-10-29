<?php
namespace WPGraphQL\Type\Union;

use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

class CommentAuthorUnion {

	/**
	 * Registers the Type
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @access public
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_union_type(
			'CommentAuthorUnion',
			[
				'name'        => 'CommentAuthorUnion',
				'typeNames'   => [ 'User', 'CommentAuthor' ],
				'resolveType' => function ( $source ) use ( $type_registry ) {

					if ( $source instanceof User ) {
						$type = $type_registry->get_type( 'User' );

					} else {
						$type = $type_registry->get_type( 'CommentAuthor' );

					}

					return $type;
				},
			]
		);
	}
}

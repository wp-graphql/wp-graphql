<?php
namespace WPGraphQL\Type;

use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

add_action( 'init_type_registry', function( TypeRegistry $type_registry ) {

	$comment_author_type = $type_registry->get_type( 'CommentAuthor' );
	$user_type           = $type_registry->get_type( 'User' );

	register_graphql_union_type(
		'CommentAuthorUnion',
		[
			'name'        => 'CommentAuthorUnion',
			'typeNames'   => [ 'User', 'CommentAuthor' ],
			'resolveType' => function ( $source ) use ( $comment_author_type, $user_type ) {

				if ( $source instanceof User ) {
					$type = $user_type;
				} else {
					$type = $comment_author_type;
				}

				return $type;
			},
		]
	);

} );


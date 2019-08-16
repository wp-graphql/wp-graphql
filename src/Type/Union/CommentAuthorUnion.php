<?php
namespace WPGraphQL\Type;

use WPGraphQL\Model\User;
use WPGraphQL\TypeRegistry;

$comment_author_type = TypeRegistry::get_type( 'CommentAuthor' );
$user_type           = TypeRegistry::get_type( 'User' );

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

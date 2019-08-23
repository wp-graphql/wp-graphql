<?php
namespace WPGraphQL\Type;

use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

add_action( 'graphql_register_types', function( TypeRegistry $type_registry ) {

	$type_registry->register_union_type(
		'CommentAuthorUnion',
		[
			'name'        => 'CommentAuthorUnion',
			'typeNames'   => [ 'User', 'CommentAuthor' ],
			'resolveType' => function ( $source ) use ( $type_registry ) {

				if ( $source instanceof User ) {
					$type = $type_registry->get_type( 'User' );;
				} else {
					$type = $type_registry->get_type( 'CommentAuthor' );;
				}

				return $type;
			},
		]
	);

}, 50 );


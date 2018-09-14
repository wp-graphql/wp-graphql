<?php
/**
 * This file contains access functions for various class methods
 *
 * @since 0.0.2
 */

/**
 * Formats the name of a field so that it plays nice with GraphiQL
 *
 * @param string $field_name Name of the field
 *
 * @access public
 * @return string Name of the field
 * @since  0.0.2
 */
function graphql_format_field_name( $field_name ) {
	$field_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $field_name );
	$field_name = preg_replace( '/[^A-Za-z0-9]/i', '', ucwords( $field_name ) );
	$field_name = lcfirst( $field_name );

	return $field_name;
}

/**
 * Provides a simple way to run a GraphQL query with out posting a request to the endpoint.
 *
 * @param string $request        The GraphQL query to run
 * @param string $operation_name The name of the operation
 * @param string $variables      Variables to be passed to your GraphQL request
 *
 * @access public
 * @return array
 * @since  0.0.2
 */
function do_graphql_request( $request, $operation_name = '', $variables = '' ) {
	return \WPGraphQL::do_graphql_request( $request, $operation_name, $variables );
}

function register_graphql_type( $type_name, $config ) {
	\WPGraphQL\TypeRegistry::register_type( $type_name, $config );
}

function register_graphql_object_type( $type_name, $config ) {
	$config['kind'] = 'object';
	register_graphql_type( $type_name, $config );
}

function register_graphql_input_type( $type_name, $config ) {
	$config['kind'] = 'input';
	register_graphql_type( $type_name, $config );
}

function register_graphql_union_type( $type_name, $config ) {
	$config['kind'] = 'union';
	register_graphql_type( $type_name, $config );
}

function register_graphql_enum_type( $type_name, $config ) {
	$config['kind'] = 'enum';
	register_graphql_type( $type_name, $config );
}

function register_graphql_field( $type_name, $field_name, $config ) {
	\WPGraphQL\TypeRegistry::register_field( $type_name, $field_name, $config );
}

function register_graphql_fields( $type_name, array $fields ) {
	\WPGraphQL\TypeRegistry::register_fields( $type_name, $fields );
}

function register_graphql_schema( $schema_name, array $config ) {
	\WPGraphQL\SchemaRegistry::register_schema( $schema_name, $config );
}

function register_graphql_connection( $config ) {
	\WPGraphQL\TypeRegistry::register_connection( $config );
}

//add_action( 'graphql_register_types', function () {
//	register_graphql_object_type( 'Goo', [
//		'fields' => [
//			'goo' => [
//				'type' => 'String',
//			],
//		],
//	] );
//
//	register_graphql_connection( [
//		'fromType'         => 'RootQuery',
//		'toType'           => 'Goo',
//		'fromFieldName'    => 'postsConnection',
//		'connectionArgs'   => [
//			'authorEmail'        => [
//				'type'        => 'String',
//				'description' => __( 'Comment author email address.', 'wp-graphql' ),
//			],
//			'authorUrl'          => [
//				'type'        => 'String',
//				'description' => __( 'Comment author URL.', 'wp-graphql' ),
//			],
//			'authorIn'           => [
//				'type'        => [
//					'list_of' => 'ID'
//				],
//				'description' => __( 'Array of author IDs to include comments for.', 'wp-graphql' ),
//			],
//			'authorNotIn'        => [
//				'type'        => [
//					'list_of' => 'ID'
//				],
//				'description' => __( 'Array of author IDs to exclude comments for.', 'wp-graphql' ),
//			],
//			'commentIn'          => [
//				'type'        => [
//					'list_of' => 'ID'
//				],
//				'description' => __( 'Array of comment IDs to include.', 'wp-graphql' ),
//			],
//			'commentNotIn'       => [
//				'type'        => [
//					'list_of' => 'ID'
//				],
//				'description' => __( 'Array of IDs of users whose unapproved comments will be returned by the
//							query regardless of status.', 'wp-graphql' ),
//			],
//			'includeUnapproved'  => [
//				'type'        => [
//					'list_of' => 'ID'
//				],
//				'description' => __( 'Array of author IDs to include comments for.', 'wp-graphql' ),
//			],
//			'karma'              => [
//				'type'        => 'Int',
//				'description' => __( 'Karma score to retrieve matching comments for.', 'wp-graphql' ),
//			],
//			'orderby'            => [
//				'type'        => 'CommentsOrderbyEnum',
//				'description' => __( 'Field to order the comments by.', 'wp-graphql' ),
//			],
//			'order'              => [
//				'type' => 'CommentsOrderEnum',
//			],
//			'parent'             => [
//				'type'        => 'Int',
//				'description' => __( 'Parent ID of comment to retrieve children of.', 'wp-graphql' ),
//			],
//			'parentIn'           => [
//				'type'        => [
//					'list_of' => 'ID',
//				],
//				'description' => __( 'Array of parent IDs of comments to retrieve children for.', 'wp-graphql' ),
//			],
//			'parentNotIn'        => [
//				'type'        => [
//					'list_of' => 'ID',
//				],
//				'description' => __( 'Array of parent IDs of comments *not* to retrieve children
//							for.', 'wp-graphql' ),
//			],
//			'contentAuthorIn'    => [
//				'type'        => [
//					'list_of' => 'ID',
//				],
//				'description' => __( 'Array of author IDs to retrieve comments for.', 'wp-graphql' ),
//			],
//			'contentAuthorNotIn' => [
//				'type'        => [
//					'list_of' => 'ID',
//				],
//				'description' => __( 'Array of author IDs *not* to retrieve comments for.', 'wp-graphql' ),
//			],
//			'contentId'          => [
//				'type'        => 'ID',
//				'description' => __( 'Limit results to those affiliated with a given content object
//							ID.', 'wp-graphql' ),
//			],
//			'contentIdIn'        => [
//				'type'        => [
//					'list_of' => 'ID',
//				],
//				'description' => __( 'Array of content object IDs to include affiliated comments
//							for.', 'wp-graphql' ),
//			],
//			'contentIdNotIn'     => [
//				'type'        => [
//					'list_of' => 'ID',
//				],
//				'description' => __( 'Array of content object IDs to exclude affiliated comments
//							for.', 'wp-graphql' ),
//			],
//			'contentAuthor'      => [
//				'type'        => [
//					'list_of' => 'ID',
//				],
//				'description' => __( 'Content object author ID to limit results by.', 'wp-graphql' ),
//			],
//			'contentStatus'      => [
//				'type'        => [
//					'list_of' => 'PostStatusEnum'
//				],
//				'description' => __( 'Array of content object statuses to retrieve affiliated comments for.
//							Pass \'any\' to match any value.', 'wp-graphql' ),
//			],
//			'contentType'        => [
//				'type'        => [
//					'list_of' => 'PostTypeEnum'
//				],
//				'description' => __( 'Content object type or array of types to retrieve affiliated comments for. Pass \'any\' to match any value.', 'wp-graphql' ),
//			],
//			'contentName'        => [
//				'type'        => 'String',
//				'description' => __( 'Content object name to retrieve affiliated comments for.', 'wp-graphql' ),
//			],
//			'contentParent'      => [
//				'type'        => 'Int',
//				'description' => __( 'Content Object parent ID to retrieve affiliated comments for.', 'wp-graphql' ),
//			],
//			'search'             => [
//				'type'        => 'String',
//				'description' => __( 'Search term(s) to retrieve matching comments for.', 'wp-graphql' ),
//			],
//			'status'             => [
//				'type'        => 'String',
//				'description' => __( 'Comment status to limit results by.', 'wp-graphql' ),
//			],
//			'commentType'        => [
//				'type'        => 'String',
//				'description' => __( 'Include comments of a given type.', 'wp-graphql' ),
//			],
//			'commentTypeIn'      => [
//				'type'        => [
//					'list_of' => 'String',
//				],
//				'description' => __( 'Include comments from a given array of comment types.', 'wp-graphql' ),
//			],
//			'commentTypeNotIn'   => [
//				'type'        => 'String',
//				'description' => __( 'Exclude comments from a given array of comment types.', 'wp-graphql' ),
//			],
//			'userId'             => [
//				'type'        => 'Int',
//				'description' => __( 'Include comments for a specific user ID.', 'wp-graphql' ),
//			],
//		],
//		'connectionFields' => [
//			'nodes' => [
//				'type'        => [
//					'list_of' => 'Goo',
//				],
//				'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
//				'resolve'     => function ( $source, $args, $context, $info ) {
//					return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
//				},
//			],
//		]
//	] );
//} );


//add_action( 'graphql_register_types', function() {
//
//	register_graphql_field( 'RootQuery', 'myNewType', [
//		'type' => 'MyNewType',
//		'resolve' => function(  ) {
//			return [
//				'id' => 'something',
//				'user' => function() {
//					return \WPGraphQL\Data\DataSource::resolve_user( get_current_user_id() );
//				}
//			];
//		}
//	] );
//
//	register_graphql_type( 'MyNewType', [
//		'kind' => 'object',
//		'fields' => [
//			'id' => [
//				'type' => 'id',
//			],
//			'user' => [
//				'type' => 'User',
//			],
//		]
//	]);
//
//	register_graphql_connection([
//		'fromType' => 'MyCustomBlock',
//		'toType' => 'MediaItem',
//		'fromFieldName' => 'connectedUsers',
//		'resolve'
//	]);
//
//
//
//} );

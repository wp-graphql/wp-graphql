<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

/**
 * Class Comments
 *
 * This class organizes the registration of connections to Comments
 *
 * @package WPGraphQL\Connection
 */
class Comments {

	/**
	 * Register connections to Comments
	 *
	 * @access public
	 */
	public static function register_connections() {

		/**
		 * Register connection from RootQuery to Comments
		 */
		register_graphql_connection( self::get_connection_config() );

		/**
		 * Register connection from User to Comments
		 */
		register_graphql_connection( self::get_connection_config( [ 'fromType' => 'User' ] ) );

		/**
		 * Register connection from Comment to children comments
		 */
		register_graphql_connection(
			self::get_connection_config(
				[
					'fromType'      => 'Comment',
					'fromFieldName' => 'children',
				]
			)
		);

		/**
		 * Register Connections from all existing PostObject Types to Comments
		 */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				$post_type_object = get_post_type_object( $post_type );
				if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
					register_graphql_connection(
						self::get_connection_config(
							[
								'fromType'      => $post_type_object->graphql_single_name,
								'toType'        => 'Comment',
								'fromFieldName' => 'comments',
							]
						)
					);
				}
			}
		}
	}

	/**
	 * Given an array of $args, this returns the connection config, merging the provided args
	 * with the defaults
	 *
	 * @access public
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public static function get_connection_config( $args = [] ) {
		$defaults = [
			'fromType'       => 'RootQuery',
			'toType'         => 'Comment',
			'fromFieldName'  => 'comments',
			'connectionArgs' => self::get_connection_args(),
			'resolveNode'    => function ( $id, $args, $context, $info ) {
				return DataSource::resolve_comment( $id, $context );
			},
			'resolve'        => function ( $root, $args, $context, $info ) {
				return DataSource::resolve_comments_connection( $root, $args, $context, $info );
			},
		];

		return array_merge( $defaults, $args );
	}

	/**
	 * This returns the connection args for the Comment connection
	 *
	 * @access public
	 * @return array
	 */
	public static function get_connection_args() {
		return [
			'authorEmail'        => [
				'type'        => 'String',
				'description' => __( 'Comment author email address.', 'wp-graphql' ),
			],
			'authorUrl'          => [
				'type'        => 'String',
				'description' => __( 'Comment author URL.', 'wp-graphql' ),
			],
			'authorIn'           => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of author IDs to include comments for.', 'wp-graphql' ),
			],
			'authorNotIn'        => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of author IDs to exclude comments for.', 'wp-graphql' ),
			],
			'commentIn'          => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of comment IDs to include.', 'wp-graphql' ),
			],
			'commentNotIn'       => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __(
					'Array of IDs of users whose unapproved comments will be returned by the
							query regardless of status.',
					'wp-graphql'
				),
			],
			'includeUnapproved'  => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of IDs or email addresses of users whose unapproved comments will be returned by the query regardless of $status. Default empty', 'wp-graphql' ),
			],
			'karma'              => [
				'type'        => 'Int',
				'description' => __( 'Karma score to retrieve matching comments for.', 'wp-graphql' ),
			],
			'orderby'            => [
				'type'        => 'CommentsConnectionOrderbyEnum',
				'description' => __( 'Field to order the comments by.', 'wp-graphql' ),
			],
			'order'              => [
				'type'        => 'OrderEnum',
				'description' => __( 'The cardinality of the order of the connection', 'wp-graphql' ),
			],
			'parent'             => [
				'type'        => 'Int',
				'description' => __( 'Parent ID of comment to retrieve children of.', 'wp-graphql' ),
			],
			'parentIn'           => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of parent IDs of comments to retrieve children for.', 'wp-graphql' ),
			],
			'parentNotIn'        => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __(
					'Array of parent IDs of comments *not* to retrieve children
							for.',
					'wp-graphql'
				),
			],
			'contentAuthorIn'    => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of author IDs to retrieve comments for.', 'wp-graphql' ),
			],
			'contentAuthorNotIn' => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of author IDs *not* to retrieve comments for.', 'wp-graphql' ),
			],
			'contentId'          => [
				'type'        => 'ID',
				'description' => __(
					'Limit results to those affiliated with a given content object
							ID.',
					'wp-graphql'
				),
			],
			'contentIdIn'        => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __(
					'Array of content object IDs to include affiliated comments
							for.',
					'wp-graphql'
				),
			],
			'contentIdNotIn'     => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __(
					'Array of content object IDs to exclude affiliated comments
							for.',
					'wp-graphql'
				),
			],
			'contentAuthor'      => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Content object author ID to limit results by.', 'wp-graphql' ),
			],
			'contentStatus'      => [
				'type'        => [
					'list_of' => 'PostStatusEnum',
				],
				'description' => __(
					'Array of content object statuses to retrieve affiliated comments for.
							Pass \'any\' to match any value.',
					'wp-graphql'
				),
			],
			'contentType'        => [
				'type'        => [
					'list_of' => 'PostTypeEnum',
				],
				'description' => __( 'Content object type or array of types to retrieve affiliated comments for. Pass \'any\' to match any value.', 'wp-graphql' ),
			],
			'contentName'        => [
				'type'        => 'String',
				'description' => __( 'Content object name to retrieve affiliated comments for.', 'wp-graphql' ),
			],
			'contentParent'      => [
				'type'        => 'Int',
				'description' => __( 'Content Object parent ID to retrieve affiliated comments for.', 'wp-graphql' ),
			],
			'search'             => [
				'type'        => 'String',
				'description' => __( 'Search term(s) to retrieve matching comments for.', 'wp-graphql' ),
			],
			'status'             => [
				'type'        => 'String',
				'description' => __( 'Comment status to limit results by.', 'wp-graphql' ),
			],
			'commentType'        => [
				'type'        => 'String',
				'description' => __( 'Include comments of a given type.', 'wp-graphql' ),
			],
			'commentTypeIn'      => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'Include comments from a given array of comment types.', 'wp-graphql' ),
			],
			'commentTypeNotIn'   => [
				'type'        => 'String',
				'description' => __( 'Exclude comments from a given array of comment types.', 'wp-graphql' ),
			],
			'userId'             => [
				'type'        => 'Id',
				'description' => __( 'Include comments for a specific user ID.', 'wp-graphql' ),
			],
		];
	}
}

<?php

namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;

class Comments {

	/**
	 * Defines the Type the connection is connecting to
	 * @var string
	 */
	protected static $to_type = 'Comment';

	/**
	 * Registers the connection
	 * @param array $config
	 */
	public static function register_connection( array $config = [] ) {

		$default = [
			'fromType'         => 'RootQuery',
			'toType'           => self::$to_type,
			'fromFieldName'    => 'comments',
			'connectionArgs'   => [
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
						'list_of' => 'ID'
					],
					'description' => __( 'Array of author IDs to include comments for.', 'wp-graphql' ),
				],
				'authorNotIn'        => [
					'type'        => [
						'list_of' => 'ID'
					],
					'description' => __( 'Array of author IDs to exclude comments for.', 'wp-graphql' ),
				],
				'commentIn'          => [
					'type'        => [
						'list_of' => 'ID'
					],
					'description' => __( 'Array of comment IDs to include.', 'wp-graphql' ),
				],
				'commentNotIn'       => [
					'type'        => [
						'list_of' => 'ID'
					],
					'description' => __( 'Array of IDs of users whose unapproved comments will be returned by the query regardless of status.', 'wp-graphql' ),
				],
				'includeUnapproved'  => [
					'type'        => [
						'list_of' => 'ID'
					],
					'description' => __( 'Array of author IDs to include comments for.', 'wp-graphql' ),
				],
				'karma'              => [
					'type'        => 'Int',
					'description' => __( 'Karma score to retrieve matching comments for.', 'wp-graphql' ),
				],
				'orderby'            => [
					'type'        => 'CommentsOrderbyEnum',
					'description' => __( 'Field to order the comments by.', 'wp-graphql' ),
				],
				'order'              => [
					'type' => 'CommentsOrderEnum',
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
					'description' => __( 'Array of parent IDs of comments *not* to retrieve children for.', 'wp-graphql' ),
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
					'description' => __( 'Limit results to those affiliated with a given content object ID.', 'wp-graphql' ),
				],
				'contentIdIn'        => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of content object IDs to include affiliated comments for.', 'wp-graphql' ),
				],
				'contentIdNotIn'     => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of content object IDs to exclude affiliated comments for.', 'wp-graphql' ),
				],
				'contentAuthor'      => [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Content object author ID to limit results by.', 'wp-graphql' ),
				],
				'contentStatus'      => [
					'type'        => [
						'list_of' => 'PostStatusEnum'
					],
					'description' => __( 'Array of content object statuses to retrieve affiliated comments for.', 'wp-graphql' ),
				],
				'contentType'        => [
					'type'        => [
						'list_of' => 'PostTypeEnum'
					],
					'description' => __( 'Content object type or array of types to retrieve affiliated comments for.', 'wp-graphql' ),
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
					'type'        => 'Int',
					'description' => __( 'Include comments for a specific user ID.', 'wp-graphql' ),
				],
			],
			'connectionFields' => [
				'nodes' => [
					'type' => [
						'list_of'     => 'Comment',
					],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
					'resolve'     => function ( $source, $args, $context, $info ) {
						return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
					},
				],
			],
			'resolve' => function( $source, $args, AppContext $context, ResolveInfo $info ) {
				$connection = DataSource::resolve_comments_connection( $source, $args, $context, $info );
				return $connection;
			}
		];

		register_graphql_connection( array_merge( $default, $config ) );
	}
}
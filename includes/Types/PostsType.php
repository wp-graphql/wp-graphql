<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Types\PostType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;

class PostsType extends AbstractObjectType {

	/**
	 * getDescription
	 *
	 * Returns the description for the PostsType
	 *
	 * @return mixed
	 * @since 0.0.01
	 */
	public function getDescription() {
		return __( 'The base PostsType with info about the query and a list of queried items', 'wp-graphqhl' );
	}

	/**
	 * build
	 *
	 * Defines the Object Type
	 *
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * @todo: add more query_vars to the fields for better introspection into queries
		 */

		$config->addField(
			'items',
			[
				'type' => new ListType( new PostType() ),
				'description' => __( 'List of items matching the query', 'dfm-graphql-endpoints' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value->posts ) && is_array( $value->posts ) ) ? $value->posts : [];
				},
			]
		);

		$config->addField(
			'per_page',
			[
				'type' => new IntType(),
				'description' => __( 'The number of items displayed in the current paginated request', 'dfm-graphql-endpoints' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->query_vars['posts_per_page'] ) ? $value->query_vars['posts_per_page'] : 0;
				}
			]
		);

		$config->addField(
			'total',
			[
				'type' => new NonNullType( new IntType() ),
				'description' => __( 'The total number of items that match the query', 'dfm-graphql-endpoints' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->found_posts ) ? absint( $value->found_posts ) : 0;
				}
			]

		);

		$config->addField(
			'total_pages',
			[
				'type' => new NonNullType( new IntType() ),
				'description' => __( 'The total number of pages', 'dfm-graphql-endpoints' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {

					$total_pages = 0;

					if ( ! empty( $value->found_posts ) && ! empty( $value->query_vars['posts_per_page'] ) ) {
						$total_pages = absint( $value->found_posts ) / absint( $value->query_vars['posts_per_page'] );
					}

					return absint( $total_pages );

				}
			]

		);

	}

}
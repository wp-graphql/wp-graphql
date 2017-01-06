<?php
namespace DFM\WPGraphQL\Types\PostObject;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;

class PostObjectsType extends AbstractObjectType {

	/**
	 * getPostType
	 *
	 * Returns the post type
	 *
	 * @return callable|mixed|null|string
	 * @since 0.0.2
	 */
	public function getPostType() {

		/**
		 * Check if the post_type was passed down in the config
		 */
		$post_type = $this->getConfig()->get( 'post_type' );

		/**
		 * Check if the post_type is a populated string, otherwise fallback to the
		 * default "post" type
		 */
		$post_type = ( ! empty( $config_post_type ) && is_string( $post_type ) ) ? $post_type : 'post';

		/**
		 * Ensure the Query only contains letters and numbers
		 */
		$post_type = preg_replace( '/[^A-Za-z0-9]/i', '', $post_type );

		/**
		 * Return the post_type
		 */
		return $post_type;

	}

	public function getName() {


		/**
		 * Get the post_type
		 */
		$post_type_name = $this->getConfig()->get( 'query_name' );

		/**
		 * Return the name with "Items" appended
		 *
		 * @since 0.0.2
		 */
		return $post_type_name . 'Results';

	}

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
	 * @param $config
	 * @return void
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * @todo: add more query_vars to the fields for better introspection into queries
		 */

		/**
		 * Create the config to pass down to the PostObject
		 */
		$postObjectConfig = [
			'post_type' => $this->getConfig()->get( 'post_type' ),
			'post_type_name' => $this->getConfig()->get( 'post_type_name' ),
		];

		$config->addField(
			'items',
			[
				'type' => new ListType( new PostObjectType( $postObjectConfig ) ),
				'description' => __( 'List of items matching the query', 'wp-graphqhl' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value->posts ) && is_array( $value->posts ) ) ? $value->posts : [];
				},
			]
		);

		$config->addField(
			'per_page',
			[
				'type' => new IntType(),
				'description' => __( 'The number of items displayed in the current paginated request', 'wp-graphqhl' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->query_vars['posts_per_page'] ) ? $value->query_vars['posts_per_page'] : null;
				}
			]
		);

		$config->addField(
			'total',
			[
				'type' => new IntType(),
				'description' => __( 'The total number of items that match the query', 'wp-graphqhl' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->found_posts ) ? absint( $value->found_posts ) : 0;
				}
			]

		);

		$config->addField(
			'total_pages',
			[
				'type' => new NonNullType( new IntType() ),
				'description' => __( 'The total number of pages', 'wp-graphqhl' ),
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
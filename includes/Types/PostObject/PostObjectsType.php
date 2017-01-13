<?php
namespace WPGraphQL\Types\PostObject;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;

/**
 * Class PostObjectsType
 *
 * Defines the PostObjectsType
 * @package WPGraphQL\Types\TermObject
 * @since 0.0.2
 */
class PostObjectsType extends AbstractObjectType {

	/**
	 * getName
	 *
	 * Returns the name of the type
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {

		/**
		 * Get the query_name
		 */
		$query_name = $this->getConfig()->get( 'query_name' );

		/**
		 * Return the name with "Results" appended
		 *
		 * @since 0.0.2
		 */
		return $query_name;

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
			'query_name' => $this->getConfig()->get( 'query_name' ),
		];


		$fields = [
			'items' =>	[
				'type' => new ListType( new PostObjectType( $postObjectConfig ) ),
				'description' => __( 'List of items matching the query', 'wp-graphqhl' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ( ! empty( $value->posts ) && is_array( $value->posts ) ) ? $value->posts : [];
				},
			],
			'per_page' => [
				'type' => new IntType(),
				'description' => __( 'The number of items displayed in the current paginated request', 'wp-graphqhl' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->query_vars['posts_per_page'] ) ? $value->query_vars['posts_per_page'] : null;
				}
			],
			'total' => [
				'type' => new IntType(),
				'description' => __( 'The total number of items that match the query', 'wp-graphqhl' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->found_posts ) ? absint( $value->found_posts ) : 0;
				}
			],
			'total_pages' => [
				'type' => new NonNullType( new IntType() ),
				'description' => __( 'The total number of pages', 'wp-graphqhl' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					$total_pages = 0;
					if ( ! empty( $value->found_posts ) && ! empty( $value->query_vars['posts_per_page'] ) ) {
						$total_pages = absint( $value->found_posts ) / absint( $value->query_vars['posts_per_page'] );
					}
					return absint( $total_pages );
				}
			],
		];

		/**
		 * Filter the fields that are part of the PostObjectsType
		 * @since 0.0.2
		 */
		$fields = apply_filters( 'graphql_post_objects_type_fields_' . $this->getConfig()->get( 'post_type' ), $fields );

		/**
		 * Add the fields
		 */
		$config->addFields( $fields );

	}

}
<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use WPGraphQL\Type\Enum\PostObjectOrderbyEnumType;
use WPGraphQL\Type\Enum\PostStatusEnumType;
use WPGraphQL\Types;

class PostObjectQueryArgsType extends InputObjectType {

	public function __construct() {

		$config = [
			'name'   => 'queryArgs',
			'fields' => function() {
				$fields = [
					'author'        => [
						'type'        => Types::int(),
						'description' => __( 'The user that\'s connected as the author of the object. 
									Use the userId for the author object.', 'wp-graphql' ),
					],
					'authorName'    => [
						'type' => Types::string(),
					],
					'cat'           => [
						'type'        => Types::int(),
						'description' => __( 'Category ID', 'wp-graphql' ),
					],
					'categoryName'  => [
						'type'        => Types::string(),
						'description' => __( 'Use Category Slug', 'wp-graphql' ),
					],
					'categoryAnd'   => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Array of category IDs, used to display objects in one 
									category AND another', 'wp-graphql' ),
					],
					'categoryIn'    => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Array of category IDs, used to display objects from one 
									category OR another', 'wp-graphql' ),
					],
					'categoryNotIn' => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Array of category IDs, used to exclude objects in specified 
									categories', 'wp-graphql' ),
					],
					'taxQuery'      => [
						'type' => Types::tax_query(),
						'description' => __( 'Query objects by taxonomy parameters', 'wp-graphql' ),
					],
					/**
					 * Post & Page Parameters
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Post_.26_Page_Parameters
					 * @since 0.0.2
					 */
					// was 'p'
					'id'            => [
						'type'        => Types::int(),
						'description' => __( 'Specific ID of the object', 'wp-graphql' ),
					],
					'name'          => [
						'type'        => Types::string(),
						'description' => __( 'Slug / post_name of the object', 'wp-graphql' ),
					],
					'title'         => [
						'type'        => Types::string(),
						'description' => __( 'Title of the object', 'wp-graphql' ),
					],
					'parent'        => [
						'type'        => Types::string(),
						'description' => __( 'Use ID to return only children. Use 0 to return only top-level items', 'wp-graphql' ),
					],
					'parentIn'      => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Specify objects whose parent is in an array', 'wp-graphql' ),
					],
					'parentNotIn'   => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Specify posts whose parent is not in an array', 'wp-graphql' ),
					],
					'in'            => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Array of IDs for the objects to retrieve', 'wp-graphql' ),
					],
					'notIn'         => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Specify IDs NOT to retrieve. If this is used in the same query as "in", it will be ignored', 'wp-graphql' ),
					],
					'nameIn'        => [
						'type'        => Types::list_of( Types::string() ),
						'description' => __( 'Specify objects to retrieve. Use slugs', 'wp-graphql' ),
					],
					/**
					 * Password parameters
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Password_Parameters
					 * @since 0.0.2
					 */
					'hasPassword'   => [
						'type'        => Types::string(),
						'description' => __( 'True for objects with passwords; False for objects without passwords; null for all objects with or without passwords', 'wp-graphql' ),
					],
					'password'      => [
						'type'        => Types::string(),
						'description' => __( 'Show posts with a specific password.', 'wp-graphql' ),
					],

					/**
					 * post_type
					 *
					 * NOTE: post_type is intentionally not supported as it's the post_type is the entity entry point for the queries
					 *
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Type_Parameters
					 * @since 0.0.2
					 */

					/**
					 * Status parameters
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Status_Parameters
					 * @since 0.0.2
					 */
					'status'        => [
						'type'        => Types::post_status_enum(),
					],

					/**
					 * Order & Orderby parameters
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters
					 * @since 0.0.2
					 */
					'orderby'       => [
						'type'        => Types::post_object_orderby_enum(),
						'description' => sprintf( __( 'What paramater to use to order the %s by.', 'wp-graphql' ), $post_type_object->graphql_plural_name ),
					],
					'dateQuery' => Types::date_query(),
					'metaQuery' => Types::meta_query(),
					'mimeType' => [
						'type' => Types::mime_type_enum(),
						'description' => __( 'Get objects with a specific mimeType property', 'wp-graphql' ),
					],
				];

				ksort( $fields );
				return $fields;
			},
		];

		parent::__construct( $config );

	}

}

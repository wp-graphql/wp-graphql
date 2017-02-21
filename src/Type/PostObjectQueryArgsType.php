<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use WPGraphQL\Types;

class PostObjectQueryArgsType extends InputObjectType {

	private static $orderby_enum;

	public function __construct() {

		$config = [
			'name'   => 'queryArgs',
			'fields' => function() {
				$fields = [

					/**
					 * Author $args
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Author_Parameters
					 * @since 0.0.5
					 */
					'author'        => [
						'type'        => Types::int(),
						'description' => __( 'The user that\'s connected as the author of the object. Use the 
						userId for the author object.', 'wp-graphql' ),
					],
					'authorName'    => [
						'type'        => Types::string(),
						'description' => __( 'Find objects connected to the author by the author\'s "nicename"', 'wp-graphql' ),
					],
					'authorIn'      => [
						'type'        => Types::list_of( Types::id() ),
						'description' => __( 'Find objects connected to author(s) in the array of author\'s userIds', 'wp-graphql' ),
					],
					'authorNotIn'   => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Find objects NOT connected to author(s) in the array of author\'s 
						userIds', 'wp-graphql' ),
					],

					/**
					 * Category $args
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Category_Parameters
					 * @since 0.0.5
					 */
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

					/**
					 * Tag $args
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Tag_Parameters
					 * @since 0.0.5
					 */
					'tag'           => [
						'type'        => Types::string(),
						'description' => __( 'Tag Slug', 'wp-graphql' ),
					],
					'tagId'         => [
						'type'        => Types::string(),
						'description' => __( 'Use Tag ID', 'wp-graphql' ),
					],
					'tagAnd'        => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Array of tag IDs, used to display objects in one tag AND 
						another', 'wp-graphql' ),
					],
					'tagIn'         => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Array of tag IDs, used to display objects from one tag OR 
						another', 'wp-graphql' ),
					],
					'tagNotIn'      => [
						'type'        => Types::list_of( Types::int() ),
						'description' => __( 'Array of tag IDs, used to exclude objects in specified 
						tags', 'wp-graphql' ),
					],
					'tagSlugAnd'    => [
						'type'        => Types::list_of( Types::string() ),
						'description' => __( 'Array of tag slugs, used to display objects from one tag OR 
						another', 'wp-graphql' ),
					],
					'tagSlugIn'     => [
						'type'        => Types::list_of( Types::string() ),
						'description' => __( 'Array of tag slugs, used to exclude objects in specified 
						tags', 'wp-graphql' ),
					],

					/**
					 * Search Parameter
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Search_Parameter
					 * @since 0.0.5
					 */
					'search'        => [
						'name'        => 'search',
						'type'        => Types::string(),
						'description' => __( 'Show Posts based on a keyword search', 'wp-graphql' ),
					],

					/**
					 * Post & Page Parameters
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Post_.26_Page_Parameters
					 * @since 0.0.5
					 */
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
						'description' => __( 'Use ID to return only children. Use 0 to return only top-level 
						items', 'wp-graphql' ),
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
						'description' => __( 'Specify IDs NOT to retrieve. If this is used in the same query as "in", 
						it will be ignored', 'wp-graphql' ),
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
						'description' => __( 'True for objects with passwords; False for objects without passwords; 
						null for all objects with or without passwords', 'wp-graphql' ),
					],
					'password'      => [
						'type'        => Types::string(),
						'description' => __( 'Show posts with a specific password.', 'wp-graphql' ),
					],

					/**
					 * post_type
					 *
					 * NOTE: post_type is intentionally not supported as it's the post_type is the entity entry
					 * point for the queries
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
						'type' => Types::post_status_enum(),
					],

					/**
					 * Order & Orderby parameters
					 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters
					 * @since 0.0.2
					 */
					'orderby'       => [
						'type'        => self::orderby_enum(),
						'description' => __( 'What paramater to use to order the objects by.', 'wp-graphql' ),
					],
					'dateQuery'     => Types::date_query(),
					'mimeType'      => [
						'type'        => Types::mime_type_enum(),
						'description' => __( 'Get objects with a specific mimeType property', 'wp-graphql' ),
					],
				];

				/**
				 * Filter the input fields.
				 *
				 * This allows plugins/themes to hook in and alter what input fields should be available for use
				 * with PostObjectQueries (WP_Query)
				 *
				 * @since 0.0.5
				 */
				$fields = apply_filters( 'graphql_post_object_query_args_type_fields', $fields );

				/**
				 * Sort the fields alphabetically by key. This makes reading through docs much easier
				 * @since 0.0.2
				 */
				ksort( $fields );

				return $fields;
			},
		];

		parent::__construct( $config );

	}

	/**
	 * orderby_enum
	 *
	 * This returns the orderby enum type for the PostObjectQueryArgs
	 *
	 * @return EnumType
	 * @since 0.0.5
	 */
	private static function orderby_enum() {

		if ( null === self::$orderby_enum ) {

			self::$orderby_enum = new EnumType( [
				'name'   => 'orderby',
				'values' => [
					[
						'name'        => 'NONE',
						'value'       => 'none',
						'description' => __( 'No order', 'wp-graphql' ),
					],
					[
						'name'        => 'ID',
						'value'       => 'ID',
						'description' => __( 'Order by the object\'s id. Note the 
									capitalization', 'wp-graphql' ),
					],
					[
						'name'        => 'AUTHOR',
						'value'       => 'author',
						'description' => __( 'Order by author', 'wp-graphql' ),
					],
					[
						'name'        => 'TITLE',
						'value'       => 'title',
						'description' => __( 'Order by title', 'wp-graphql' ),
					],
					[
						'name'        => 'SLUG',
						'value'       => 'name',
						'description' => __( 'Order by slug', 'wp-graphql' ),
					],
					[
						'name'        => 'DATE',
						'value'       => 'date',
						'description' => __( 'Order by date', 'wp-graphql' ),
					],
					[
						'name'        => 'MODIFIED',
						'value'       => 'modified',
						'description' => __( 'Order by last modified date', 'wp-graphql' ),
					],
					[
						'name'        => 'PARENT',
						'value'       => 'parent',
						'description' => __( 'Order by parent ID', 'wp-graphql' ),
					],
					[
						'name'        => 'COMMENT_COUNT',
						'value'       => 'comment_count',
						'description' => __( 'Order by number of comments', 'wp-graphql' ),
					],
					[
						'name'        => 'RELEVANCE',
						'value'       => 'relevance',
						'description' => __( 'Order by search terms in the following order: First, whether 
									the entire sentence is matched. Second, if all the search terms are within the 
									titles. Third, if any of the search terms appear in the titles. And, fourth, if the 
									full sentence appears in the contents.', 'wp-graphql' ),
					],
					[
						'name'        => 'IN',
						'value'       => 'post__in',
						'description' => __( 'Preserve the ID order given in the IN array', 'wp-graphql' ),
					],
					[
						'name'        => 'NAME_IN',
						'value'       => 'post_name__in',
						'description' => __( 'Preserve slug order given in the NAME_IN array', 'wp-graphql' ),
					],
				],
			] );

		}

		return self::$orderby_enum;

	}

}

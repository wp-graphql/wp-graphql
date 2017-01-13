<?php
namespace WPGraphQL\Types\PostObject;

use WPGraphQL\Types\PostObject\TaxQuery\TaxQueryType;
use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostObjectQueryArgs
 *
 * This class sets up the args that can be passed to the PostObjectQueryType (WP_Query)
 * The args map closely to the args in WordPress core's WP_Query with some subtle
 * differences in naming, and some fields were intentionally left out, such as the "post_type" as it
 *
 *
 * @package WPGraphQL\Entities\PostObject
 */
class PostObjectQueryArgs extends AbstractInputObjectType {

	/**
	 * getName
	 *
	 * Establishes the name of the Query Args
	 *
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {

		$query_name = $this->getConfig()->get( 'query_name' );
		$name = ! empty( $query_name ) ? $query_name : 'Post';
		return $name . 'Args';

	}

	/**
	 * build
	 *
	 * This builds out the PostObjectQueryArgs
	 *
	 * @since 0.0.2
	 * @param $config
	 * @return mixed|void
	 */
	public function build( $config ) {

		/**
		 * Category Parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Category_Parameters
		 * @since 0.0.1
		 */
		$category_fields = [
			[
				'name' => 'cat',
				'type' => new IntType(),
				'description' => __( 'Category ID', 'wp-graphql' ),
			],
			[
				'name' => 'category_name',
				'type' => new StringType(),
				'description' => __( 'Use Category Slug', 'wp-graphql' ),
			],
			[
				'name' => 'category__and',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of category IDs, used to display objects in one category AND another', 'wp-graphql' ),
			],
			[
				'name' => 'category__in',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of category IDs, used to display objects from one category OR another', 'wp-graphql' ),
			],
			[
				'name' => 'category__not_in',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of category IDs, used to exclude objects in specified categories', 'wp-graphql' ),
			],
		];


		/**
		 * Tag Parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Category_Parameters
		 * @since 0.0.2
		 */
		$tag_fields = [
			[
				'name' => 'tag',
				'type' => new StringType(),
				'description' => __( 'Tag Slug', 'wp-graphql' ),
			],
			[
				'name' => 'tag_id',
				'type' => new StringType(),
				'description' => __( 'Use Tag ID', 'wp-graphql' ),
			],
			[
				'name' => 'tag__and',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of tag IDs, used to display objects in one tag AND another', 'wp-graphql' ),
			],
			[
				'name' => 'tag__in',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of tag IDs, used to display objects from one tag OR another', 'wp-graphql' ),
			],
			[
				'name' => 'tag__not_in',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of tag IDs, used to exclude objects in specified tags', 'wp-graphql' ),
			],
			[
				'name' => 'tag_slug__and',
				'type' => new ListType( new StringType() ),
				'description' => __( 'Array of tag slugs, used to display objects from one tag OR another', 'wp-graphql' ),
			],
			[
				'name' => 'tag_slug__in',
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of tag slugs, used to exclude objects in specified tags', 'wp-graphql' ),
			],
		];

		/**
		 * Taxonomy Parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Taxonomy_Parameters
		 * @since 0.0.2
		 */
		$taxonomy_fields = [
			[
				'name' => 'tax_query',
				'type' => new TaxQueryType(),
				// 'type' => new StringType(),
				'description' => __( 'Query objects using Taxonomy paramaters', 'wp-graphql' ),
			],
		];

		/**
		 * Search Parameter
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Search_Parameter
		 * @since 0.0.2
		 */
		$search_fields = [
			[
				'name' => 'search', // originally "s"
				'type' => new StringType(),
				'description' => __( 'Show Posts based on a keyword search', 'wp-graphql' ),
			]
		];

		/**
		 * Post & Page Parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Post_.26_Page_Parameters
		 * @since 0.0.2
		 */
		$post_page_fields = [
			[
				'name' => 'id', // originally "p"
				'type' => new IntType(),
				'description' => __( 'Specific ID of the object', 'wp-graphql' ),
			],
			[
				'name' => 'name',
				'type' => new StringType(),
				'description' => __( 'Slug / post_name of the object', 'wp-graphql' ),
			],
			[
				'name' => 'title',
				'type' => new StringType(),
				'description' => __( 'Title of the object', 'wp-graphql' ),
			],
			[
				'name' => 'parent', // originally "post_parent"
				'type' => new StringType(),
				'description' => __( 'Use ID to return only children. Use 0 to return only top-level items', 'wp-graphql' ),
			],
			[
				'name' => 'parent__in', // originally "post_parent__in"
				'type' => new ListType( new IntType() ),
				'description' => __( 'Specify objects whose parent is in an array', 'wp-graphql' ),
			],
			[
				'name' => 'parent__not_in', // originally "post_parent__not_in"
				'type' => new ListType( new IntType() ),
				'description' => __( 'Specify posts whose parent is not in an array', 'wp-graphql' ),
			],
			[
				'name' => 'in', // originally post__in
				'type' => new ListType( new IntType() ),
				'description' => __( 'Array of IDs for the objects to retrieve', 'wp-graphql' ),
			],
			[
				'name' => 'not_in', // originally "post__not_in"
				'type' => new ListType( new IntType() ),
				'description' => __( 'Specify IDs NOT to retrieve. If this is used in the same query as "in", it will be ignored', 'wp-graphql' ),
			],
			[
				'name' => 'name__in', // originally "post_name__in"
				'type' => new ListType( new StringType() ),
				'description' => __( 'Specify objects to retrieve. Use slugs', 'wp-graphql' ),
			],
		];

		/**
		 * Password parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Password_Parameters
		 * @since 0.0.2
		 */
		$password_fields = [
			[
				'name' => 'has_password',
				'type' => new StringType(),
				'description' => __( 'True for objects with passwords; False for objects without passwords; null for all objects with or without passwords', 'wp-graphql' ),
			],
			[
				'name' => 'password',
				'type' => new StringType(),
				'description' => __( 'Show posts with a specific password.', 'wp-graphql' )
			],
		];

		/**
		 * Post Type parameters
		 *
		 * post_type is not supported as it's the post_type is the entity entry point for the queries
		 *
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Type_Parameters
		 * @since 0.0.2
		 */

		/**
		 * Status parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Status_Parameters
		 * @since 0.0.2
		 */
		$status_fields = [
			[
				'name' => 'status',
				// @todo: convert to enum of post_stati
				'type' => new StringType(),
				'description' => __( 'Use post status. Retrieves posts by Post Status. Default value is \'publish\'.', 'wp-graphql' ),
			],
		];

		/**
		 * Pagination parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Pagination_Parameters
		 * @since 0.0.2
		 */
		$pagination_fields = [
			[
				'name' => 'nopaging',
				'type' => new BooleanType(),
				'description' => __( 'True to not use pagination. False to use pagination. Default: False', 'wp-graphql' ),
			],
			[
				'name' => 'per_page',
				'type' => new IntType(),
				'description' => __( 'Number of items to show per page.', 'wp-graphql' ),
			],

			// @todo: discuss implementing posts_per_archive_page field

			[
				'name' => 'offset',
				'type' => new StringType(),
				'description' => __( 'Number of items to displace or pass over. WARNING: Setting the offset overrides/ignores the paged parameter and breaks pagination.', 'wp-graphql' ),
			],
			[
				'name' => 'paged',
				'type' => new IntType(),
				'description' => __( 'Number of page. Show the posts that would normally show up just on page X.', 'wp-graphql' ),
			],

			// @todo: discuss implementing page field

			[
				'name' => 'ignore_sticky_posts',
				'type' => new BooleanType(),
				'description' => __( 'Ignore object stickiness', 'wp-graphql' ),
			],
		];

		/**
		 * Order & Orderby parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters
		 * @since 0.0.2
		 */
		$order_fields = [
			[
				'name' => 'order',
				'type' => new ListType( new StringType() ), // @todo: convert to enum
				'description' => __( 'Designates the ascending or descending order of the \'orderby\' parameter. Defaults to \'DESC\'.', 'wp-graphql' ),
			],
			[
				'name' => 'orderby',
				// @todo: convert to enum type of possible orderby paramaters
				'type' => new ListType( new StringType() ),
				'description' => __( 'Number of items to show per page.', 'wp-graphql' ),
			],
		];

		/**
		 * Date parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Date_Parameters
		 * @since 0.0.2
		 */
		$date_fields = [
			[
				'name' => 'year',
				'type' => new IntType(),
				'description' => __( '4 digit year (e.g. 2016', 'wp-graphql' ),
			],
			[
				'name' => 'monthnum',
				// @todo: convert to enum
				'type' => new IntType(),
				'description' => __( 'Month number. (from 1 to 12)', 'wp-graphql' ),
			],
			[
				'name' => 'w',
				// @todo: convert to enum
				'type' => new IntType(),
				'description' => __( 'Week of the year. Note: This is dependent on the "start_of_week" option.', 'wp-graphql' ),
			],
			[
				'name' => 'day',
				// @todo: convert to enum
				'type' => new IntType(),
				'description' => __( 'Day of the month (from 1 to 31)', 'wp-graphql' ),
			],
			[
				'name' => 'hour',
				// @todo: convert to enum
				'type' => new IntType(),
				'description' => __( 'Hour of the day (from 0 to 23)', 'wp-graphql' ),
			],
			[
				'name' => 'minute',
				// @todo: convert to enum
				'type' => new IntType(),
				'description' => __( 'Minute (from 0 to 60)', 'wp-graphql' ),
			],
			[
				'name' => 'second',
				// @todo: convert to enum
				'type' => new IntType(),
				'description' => __( 'Second (from 0 to 60)', 'wp-graphql' ),
			],
			[
				// @todo: support a different name instead of "m"? like "yearmonth?"
				'name' => 'm',
				'type' => new IntType(),
				'description' => __( 'YearMonth (For e.g.: 201307)', 'wp-graphql' ),
			],
//			[
//				'name' => 'date_query',
//				// @todo: convert to DateQuery type
//				'type' => new StringType(),
//				'description' => __( 'Advanced date paramaters', 'wp-graphql' ),
//			],
		];

		/**
		 * Custom Field parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Custom_Field_Parameters
		 * @since 0.0.2
		 */
		$meta_fields = [
			[
				'name' => 'meta_key',
				'type' => new StringType(),
				'description' => __( 'Custom field key', 'wp-graphql' ),
			],
			[
				'name' => 'meta_value',
				'type' => new StringType(),
				'description' => __( 'Custom field value', 'wp-graphql' ),
			],
			[
				'name' => 'meta_value_num',
				'type' => new IntType(),
				'description' => __( 'Custom field value, if integer', 'wp-graphql' ),
			],
			[
				'name' => 'meta_compare',
				'type' => new StringType(), // @todo: convert to enum
				'description' => __( 'Operator to test the \'meta_value\'. Possible values are \'=\', \'!=\', \'>\', \'>=\', \'<\', \'<=\', \'LIKE\', \'NOT LIKE\', \'IN\', \'NOT IN\', \'BETWEEN\', \'NOT BETWEEN\', \'NOT EXISTS\', \'REGEXP\', \'NOT REGEXP\' or \'RLIKE\'. Default value is \'=\'.', 'wp-graphql' ),
			],
//			[
//				'name' => 'meta_query',
//				// @todo: Support meta_query
//				'type' => new StringType(),
//				'description' => __( 'Advanced custom meta field queries', 'wp-graphql' ),
//			],
		];

		/**
		 * Permission parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Permission_Parameters
		 * @since 0.0.2
		 *
		 * @todo: need to look at this field a bit more to see the best way to implement
		 */

		/**
		 * Mime Type parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Mime_Type_Parameters
		 * @since 0.0.2
		 */
		$permission_fields = [
			[
				'name' => 'mime_type',
				// @todo: possibly convert to enum of existing mime types?
				'type' => new ListType( new StringType() ),
				'description' => __( 'Allowed mime types', 'wp-graphql' ),
			],
		];

		/**
		 * Caching Paramaters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Caching_Parameters
		 * @since 0.0.2
		 */
		$caching_fields = [
			[
				'name' => 'cache_results',
				'type' => new BooleanType(),
				'description' => __( 'Should the object be added to the cache?', 'wp-graphql' ),
			],
			[
				'name' => 'update_meta_cache', // update_post_meta_cache
				'type' => new BooleanType(),
				'description' => __( 'Should the object custom meta added to the cache?', 'wp-graphql' ),
			],
			[
				'name' => 'update_term_cache', // update_post_term_cache
				'type' => new BooleanType(),
				'description' => __( 'Should the object terms be added to the cache?', 'wp-graphql' ),
			],
		];

		/**
		 * Fields
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Return_Fields_Parameter
		 * @since 0.0.2
		 */
		$return_fields = [
			[
				'name' => 'fields',
				'type' => new StringType(), // @todo: convert to enum (ids, id=>parent, any)
				'description' => __( 'Which fields should be returned? All fields returned by default. There are 2 other options: "ids", "id=>parent", anything else will return all fields', 'wp-graphql' ),
			]
		];

		/**
		 * Merge the fields
		 */
		$fields = array_merge(
			$category_fields,
			$tag_fields,
			$taxonomy_fields,
			$search_fields,
			$post_page_fields,
			$password_fields,
			$status_fields,
			$pagination_fields,
			$order_fields,
			$date_fields,
			$meta_fields,
			$permission_fields,
			$caching_fields,
			$return_fields
		);

		/**
		 * Filter the fields that are passed to the query args
		 * @since 0.0.1
		 */
		$fields = apply_filters( 'graphql_query_args_fields_' . $this->getConfig()->get( 'post_type' ), $fields, $config );

		/**
		 * If there are fields, add them to the config
		 */
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			$config->addFields( $fields );
		}

	}

}
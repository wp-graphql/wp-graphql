<?php
namespace DFM\WPGraphQL\Queries;

use DFM\WPGraphQL\Types\PostsType;
use DFM\WPGraphQL\Types\QueryTypes\TaxQuery\TaxQueryType;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostsQuery
 *
 * Define the PostsQuery
 *
 * @package DFM\WPGraphQL\Queries
 * @since 0.0.1
 */
class PostsQuery extends AbstractField {

	/**
	 * getPostType
	 *
	 * Returns the post type for this query
	 *
	 * @return string
	 * @since 0.0.2
	 */
	public function getPostType() {
		return 'post';
	}

	/**
	 * getDefaultPostsPerPage
	 *
	 * Returns the default number of posts_per_page
	 *
	 * @return int
	 * @since 0.0.2
	 */
	public function getDefaultPostsPerPage() {
		return 10;
	}

	/**
	 * getName
	 *
	 * This returns the name of the query
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getName() {
		return __( 'posts', 'wp-graphql' );
	}

	/**
	 * getType
	 *
	 * This defines the type that returns for the ArticleQuery
	 *
	 * @return ListType
	 * @since 0.0.1
	 */
	public function getType() {
		return new PostsType();
	}

	/**
	 * getDescription
	 *
	 * This returns the description of the ArticleQuery
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'Retrieve a list of posts', 'dfm-graphql-endpoints' );
	}

	/**
	 * resolve
	 *
	 * This defines the
	 *
	 * @since 0.0.1
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return array
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {

		// Set the default $query_args
		$query_args = [
			'post_type' => $this->getPostType(),
			'posts_per_page' => $this->getDefaultPostsPerPage(),
		];

		/**
		 * Convert the Schema friendly names to the WP_Query friendly names
		 */
		$query_args['s'] = $args['search'];
		$query_args['p'] = $args['id'];
		$query_args['post_parent'] = $args['parent'];
		$query_args['post_parent__in'] = $args['parent__in'];
		$query_args['post_parent__not_in'] = $args['parent__not_in'];
		$query_args['post__in'] = $args['in'];
		$query_args['post__not_in'] = $args['not_in'];
		$query_args['name_in'] = $args['post_name__in'];

		/**
		 * Clean up the Schema friendly names so they're not cluttering the args that are
		 * sent to the WP_Query
		 */
		unset( $args['search'] );
		unset( $args['id'] );
		unset( $args['parent'] );
		unset( $args['parent__in'] );
		unset( $args['in'] );
		unset( $args['not_in'] );
		unset( $args['post_name__in'] );

		// Combine the default $query_arts with the $args passed by the query
		$query_args = wp_parse_args( $args, $query_args );

		// Make sure the per_page has a max of 100
		// as we don't want to overload things
		$query_args['posts_per_page'] = ( ! empty( $args['per_page'] ) && 100 >= ( $args['per_page'] ) ) ? $args['per_page'] : $query_args['posts_per_page'];

		// Clean up the unneeded $query_args
		unset( $query_args['per_page'] );

		// Run the Query
		$articles = new \WP_Query( $query_args );

		// Return the posts from the WP_Query
		return $articles;

	}

	/**
	 * build
	 *
	 * This adds the arguments to the ArticleQuery
	 *
	 * @param FieldConfig $config
	 * @since 0.0.1
	 */
	public function build( FieldConfig $config ) {

		/**
		 * Author Paramaters
		 * @see https://codex.wordpress.org/Class_Reference/WP_Query#Author_Parameters
		 * @since 0.0.1
		 */
		$config->addArguments(
			[
				[
					'name' => 'author',
					'type' => new IntType(),
					'description' => __( 'Author ID', 'wp-graphql' )
				],
				[
					'name' => 'author_name',
					'type' => new StringType(),
					'description' => __( 'Author nicename (NOT name)', 'wp-graphql' )
				],
				[
					'name' => 'author__in',
					'type' => new ListType( new IntType() ),
					'description' => __( 'List of Author IDs to include', 'wp-graphql' )
				],
				[
					'name' => 'author__not_in',
					'type' => new ListType ( new IntType() ),
					'description' => __( 'List of Author IDs to exclude', 'wp-graphql' )
				]
			]
		);

		/**
		 * Category Paramaters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Category_Parameters
		 * @since 0.0.1
		 */
		$config->addArguments(
			[
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
			]
		);

		/**
		 * Tag Paramaters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Category_Parameters
		 * @since 0.0.2
		 */
		$config->addArguments(
			[
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
			]
		);

		/**
		 * Taxonomy Paramaters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Taxonomy_Parameters
		 * @since 0.0.2
		 */
		$config->addArguments(
			[
				[
					'name' => 'tax_query',
					'type' => new TaxQueryType(),
					'description' => __( 'Query objects using Taxonomy paramaters', 'wp-graphql' ),
				]
			]
		);

		/**
		 * Search Paramater
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Search_Parameter
		 * @since 0.0.2
		 */
		$config->addArgument(
			'search', // originally "s"
			[
				'type' => new StringType(),
				'description' => __( 'Show Posts based on a keyword search', 'wp-graphql' ),
			]
		);

		/**
		 * Post & Page Parameters
		 * @see: https://codex.wordpress.org/Class_Reference/WP_Query#Post_.26_Page_Parameters
		 * @since 0.0.2
		 */
		$config->addArguments(
			[
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
			]
		);


		$config->addArgument(
			'per_page',
			[
				'type' => new IntType(),
				'description' => __( 'The number of items to query, with a max of 100', 'wp-graphql' )
			]
		);

		$config->addArgument(
			'paged',
			[
				'type' => new IntType(),
				'description' => __( 'The Number of page to show', 'wp-graphql' )
			]
		);

		$config->addArgument(
			'post_type',
			[
				'type' => new StringType(),
				'description' => __( 'Type of post', 'wp-graphql' )
			]
		);

	}

}
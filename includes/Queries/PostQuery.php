<?php
namespace DFM\WPGraphQL\Queries;

use DFM\WPGraphQL\Types\PostsType;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostQuery
 *
 * Define the PostQuery
 *
 * @package DFM\WPGraphQL\Queries
 * @since 0.0.1
 */
class PostQuery extends AbstractField  {

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
			'post_type' => 'post',
			'posts_per_page' => 10,
		];

		// Combine the defaults with the passed args
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
					'description' => __( 'Category ID', 'wp-graphql' )
				],
				[
					'name' => 'category_name',
					'type' => new StringType(),
					'description' => __( 'Use Category Slug', 'wp-graphql' )
				]
			]
		);
		$config->addArgument( 'category__and', new ListType( new IntType() ) );
		$config->addArgument( 'category__in', new ListType( new StringType() ) );
		$config->addArgument( 'category__not_in', new ListType( new StringType() ) );

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

		// @todo: add descriptions to all the following arguments
		$config->addArgument( 'tag', new StringType() );
		$config->addArgument( 'tag_id', new IntType() );
		$config->addArgument( 'tag__and', new ListType( new IntType() ) );
		$config->addArgument( 'tag__in', new ListType( new IntType() ) );
		$config->addArgument( 'tag__not_in', new ListType( new IntType() ) );
		$config->addArgument( 'tag_slug__and', new ListType( new StringType() ) );
		$config->addArgument( 'tag_slug__in', new ListType( new StringType() ) );

		// Post & Page Paramaters
		$config->addArgument( 'p', new IntType() );
		$config->addArgument( 'name', new StringType() );
		$config->addArgument( 'title', new StringType() );
		$config->addArgument( 'page_id', new IntType() );
		$config->addArgument( 'pagename', new StringType() );
		$config->addArgument( 'post_parent', new StringType() );
		$config->addArgument( 'post_parent__in', new ListType( new IntType() ) );
		$config->addArgument( 'post_parent__not_in', new ListType( new IntType() ) );
		$config->addArgument( 'post__in', new ListType( new IntType() ) );
		$config->addArgument( 'post__not_in', new ListType( new IntType() ) );
		$config->addArgument( 'post_name__in', new ListType( new StringType() ) );


	}

}
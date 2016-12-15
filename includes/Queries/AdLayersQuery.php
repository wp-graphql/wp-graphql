<?php
namespace DFM\WPGraphQL\Queries;

use DFM\WPGraphQL\Types\AdLayersType;
use DFM\WPGraphQL\Types\PostsType;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class AdLayersQuery
 *
 * Define the AdLayersQuery
 *
 * @package DFM\WPGraphQL\Queries
 * @since 0.0.2
 */
class AdLayersQuery extends PostsQuery {

	/**
	 * getName
	 *
	 * This returns the name of the query
	 *
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'ad_layers', 'wp-graphql' );
	}

	/**
	 * getType
	 *
	 * This defines the type that returns for the ArticleQuery
	 *
	 * @return ListType
	 * @since 0.0.2
	 */
	public function getType() {
		return new AdLayersType();
	}

	/**
	 * getDescription
	 *
	 * This returns the description of the ArticleQuery
	 *
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Retrieve a list of posts', 'dfm-graphql-endpoints' );
	}

	/**
	 * resolve
	 *
	 * This defines the
	 *
	 * @since 0.0.2
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return array
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {

		// Set the default $query_args
		$query_args = [
			'post_type' => 'ad-layer',
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

}
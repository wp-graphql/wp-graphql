<?php
namespace DFM\WPGraphQL\Queries;

use DFM\WPGraphQL\Types\TermsType;
use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class TermQuery
 *
 * The TermQuery is used to query terms! It has full parity with get_terms
 *
 * @package DFM\WPGraphQL\Queries
 * @since 0.0.1
 */
class TermQuery extends AbstractField {

	/**
	 * getName
	 *
	 * Returns the name of the query
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getName() {
		return __( 'terms', 'wp-graphql' );
	}

	/**
	 * getType
	 *
	 * Returns the object type the query should return
	 *
	 * TermsType provides details about the TermQuery, as well as the items that are returned by the query
	 *
	 * @return TermsType
	 * @since 0.0.1
	 */
	public function getType() {

		return new TermsType();

	}

	/**
	 * getDescription
	 *
	 * Returns the description of the query
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {

		return __( 'Retrieve a list of terms', 'wp-graphql' );

	}

	/**
	 * resolve
	 *
	 * Processes the query
	 *
	 * TermQuery uses get_terms to retrieve term items and returns a $terms object with
	 * data related to the query, including an array of the queried term objects
	 *
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 * @return \stdClass
	 * @since 0.0.1
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {

		// Create a new $terms object to add data to
		$terms = new \stdClass();

		// Set the $defualt_args
		$query_args = [
			'taxonomy' => 'category',
			'number' => 10,
			'offset' => 0
		];

		// Combine the $default_args with what was passed through the query
		$query_args = wp_parse_args( $args, $query_args );

		// Use the per_page $arg if it's set, and below 100. Otherwise, use the default number value
		$query_args['number'] = ( ! empty( $args['number'] ) && 100 >= ( $args['number'] ) ) ? $args['number'] : $query_args['number'];

		$count_args = $query_args;
		unset( $count_args['number'] );
		unset( $count_args['offset'] );

		// Get the total number of terms for the taxonomy
		$total_terms = wp_count_terms( $query_args['taxonomy'], $count_args );

		// Get the number of terms to show per-page
		$per_page = absint( $query_args['number'] );

		// Get which page we're currently on (current offset divided by page)
		$page = ceil( ( ( (int) $query_args['offset'] ) / $per_page ) + 1 );

		// Add useful data to the $terms array
		$terms->taxonomy = $query_args['taxonomy'];
		$terms->per_page = $per_page;
		$terms->page = $page;
		$terms->items = get_terms( $query_args );
		$terms->total = ( ! empty( $total_terms ) ) ? (int) $total_terms : 0;
		$terms->total_pages = ceil( $total_terms / $per_page );

		// Return an array with data related to the term query, including the terms
		return $terms;

	}

	/**
	 * build
	 *
	 * Adds arguments for the TermQuery
	 *
	 * @param FieldConfig $config
	 * @since 0.0.1
	 */
	public function build( FieldConfig $config ) {

		// @todo: add descriptions to each argument
		$config->addArgument( 'taxonomy', new StringType() );
		$config->addArgument( 'orderby', new StringType() );
		$config->addArgument( 'order', new StringType() );
		$config->addArgument( 'hide_empty', new BooleanType() );
		$config->addArgument( 'include', new ListType( new IntType() ) );
		$config->addArgument( 'exclude', new ListType( new IntType() ) );
		$config->addArgument( 'exclude_tree', new ListType( new IntType() ) );
		$config->addArgument( 'number', new IntType() );
		$config->addArgument( 'offset', new IntType() );
		$config->addArgument( 'fields', new ListType( new StringType() ) );
		$config->addArgument( 'name', new StringType() );
		$config->addArgument( 'slug', new StringType() );
		$config->addArgument( 'hierarchical', new BooleanType() );
		$config->addArgument( 'search', new StringType() );
		$config->addArgument( 'name__like', new StringType() );
		$config->addArgument( 'description__like', new StringType() );
		$config->addArgument( 'pad_counts', new BooleanType() );
		$config->addArgument( 'get', new StringType() );
		$config->addArgument( 'child_of', new IntType() );
		$config->addArgument( 'parent', new IntType() );
		$config->addArgument( 'childless', new BooleanType() );
		$config->addArgument( 'cache_domain', new StringType() );
		$config->addArgument( 'update_term_meta_cache', new BooleanType() );

		// @todo: implement support for meta_query
		// $config->addArgument( 'meta_query', new StringType() );

		$config->addArgument( 'meta_key', new StringType() );
		$config->addArgument( 'meta_value', new StringType() );

	}

}
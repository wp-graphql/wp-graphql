<?php
namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class TaxonomyConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class TaxonomyConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 *
	 * @var array
	 */
	protected $query;

	/**
	 * ContentTypeConnectionResolver constructor.
	 *
	 * @param mixed       $source     source passed down from the resolve tree
	 * @param array       $args       array of arguments input in the field as part of the GraphQL query
	 * @param AppContext  $context    Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info       Info about fields passed down the resolve tree
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {

		$ids     = [];
		$queried = $this->query;

		if ( empty( $queried ) ) {
			return $ids;
		}

		foreach ( $queried as $item ) {
			$ids[] = $item;
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_query_args() {
		// If any args are added to filter/sort the connection
		return [];
	}


	/**
	 * Get the items from the source
	 *
	 * @return array
	 */
	public function get_query() {
		if ( isset( $this->query_args['name'] ) ) {
			return [ $this->query_args['name'] ];
		}

		if ( isset( $this->query_args['in'] ) ) {
			return is_array( $this->query_args['in'] ) ? $this->query_args['in'] : [ $this->query_args['in'] ];
		}

		$query_args = $this->query_args;
		return \WPGraphQL::get_allowed_taxonomies( 'names', $query_args );
	}

	/**
	 * The name of the loader to load the data
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'taxonomy';
	}

	/**
	 * Determine if the offset used for pagination is valid
	 *
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return (bool) get_taxonomy( $offset );
	}

	/**
	 * Determine if the query should execute
	 *
	 * @return bool
	 */
	public function should_execute() {
		return true;
	}

}

<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class ContentTypeConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class ContentTypeConnectionResolver extends AbstractConnectionResolver {

	/**
	 * ContentTypeConnectionResolver constructor.
	 *
	 * @param $source
	 * @param $args
	 * @param $context
	 * @param $info
	 *
	 * @throws \Exception
	 */
	public function __construct( $source, $args, $context, $info ) {
		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * @return bool|int|mixed|null|string
	 */
	public function get_offset() {
		$offset = null;
		if ( ! empty( $this->args['after'] ) ) {
			$offset = substr( base64_decode( $this->args['after'] ), strlen( 'arrayconnection:' ) );
		} elseif ( ! empty( $this->args['before'] ) ) {
			$offset = substr( base64_decode( $this->args['before'] ), strlen( 'arrayconnection:' ) );
		}
		return $offset;
	}

	/**
	 * Get the IDs from the source
	 *
	 * @return array|mixed|null
	 */
	public function get_ids() {

		if ( isset( $this->query_args['name'] ) ) {
			return [ $this->query_args['name'] ];
		}

		$ids     = [];
		$queried = $this->get_query();

		if ( empty( $queried ) ) {
			return $ids;
		}

		foreach ( $queried as $key => $item ) {
			$ids[ $key ] = $item;
		}

		return $ids;

	}

	/**
	 * @return array
	 */
	public function get_query_args() {

		$query_args = [
			'show_in_graphql' => true,
		];

		return $query_args;

	}


	/**
	 * Get the items from the source
	 *
	 * @return array|mixed|null
	 */
	public function get_query() {

		if ( isset( $this->query_args['contentTypeNames'] ) && is_array( $this->query_args['contentTypeNames'] ) ) {
			return $this->query_args['contentTypeNames'];
		}

		$query_args = $this->get_query_args();
		return array_values( get_post_types( $query_args ) );
	}

	/**
	 * Get the nodes from the query.
	 *
	 * We slice the array to match the amount of items that was asked for, as we over-fetched
	 * by 1 item to calculate pageInfo.
	 *
	 * For backward pagination, we reverse the order of nodes.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_nodes() {

		$nodes = parent::get_nodes();

		if ( isset( $this->args['after'] ) ) {
			$key   = array_search( $this->get_offset(), array_keys( $nodes ), true );
			$nodes = array_slice( $nodes, $key + 1, null, true );
		}

		if ( isset( $this->args['before'] ) ) {
			$nodes = array_reverse( $nodes );
			$key   = array_search( $this->get_offset(), array_keys( $nodes ), true );
			$nodes = array_slice( $nodes, $key + 1, null, true );
			$nodes = array_reverse( $nodes );
		}

		$nodes = array_slice( $nodes, 0, $this->query_amount, true );

		return ! empty( $this->args['last'] ) ? array_filter( array_reverse( $nodes, true ) ) : $nodes;
	}

	/**
	 * The name of the loader to load the data
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'post_type';
	}

	/**
	 * Determine if the offset used for pagination is valid
	 *
	 * @param $offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return true;
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

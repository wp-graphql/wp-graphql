<?php
namespace WPGraphQL\Data\Connection;
use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class EnqueuedStylesheetConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class EnqueuedStylesheetConnectionResolver extends AbstractConnectionResolver {

	/**
	 * EnqueuedStylesheetConnectionResolver constructor.
	 *
	 * @param mixed       $source     source passed down from the resolve tree
	 * @param array       $args       array of arguments input in the field as part of the GraphQL query
	 * @param AppContext  $context    Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info       Info about fields passed down the resolve tree
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Filter the query amount to be 1000 for
		 */
		add_filter( 'graphql_connection_max_query_amount', function( $max, $source, $args, $context, ResolveInfo $info ) {
			if ( 'enqueuedStylesheets' === $info->fieldName || 'registeredStylesheets' === $info->fieldName ) {
				return 1000;
			}
			return $max;
		}, 10, 5 );

		parent::__construct( $source, $args, $context, $info );
	}

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
	 * @return array|void
	 */
	public function get_query_args() {
		// If any args are added to filter/sort the connection
	}


	/**
	 * Get the items from the source
	 *
	 * @return array|mixed|null
	 */
	public function get_query() {
		return $this->source->enqueuedStylesheetsQueue ? $this->source->enqueuedStylesheetsQueue : [];
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
	 * @throws Exception
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
		return 'enqueued_stylesheet';
	}

	/**
	 * Determine if the model is valid
	 *
	 * @param array $model
	 *
	 * @return bool
	 */
	protected function is_valid_model( $model ) {
		return isset( $model->handle ) ? true : false;
	}

	/**
	 * Determine if the offset used for pagination is valid
	 *
	 * @param mixed $offset
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

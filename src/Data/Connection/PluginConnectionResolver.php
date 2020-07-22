<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.0.5
 */
class PluginConnectionResolver extends AbstractConnectionResolver {

	/**
	 * PluginConnectionResolver constructor.
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
	 * @return array
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

	}

	/**
	 * @return array|mixed
	 */
	public function get_query() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		// This is missing "must use" and "drop-in" plugins.
		$plugins = apply_filters( 'all_plugins', get_plugins() );
		return array_keys( $plugins );
	}

	/**
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
	 * @return string
	 */
	public function get_loader_name() {
		return 'plugin';
	}

	/**
	 * @param $offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return true;
	}

	/**
	 * @return bool
	 */
	public function should_execute() {
		return current_user_can( 'update_plugins' );
	}

}

<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;

/**
 * Class ThemeConnectionResolver
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.5.0
 */
class ThemeConnectionResolver extends AbstractConnectionResolver {

	/**
	 * @return mixed
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
		return $this->query_args;
	}


	/**
	 * Get the items from the source
	 *
	 * @return array|mixed|null
	 */
	public function get_query() {
		$query_args = $this->get_query_args();
		return array_keys( wp_get_themes( $query_args ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_for_nodes() {
		if ( empty( $this->ids ) ) {
			return [];
		}

		$ids = $this->ids;

		// If pagination is going backwards, revers the array of IDs
		$ids = ! empty( $this->args['last'] ) ? array_reverse( $ids ) : $ids;

		if ( ! empty( $this->get_offset() ) ) {
			// Determine if the offset is in the array
			$key = array_search( $this->get_offset(), $ids, true );
			if ( false !== $key ) {
				$key = absint( $key );
				if ( ! empty( $this->args['before'] ) ) {
					// Slice the array from the back.
					$ids = array_slice( $ids, 0, $key, true );
				} else {
					// Slice the array from the front.
					$key ++;
					$ids = array_slice( $ids, $key, null, true );
				}
			}
		}

		$ids = array_slice( $ids, 0, $this->query_amount, true );

		return $ids;
	}

	/**
	 * The name of the loader to load the data
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'theme';
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

	/**
	 * Creates the connection for themes
	 *
	 * @param mixed       $source  The query results of the query calling this relation
	 * @param array       $args    Query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @since  0.5.0
	 * @return array
	 * @throws \Exception
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$themes_array = [];
		$themes       = wp_get_themes();
		if ( is_array( $themes ) && ! empty( $themes ) ) {
			foreach ( $themes as $theme ) {
				$theme_obj = DataSource::resolve_theme( $theme->get_stylesheet() );
				if ( 'private' !== $theme_obj->get_visibility() ) {
					$themes_array[] = $theme_obj;
				}
			}
		}

		$connection = Relay::connectionFromArray( $themes_array, $args );

		$nodes = [];
		if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
			foreach ( $connection['edges'] as $edge ) {
				$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
			}
		}

		$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

		return ! empty( $themes_array ) ? $connection : [];
	}

}

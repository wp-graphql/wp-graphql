<?php
namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class ContentTypeConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class ContentTypeConnectionResolver extends AbstractConnectionResolver {

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
	 * We're slicing here since the query doesnt support pagination.
	 *
	 * @return array|mixed|null
	 */
	public function get_ids() {

		if ( isset( $this->query_args['name'] ) ) {
			return [ $this->query_args['name'] ];
		}

		$ids     = [];
		$queried = $this->query;

		if ( empty( $queried ) ) {
			return $ids;
		}

		foreach ( $queried as $key => $item ) {
			$ids[ $key ] = $item;
		}

		$offset = $this->get_offset();

		if ( ! empty( $offset ) ) {
			// Determine if the offset is in the array
			$key = array_search( $offset, $ids, true );

			if ( false !== $key ) {
				$key = absint( $key );
				if ( ! empty( $this->args['after'] ) ) {
					// Slice the array from the front.
					$key ++;
					$ids = array_slice( $ids, $key, null, true );
				} else {
					// Slice the array from the back.
					$ids = array_slice( $ids, 0, $key, true );
				}
			}
		}

		// If pagination is going backwards, reverse the array of IDs
		$ids = ! empty( $this->args['last'] ) ? array_reverse( $ids ) : $ids;

		// Slice the array to n+1, so prev/next checks can work.
		$ids = array_slice( $ids, 0, $this->query_amount + 1, true );

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

		$query_args = $this->query_args;
		return array_values( \WPGraphQL::get_allowed_post_types( 'names', $query_args ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Since WP doesnt allow pagination of post types, we slice them in get_query().
	 */
	public function get_ids_for_nodes() {
		if ( empty( $this->ids ) ) {
			return [];
		}

		$ids = $this->ids;

		$ids = array_slice( $ids, 0, $this->query_amount, true );

		// If pagination is going backwards, reverse the array of IDs
		$ids = ! empty( $this->args['last'] ) ? array_reverse( $ids ) : $ids;

		return $ids;
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
	 * @param mixed $offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return get_post_type_object( $offset ) instanceof \WP_Post_Type;
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

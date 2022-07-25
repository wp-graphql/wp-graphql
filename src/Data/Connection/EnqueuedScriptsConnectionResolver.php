<?php
namespace WPGraphQL\Data\Connection;
use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class EnqueuedScriptsConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class EnqueuedScriptsConnectionResolver extends AbstractConnectionResolver {

	/**
	 * EnqueuedScriptsConnectionResolver constructor.
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
		add_filter( 'graphql_connection_max_query_amount', function ( $max, $source, $args, $context, ResolveInfo $info ) {
			if ( 'enqueuedScripts' === $info->fieldName || 'registeredScripts' === $info->fieldName ) {
				return 1000;
			}
			return $max;
		}, 10, 5 );

		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
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
		return $this->source->enqueuedScriptsQueue ? $this->source->enqueuedScriptsQueue : [];
	}

	/**
	 * The name of the loader to load the data
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'enqueued_script';
	}

	/**
	 * Determine if the model is valid
	 *
	 * @param ?\_WP_Dependency $model
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
		global $wp_scripts;
		return isset( $wp_scripts->registered[ $offset ] );
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

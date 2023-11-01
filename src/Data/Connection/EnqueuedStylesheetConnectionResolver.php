<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class EnqueuedStylesheetConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class EnqueuedStylesheetConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 *
	 * @var string[]
	 */
	protected $query;

	/**
	 * {@inheritDoc}
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {

		/**
		 * Filter the query amount to be 1000 for
		 */
		add_filter(
			'graphql_connection_max_query_amount',
			static function ( $max, $source, $args, $context, ResolveInfo $info ) {
				if ( 'enqueuedStylesheets' === $info->fieldName || 'registeredStylesheets' === $info->fieldName ) {
					return 1000;
				}
				return $max;
			},
			10,
			5
		);

		parent::__construct( $source, $args, $context, $info );
	}

	/**
	 * Get the IDs from the source
	 *
	 * @return mixed[]
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
	 * @return string[]
	 */
	public function get_query() {
		return $this->source->enqueuedStylesheetsQueue ? $this->source->enqueuedStylesheetsQueue : [];
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
	 * @param ?\_WP_Dependency $model
	 *
	 * @return bool
	 */
	protected function is_valid_model( $model ) {
		return isset( $model->handle );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ) {
		global $wp_styles;
		return isset( $wp_styles->registered[ $offset ] );
	}

	/**
	 * D{@inheritDoc}
	 */
	public function should_execute() {
		return true;
	}
}

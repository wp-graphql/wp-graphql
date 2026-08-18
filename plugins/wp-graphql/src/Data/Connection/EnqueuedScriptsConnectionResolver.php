<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class EnqueuedScriptsConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<string[]>
 */
class EnqueuedScriptsConnectionResolver extends AbstractConnectionResolver {
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
	protected function prepare_query_args( array $args ): array {
		return $args;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function query( array $query_args ) {
		$queue   = $this->source->enqueuedScriptsQueue ? $this->source->enqueuedScriptsQueue : [];
		$handles = $query_args['where']['handlesIn'] ?? null;

		if ( ! is_array( $handles ) ) {
			return $queue;
		}

		return array_values( array_intersect( $queue, $handles ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'enqueued_script';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function max_query_amount(): int {
		return 1000;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ?\_WP_Dependency $model The model to check.
	 */
	protected function is_valid_model( $model ) {
		return isset( $model->handle );
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ) {
		global $wp_scripts;
		return isset( $wp_scripts->registered[ $offset ] );
	}
}

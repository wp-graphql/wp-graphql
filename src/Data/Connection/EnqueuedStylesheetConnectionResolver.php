<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class EnqueuedStylesheetConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class EnqueuedStylesheetConnectionResolver extends EnqueuedScriptsConnectionResolver {
	/**
	 * {@inheritDoc}
	 */
	protected function query( array $query_args ) {
		return $this->source->enqueuedStylesheetsQueue ? $this->source->enqueuedStylesheetsQueue : [];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'enqueued_stylesheet';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ): bool {
		global $wp_styles;
		return isset( $wp_styles->registered[ $offset ] );
	}
}

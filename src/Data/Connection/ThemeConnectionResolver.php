<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class ThemeConnectionResolver
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.5.0
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<string[]>
 */
class ThemeConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query(): array {
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
		return [
			'allowed' => null,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function query( array $query_args ) {
		return array_keys( wp_get_themes( $query_args ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'theme';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ): bool {
		$theme = wp_get_theme( $offset );
		return $theme->exists();
	}
}

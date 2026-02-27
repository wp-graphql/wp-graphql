<?php

namespace WPGraphQL\Acf\ThirdParty\WPGraphQLSmartCache;

class WPGraphQLSmartCache {

	/**
	 * @var \WPGraphQL\SmartCache\Cache\Invalidation
	 *
	 * @phpstan-ignore-next-line
	 */
	protected $invalidation;

	/**
	 * Initialize support for WPGraphQL Smart Cache
	 */
	public function init(): void {

		/**
		 * Add support for WPGraphQL Smart Cache invalidation for ACF Option Pages
		 */
		add_action( 'graphql_cache_invalidation_init', [ $this, 'initialize_cache_invalidation' ], 10, 1 );
	}

	/**
	 * @param \WPGraphQL\SmartCache\Cache\Invalidation $invalidation
	 *
	 * @return void
	 * @phpstan-ignore-next-line
	 */
	public function initialize_cache_invalidation( \WPGraphQL\SmartCache\Cache\Invalidation $invalidation ) {
			$this->invalidation = $invalidation;

			add_action( 'updated_option', [ $this, 'updated_acf_option_cb' ], 10, 3 );
	}

	/**
	 * Purge Cache after ACF Option Page is updated
	 *
	 * @param string $option The name of the option being updated
	 * @param mixed  $value The value of the option being updated
	 * @param mixed  $original_value The original / previous value of the option
	 */
	public function updated_acf_option_cb( string $option, $value, $original_value ): void {
		// phpcs:ignore
		if ( ! isset( $_POST['_acf_screen'] ) || 'options' !== $_POST['_acf_screen'] ) {
			return;
		}

		// phpcs:ignore
		$options_page = $_GET['page'] ?? null;

		if ( empty( $options_page ) ) {
			return;
		}

		$id = \GraphQLRelay\Relay::toGlobalId( 'acf_options_page', $options_page );

		// @phpstan-ignore-next-line
		$this->invalidation->purge( $id, sprintf( 'update_acf_options_page ( "%s" )', $options_page ) );
	}
}

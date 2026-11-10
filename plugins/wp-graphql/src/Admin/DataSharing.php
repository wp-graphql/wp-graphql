<?php

namespace WPGraphQL\Admin;

/**
 * Class DataSharing
 *
 * The Data Sharing tab of the WPGraphQL settings page, and the products registered on it with
 * register_graphql_data_sharing_product().
 *
 * Each registered product gets a Share Usage Data toggle on the tab, handled by its own
 * UsageTracking instance with a field name of its own, so a product's toggle only ever changes
 * that product's opt-in state.
 *
 * @package WPGraphQL\Admin
 *
 * @since x-release-please-version
 */
final class DataSharing {

	/**
	 * The settings section (and option name) of the Data Sharing tab.
	 */
	public const SECTION = 'graphql_data_sharing_settings';

	/**
	 * The prefix of each product's Share Usage Data field name. The product's slug follows it.
	 */
	public const USAGE_DATA_FIELD_PREFIX = 'share_usage_data_';

	/**
	 * The registered products, keyed by Appsero client slug.
	 *
	 * @var array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}>
	 */
	private static $products = [];

	/**
	 * Registers a product. See register_graphql_data_sharing_product() for the config.
	 *
	 * @param string              $slug   The product's Appsero client slug.
	 * @param array<string,mixed> $config The product config.
	 *
	 * @return bool Whether the product was registered.
	 */
	public static function register_product( string $slug, array $config ): bool {
		$field_name = self::get_usage_data_field_name( $slug );

		if ( self::USAGE_DATA_FIELD_PREFIX === $field_name ) {
			self::doing_it_wrong( __( 'A data sharing product needs the slug of its Appsero client.', 'wp-graphql' ) );
			return false;
		}

		if ( ! isset( $config['insights'] ) || ! $config['insights'] instanceof \Appsero\Insights ) {
			self::doing_it_wrong( __( 'A data sharing product needs its initialized Appsero insights instance as "insights".', 'wp-graphql' ) );
			return false;
		}

		foreach ( [ 'name', 'hash' ] as $required ) {
			if ( empty( $config[ $required ] ) || ! is_string( $config[ $required ] ) ) {
				self::doing_it_wrong(
					sprintf(
						// translators: %s is the name of the missing config key.
						__( 'A data sharing product needs "%s".', 'wp-graphql' ),
						$required
					)
				);
				return false;
			}
		}

		if ( isset( $config['version'] ) && ! is_string( $config['version'] ) ) {
			self::doing_it_wrong( __( 'A data sharing product\'s "version" must be a string.', 'wp-graphql' ) );
			return false;
		}

		// Two slugs that sanitize to the same key would share one field, and one toggle.
		foreach ( self::$products as $product ) {
			if ( $product['usage_tracking']->get_field_name() === $field_name ) {
				self::doing_it_wrong(
					sprintf(
						// translators: %s is the Appsero client slug.
						__( 'A data sharing product with the slug "%s" is already registered.', 'wp-graphql' ),
						$slug
					)
				);
				return false;
			}
		}

		$usage_tracking = new UsageTracking( $config['insights'], $slug, self::SECTION, $config['name'], $field_name );
		$usage_tracking->init();

		if ( empty( self::$products ) ) {
			// After the sections registered at the default priority, so Data Sharing is the last tab.
			add_action( 'graphql_init_settings', [ self::class, 'register_section' ], 100 );
		}

		self::$products[ $slug ] = [
			'name'           => $config['name'],
			'slug'           => $slug,
			'hash'           => $config['hash'],
			'version'        => $config['version'] ?? '',
			'usage_tracking' => $usage_tracking,
		];

		return true;
	}

	/**
	 * The registered products, keyed by Appsero client slug, in the order they were registered.
	 *
	 * @return array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}>
	 */
	public static function get_products(): array {
		return self::$products;
	}

	/**
	 * The name of a product's Share Usage Data field in the Data Sharing section.
	 *
	 * @param string $slug The product's Appsero client slug.
	 */
	public static function get_usage_data_field_name( string $slug ): string {
		return self::USAGE_DATA_FIELD_PREFIX . sanitize_key( $slug );
	}

	/**
	 * Registers the Data Sharing tab.
	 *
	 * Hooked to `graphql_init_settings` once a product is registered, so the tab never shows empty.
	 *
	 * @param \WPGraphQL\Admin\Settings\SettingsRegistry $registry The settings registry.
	 */
	public static function register_section( Settings\SettingsRegistry $registry ): void {
		$registry->register_section(
			self::SECTION,
			[
				'title' => __( 'Data Sharing', 'wp-graphql' ),
				'desc'  => __( 'Choose what this site shares with the WPGraphQL team. These settings don\'t change how WPGraphQL works on your site.', 'wp-graphql' ),
			]
		);
	}

	/**
	 * Removes the copy of WPGraphQL's Share Usage Data toggle that older versions kept in the general settings.
	 *
	 * The toggle moved to the Data Sharing tab. Appsero's `{slug}_allow_tracking` option holds the
	 * opt-in state, so it carries over on its own and only the old copy is left behind. Removing it
	 * means that a downgrade, where the old toggle reads this copy again, starts from the real state
	 * shown on the settings page instead of a value that may be out of date.
	 *
	 * Runs as an update routine, while WPGraphQL loads.
	 */
	public static function remove_legacy_usage_data_setting(): void {
		$settings = get_option( 'graphql_general_settings' );

		if ( ! is_array( $settings ) || ! array_key_exists( UsageTracking::FIELD_NAME, $settings ) ) {
			return;
		}

		unset( $settings[ UsageTracking::FIELD_NAME ] );
		update_option( 'graphql_general_settings', $settings );
	}

	/**
	 * Reports a registration that was rejected.
	 *
	 * @param string $message The reason.
	 */
	private static function doing_it_wrong( string $message ): void {
		_doing_it_wrong( 'register_graphql_data_sharing_product', esc_html( $message ), 'x-release-please-version' );
	}
}

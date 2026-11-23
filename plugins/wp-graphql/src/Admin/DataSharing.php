<?php

namespace WPGraphQL\Admin;

/**
 * Class DataSharing
 *
 * The Data Sharing tab of the WPGraphQL settings page, and the plugins registered on it with
 * register_graphql_data_sharing_plugin().
 *
 * Each registered plugin gets a Share Usage Data toggle on the tab, handled by its own
 * UsageTracking instance with a field name of its own, so a plugin's toggle only ever changes
 * that plugin's opt-in state. Each also gets an email updates checkbox, below one shared address
 * field, handled by EmailUpdates. DataSharingNotice asks about both in one admin notice, in place
 * of each plugin's own Appsero notice.
 *
 * @package WPGraphQL\Admin
 *
 * @since 2.29.0
 */
final class DataSharing {

	/**
	 * The settings section (and option name) of the Data Sharing tab.
	 */
	public const SECTION = 'graphql_data_sharing_settings';

	/**
	 * The prefix of each plugin's Share Usage Data field name. The plugin's slug follows it.
	 */
	public const USAGE_DATA_FIELD_PREFIX = 'share_usage_data_';

	/**
	 * The registered plugins, keyed by Appsero client slug.
	 *
	 * @var array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}>
	 */
	private static $plugins = [];

	/**
	 * The email updates rows of the tab, set up when the first plugin registers.
	 *
	 * @var \WPGraphQL\Admin\EmailUpdates|null
	 */
	private static $email_updates;

	/**
	 * Registers a plugin. See register_graphql_data_sharing_plugin() for the config.
	 *
	 * @param string              $slug   The plugin's Appsero client slug.
	 * @param array<string,mixed> $config The plugin config.
	 *
	 * @return bool Whether the plugin was registered.
	 */
	public static function register_plugin( string $slug, array $config ): bool {
		$field_name = self::get_usage_data_field_name( $slug );

		if ( self::USAGE_DATA_FIELD_PREFIX === $field_name ) {
			self::doing_it_wrong( __( 'A data sharing plugin needs the slug of its Appsero client.', 'wp-graphql' ) );
			return false;
		}

		if ( ! isset( $config['insights'] ) || ! $config['insights'] instanceof \Appsero\Insights ) {
			self::doing_it_wrong( __( 'A data sharing plugin needs its initialized Appsero insights instance as "insights".', 'wp-graphql' ) );
			return false;
		}

		foreach ( [ 'name', 'hash' ] as $required ) {
			if ( empty( $config[ $required ] ) || ! is_string( $config[ $required ] ) ) {
				self::doing_it_wrong(
					sprintf(
						// translators: %s is the name of the missing config key.
						__( 'A data sharing plugin needs "%s".', 'wp-graphql' ),
						$required
					)
				);
				return false;
			}
		}

		if ( isset( $config['version'] ) && ! is_string( $config['version'] ) ) {
			self::doing_it_wrong( __( 'A data sharing plugin\'s "version" must be a string.', 'wp-graphql' ) );
			return false;
		}

		// Two slugs that sanitize to the same key would share one field, and one toggle.
		foreach ( self::$plugins as $plugin ) {
			if ( $plugin['usage_tracking']->get_field_name() === $field_name ) {
				self::doing_it_wrong(
					sprintf(
						// translators: %s is the Appsero client slug.
						__( 'A data sharing plugin with the slug "%s" is already registered.', 'wp-graphql' ),
						$slug
					)
				);
				return false;
			}
		}

		$usage_tracking = new UsageTracking( $config['insights'], $slug, self::SECTION, $config['name'], $field_name );
		$usage_tracking->init();

		if ( empty( self::$plugins ) ) {
			// After the sections registered at the default priority, so Data Sharing is the last tab.
			add_action( 'graphql_init_settings', [ self::class, 'register_section' ], 100 );
		}

		if ( null === self::$email_updates ) {
			self::$email_updates = new EmailUpdates();
			self::$email_updates->init();

			( new DataSharingNotice( self::$email_updates ) )->init();
		}

		// The Data Sharing notice asks instead, once for every registered plugin.
		remove_action( 'admin_notices', [ $config['insights'], 'admin_notice' ] );

		self::$plugins[ $slug ] = [
			'name'           => $config['name'],
			'slug'           => $slug,
			'hash'           => $config['hash'],
			'version'        => $config['version'] ?? '',
			'usage_tracking' => $usage_tracking,
		];

		return true;
	}

	/**
	 * The registered plugins, keyed by Appsero client slug, in the order they were registered.
	 *
	 * @return array<string,array{name:string,slug:string,hash:string,version:string,usage_tracking:\WPGraphQL\Admin\UsageTracking}>
	 */
	public static function get_registered_plugins(): array {
		return self::$plugins;
	}

	/**
	 * The name of a plugin's Share Usage Data field in the Data Sharing section.
	 *
	 * @param string $slug The plugin's Appsero client slug.
	 */
	public static function get_usage_data_field_name( string $slug ): string {
		return self::USAGE_DATA_FIELD_PREFIX . sanitize_key( $slug );
	}

	/**
	 * Registers the Data Sharing tab.
	 *
	 * Hooked to `graphql_init_settings` once a plugin is registered, so the tab never shows empty.
	 *
	 * @param \WPGraphQL\Admin\Settings\SettingsRegistry $registry The settings registry.
	 */
	public static function register_section( Settings\SettingsRegistry $registry ): void {
		$registry->register_section(
			self::SECTION,
			[
				'title' => __( 'Data Sharing', 'wp-graphql' ),
				'desc'  => self::get_section_description(),
			]
		);
	}

	/**
	 * The Data Sharing tab's description: what the toggles send, said once for every plugin on the tab.
	 */
	public static function get_section_description(): string {
		// SettingsRegistry::admin_init() puts a backslash before every double quote in a section description,
		// so the link's attributes use single quotes.
		$learn_more = "<a href='https://appsero.com/privacy-policy/' target='_blank' rel='noopener noreferrer'>" . esc_html__( 'Learn more', 'wp-graphql' ) . '</a>';

		return '<p>' . esc_html__( 'Choose what this site shares with the WPGraphQL team. These settings don\'t change how WPGraphQL works on your site.', 'wp-graphql' ) . '</p>'
			. '<p>' . sprintf(
				// translators: %s is a link to the privacy policy of the telemetry provider.
				esc_html__( 'A plugin whose box is checked sends details about this site to help guide its development, once when the box is checked and then about once a week: server environment and WordPress version, site name, URL and IP address, site language, active theme and plugins, number of users, and this site\'s admin email address and an administrator\'s name. Sharing usage data doesn\'t sign anyone up for emails. Uncheck a plugin\'s box to stop sharing for it at any time. %s', 'wp-graphql' ),
				$learn_more
			) . '</p>'
			. '<p>' . esc_html__( 'While a plugin is opted in, deactivating it from the Plugins screen may show a form asking why. Submitting it sends that reason with the same site details as a check-in.', 'wp-graphql' ) . '</p>';
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
		_doing_it_wrong( 'register_graphql_data_sharing_plugin', esc_html( $message ), '2.29.0' );
	}
}

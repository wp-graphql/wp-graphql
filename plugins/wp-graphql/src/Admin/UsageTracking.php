<?php

namespace WPGraphQL\Admin;

/**
 * Class UsageTracking
 *
 * Adds a settings toggle for the Appsero usage tracking opt-in, so a site that
 * opted in through the one-time admin notice can opt out again later.
 *
 * Appsero's `{slug}_allow_tracking` option stays the source of truth. The
 * toggle always displays that state, and saving it calls Appsero's `optin()` /
 * `optout()` so the weekly cron and the `{slug}_tracker_optin|optout` actions
 * stay consistent with it.
 *
 * @package WPGraphQL\Admin
 */
class UsageTracking {

	/**
	 * The name of the settings field.
	 */
	public const FIELD_NAME = 'share_usage_data';

	/**
	 * @var \Appsero\Insights
	 */
	protected $insights;

	/**
	 * The Appsero client slug, used as the prefix of its options and actions.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * The settings section (and option name) the toggle is stored in.
	 *
	 * @var string
	 */
	protected $section;

	/**
	 * The product name shown in the toggle's description.
	 *
	 * @var string
	 */
	protected $product_name;

	/**
	 * Extensions with their own Appsero client can reuse this class with their own section and name.
	 *
	 * @param \Appsero\Insights $insights     The initialized Appsero insights instance.
	 * @param string            $slug         The Appsero client slug.
	 * @param string            $section      The settings section to add the toggle to.
	 * @param string            $product_name The product name shown in the toggle's description.
	 */
	public function __construct( $insights, string $slug, string $section = 'graphql_general_settings', string $product_name = 'WPGraphQL' ) {
		$this->insights     = $insights;
		$this->slug         = $slug;
		$this->section      = $section;
		$this->product_name = $product_name;
	}

	/**
	 * Register the hooks.
	 */
	public function init(): void {
		add_action( 'graphql_register_settings', [ $this, 'register_field' ] );

		// Runs before `update_option()` skips a write that matches the stored value, and before it
		// falls through to `add_option()` for a new option, so every save of the section is seen.
		add_filter( 'pre_update_option_' . $this->section, [ $this, 'on_pre_update_option' ], 10, 2 );

		// Opting in or out through the admin notice or the URL bypasses the settings page.
		add_action( $this->slug . '_tracker_optin', [ $this, 'sync_stored_value' ] );
		add_action( $this->slug . '_tracker_optout', [ $this, 'sync_stored_value' ] );
	}

	/**
	 * Whether the site has opted in to usage tracking.
	 */
	public function is_tracking_allowed(): bool {
		return 'yes' === get_option( $this->slug . '_allow_tracking', 'no' );
	}

	/**
	 * Register the toggle on the settings page.
	 */
	public function register_field(): void {
		register_graphql_settings_field(
			$this->section,
			[
				'name'    => self::FIELD_NAME,
				'label'   => __( 'Share Usage Data', 'wp-graphql' ),
				'desc'    => sprintf(
					// translators: %1$s is the product name, %2$s is a link to the privacy policy of the telemetry provider.
					__( 'Send details about this site to help guide %1$s development, once when checked and then weekly: server environment and WordPress version, site name and URL, site language, active plugins, number of users, and the admin name and email address. Uncheck to stop sharing at any time. %2$s', 'wp-graphql' ),
					esc_html( $this->product_name ),
					'<a href="https://appsero.com/privacy-policy/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn more', 'wp-graphql' ) . '</a>'
				),
				'type'    => 'checkbox',
				'default' => 'off',
				// Always show the real opt-in state rather than the stored copy.
				'value'   => $this->is_tracking_allowed() ? 'on' : 'off',
			]
		);
	}

	/**
	 * Apply the toggle when the settings section is saved.
	 *
	 * @param mixed $value     The new option value.
	 * @param mixed $old_value The stored option value, or false when the option is new.
	 *
	 * @return mixed The new option value, unchanged.
	 */
	public function on_pre_update_option( $value, $old_value ) {
		$this->apply_requested_state( $old_value, $value );

		return $value;
	}

	/**
	 * Keep the stored copy of the toggle in step with the real opt-in state.
	 *
	 * Without this, a write that re-saves the whole section (such as changing a
	 * different field) would carry a stale value along with it.
	 */
	public function sync_stored_value(): void {
		$stored = get_option( $this->section );

		if ( ! is_array( $stored ) || ! isset( $stored[ self::FIELD_NAME ] ) ) {
			return;
		}

		$actual = $this->is_tracking_allowed() ? 'on' : 'off';

		if ( $stored[ self::FIELD_NAME ] === $actual ) {
			return;
		}

		$stored[ self::FIELD_NAME ] = $actual;
		update_option( $this->section, $stored );
	}

	/**
	 * Opt in or out when a write asks for a different state than the current one.
	 *
	 * The two directions are deliberately asymmetric. An "off" is always honored, so a
	 * withdrawal of consent is never missed. An "on" only opts in when the write actually
	 * changed the toggle, so re-saving the section for another field with a stale stored
	 * value can never grant consent on the site's behalf.
	 *
	 * @param mixed $old_value The stored option value, or false when the option is new.
	 * @param mixed $value     The new option value.
	 */
	protected function apply_requested_state( $old_value, $value ): void {
		if ( ! is_array( $value ) || ! isset( $value[ self::FIELD_NAME ] ) ) {
			return;
		}

		$requested = $value[ self::FIELD_NAME ];
		$allowed   = $this->is_tracking_allowed();

		if ( 'off' === $requested ) {
			if ( $allowed ) {
				$this->insights->optout();
			}
			return;
		}

		if ( 'on' !== $requested || $allowed ) {
			return;
		}

		$unchanged = is_array( $old_value ) && isset( $old_value[ self::FIELD_NAME ] ) && 'on' === $old_value[ self::FIELD_NAME ];

		if ( ! $unchanged ) {
			$this->insights->optin();
		}
	}
}

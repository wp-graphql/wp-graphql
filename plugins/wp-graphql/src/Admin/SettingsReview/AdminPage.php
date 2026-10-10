<?php

namespace WPGraphQL\Admin\SettingsReview;

/**
 * Class AdminPage
 *
 * The settings review's admin screen: its place in the menu, the element the app mounts into, the
 * assets it needs and the data it starts with.
 *
 * The page has an item in the GraphQL menu while there are settings to review. Once the review is
 * completed or skipped the item is hidden and the page is reached from the Settings page, which is
 * why this class also keeps GraphQL > Settings highlighted while the review is open.
 *
 * @package WPGraphQL\Admin\SettingsReview
 */
final class AdminPage {

	/**
	 * The review this page renders.
	 */
	private SettingsReview $review;

	/**
	 * The hook suffix of the admin page, set when the page is registered.
	 */
	private ?string $hook_suffix = null;

	/**
	 * Whether the review has an item in the GraphQL menu for the current request.
	 */
	private bool $shows_in_menu = false;

	/**
	 * AdminPage constructor.
	 *
	 * @param \WPGraphQL\Admin\SettingsReview\SettingsReview $review The settings review.
	 */
	public function __construct( SettingsReview $review ) {
		$this->review = $review;
	}

	/**
	 * Returns the URL of the review admin page.
	 */
	public static function get_url(): string {
		return admin_url( 'admin.php?page=' . SettingsReview::PAGE_SLUG );
	}

	/**
	 * Registers the review admin page.
	 */
	public function register(): void {
		$this->shows_in_menu = $this->review->should_invite();

		// An empty parent registers the page (URL, capability check) without a menu item.
		$parent_slug = $this->shows_in_menu ? self::get_menu_parent_slug() : '';

		$hook_suffix = add_submenu_page(
			$parent_slug,
			__( 'Review WPGraphQL Settings', 'wp-graphql' ),
			__( 'Review Settings', 'wp-graphql' ),
			SettingsReview::CAPABILITY,
			SettingsReview::PAGE_SLUG,
			[ $this, 'render' ]
		);

		$this->hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : null;

		if ( null !== $this->hook_suffix ) {
			add_action( 'load-' . $this->hook_suffix, [ $this, 'set_page_title' ] );
		}
	}

	/**
	 * Sets the browser tab title for the review page.
	 *
	 * WordPress takes the title from the admin menu, and the review may not have a menu item.
	 */
	public function set_page_title(): void {
		global $title;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress reads the admin page title from this global.
		$title = __( 'Review WPGraphQL Settings', 'wp-graphql' );
	}

	/**
	 * Whether the review has an item in the GraphQL menu for the current request.
	 */
	public function shows_in_menu(): bool {
		return $this->shows_in_menu;
	}

	/**
	 * Highlights the GraphQL menu while the review is open without a menu item of its own.
	 *
	 * @param string|null $parent_file The parent menu slug to highlight.
	 */
	public function highlight_parent_menu( $parent_file ): ?string {
		if ( $this->shows_in_menu || ! $this->is_review_page() ) {
			return $parent_file;
		}

		return self::get_menu_parent_slug();
	}

	/**
	 * Highlights GraphQL > Settings while the review is open without a menu item of its own.
	 *
	 * @param string|null $submenu_file The submenu slug to highlight.
	 */
	public function highlight_settings_submenu( $submenu_file ): ?string {
		if ( $this->shows_in_menu || ! $this->is_review_page() ) {
			return $submenu_file;
		}

		return 'graphql-settings';
	}

	/**
	 * Renders the element the review app mounts into.
	 */
	public function render(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Review WPGraphQL Settings', 'wp-graphql' ) . '</h1>';

		if ( ! file_exists( WPGRAPHQL_PLUGIN_DIR . 'build/settingsReview.asset.php' ) ) {
			$message = sprintf(
				/* translators: 1: npm ci command, 2: npm run build command, 3: releases URL */
				__( 'The settings review requires JavaScript assets that need to be built. Please run %1$s followed by %2$s in the plugin directory, or <a href="%3$s" target="_blank">download a release</a> that includes pre-built assets.', 'wp-graphql' ),
				'<code>npm ci</code>',
				'<code>npm run build</code>',
				'https://github.com/wp-graphql/wp-graphql/releases'
			);
			echo '<div class="notice notice-warning inline" style="margin-top: 20px;"><p>' . wp_kses_post( $message ) . '</p></div>';
		} else {
			echo '<div id="wpgraphql-settings-review"></div>';
		}

		echo '</div>';
	}

	/**
	 * Returns the data the review app starts with.
	 *
	 * @return array<string,mixed>
	 */
	public function get_bootstrap_data(): array {
		return [
			'restPath'       => SettingsReview::REST_NAMESPACE . SettingsReview::REST_ROUTE,
			'steps'          => $this->review->get_steps(),
			'fields'         => array_values( $this->review->get_fields() ),
			'values'         => (object) $this->review->get_values(),
			'unreviewedKeys' => $this->review->get_unreviewed_field_keys(),
			'state'          => (object) ReviewState::get(),
			'settingsUrl'    => admin_url( 'admin.php?page=graphql-settings' ),
			'docsUrl'        => 'https://www.wpgraphql.com/docs/security',
		];
	}

	/**
	 * Enqueues the review app on its admin page.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ): void {
		if ( null === $this->hook_suffix || $this->hook_suffix !== $hook_suffix ) {
			return;
		}

		$asset_path = WPGRAPHQL_PLUGIN_DIR . 'build/settingsReview.asset.php';

		// Bail if build assets don't exist (e.g., dev install without running npm build).
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is validated with file_exists() above
		$asset_file = include $asset_path;

		wp_enqueue_script(
			'wpgraphql-settings-review',
			WPGRAPHQL_PLUGIN_URL . 'build/settingsReview.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations( 'wpgraphql-settings-review', 'wp-graphql', WPGRAPHQL_PLUGIN_DIR . 'languages' );

		wp_localize_script( 'wpgraphql-settings-review', 'wpgraphqlSettingsReview', $this->get_bootstrap_data() );

		wp_enqueue_style( 'wp-components' );

		if ( file_exists( WPGRAPHQL_PLUGIN_DIR . 'build/settingsReview.css' ) ) {
			wp_enqueue_style(
				'wpgraphql-settings-review',
				WPGRAPHQL_PLUGIN_URL . 'build/settingsReview.css',
				[ 'wp-components' ],
				$asset_file['version']
			);
		}
	}

	/**
	 * Whether the current admin page is the review.
	 */
	private function is_review_page(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return null !== $this->hook_suffix && $screen instanceof \WP_Screen && $this->hook_suffix === $screen->id;
	}

	/**
	 * Returns the slug of the GraphQL menu the review belongs to.
	 */
	private static function get_menu_parent_slug(): string {
		return 'off' === get_graphql_setting( 'graphiql_enabled' ) ? 'graphql-settings' : 'graphiql-ide';
	}
}

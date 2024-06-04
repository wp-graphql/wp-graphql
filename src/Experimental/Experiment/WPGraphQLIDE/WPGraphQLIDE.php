<?php
namespace WPGraphQL\Experimental\Experiment\WPGraphQLIDE;

class WPGraphQLIDE {

	public const WPGRAPHQL_IDE_ROOT_ELEMENT_ID = 'wpgraphql-ide-root';

	/**
	 * Initialize the WPGraphQL IDE
	 */
	public function init(): void {

		// Register the IDE settings
		add_action( 'graphql_register_settings', [ $this, 'register_ide_settings' ] );

		/**
		 * Rename and reorder the submenu items under 'GraphQL'.s
		 */
		add_action( 'admin_menu', [ $this, 'rename_and_reorder_submenu_items' ], 999 );

		add_filter( 'graphql_get_setting_section_field_value', [ $this, 'ensure_graphiql_link_is_unchecked' ], 10, 5 );
		add_filter( 'graphql_setting_field_config', [ $this, 'update_graphiql_link_field_config' ], 10, 3 );
		add_filter( 'script_loader_tag', [ $this, 'filter_script_loader_tag' ], 10, 2 );
		add_filter( 'graphql_admin_notices_is_allowed_admin_page', [ $this, 'allow_ide_page_for_admin_notices' ], 10, 3 );
		add_action( 'graphql_admin_notices_render_notice', [ $this, 'graphql_admin_notices_render_notice' ], 10, 4 );
		add_action( 'graphql_admin_notices_render_notices', [ $this, 'graphql_admin_notices_render_notices' ], 10, 1 );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_graphql_ide_menu_icon_css' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_graphql_ide_menu_icon_css' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_react_app_with_styles' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_react_app_with_styles' ] );
		add_action( 'admin_bar_menu', [ $this, 'register_wpadminbar_menus' ], 999 );
		add_action( 'admin_menu', [ $this, 'register_dedicated_ide_menu' ] );
	}

	/**
	 * Generates the SVG logo for GraphQL.
	 *
	 * @return string The SVG logo markup.
	 */
	public function graphql_logo_svg(): string {
		return <<<XML
		<svg xmlns="http://www.w3.org/2000/svg" fill="color(display-p3 .8824 0 .5961)" viewBox="0 0 100 100">
			<path fill-rule="evenodd" d="m50 6.903 37.323 21.549v43.096L50 93.097 12.677 71.548V28.451L50 6.903ZM16.865 30.87v31.656L44.28 15.041 16.864 30.87ZM50 13.51 18.398 68.246h63.205L50 13.509Zm27.415 58.924h-54.83L50 88.261l27.415-15.828Zm5.72-9.908L55.72 15.041 83.136 30.87v31.656Z" clip-rule="evenodd"/>
			<circle cx="50" cy="9.321" r="8.82"/>
			<circle cx="85.229" cy="29.66" r="8.82"/>
			<circle cx="85.229" cy="70.34" r="8.82"/>
			<circle cx="50" cy="90.679" r="8.82"/>
			<circle cx="14.766" cy="70.34" r="8.82"/>
			<circle cx="14.766" cy="29.66" r="8.82"/>
		</svg>
XML;
	}

	/**
	 * Enqueues custom CSS to set the "GraphQL IDE" menu item icon in the WordPress Admin Bar.
	 */
	public function enqueue_graphql_ide_menu_icon_css(): void {
		if ( $this->user_lacks_capability() ) {
			return;
		}

		$custom_css = '
		#wp-admin-bar-wpgraphql-ide .ab-icon::before,
		#wp-admin-bar-wpgraphql-ide .ab-icon::before {
			background-image: url("data:image/svg+xml;base64,' . base64_encode( $this->graphql_logo_svg() ) . '");
			background-size: 100%;
			border-radius: 12px;
			box-sizing: border-box;
			content: "";
			display: inline-block;
			height: 24px;
			width: 24px;
		}
	';

		wp_add_inline_style( 'admin-bar', $custom_css );
	}

	/**
	 * Renders the container for the dedicated IDE page for the React app to be mounted to.
	 */
	public function render_dedicated_ide_page(): void {
		echo '<div id="' . esc_attr( self::WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"></div>';
	}

	/**
	 * Registers a submenu page for the dedicated GraphQL IDE.
	 *
	 * This function checks if the current user has the necessary capabilities
	 * to access the GraphQL IDE. If the user has the required capabilities,
	 * it adds a submenu page under a specified parent menu. The submenu page
	 * is intended to provide access to a dedicated GraphQL IDE within the WordPress
	 * admin area.
	 *
	 * @see add_submenu_page() For more information on adding submenu pages.
	 * @link https://developer.wordpress.org/reference/functions/add_submenu_page/
	 */
	public function register_dedicated_ide_menu(): void {
		if ( $this->user_lacks_capability() ) {
			return;
		}

		// Remove the legacy submenu without affecting the ability to directly link to the legacy IDE (wp-admin/admin.php?page=graphiql-ide)
		$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );
		$show_legacy_editor   = isset( $graphql_ide_settings['graphql_ide_show_legacy_editor'] ) ? $graphql_ide_settings['graphql_ide_show_legacy_editor'] : 'off';

		if ( 'off' === $show_legacy_editor ) {
			remove_submenu_page( 'graphiql-ide', 'graphiql-ide' );
		}

		add_submenu_page(
			'graphiql-ide',
			__( 'GraphQL IDE', 'wp-graphql' ),
			__( 'GraphQL IDE', 'wp-graphql' ),
			'manage_options',
			'graphql-ide',
			[ $this, 'render_dedicated_ide_page' ]
		);
	}

	/**
	 * Registers the plugin's custom menu item in the WordPress Admin Bar.
	 *
	 * @global \WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar instance.
	 */
	public function register_wpadminbar_menus(): void {
		if ( $this->user_lacks_capability() ) {
			return;
		}

		global $wp_admin_bar;

		$app_context = $this->get_app_context();

		// Retrieve the settings array
		$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );

		// Get the specific link behavior value, default to 'drawer' if not set
		$link_behavior = isset( $graphql_ide_settings['graphql_ide_link_behavior'] ) ? $graphql_ide_settings['graphql_ide_link_behavior'] : 'drawer';

		if ( 'drawer' === $link_behavior && ! $this->current_screen_is_dedicated_ide_page() ) {
			// Drawer Button
			$wp_admin_bar->add_node(
				[
					'id'    => 'wpgraphql-ide',
					'title' => '<div id="' . esc_attr( self::WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"><span class="ab-icon"></span>' . $app_context['drawerButtonLabel'] . '</div>',
					'href'  => '#',
				]
			);
		} elseif ( 'disabled' !== $link_behavior ) {
			// Link to the new dedicated IDE page.
			$wp_admin_bar->add_node(
				[
					'id'    => 'wpgraphql-ide',
					'title' => '<span class="ab-icon"></span>' . $app_context['drawerButtonLabel'],
					'href'  => admin_url( 'admin.php?page=graphql-ide' ),
				]
			);
		}
	}

	/**
	 * Checks if the current admin page is the new WPGraphQL IDE page.
	 *
	 * @return bool True if the current page is the new WPGraphQL IDE page, false otherwise.
	 */
	public function is_ide_page(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return 'graphql_page_graphql-ide' === $screen->id;
	}

	/**
	 * Checks if the current admin page is the legacy GraphiQL IDE page.
	 *
	 * @return bool True if the current page is the legacy GraphiQL IDE page, false otherwise.
	 */
	public function is_legacy_ide_page(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return 'toplevel_page_graphiql-ide' === $screen->id;
	}

	/**
	 * Checks if the current user lacks the capability required to load scripts and styles for the GraphQL IDE.
	 *
	 * @return bool Whether the user lacks the required capability.
	 */
	public function user_lacks_capability(): bool {
		$capability_required = apply_filters( 'wpgraphqlide_capability_required', 'manage_options' );

		return ! current_user_can( $capability_required );
	}

	/**
	 * Determines if the current admin page is a dedicated WPGraphQL IDE page.
	 *
	 * This function checks whether the current admin page is exclusively for either the new GraphQL IDE
	 * or the legacy GraphiQL IDE, distinguishing them from other admin pages where the IDE might be present
	 * in a drawer. It is designed to help in tailoring the admin interface or enqueuing specific scripts
	 * and styles on these dedicated IDE pages.
	 *
	 * @return bool True if the current page is a dedicated WPGraphQL IDE page, false otherwise.
	 */
	public function current_screen_is_dedicated_ide_page(): bool {
		return $this->is_ide_page() || $this->is_legacy_ide_page();
	}

	/**
	 * Enqueues the React application script and associated styles.
	 */
	public function enqueue_react_app_with_styles(): void {
		if ( $this->is_legacy_ide_page() ) {
			return;
		}

		if ( ! class_exists( '\WPGraphQL\Router' ) ) {
			return;
		}

		if ( $this->user_lacks_capability() ) {
			return;
		}

		// Don't enqueue new styles/scripts on the legacy IDE page
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( 'toplevel_page_graphiql-ide' === $screen->id ) {
				return;
			}
		}

		$asset_file         = include WPGRAPHQL_PLUGIN_DIR . '/src/Experimental/Experiment/WPGraphQLIDE/build/index.asset.php';
		$render_asset_file  = include WPGRAPHQL_PLUGIN_DIR . '/src/Experimental/Experiment/WPGraphQLIDE/build/render.asset.php';
		$graphql_asset_file = include WPGRAPHQL_PLUGIN_DIR . '/src/Experimental/Experiment/WPGraphQLIDE//build/graphql.asset.php';

		$app_context = $this->get_app_context();

		wp_register_script(
			'graphql',
			plugins_url( 'build/graphql.js', __FILE__ ),
			$graphql_asset_file['dependencies'],
			$graphql_asset_file['version'],
			false
		);

		wp_enqueue_script(
			'wpgraphql-ide',
			plugins_url( 'build/index.js', __FILE__ ),
			array_merge( $asset_file['dependencies'], [ 'graphql' ] ),
			$asset_file['version'],
			false
		);

		$localized_data = [
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'graphqlEndpoint'     => trailingslashit( site_url() ) . 'index.php?' . \WPGraphQL\Router::$route,
			'rootElementId'       => self::WPGRAPHQL_IDE_ROOT_ELEMENT_ID,
			'context'             => $app_context,
			'isDedicatedIdePage'  => $this->current_screen_is_dedicated_ide_page(),
			'dedicatedIdeBaseUrl' => $this->get_dedicated_ide_base_url(),
		];

		wp_localize_script(
			'wpgraphql-ide',
			'WPGRAPHQL_IDE_DATA',
			$localized_data
		);

		// Extensions looking to extend GraphiQL can hook in here,
		// after the window object is established, but before the App renders
		do_action( 'wpgraphqlide_enqueue_script', $app_context );

		wp_enqueue_script(
			'wpgraphql-ide-render',
			plugins_url( 'build/render.js', __FILE__ ),
			array_merge( $asset_file['dependencies'], [ 'wpgraphql-ide', 'graphql' ] ),
			$render_asset_file['version'],
			false
		);

		wp_enqueue_style( 'wpgraphql-ide', plugins_url( 'build/index.css', __FILE__ ), [], $asset_file['version'] );
		wp_enqueue_style( 'wpgraphql-ide-render', plugins_url( 'build/render.css', __FILE__ ), [], $asset_file['version'] );

		// Avoid running custom styles through a build process for an improved developer experience.
		wp_enqueue_style( 'wpgraphql-ide-styles', plugins_url( 'styles/wpgraphql-ide.css', __FILE__ ), [], $asset_file['version'] );
	}

	/**
	 * Retrieves the base URL for the dedicated WPGraphQL IDE page.
	 *
	 * @return string The URL for the dedicated IDE page within the WordPress admin.
	 */
	public function get_dedicated_ide_base_url(): string {
		return menu_page_url( 'graphql-ide', false );
	}

	/**
	 * Retrieves the specific header of this plugin.
	 *
	 * @param string $key The plugin data key.
	 * @return string|null The version number of the plugin. Returns an empty string if the version is not found.
	 */
	public function get_plugin_header( string $key = '' ): ?string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( empty( $key ) ) {
			return null;
		}

		$plugin_data = get_plugin_data( __FILE__ );

		return $plugin_data[ $key ] ?? null;
	}

	/**
	 * Retrieves app context.
	 *
	 * @return array<mixed> The possibly filtered app context array.
	 */
	public function get_app_context(): array {
		$current_user = wp_get_current_user();

		// Get the avatar URL for the current user. Returns an empty string if no user is logged in.
		$avatar_url = $current_user->exists() ? get_avatar_url( $current_user->ID ) : '';

		return apply_filters(
			'wpgraphqlide_context',
			[
				'pluginVersion'     => $this->get_plugin_header( 'Version' ),
				'pluginName'        => $this->get_plugin_header( 'Name' ),
				'externalFragments' => apply_filters( 'wpgraphqlide_external_fragments', [] ),
				'avatarUrl'         => $avatar_url,
				'drawerButtonLabel' => __( 'GraphQL IDE', 'wp-graphql' ),
			]
		);
	}

	/**
	 * Add styles to hide generic admin notices on the graphiql-ide page
	 * and play nice with notices added via register_graphql_admin_notice
	 *
	 * @param array<string,mixed> $notices The array of notices to render
	 */
	public static function graphql_admin_notices_render_notices( array $notices ): void {
		echo '
		<style>
			body.graphql_page_graphql-ide #wpbody .wpgraphql-admin-notice {
				display: block;
				position: absolute;
				top: 0;
				right: 0;
				z-index: 1;
				min-width: 40%;
			}
			body.graphql_page_graphql-ide #wpbody .graphiql-container {
				padding-top: ' . count( $notices ) * 45 . 'px;
			}
			body.graphql_page_graphql-ide #wpgraphql-ide-root {
				height: calc(100vh - var(--wp-admin--admin-bar--height) - ' . count( $notices ) * 45 . 'px);
			}
		</style>
		';
	}

	/**
	 * Add styles to apply top margin to notices added via register_graphql_admin_notice
	 *
	 * @param string              $notice_slug The slug of the notice
	 * @param array<string,mixed> $notice The notice data
	 * @param bool                $is_dismissable Whether the notice is dismissable
	 * @param int                 $count The count of notices
	 */
	public function graphql_admin_notices_render_notice( string $notice_slug, array $notice, bool $is_dismissable, int $count ): void {

		echo '
		<style>
			body.graphql_page_graphql-ide #wpbody #wpgraphql-admin-notice-' . esc_attr( $notice_slug ) . ' {
				top: ' . esc_attr( ( $count * 45 ) . 'px' ) . '
			}
		</style>
		';
	}

	/**
	 * Filter to allow graphql admin notices to be displayed on the dedicated IDE page.
	 *
	 * @param bool          $is_plugin_scoped_page True if the current page is within scope of the plugin's pages.
	 * @param string        $current_page_id The ID of the current admin page.
	 * @param array<string> $allowed_pages The list of allowed pages.
	 */
	public function allow_ide_page_for_admin_notices( bool $is_plugin_scoped_page, string $current_page_id, array $allowed_pages ): bool {

		// If the current page is the dedicated IDE page, we want to allow notices to be displayed.
		if ( 'graphql_page_graphql-ide' === $current_page_id ) {
			return true;
		}

		return $is_plugin_scoped_page;
	}

	/**
	 * Modifies the script tag for specific scripts to add the 'defer' attribute.
	 *
	 * This function checks if the script handle matches 'wpgraphql-ide' and, if so,
	 * adds the 'defer' attribute to the script tag. This allows the script to be executed
	 * after the HTML document is parsed but before the DOMContentLoaded event.
	 *
	 * @param string $tag    The HTML `<script>` tag of the enqueued script.
	 * @param string $handle The script's registered handle in WordPress.
	 *
	 * @return string Modified script tag with 'defer' attribute included if handle matches; otherwise, unchanged.
	 */
	public function filter_script_loader_tag( string $tag, string $handle ): string {

		if ( 'wpgraphql-ide' === $handle ) {
			return str_replace( ' src', ' defer="defer" src', $tag );
		}

		return $tag;
	}

	/**
	 * Update the existing GraphiQL link field configuration to say "Legacy".
	 *
	 * @param array<string, mixed> $field_config The field configuration array.
	 * @param string               $field_name   The name of the field.
	 * @param string               $section      The section the field belongs to.
	 *
	 * @return array<string, mixed> The modified field configuration array.
	 */
	public function update_graphiql_link_field_config( array $field_config, string $field_name, string $section ): array {
		if ( 'show_graphiql_link_in_admin_bar' === $field_name && 'graphql_general_settings' === $section ) {
			$field_config['desc'] = sprintf(
				'%1$s<br><p class="description">%2$s</p>',
				__( 'Show the GraphiQL IDE link in the WordPress Admin Bar.', 'wp-graphql' ),
				sprintf(
				/* translators: %s: Strong opening tag */
					__( '%1$sNote:%2$s This setting has been disabled by the new WPGraphQL IDE. Related settings are now available under the "IDE Settings" tab.', 'wp-graphql' ),
					'<strong>',
					'</strong>'
				)
			);
			$field_config['disabled'] = true;
			$field_config['value']    = 'off';
		}
		return $field_config;
	}

	/**
	 * Ensure the `show_graphiql_link_in_admin_bar` setting is always unchecked.
	 *
	 * @param mixed                $value          The value of the field.
	 * @param mixed                $default_value  The default value if there is no value set.
	 * @param string               $option_name    The name of the option.
	 * @param array<string, mixed> $section_fields The setting values within the section.
	 * @param string               $section_name   The name of the section the setting belongs to.
	 * @return mixed The modified value of the field.
	 */
	public function ensure_graphiql_link_is_unchecked( $value, $default_value, string $option_name, array $section_fields, string $section_name ) {
		if ( 'show_graphiql_link_in_admin_bar' === $option_name && 'graphql_general_settings' === $section_name ) {
			return 'off';
		}
		return $value;
	}


	/**
	 * Rename and reorder the submenu items under 'GraphQL'.
	 */
	public function rename_and_reorder_submenu_items(): void {
		global $submenu;

		if ( isset( $submenu['graphiql-ide'] ) ) {
			$temp_submenu = $submenu['graphiql-ide'];
			foreach ( $temp_submenu as $key => $value ) {
				if ( 'GraphiQL IDE' === $value[0] ) {
					$temp_submenu[ $key ][0] = 'Legacy GraphQL IDE';
					$legacy_item             = $temp_submenu[ $key ];
					unset( $temp_submenu[ $key ] );
					$temp_submenu = array_values( $temp_submenu );
					array_splice( $temp_submenu, 1, 0, [ $legacy_item ] );
					break;
				}
			}
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$submenu['graphiql-ide'] = $temp_submenu;
		}
	}

	/**
	 * Register custom GraphQL settings.
	 */
	public function register_ide_settings(): void {
		// Add a tab section to the graphql admin settings page
		register_graphql_settings_section(
			'graphql_ide_settings',
			[
				'title' => __( 'IDE Settings', 'wp-graphql' ),
				'desc'  => __( 'Customize your WPGraphQL IDE experience sitewide. Individual users can override these settings in their user profile.', 'wp-graphql' ),
			]
		);

		register_graphql_settings_field(
			'graphql_ide_settings',
			[
				'name'              => 'graphql_ide_link_behavior',
				'label'             => __( 'Admin Bar Link Behavior', 'wp-graphql' ),
				'desc'              => __( 'How would you like to access the GraphQL IDE from the admin bar?', 'wp-graphql' ),
				'type'              => 'radio',
				'options'           => [
					'drawer'         => __( 'Drawer (recommended) — open the IDE in a slide up drawer from any page', 'wp-graphql' ),
					'dedicated_page' => sprintf(
						wp_kses_post(
							sprintf(
							/* translators: %s: URL to the GraphQL IDE page */
								__( 'Dedicated Page — direct link to <a href="%1$s">%1$s</a>', 'wp-graphql' ),
								esc_url( admin_url( 'admin.php?page=graphql-ide' ) )
							)
						)
					),
					'disabled'       => __( 'Disabled — remove the IDE link from the admin bar', 'wp-graphql' ),
				],
				'default'           => 'drawer',
				'sanitize_callback' => [ $this, 'sanitize_custom_graphql_ide_link_behavior' ],
			]
		);

		register_graphql_settings_field(
			'graphql_ide_settings',
			[
				'name'  => 'graphql_ide_show_legacy_editor',
				'label' => __( 'Show Legacy Editor', 'wp-graphql' ),
				'desc'  => __( 'Show the legacy editor', 'wp-graphql' ),
				'type'  => 'checkbox',
			]
		);
	}

	/**
	 * Sanitize the input value for the custom GraphQL IDE link behavior setting.
	 *
	 * @param string $value The input value.
	 *
	 * @return string The sanitized value.
	 */
	public function sanitize_custom_graphql_ide_link_behavior( string $value ): string {
		$valid_values = [ 'drawer', 'dedicated_page', 'disabled' ];

		if ( in_array( $value, $valid_values, true ) ) {
			return $value;
		}

		return 'drawer';
	}
}

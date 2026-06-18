<?php
/**
 * Admin bar nodes, dedicated IDE submenu, admin-notice CSS, and the
 * SVG logo used by the admin-bar icon.
 *
 * @package WPGraphQLIDE
 */

declare(strict_types = 1);

namespace WPGraphQLIDE;

/**
 * Wires the IDE into the WordPress admin chrome — admin bar item, dedicated
 * page submenu, plugin action link, and the inline CSS that styles
 * GraphQL admin notices on the IDE page.
 */
class AdminUI {

	/**
	 * Registers the plugin's custom menu item in the WordPress Admin Bar.
	 *
	 * @global \WP_Admin_Bar $wp_admin_bar The WordPress Admin Bar instance.
	 */
	public static function register_wpadminbar_menus(): void {
		if ( ! user_has_graphql_ide_capability() ) {
			return;
		}

		global $wp_admin_bar;

		$app_context = AssetEnqueue::app_context();

		$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );
		$link_behavior        = isset( $graphql_ide_settings['graphql_ide_link_behavior'] ) ? $graphql_ide_settings['graphql_ide_link_behavior'] : 'drawer';

		if ( 'drawer' === $link_behavior && ! current_screen_is_dedicated_ide_page() ) {
			$wp_admin_bar->add_node(
				[
					'id'    => 'wpgraphql-ide',
					'title' => '<div id="' . esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"><span class="ab-icon"></span>' . esc_html( $app_context['drawerButtonLabel'] ) . '</div>',
					'href'  => '#',
				]
			);
		} elseif ( 'disabled' !== $link_behavior ) {
			$wp_admin_bar->add_node(
				[
					'id'    => 'wpgraphql-ide',
					'title' => '<span class="ab-icon"></span>' . esc_html( $app_context['drawerButtonLabel'] ),
					'href'  => esc_url( admin_url( 'admin.php?page=graphql-ide' ) ),
				]
			);
		}
	}

	/**
	 * Registers a submenu page for the dedicated GraphQL IDE and reorder the items.
	 */
	public static function register_dedicated_ide_menu(): void {
		if ( ! user_has_graphql_ide_capability() ) {
			return;
		}

		// Remove the legacy submenu without affecting the ability to directly link to the legacy IDE (wp-admin/admin.php?page=graphiql-ide).
		$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );
		$show_legacy_editor   = isset( $graphql_ide_settings['graphql_ide_show_legacy_editor'] ) ? $graphql_ide_settings['graphql_ide_show_legacy_editor'] : 'off';

		if ( 'off' === $show_legacy_editor ) {
			remove_submenu_page( 'graphiql-ide', 'graphiql-ide' );
		}

		add_submenu_page(
			'graphiql-ide',
			esc_html__( 'GraphQL IDE', 'wpgraphql-ide' ),
			esc_html__( 'GraphQL IDE', 'wpgraphql-ide' ),
			wpgraphql_ide_get_capability(),
			'graphql-ide',
			[ self::class, 'render_dedicated_ide_page' ]
		);

		add_action( 'admin_menu', [ self::class, 'reorder_graphql_submenu_items' ], 100 );
	}

	/**
	 * Reorder the submenu items under the GraphQL menu.
	 */
	public static function reorder_graphql_submenu_items(): void {
		global $submenu;

		if ( ! isset( $submenu['graphiql-ide'] ) ) {
			return;
		}

		$graphql_ide_settings = get_option( 'graphql_ide_settings', [] );
		$show_legacy_editor   = isset( $graphql_ide_settings['graphql_ide_show_legacy_editor'] ) ? $graphql_ide_settings['graphql_ide_show_legacy_editor'] : 'off';

		$graphql_ide  = null;
		$graphiql_ide = null;
		$extensions   = null;
		$settings     = null;
		$other_items  = [];

		foreach ( $submenu['graphiql-ide'] as $item ) {
			switch ( $item[0] ) {
				case 'GraphQL IDE':
					$graphql_ide = $item;
					break;
				case 'GraphiQL IDE':
					$graphiql_ide = $item;
					break;
				case 'Extensions':
					$extensions = $item;
					break;
				case 'Settings':
					$settings = $item;
					break;
				default:
					$other_items[] = $item;
					break;
			}
		}

		$ordered_submenu = [];

		if ( $graphql_ide ) {
			$ordered_submenu[] = $graphql_ide;
		}
		if ( 'on' === $show_legacy_editor && $graphiql_ide ) {
			$graphiql_ide[0]   = esc_html__( 'Legacy GraphQL IDE', 'wpgraphql-ide' );
			$ordered_submenu[] = $graphiql_ide;
		}
		if ( $extensions ) {
			$ordered_submenu[] = $extensions;
		}
		if ( $settings ) {
			$ordered_submenu[] = $settings;
		}

		foreach ( $other_items as $item ) {
			$ordered_submenu[] = $item;
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu['graphiql-ide'] = $ordered_submenu;
	}

	/**
	 * Renders the container for the dedicated IDE page for the React app to be mounted to.
	 */
	public static function render_dedicated_ide_page(): void {
		echo '<div id="' . esc_attr( WPGRAPHQL_IDE_ROOT_ELEMENT_ID ) . '"></div>';
	}

	/**
	 * Enqueues custom CSS to set the "GraphQL IDE" menu item icon in the WordPress Admin Bar.
	 */
	public static function enqueue_graphql_ide_menu_icon_css(): void {
		if ( ! user_has_graphql_ide_capability() ) {
			return;
		}

		// Render the elephant as a CSS mask painted with `currentColor`
		// instead of a `background-image`. The sidebar menu icon does the
		// same thing via WP core, which is why it adapts to the active
		// color scheme; the admin bar's `<span class="ab-icon">::before`
		// uses a raw `background-image` by default, so a baked-in fill
		// (white) renders as-is and disappears on Light scheme's white
		// chrome. With `mask` + `currentColor`, the icon inherits the
		// admin bar's text color and reads correctly in every scheme.
		$svg_url    = 'url("data:image/svg+xml;base64,' . base64_encode( self::graphql_logo_svg() ) . '")';
		$custom_css = '
			#wp-admin-bar-wpgraphql-ide .ab-icon::before {
				-webkit-mask: ' . $svg_url . ' center / contain no-repeat;
				mask: ' . $svg_url . ' center / contain no-repeat;
				background-color: currentColor;
				box-sizing: border-box;
				content: "";
				display: inline-block;
				height: 20px;
				width: 20px;
				vertical-align: middle;
			}
		';

		wp_add_inline_style( 'admin-bar', wp_kses_post( $custom_css ) );
	}

	/**
	 * Adds styles to hide generic admin notices on the GraphQL IDE page.
	 *
	 * @param array<int, mixed> $notices The array of notices to render.
	 */
	public static function graphql_admin_notices_render_notices( array $notices ): void {
		$custom_css = '
			body.graphql_page_graphql-ide #wpbody .wpgraphql-admin-notice {
				display: block;
				position: absolute;
				top: 0;
				right: 0;
				z-index: 1;
				min-width: 40%;
			}
			body.graphql_page_graphql-ide #wpgraphql-ide-root {
				height: calc(100vh - var(--wp-admin--admin-bar--height) - ' . count( $notices ) * 45 . 'px);
			}
		';

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_style( 'wpgraphql-ide-admin-notices', false );
		wp_enqueue_style( 'wpgraphql-ide-admin-notices' );
		wp_add_inline_style( 'wpgraphql-ide-admin-notices', wp_kses_post( $custom_css ) );
	}

	/**
	 * Adds styles to apply top margin to notices added via register_graphql_admin_notice.
	 *
	 * @param string               $notice_slug    The slug of the notice.
	 * @param array<string, mixed> $notice         The notice data.
	 * @param bool                 $is_dismissable Whether the notice is dismissable.
	 * @param int                  $count          The count of notices.
	 */
	public static function graphql_admin_notices_render_notice( string $notice_slug, array $notice, bool $is_dismissable, int $count ): void {
		unset( $notice, $is_dismissable );

		$custom_css = '
			body.graphql_page_graphql-ide #wpbody #wpgraphql-admin-notice-' . esc_attr( $notice_slug ) . ' {
				top: ' . esc_attr( ( $count * 45 ) . 'px' ) . ';
			}
		';

		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_register_style( 'wpgraphql-ide-admin-notice', false );
		wp_enqueue_style( 'wpgraphql-ide-admin-notice' );
		wp_add_inline_style( 'wpgraphql-ide-admin-notice', $custom_css );
	}

	/**
	 * Filters to allow GraphQL admin notices to be displayed on the dedicated IDE page.
	 *
	 * @param bool               $is_plugin_scoped_page True if the current page is within scope of the plugin's pages.
	 * @param string             $current_page_id       The ID of the current admin page.
	 * @param array<int, string> $allowed_pages         The list of allowed pages.
	 * @return bool Whether the admin notice is allowed on the current page.
	 */
	public static function graphql_admin_notices_is_allowed_admin_page( bool $is_plugin_scoped_page, string $current_page_id, array $allowed_pages ): bool {
		unset( $allowed_pages );

		if ( 'graphql_page_graphql-ide' === $current_page_id ) {
			return true;
		}

		return $is_plugin_scoped_page;
	}

	/**
	 * Adds a settings link to the plugin actions.
	 *
	 * @param array<int, string> $links The existing action links.
	 * @return array<int, string> The modified action links.
	 */
	public static function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=graphql-settings#graphql_ide_settings' ) ),
			esc_html__( 'Settings', 'wpgraphql-ide' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Adds Docs and Support row-meta links to the plugin listing on the Plugins screen.
	 *
	 * @param array<int, string> $links The existing row-meta links.
	 * @param string             $file  The plugin file path being filtered.
	 * @return array<int, string> The modified row-meta links.
	 */
	public static function add_plugin_row_meta( array $links, string $file ): array {
		if ( plugin_basename( WPGRAPHQL_IDE_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/wp-graphql/wp-graphql/tree/main/plugins/wp-graphql-ide/docs' ),
			esc_html__( 'Docs', 'wpgraphql-ide' )
		);

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/wp-graphql/wp-graphql/issues' ),
			esc_html__( 'Support', 'wpgraphql-ide' )
		);

		return $links;
	}

	/**
	 * Returns the WPGraphQL elephant mark, used by the admin bar entry's
	 * `:before` mask. The mask renders the SVG's alpha shape — fills are
	 * ignored — so we can use core wp-graphql's `wpgraphql-elephant.svg`
	 * verbatim without recoloring it.
	 *
	 * Core doesn't expose a public getter for this asset, so we read the
	 * file directly. If core ever ships one (e.g.
	 * `\WPGraphQL\Admin\GraphiQL\GraphiQL::get_logo_svg()`), swap this
	 * call for that.
	 *
	 * @return string The SVG logo markup, ready to base64-encode for a data URL.
	 */
	public static function graphql_logo_svg(): string {
		if ( defined( 'WPGRAPHQL_PLUGIN_DIR' ) ) {
			$path = WPGRAPHQL_PLUGIN_DIR . 'src/assets/wpgraphql-elephant.svg';
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- local file under the sibling core plugin's path
			$contents = is_readable( $path ) ? file_get_contents( $path ) : false;
			if ( false !== $contents ) {
				return (string) $contents;
			}
		}

		// Defensive fallback for environments where the core file isn't
		// reachable (stripped install, custom autoloader): a minimal
		// elephant glyph traced from the same source. Fill color is
		// irrelevant — the mask treats this as an alpha shape.
		$svg  = '<svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg" aria-label="WPGraphQL">';
		$svg .= '<g transform="matrix(1.93745,0,0,1.93745,-78.2284,-72.6723)" fill="#000">';
		$svg .= '<path d="M81.524,72.256C84.261,72.256 86.48,70.037 86.48,67.3C86.48,64.563 84.261,62.344 81.524,62.344C78.787,62.344 76.568,64.563 76.568,67.3C76.568,70.037 78.787,72.256 81.524,72.256Z"/>';
		$svg .= '<path d="M118.588,90.488C116.007,90.05 113.769,92.012 113.736,94.502C113.711,96.529 112.592,98.429 110.696,99.148C107.17,100.49 103.825,97.905 103.825,94.555L103.825,67.593C103.825,56.199 95.375,46.392 84.052,45.14C71.89,43.794 61.393,52.301 59.526,63.674C59.526,63.682 59.518,63.691 59.51,63.691C49.446,65.888 42,74.837 42,85.47L42,103.665C42,105.933 43.838,107.77 46.105,107.77L55.372,107.77C57.635,107.77 59.353,105.92 59.345,103.657C59.332,100.213 62.851,97.574 66.481,99.152C68.231,99.916 69.264,101.716 69.256,103.624C69.247,105.912 71.102,107.766 73.385,107.766L82.495,107.766C84.762,107.766 86.6,105.928 86.6,103.661L86.6,85.495C86.6,84.83 86.472,84.161 86.162,83.575C85.378,82.076 83.854,81.229 82.251,81.316C82.016,81.328 81.772,81.337 81.529,81.337C73.798,81.337 67.496,75.047 67.488,67.316L67.488,67.3L67.55,66.466C68.058,59.536 73.468,53.846 80.397,53.3C88.648,52.648 95.574,59.181 95.574,67.296L95.574,94.341C95.574,100.663 100.666,106.779 106.926,107.638C114.954,108.741 121.863,102.575 121.999,94.787C122.036,92.714 120.641,90.831 118.596,90.484L118.588,90.488ZM78.337,89.72L78.337,99.098C78.337,99.325 78.151,99.511 77.924,99.511L77.143,99.511C76.97,99.511 76.817,99.399 76.759,99.234C74.942,94.105 70.04,90.426 64.3,90.426C58.56,90.426 53.658,94.11 51.841,99.234C51.783,99.399 51.63,99.511 51.453,99.511L50.672,99.511C50.445,99.511 50.259,99.325 50.259,99.098L50.259,85.47C50.259,79.482 54.005,74.341 59.328,72.33C59.559,72.243 59.811,72.384 59.869,72.623C61.987,81.233 69.128,87.898 77.99,89.315C78.188,89.348 78.337,89.517 78.337,89.72Z"/>';
		$svg .= '</g></svg>';

		return $svg;
	}
}

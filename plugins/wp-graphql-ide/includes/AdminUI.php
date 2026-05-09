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
			'manage_graphql_ide',
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

		$custom_css = '
			#wp-admin-bar-wpgraphql-ide .ab-icon::before,
			#wp-admin-bar-wpgraphql-ide .ab-icon::before {
				background-image: url("data:image/svg+xml;base64,' . base64_encode( self::graphql_logo_svg() ) . '");
				background-size: 100%;
				border-radius: 12px;
				box-sizing: border-box;
				content: "";
				display: inline-block;
				height: 24px;
				width: 24px;
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
	 * Generates the SVG logo for GraphQL.
	 *
	 * @return string The SVG logo markup.
	 */
	public static function graphql_logo_svg(): string {
		$svg  = '<svg width="36" height="36" viewBox="0 0 512 512" fill="none" xmlns="http://www.w3.org/2000/svg" aria-label="WPGraphQL">';
		$svg .= '<circle cx="256" cy="256" r="256" fill="#0E1628"></circle>';
		$svg .= '<path d="m117.592 300.896c0-35.138.58-39.429 7.074-52.301 5.682-11.133 20.758-25.05 30.732-28.065 2.203-.696 2.899.348 6.726 9.858 12.408 31.195 37.11 54.505 69.349 65.29l8.465 2.899.348 16.815c.116 9.394-.116 16.932-.58 16.816-.58 0-2.899-3.131-5.45-6.958-11.945-18.671-35.718-30.036-59.724-28.645-21.802 1.276-40.589 12.061-52.765 30.152l-4.175 6.147zm25.165 85.353c10.09-3.015 17.743-13.568 17.743-24.47 0-7.77 9.51-16.699 17.627-16.699 10.321 0 17.396 6.958 18.787 18.44 1.276 10.32 5.567 16.815 14.032 21.337 4.407 2.436 6.147 2.552 32.471 2.552 26.441 0 28.065-.116 32.588-2.552 5.566-3.015 11.712-9.51 12.872-14.032.58-1.74.928-25.049.928-51.838v-48.706l-2.9-5.103c-4.87-8.582-10.437-11.597-24.469-13.452-19.019-2.436-30.036-7.538-41.053-18.787-8.117-8.118-14.96-21.57-16.815-33.051-3.71-21.918 7.19-46.503 26.325-59.26 11.48-7.654 20.526-10.437 33.979-10.437 8.813 0 12.64.58 19.25 2.9 14.728 5.218 25.745 14.031 33.515 27.02 8.234 13.916 8.002 10.205 8.698 94.514.58 68.885.928 76.539 2.783 82.337 6.146 19.02 18.903 34.559 34.443 42.097 21.338 10.437 42.212 11.133 60.767 2.087 19.019-9.393 33.747-30.615 37.69-54.389 2.435-14.612-1.16-23.193-11.83-28.528-10.32-5.219-21.917-3.827-29.107 3.479-4.639 4.639-6.262 8.118-8.234 17.86-2.551 12.06-8.118 17.394-18.323 17.394-6.378 0-12.524-3.247-15.424-8.233-2.203-3.827-2.319-6.61-2.899-78.743-.58-66.566-.812-75.727-2.667-82.801-12.409-47.895-49.403-80.366-98.69-86.513-24.584-3.015-56.94 6.843-78.858 24.354-17.627 13.916-29.108 30.615-36.53 52.997l-3.479 9.974-11.944 4.29c-19.02 6.727-28.645 12.641-42.909 26.441-12.872 12.525-21.802 26.441-27.6 43.14-5.335 15.772-5.799 21.339-5.799 75.844v51.374l2.668 5.102c3.015 5.683 10.089 11.25 16.003 12.64 2.204.465 14.38.929 27.253 1.044 17.511.116 24.701-.347 29.108-1.623zm132.204-172.793c6.03-2.551 8.35-4.87 11.48-11.597 4.523-9.625 3.248-20.526-3.362-28.064-4.755-5.45-9.51-7.306-18.555-7.306-6.03 0-8.234.58-12.64 3.363-15.077 9.51-14.265 34.79 1.39 42.792 6.147 3.016 15.425 3.363 21.687.812z" fill="#FF8C1A" fill-rule="nonzero"></path>';
		$svg .= '</svg>';

		return $svg;
	}
}

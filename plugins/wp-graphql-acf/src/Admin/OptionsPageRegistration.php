<?php
namespace WPGraphQL\Acf\Admin;

use WPGraphQL\Utils\Utils;

class OptionsPageRegistration {

	/**
	 * Initialize Support for ACF Options Page UIs
	 */
	public function init(): void {

		// Add GraphQL columns to the ACF Options Page registration columns
		// NOTE: the priority must be lower (a bigger number) than 10 to not conflict
		// with the default ACF columns filter
		add_filter( 'manage_acf-ui-options-page_posts_columns', [ $this, 'add_graphql_type_column' ], 20, 1 );

		// Display the GraphQL Type in the ACF Taxonomy Registration Columns
		add_action( 'manage_acf-ui-options-page_posts_custom_column', [ $this, 'render_graphql_columns' ], 10, 2 );

		// Add registration fields to the ACF Options Pages output for exporting / saving as PHP
		add_filter( 'acf/ui_options_page/registration_args', [ $this, 'add_registration_fields' ], 10, 2 );

		// Add tha GraphQL Tab to the ACF Post Type registration screen
		add_filter( 'acf/ui_options_page/additional_settings_tabs', [ $this, 'add_tabs' ] );

		// Render the graphql settings tab in the ACF post type registration screen
		add_action( 'acf/ui_options_page/render_settings_tab/graphql', [ $this, 'render_settings_tab' ] );

		// Hook into acf_get_options_pages to preserve show_in_graphql for programmatically registered pages
		add_filter( 'acf_get_options_pages', [ $this, 'preserve_show_in_graphql' ], 10, 1 );
	}

	/**
	 * @param array<mixed> $args
	 * @param array<mixed> $post
	 *
	 * @return array<mixed>
	 */
	public function add_registration_fields( array $args, array $post ): array {
		$show_in_graphql = false;

		if ( isset( $args['show_in_graphql'] ) ) {
			$show_in_graphql = (bool) $args['show_in_graphql'];
		} elseif ( isset( $post['show_in_graphql'] ) ) {
			$show_in_graphql = (bool) $post['show_in_graphql'];
		}

		$args['show_in_graphql'] = $show_in_graphql;

		$graphql_type_name = '';

		if ( ! empty( $args['graphql_type_name'] ) ) {
			$graphql_type_name = $args['graphql_type_name'];
		} elseif ( ! empty( $post['graphql_type_name'] ) ) {
			$graphql_type_name = $post['graphql_type_name'];
		} elseif ( isset( $args['page_title'] ) ) {
			$graphql_type_name = Utils::format_field_name( $args['page_title'], false );
		}

		// if a graphql_single_name exists, use it, otherwise use the formatted version of the singular_name label
		$args['graphql_type_name'] = $graphql_type_name;

		return $args;
	}

	/**
	 * @param array<mixed> $tabs
	 *
	 * @return array<mixed>
	 */
	public function add_tabs( array $tabs ): array {
		$tabs['graphql'] = __( 'GraphQL', 'wpgraphql-acf' );
		return $tabs;
	}

	/**
	 * @param array<mixed> $acf_ui_options_page
	 */
	public function render_settings_tab( array $acf_ui_options_page ): void {
		acf_render_field_wrap(
			[
				'type'         => 'true_false',
				'name'         => 'show_in_graphql',
				'key'          => 'show_in_graphql',
				'prefix'       => 'acf_ui_options_page',
				'value'        => isset( $acf_ui_options_page['show_in_graphql'] ) && true === (bool) $acf_ui_options_page['show_in_graphql'] ? 1 : 0,
				'ui'           => true,
				'label'        => __( 'Show in GraphQL', 'wpgraphql-acf' ),
				'instructions' => __( 'Whether to show the Post Type in the WPGraphQL Schema.', 'wpgraphql-acf' ),
				'default'      => false,
			]
		);

		$graphql_type_name = $acf_ui_options_page['graphql_type_name'] ?? '';

		if ( empty( $graphql_type_name ) ) {
			$graphql_type_name = ! empty( $acf_ui_options_page['page_title'] ) ? Utils::format_field_name( $acf_ui_options_page['page_title'], true ) : '';
		}

		$graphql_type_name = ucfirst( Utils::format_field_name( $graphql_type_name, true ) );

		acf_render_field_wrap(
			[
				'type'         => 'text',
				'name'         => 'graphql_type_name',
				'key'          => 'graphql_type_name',
				'prefix'       => 'acf_ui_options_page',
				'value'        => $graphql_type_name,
				'label'        => __( 'GraphQL Type Name', 'wpgraphql-acf' ),
				'instructions' => __( 'How the Options Page should be referenced in the GraphQL Schema.', 'wpgraphql-acf' ),
				'default'      => $graphql_type_name,
				'required'     => 1,
				'conditions'   => [
					'field'    => 'show_in_graphql',
					'operator' => '==',
					'value'    => '1',
				],
			],
			'div',
			'field'
		);
	}

	/**
	 * Preserve show_in_graphql value for programmatically registered options pages.
	 * ACF doesn't store custom fields like show_in_graphql when pages are registered programmatically,
	 * so we need to restore it from the registration args.
	 *
	 * @param array<mixed> $options_pages The options pages from ACF
	 *
	 * @return array<mixed>
	 */
	public function preserve_show_in_graphql( array $options_pages ): array {
		if ( empty( $options_pages ) || ! is_array( $options_pages ) ) {
			return $options_pages;
		}

		// For each options page, check if show_in_graphql was set during registration
		// and restore it if ACF didn't preserve it
		return array_map(
			static function ( $option_page ) {
				// If show_in_graphql is not set, allow it to be filtered
				// This allows programmatic registrations to set show_in_graphql => false
				if ( ! isset( $option_page['show_in_graphql'] ) ) {
					$option_page['show_in_graphql'] = apply_filters( 'wpgraphql/acf/options_page/show_in_graphql', true, $option_page );
				}
				return $option_page;
			},
			$options_pages
		);
	}

	/**
	 * Given a list of columns, add "graphql_type" as a column.
	 *
	 * @param array<mixed> $columns The columns on the post type table
	 *
	 * @return array<mixed>
	 */
	public function add_graphql_type_column( array $columns ): array {
		$columns['show_in_graphql'] = __( 'Show in GraphQL', 'wpgraphql-acf' );
		$columns['graphql_type']    = __( 'GraphQL Type', 'wpgraphql-acf' );
		return $columns;
	}

	/**
	 * Determine and echo the markup to show for the graphql_type column
	 *
	 * @param string $column_name The name of the column being rendered
	 * @param int    $post_id     The ID of the post the column is being displayed for
	 */
	public function render_graphql_columns( string $column_name, int $post_id ): void {
		$post_type = acf_get_internal_post_type( $post_id, 'acf-ui-options-page' );

		// if there's no post type, bail early
		if ( empty( $post_type ) ) {
			return;
		}

		// Determine the output for the column
		switch ( $column_name ) {
			case 'graphql_type':
				$graphql_type = Utils::format_type_name( \WPGraphQL\Acf\Utils::get_field_group_name( $post_type ) );
				echo esc_html( $graphql_type );
				break;
			case 'show_in_graphql':
				$show = isset( $post_type['show_in_graphql'] ) && true === (bool) $post_type['show_in_graphql'] ? 'true' : 'false';
				echo esc_html( $show );
				break;
			default:
		}
	}
}

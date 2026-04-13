<?php

namespace WPGraphQL\Acf\Admin;

use WPGraphQL\Utils\Utils;

/**
 * Extend functionality of the ACF Custom Post Type registration
 */
class PostTypeRegistration {

	/**
	 * Initialize support for extending ACF Post Type Registration
	 */
	public function init(): void {

		// Add GraphQL columns to the ACF Custom Post Type registration columns
		// NOTE: the priority must be lower (a bigger number) than 10 to not conflict
		// with the default ACF columns filter
		add_filter( 'manage_acf-post-type_posts_columns', [ $this, 'add_graphql_type_column' ], 20, 1 );

		// Display the GraphQL Type in the ACF Taxonomy Registration Columns
		add_action( 'manage_acf-post-type_posts_custom_column', [ $this, 'render_graphql_columns' ], 10, 2 );

		// Add registration fields to the ACF Post Type output for exporting / saving as PHP
		add_filter( 'acf/post_type/registration_args', [ $this, 'add_cpt_registration_fields' ], 10, 2 );

		// @todo: DELETE ME. This filter existed for one of the versions of beta but was renamed above.
		// this is a polyfill for tests to pass
		add_filter( 'acf/post_type_args', [ $this, 'add_cpt_registration_fields' ], 10, 2 );

		// Add tha GraphQL Tab to the ACF Post Type registration screen
		add_filter( 'acf/post_type/additional_settings_tabs', [ $this, 'add_tabs' ] );

		// Render the graphql settings tab in the ACF post type registration screen
		add_action( 'acf/post_type/render_settings_tab/graphql', [ $this, 'render_settings_tab' ] );

		// Enqueue the scripts for the CPT registration screen to help with setting default values / validation
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ], 10, 1 );
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
	 * @param array<mixed> $acf_post_type
	 */
	public function render_settings_tab( array $acf_post_type ): void {
		acf_render_field_wrap(
			[
				'type'         => 'true_false',
				'name'         => 'show_in_graphql',
				'key'          => 'show_in_graphql',
				'prefix'       => 'acf_post_type',
				'value'        => isset( $acf_post_type['show_in_graphql'] ) && (bool) $acf_post_type['show_in_graphql'],
				'ui'           => true,
				'label'        => __( 'Show in GraphQL', 'wpgraphql-acf' ),
				'instructions' => __( 'Whether to show the Post Type in the WPGraphQL Schema.', 'wpgraphql-acf' ),
				'default'      => false,
			]
		);

		$graphql_single_name = $acf_post_type['graphql_single_name'] ?? '';

		if ( empty( $graphql_single_name ) ) {
			$graphql_single_name = ! empty( $acf_post_type['labels']['singular_name'] ) ? Utils::format_field_name( $acf_post_type['labels']['singular_name'], true ) : '';
		}

		$graphql_single_name = Utils::format_field_name( $graphql_single_name, true );

		acf_render_field_wrap(
			[
				'type'         => 'text',
				'name'         => 'graphql_single_name',
				'key'          => 'graphql_single_name',
				'prefix'       => 'acf_post_type',
				'value'        => $graphql_single_name,
				'label'        => __( 'GraphQL Single Name', 'wpgraphql-acf' ),
				'instructions' => __( 'How the type should be referenced in the GraphQL Schema.', 'wpgraphql-acf' ),
				'default'      => $graphql_single_name,
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

		$graphql_plural_name = $acf_post_type['graphql_plural_name'] ?? '';

		if ( empty( $graphql_plural_name ) ) {
			$graphql_plural_name = ! empty( $acf_post_type['labels']['name'] ) ? Utils::format_field_name( $acf_post_type['labels']['name'], true ) : '';
		}

		$graphql_plural_name = Utils::format_field_name( $graphql_plural_name, true );

		acf_render_field_wrap(
			[
				'type'         => 'text',
				'name'         => 'graphql_plural_name',
				'key'          => 'graphql_plural_name',
				'prefix'       => 'acf_post_type',
				'value'        => $graphql_plural_name,
				'label'        => __( 'GraphQL Plural Name', 'wpgraphql-acf' ),
				'instructions' => __( 'How the type should be referenced in the GraphQL Schema.', 'wpgraphql-acf' ),
				'default'      => $graphql_plural_name,
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
	 * @param array<mixed> $args
	 * @param array<mixed> $post_type
	 *
	 * @return array<mixed>
	 */
	public function add_cpt_registration_fields( array $args, array $post_type ): array {

		// respect the show_in_graphql value. If not set, use the value of $args['public'] to determine if the post type should be shown in graphql
		$args['show_in_graphql'] = isset( $post_type['show_in_graphql'] ) ? (bool) $post_type['show_in_graphql'] : true === $args['public'];

		$graphql_single_name = '';

		if ( isset( $post_type['graphql_single_name'] ) ) {
			$graphql_single_name = $post_type['graphql_single_name'];
		} elseif ( isset( $args['graphql_single_name'] ) ) {
			$graphql_single_name = $args['graphql_single_name'];
		} elseif ( isset( $args['labels']['singular_name'] ) ) {
			$graphql_single_name = Utils::format_field_name( $args['labels']['singular_name'], true );
		}

		// if a graphql_single_name exists, use it, otherwise use the formatted version of the singular_name label
		$args['graphql_single_name'] = $graphql_single_name;

		$graphql_plural_name = '';

		if ( isset( $post_type['graphql_plural_name'] ) ) {
			$graphql_plural_name = $post_type['graphql_plural_name'];
		} elseif ( isset( $args['graphql_plural_name'] ) ) {
			$graphql_plural_name = $args['graphql_plural_name'];
		} elseif ( isset( $args['labels']['name'] ) ) {
			$graphql_plural_name = Utils::format_field_name( $args['labels']['name'], true );
		}

		// if the plural name exists, use it. Otherwie use the formatted version of the name.
		$args['graphql_plural_name'] = $graphql_plural_name;

		return $args;
	}

	/**
	 * Enqueue the admin scripts for the ACF Post Type settings.
	 * This script is only enqueued on the ACF Post Type edit screen.
	 *
	 * @param ?string $screen
	 */
	public function enqueue_admin_scripts( ?string $screen ): void {
		global $post;

		if ( empty( $screen ) ) {
			return;
		}

		// if the screen is not a new post / edit post screen, do nothing
		if ( ! ( 'post-new.php' === $screen || 'post.php' === $screen ) ) {
			return;
		}

		// if the global post is not set, or the post type is not "acf-post-type", do nothing
		if ( ! isset( $post->post_type ) || 'acf-post-type' !== $post->post_type ) {
			return;
		}

		wp_enqueue_script(
			'graphql-acf-post-type',
			plugins_url( '/assets/admin/js/post-type-settings.js', __DIR__ ),
			[
				'acf-internal-post-type',
			],
			WPGRAPHQL_FOR_ACF_VERSION,
			true
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
		$post_type = acf_get_internal_post_type( $post_id, 'acf-post-type' );

		// if there's no post type, bail early
		if ( empty( $post_type ) ) {
			return;
		}

		// Determine the output for the column
		switch ( $column_name ) {
			case 'graphql_type':
				$graphql_type = isset( $post_type['graphql_single_name'] ) ? Utils::format_type_name( $post_type['graphql_single_name'] ) : '';
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

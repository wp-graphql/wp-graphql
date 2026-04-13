<?php
namespace WPGraphQL\Acf\ThirdParty\WPGraphQLContentBlocks;

use WPGraphQL\Acf\Registry;


class WPGraphQLContentBlocks {

	/**
	 * Initialize support for WPGraphQL Content Blocks
	 */
	public function init(): void {

		// If WPGraphQL Content Blocks is not active, bail
		if ( ! defined( 'WPGRAPHQL_CONTENT_BLOCKS_DIR' ) ) {
			return;
		}

		// Filter the interfaces returned as possible types for ACF Field Groups to be associated with
		add_filter( 'wpgraphql/acf/get_all_possible_types/interfaces', [ $this, 'add_blocks_as_possible_type' ], 10, 1 );

		// This filter was introduced in WPGraphQL Content Blocks 1.2.0
		// @see: https://github.com/wpengine/wp-graphql-content-blocks/pull/148
		add_filter( 'wpgraphql_content_blocks_should_apply_post_type_editor_blocks_interfaces', [ $this, 'filter_editor_block_interfaces' ], 10, 7 );

		// Register Block Types
		add_action( 'wpgraphql/acf/type_registry/init', [ $this, 'register_types' ], 10, 1 );
	}

	/**
	 * @param bool                     $should                                 Whether to apply the ${PostType}EditorBlock Interface. If the filter returns false, the default
	 *                                                                         logic will not execute and the ${PostType}EditorBlock will not be applied.
	 * @param string                   $block_name                             The name of the block Interfaces will be applied to
	 * @param \WP_Block_Editor_Context $block_editor_context                   The context of the Block Editor
	 * @param \WP_Post_Type            $post_type                              The Post Type an Interface might be applied to the block for
	 * @param array<mixed>             $all_registered_blocks                  Array of all registered blocks
	 * @param array<mixed>|bool        $supported_blocks_for_post_type_context Array of all supported blocks for the context
	 * @param array<mixed>             $block_and_graphql_enabled_post_types   Array of Post Types that have block editor and GraphQL support
	 */
	public function filter_editor_block_interfaces( bool $should, string $block_name, \WP_Block_Editor_Context $block_editor_context, \WP_Post_Type $post_type, array $all_registered_blocks, $supported_blocks_for_post_type_context, array $block_and_graphql_enabled_post_types ): bool {
		if ( ! empty( $all_registered_blocks[ $block_name ]->post_types ) && ! in_array( $post_type->name, $all_registered_blocks[ $block_name ]->post_types, true ) ) {
			return false;
		}
		return $should;
	}

	/**
	 * @param array<mixed> $interfaces The interfaces shown as possible types for ACF Field Groups to be associated with
	 *
	 * @return array<mixed>
	 */
	public function add_blocks_as_possible_type( array $interfaces ): array {
		$interfaces['AcfBlock'] = [
			'label'        => __( 'ACF Block', 'wpgraphql-acf' ),
			'plural_label' => __( 'All Gutenberg Blocks registered by ACF Blocks', 'wpgraphql-acf' ),
		];

		return $interfaces;
	}

	/**
	 * Register ACF Blocks to the Schema
	 *
	 * @param \WPGraphQL\Acf\Registry $registry The WPGraphQL for ACF Registry
	 *
	 * @throws \Exception
	 */
	public function register_types( Registry $registry ): void {
		if ( ! function_exists( 'acf_get_block_types' ) ) {
			return;
		}

		register_graphql_interface_type(
			'AcfBlock',
			[
				'eagerlyLoadType' => true,
				'interfaces'      => [ 'EditorBlock' ],
				'description'     => __( 'Block registered by ACF', 'wpgraphql-acf' ),
				'fields'          => [
					'name' => [
						'type' => 'String',
					],
				],
			]
		);

		$acf_block_types = acf_get_block_types();

		if ( empty( $acf_block_types ) ) {
			return;
		}

		$graphql_enabled_acf_blocks = [];

		foreach ( $acf_block_types as $acf_block_type ) {
			if ( ! $registry->should_field_group_show_in_graphql( $acf_block_type ) ) {
				continue;
			}

			$type_name = $registry->get_field_group_graphql_type_name( $acf_block_type );

			if ( empty( $type_name ) ) {
				continue;
			}

			$graphql_enabled_acf_blocks[] = $type_name;
		}

		if ( empty( $graphql_enabled_acf_blocks ) ) {
			return;
		}

		register_graphql_interfaces_to_types( [ 'AcfBlock' ], $graphql_enabled_acf_blocks );
	}
}

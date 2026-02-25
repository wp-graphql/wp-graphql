<?php
/**
 * Tests for WPGraphQL\Acf\ThirdParty\WPGraphQLContentBlocks.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit\ThirdParty
 */

use WPGraphQL\Acf\ThirdParty\WPGraphQLContentBlocks\WPGraphQLContentBlocks;

class WPGraphQLContentBlocksTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function test_add_blocks_as_possible_type_adds_acf_block(): void {
		$integration = new WPGraphQLContentBlocks();
		$integration->init();

		// When Content Blocks is not active (constant not defined), init() returns early and the filter is not added.
		if ( ! defined( 'WPGRAPHQL_CONTENT_BLOCKS_DIR' ) ) {
			// Test the method directly to cover add_blocks_as_possible_type logic.
			$interfaces = [];
			$result    = $integration->add_blocks_as_possible_type( $interfaces );
			$this->assertArrayHasKey( 'AcfBlock', $result );
			$this->assertSame( 'ACF Block', $result['AcfBlock']['label'] );
			$this->assertSame( 'All Gutenberg Blocks registered by ACF Blocks', $result['AcfBlock']['plural_label'] );
			return;
		}

		$interfaces = apply_filters( 'wpgraphql/acf/get_all_possible_types/interfaces', [] );
		$this->assertArrayHasKey( 'AcfBlock', $interfaces );
		$this->assertSame( 'ACF Block', $interfaces['AcfBlock']['label'] );
	}

	public function test_filter_editor_block_interfaces_returns_false_when_post_type_not_in_block(): void {
		if ( ! class_exists( 'WP_Block_Editor_Context' ) ) {
			$this->markTestSkipped( 'WP_Block_Editor_Context not available' );
		}
		$integration = new WPGraphQLContentBlocks();
		$post_type   = get_post_type_object( 'page' );
		$this->assertNotNull( $post_type );
		$block                 = new \stdClass();
		$block->post_types     = [ 'post' ];
		$all_registered_blocks = [ 'acf/foo' => $block ];
		$context               = new \WP_Block_Editor_Context( [] );
		$result                = $integration->filter_editor_block_interfaces( true, 'acf/foo', $context, $post_type, $all_registered_blocks, [], [] );
		$this->assertFalse( $result );
	}

	public function test_filter_editor_block_interfaces_returns_should_when_post_type_in_block(): void {
		if ( ! class_exists( 'WP_Block_Editor_Context' ) ) {
			$this->markTestSkipped( 'WP_Block_Editor_Context not available' );
		}
		$integration = new WPGraphQLContentBlocks();
		$post_type   = get_post_type_object( 'post' );
		$this->assertNotNull( $post_type );
		$block                 = new \stdClass();
		$block->post_types     = [ 'post' ];
		$all_registered_blocks = [ 'acf/foo' => $block ];
		$context               = new \WP_Block_Editor_Context( [] );
		$result                = $integration->filter_editor_block_interfaces( true, 'acf/foo', $context, $post_type, $all_registered_blocks, [], [] );
		$this->assertTrue( $result );
	}

	public function test_filter_editor_block_interfaces_returns_should_when_block_has_no_post_types(): void {
		if ( ! class_exists( 'WP_Block_Editor_Context' ) ) {
			$this->markTestSkipped( 'WP_Block_Editor_Context not available' );
		}
		$integration = new WPGraphQLContentBlocks();
		$post_type   = get_post_type_object( 'page' );
		$this->assertNotNull( $post_type );
		$block                 = new \stdClass();
		$block->post_types     = [];
		$all_registered_blocks = [ 'acf/foo' => $block ];
		$context               = new \WP_Block_Editor_Context( [] );
		$result                = $integration->filter_editor_block_interfaces( true, 'acf/foo', $context, $post_type, $all_registered_blocks, [], [] );
		$this->assertTrue( $result );
	}
}

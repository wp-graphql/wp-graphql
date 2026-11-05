<?php

class WysiwygFieldTest extends \Tests\WPGraphQL\Acf\WPUnit\AcfFieldTestCase {

	/**
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();
	}

	public function get_field_type(): string {
		return 'wysiwyg';
	}

	public function get_expected_field_resolve_type(): ?string {
		return 'String';
	}

	public function get_data_to_store():string {
		return 'some text';
	}

	public function get_block_query_fragment() {
		return '
		fragment BlockQueryFragment on AcfTestGroup {
		  testWysiwyg
		}
		';
	}

	public function get_block_data_to_store() {
		return $this->get_data_to_store();
	}

	public function get_expected_block_fragment_response() {
		return wpautop( $this->get_data_to_store() );
	}

	public function get_expected_fragment_response() {
		return wpautop( $this->get_data_to_store() );
	}

	public function get_acf_clone_fragment(): string {
		return '
		fragment AcfTestGroupFragment on AcfTestGroup {
		  ' . $this->get_formatted_clone_field_name() . '
		}
		';
	}

	/**
	 * @return mixed
	 */
	public function get_expected_clone_value() {
		return wpautop( $this->get_clone_value_to_save() );
	}

	/**
	 * Appended to the content by a callback on the_content, the way plugins add sharing buttons
	 * or related posts to it.
	 */
	private const THE_CONTENT_MARKER = '<div class="the-content-marker"></div>';

	/**
	 * Queries the given selection on the published post's AcfTestGroup while a callback on
	 * the_content appends a marker.
	 *
	 * @param string $selection The selection on the AcfTestGroup type.
	 */
	private function query_test_group_with_the_content_marker( string $selection ): array {
		$append_marker = static function ( $content ) {
			return $content . self::THE_CONTENT_MARKER;
		};

		add_filter( 'the_content', $append_marker );

		$actual = $this->graphql(
			[
				'query'     => 'query ( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { acfTestGroup { ' . $selection . ' } } }',
				'variables' => [ 'id' => $this->published_post->ID ],
			]
		);

		remove_filter( 'the_content', $append_marker );

		return $actual;
	}

	/**
	 * Regression test for wp-graphql/wp-graphql#4562: ACF formats a WYSIWYG value with its own
	 * acf_the_content filter, and the value was then run through the_content as well, so whatever
	 * other plugins hook onto the_content was added to it.
	 */
	public function testWysiwygValueIsNotRunThroughTheContent(): void {
		$value     = "First paragraph\n\nSecond paragraph";
		$field_key = $this->register_acf_field(
			[
				'name'               => 'wysiwyg_the_content',
				'graphql_field_name' => 'wysiwygTheContent',
			]
		);
		update_field( $field_key, $value, $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygTheContent' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.wysiwygTheContent', wpautop( $value ) ),
			]
		);
	}

	/**
	 * The sub fields of a group field are resolved from their stored values, so a WYSIWYG sub
	 * field gets ACF's formatting there instead of the_content.
	 */
	public function testWysiwygInGroupIsFormattedWithoutTheContent(): void {
		$value     = "First paragraph\n\nSecond paragraph";
		$group_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_group',
				'type'               => 'group',
				'name'               => 'wysiwyg_group',
				'graphql_field_name' => 'wysiwygGroup',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_group_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $group_key, [ 'field_wysiwyg_group_wysiwyg' => $value ], $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygGroup { wysiwyg }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.wysiwygGroup.wysiwyg', wpautop( $value ) ),
			]
		);
	}

	/**
	 * A WYSIWYG sub field of a group resolves to the same value get_field() returns for it,
	 * including the "]]>" escape ACF applies after acf_the_content.
	 */
	public function testWysiwygInGroupMatchesGetField(): void {
		$value     = "First paragraph\n\nA ]]> in the text";
		$group_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_group_parity',
				'type'               => 'group',
				'name'               => 'wysiwyg_group_parity',
				'graphql_field_name' => 'wysiwygGroupParity',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_group_parity_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $group_key, [ 'field_wysiwyg_group_parity_wysiwyg' => $value ], $this->published_post->ID );

		$expected = get_field( 'wysiwyg_group_parity', $this->published_post->ID )['wysiwyg'];
		$this->assertStringContainsString( ']]&gt;', $expected );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygGroupParity { wysiwyg }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.wysiwygGroupParity.wysiwyg', $expected ),
			]
		);
	}

	/**
	 * The markup a dynamic block stores: just its block comment, rendered by its callback.
	 */
	private const DYNAMIC_BLOCK = '<!-- wp:wpgraphql-acf-test/dynamic /-->';

	/**
	 * What the dynamic block's callback renders.
	 */
	private const DYNAMIC_BLOCK_OUTPUT = '<div class="dynamic-block-output">Rendered</div>';

	/**
	 * Registers a dynamic block for the duration of a test.
	 */
	private function register_dynamic_block(): void {
		register_block_type(
			'wpgraphql-acf-test/dynamic',
			[
				'render_callback' => static function () {
					return self::DYNAMIC_BLOCK_OUTPUT;
				},
			]
		);
	}

	private function unregister_dynamic_block(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'wpgraphql-acf-test/dynamic' ) ) {
			unregister_block_type( 'wpgraphql-acf-test/dynamic' );
		}
	}

	/**
	 * ACF's formatting doesn't render blocks, but WYSIWYG values were run through the_content
	 * before 3.1.0, which did, so block markup in a WYSIWYG value is still rendered.
	 */
	public function testBlockMarkupInWysiwygIsRendered(): void {
		$this->register_dynamic_block();

		$field_key = $this->register_acf_field(
			[
				'name'               => 'wysiwyg_blocks',
				'graphql_field_name' => 'wysiwygBlocks',
			]
		);
		update_field( $field_key, self::DYNAMIC_BLOCK, $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygBlocks' );

		$this->unregister_dynamic_block();

		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.acfTestGroup.wysiwygBlocks', self::NOT_NULL ) ] );
		$this->assertStringContainsString( self::DYNAMIC_BLOCK_OUTPUT, $actual['data']['post']['acfTestGroup']['wysiwygBlocks'] );
		$this->assertStringNotContainsString( 'wp:wpgraphql-acf-test/dynamic', $actual['data']['post']['acfTestGroup']['wysiwygBlocks'] );
	}

	/**
	 * Block markup is rendered on every path, including sub fields resolved from their stored values.
	 */
	public function testBlockMarkupInWysiwygInGroupIsRendered(): void {
		$this->register_dynamic_block();

		$group_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_blocks_group',
				'type'               => 'group',
				'name'               => 'wysiwyg_blocks_group',
				'graphql_field_name' => 'wysiwygBlocksGroup',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_blocks_group_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $group_key, [ 'field_wysiwyg_blocks_group_wysiwyg' => self::DYNAMIC_BLOCK ], $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygBlocksGroup { wysiwyg }' );

		$this->unregister_dynamic_block();

		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.acfTestGroup.wysiwygBlocksGroup.wysiwyg', self::NOT_NULL ) ] );
		$this->assertStringContainsString( self::DYNAMIC_BLOCK_OUTPUT, $actual['data']['post']['acfTestGroup']['wysiwygBlocksGroup']['wysiwyg'] );
	}

	/**
	 * The wpgraphql/acf/wysiwyg/render_blocks filter turns block rendering off.
	 */
	public function testBlockRenderingCanBeTurnedOff(): void {
		$this->register_dynamic_block();
		add_filter( 'wpgraphql/acf/wysiwyg/render_blocks', '__return_false' );

		$field_key = $this->register_acf_field(
			[
				'name'               => 'wysiwyg_blocks_off',
				'graphql_field_name' => 'wysiwygBlocksOff',
			]
		);
		update_field( $field_key, self::DYNAMIC_BLOCK, $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygBlocksOff' );

		remove_filter( 'wpgraphql/acf/wysiwyg/render_blocks', '__return_false' );
		$this->unregister_dynamic_block();

		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.acfTestGroup.wysiwygBlocksOff', self::NOT_NULL ) ] );
		$this->assertStringContainsString( 'wp:wpgraphql-acf-test/dynamic', $actual['data']['post']['acfTestGroup']['wysiwygBlocksOff'] );
		$this->assertStringNotContainsString( self::DYNAMIC_BLOCK_OUTPUT, $actual['data']['post']['acfTestGroup']['wysiwygBlocksOff'] );
	}

	/**
	 * A static block followed by a dynamic block, as a WYSIWYG field stores them.
	 */
	private const BLOCK_MARKUP = "<!-- wp:paragraph -->\n<p>First paragraph</p>\n<!-- /wp:paragraph -->\n\n" . self::DYNAMIC_BLOCK;

	/**
	 * Regression test for wp-graphql/wp-graphql#4605: blocks were rendered after ACF's formatting,
	 * so ACF's wpautop ran over the block markup and wrapped the rendered blocks in paragraphs.
	 * They now render the way the_content renders them.
	 */
	public function testBlockMarkupIsNotWrappedInParagraphs(): void {
		$this->register_dynamic_block();
		$expected = apply_filters( 'the_content', self::BLOCK_MARKUP );

		$field_key = $this->register_acf_field(
			[
				'name'               => 'wysiwyg_blocks_autop',
				'graphql_field_name' => 'wysiwygBlocksAutop',
			]
		);
		update_field( $field_key, self::BLOCK_MARKUP, $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygBlocksAutop' );

		$this->unregister_dynamic_block();

		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.acfTestGroup.wysiwygBlocksAutop', $expected ) ] );
	}

	/**
	 * Sub fields of a group are formatted from their stored values, and render blocks the same way.
	 */
	public function testBlockMarkupInGroupIsNotWrappedInParagraphs(): void {
		$this->register_dynamic_block();
		$expected = apply_filters( 'the_content', self::BLOCK_MARKUP );

		$group_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_blocks_autop_group',
				'type'               => 'group',
				'name'               => 'wysiwyg_blocks_autop_group',
				'graphql_field_name' => 'wysiwygBlocksAutopGroup',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_blocks_autop_group_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $group_key, [ 'field_wysiwyg_blocks_autop_group_wysiwyg' => self::BLOCK_MARKUP ], $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygBlocksAutopGroup { wysiwyg }' );

		$this->unregister_dynamic_block();

		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.acfTestGroup.wysiwygBlocksAutopGroup.wysiwyg', $expected ) ] );
	}

	/**
	 * Rows of a repeater are formatted by ACF when it formats the repeater, and render blocks the
	 * same way.
	 */
	public function testBlockMarkupInRepeaterIsNotWrappedInParagraphs(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$this->register_dynamic_block();
		$expected = apply_filters( 'the_content', self::BLOCK_MARKUP );

		$repeater_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_blocks_autop_repeater',
				'type'               => 'repeater',
				'name'               => 'wysiwyg_blocks_autop_repeater',
				'graphql_field_name' => 'wysiwygBlocksAutopRepeater',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_blocks_autop_repeater_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $repeater_key, [ [ 'field_wysiwyg_blocks_autop_repeater_wysiwyg' => self::BLOCK_MARKUP ] ], $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygBlocksAutopRepeater { wysiwyg }' );

		$this->unregister_dynamic_block();

		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.acfTestGroup.wysiwygBlocksAutopRepeater.0.wysiwyg', $expected ) ] );
	}

	/**
	 * Skipping wpautop for a value with blocks leaves ACF's acf_the_content callbacks as they were,
	 * in the same order, so a field formatted later in the request gets ACF's usual formatting.
	 * wpautop has to run before shortcode_unautop, or a shortcode on its own line ends up in a
	 * paragraph.
	 */
	public function testBlockMarkupDoesNotChangeFormattingOfLaterFields(): void {
		global $wp_filter;

		$this->register_dynamic_block();
		add_shortcode(
			'wpgraphql_acf_test_shortcode',
			static function () {
				return '<div class="shortcode-output">Shortcode</div>';
			}
		);
		$shortcode_value = "[wpgraphql_acf_test_shortcode]\n\nAfter the shortcode";
		$expected        = apply_filters( 'acf_the_content', $shortcode_value );
		$callbacks       = array_map( 'array_keys', $wp_filter['acf_the_content']->callbacks );

		$blocks_key = $this->register_acf_field(
			[
				'name'               => 'wysiwyg_blocks_first',
				'graphql_field_name' => 'wysiwygBlocksFirst',
			]
		);
		update_field( $blocks_key, self::BLOCK_MARKUP, $this->published_post->ID );
		// Registering a second field with register_acf_field() would register the field group again,
		// without the first field, so add it to the same group directly.
		acf_add_local_field(
			array_merge(
				acf_get_local_field( $blocks_key ),
				[
					'key'                => 'field_wysiwyg_shortcode_after',
					'name'               => 'wysiwyg_shortcode_after',
					'graphql_field_name' => 'wysiwygShortcodeAfter',
				]
			)
		);
		update_field( 'field_wysiwyg_shortcode_after', $shortcode_value, $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygBlocksFirst wysiwygShortcodeAfter' );

		$callbacks_after = array_map( 'array_keys', $wp_filter['acf_the_content']->callbacks );
		remove_shortcode( 'wpgraphql_acf_test_shortcode' );
		$this->unregister_dynamic_block();

		$this->assertSame( $callbacks, $callbacks_after );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.acfTestGroup.wysiwygShortcodeAfter', $expected ) ] );
	}

	/**
	 * Rendering blocks before ACF's wpautop only applies to GraphQL responses. get_field() still
	 * returns ACF's formatting, and isn't served a value formatted for GraphQL from ACF's cache.
	 */
	public function testBlockRenderingDoesNotChangeGetField(): void {
		$this->register_dynamic_block();
		$expected = str_replace( ']]>', ']]&gt;', apply_filters( 'acf_the_content', self::BLOCK_MARKUP ) );

		$field_key = $this->register_acf_field(
			[
				'name'               => 'wysiwyg_blocks_get_field',
				'graphql_field_name' => 'wysiwygBlocksGetField',
			]
		);
		update_field( $field_key, self::BLOCK_MARKUP, $this->published_post->ID );

		$this->query_test_group_with_the_content_marker( 'wysiwygBlocksGetField' );
		$actual = get_field( 'wysiwyg_blocks_get_field', $this->published_post->ID );

		$this->unregister_dynamic_block();

		$this->assertSame( $expected, $actual );
	}

	public function testWysiwygInRepeaterIsNotRunThroughTheContent(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}

		$value        = "First paragraph\n\nSecond paragraph";
		$repeater_key = $this->register_acf_field(
			[
				'key'                => 'field_wysiwyg_repeater',
				'type'               => 'repeater',
				'name'               => 'wysiwyg_repeater',
				'graphql_field_name' => 'wysiwygRepeater',
				'sub_fields'         => [
					[
						'key'                => 'field_wysiwyg_repeater_wysiwyg',
						'type'               => 'wysiwyg',
						'name'               => 'wysiwyg',
						'show_in_graphql'    => 1,
						'graphql_field_name' => 'wysiwyg',
					],
				],
			]
		);
		update_field( $repeater_key, [ [ 'field_wysiwyg_repeater_wysiwyg' => $value ] ], $this->published_post->ID );

		$actual = $this->query_test_group_with_the_content_marker( 'wysiwygRepeater { wysiwyg }' );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.wysiwygRepeater.0.wysiwyg', wpautop( $value ) ),
			]
		);
	}

//	public function queryForPostByDatabaseId() {
//		return '
//		query GetPost($id:ID!){
//		  post(id:$id idType:DATABASE_ID) {
//		    __typename
//		    ...OnWithAcfAcfTestGroup {
//		      acfTestGroup {
//		        ' . $this->get_formatted_field_name(). '
//		      }
//		    }
//		  }
//		}
//		';
//	}

//	public function testQueryFieldOnPostReturnsExpectedValue() {
//
//		$value = 'test content';
//
//		update_field( $this->published_post->ID, $this->get_field_name(), $value );
//
//		$actual = $this->graphql([
//			'query' => $this->queryForPostByDatabaseId(),
//			'variables' => [
//				'id' => $this->published_post->ID,
//			],
//		]);
//
//		codecept_debug( $actual );
//
//	}

}

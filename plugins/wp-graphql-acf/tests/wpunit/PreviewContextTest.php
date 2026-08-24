<?php

/**
 * Tests for ACF field values under WPGraphQL's request-level preview context
 * (the X-GraphQL-Preview header / extensions.preview overlay).
 *
 * The WithAcf{FieldGroup} accessor field is marked isPreviewable, so an authorized
 * preview resolves the whole field group against the preview revision (the post's
 * autosave), reproducing the data flow of the legacy asPreview node swap: field
 * values come from the autosave in the classic editor, and in the block editor only
 * when ACF's Block Editor Datastore is enabled (ACF PRO 6.8.1+), since without it
 * the block editor never stores field values on autosaves.
 */
class PreviewContextTest extends \Codeception\TestCase\WPTestCase {

	public $group_key;
	public $field_key;
	public $post_id;
	public $autosave_id;
	public $admin;

	public function setUp(): void {
		parent::setUp();

		$this->group_key = __CLASS__;
		$this->field_key = 'field_preview_context_text';
		$this->admin     = self::factory()->user->create( [ 'role' => 'administrator' ] );

		WPGraphQL::clear_schema();

		acf_add_local_field_group(
			[
				'key'                => $this->group_key,
				'title'              => 'Preview Context Fields',
				'fields'             => [
					[
						'parent'          => $this->group_key,
						'key'             => $this->field_key,
						'label'           => 'Preview Text',
						'name'            => 'preview_text',
						'type'            => 'text',
						'show_in_graphql' => 1,
					],
				],
				'location'           => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ] ] ],
				'active'             => true,
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'previewContextFields',
				'graphql_types'      => [ 'Post' ],
			]
		);

		$this->post_id = self::factory()->post->create(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Published Title',
				'post_content' => 'Published Content',
			]
		);

		update_field( 'preview_text', 'published value', $this->post_id );

		// The autosave WordPress creates when an editor clicks Preview, carrying the
		// in-progress ACF value the way ACF stores it on a revision: the value row
		// plus the field-key reference row.
		wp_set_current_user( $this->admin );

		if ( ! function_exists( 'wp_create_post_autosave' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$autosave = wp_create_post_autosave(
			[
				'post_ID'      => $this->post_id,
				'post_type'    => 'post',
				'post_title'   => 'Previewed Title',
				'post_content' => 'Previewed Content',
				'post_excerpt' => '',
			]
		);

		$this->assertNotWPError( $autosave );
		$this->autosave_id = (int) $autosave;

		update_metadata( 'post', $this->autosave_id, 'preview_text', 'previewed value' );
		update_metadata( 'post', $this->autosave_id, '_preview_text', $this->field_key );
	}

	public function tearDown(): void {
		acf_remove_local_field_group( $this->group_key );
		WPGraphQL::clear_schema();
		wp_delete_post( $this->post_id, true );
		wp_delete_user( $this->admin );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function get_query(): string {
		return '
		query PostWithAcf( $id: ID! ) {
			post( id: $id, idType: DATABASE_ID ) {
				databaseId
				isPreview
				previewContextFields {
					previewText
				}
			}
		}
		';
	}

	private function get_preview_request(): array {
		return [
			'query'      => $this->get_query(),
			'variables'  => [ 'id' => $this->post_id ],
			'extensions' => [ 'preview' => [ 'databaseId' => $this->post_id ] ],
		];
	}

	/**
	 * Classic editor: ACF values resolve from the autosave under preview context,
	 * with the node keeping its published identity.
	 */
	public function testAcfFieldResolvesFromAutosaveUnderPreviewContextClassicEditor() {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		wp_set_current_user( $this->admin );

		// Without preview context: the published value.
		$published = graphql(
			[
				'query'     => $this->get_query(),
				'variables' => [ 'id' => $this->post_id ],
			]
		);

		codecept_debug( $published );

		$this->assertArrayNotHasKey( 'errors', $published );
		$this->assertSame( 'published value', $published['data']['post']['previewContextFields']['previewText'] );

		// With preview context: the autosave value, on the published identity.
		$preview = graphql( $this->get_preview_request() );

		codecept_debug( $preview );

		$this->assertArrayNotHasKey( 'errors', $preview );
		$this->assertSame( $this->post_id, $preview['data']['post']['databaseId'], 'Identity is preserved while previewing' );
		$this->assertTrue( $preview['data']['post']['isPreview'], 'The node reports the preview state' );
		$this->assertSame( 'previewed value', $preview['data']['post']['previewContextFields']['previewText'], 'The ACF value overlays from the autosave' );

		remove_filter( 'use_block_editor_for_post', '__return_false' );
	}

	/**
	 * Migration parity: the preview context resolves the same ACF value the legacy
	 * asPreview swap resolves, so migrating transports does not change what users
	 * see in previews.
	 */
	public function testPreviewContextMatchesLegacyAsPreviewValue() {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		wp_set_current_user( $this->admin );

		$legacy = graphql(
			[
				'query'     => '
				query Legacy( $id: ID! ) {
					post( id: $id, idType: DATABASE_ID, asPreview: true ) {
						previewContextFields {
							previewText
						}
					}
				}
				',
				'variables' => [ 'id' => $this->post_id ],
			]
		);

		$context = graphql( $this->get_preview_request() );

		codecept_debug( $legacy );
		codecept_debug( $context );

		$this->assertArrayNotHasKey( 'errors', $legacy );
		$this->assertArrayNotHasKey( 'errors', $context );
		$this->assertSame( 'previewed value', $legacy['data']['post']['previewContextFields']['previewText'], 'The legacy swap resolves the autosave ACF value' );
		$this->assertSame(
			$legacy['data']['post']['previewContextFields']['previewText'],
			$context['data']['post']['previewContextFields']['previewText'],
			'Migrating from asPreview to the preview context must not change the previewed ACF value'
		);

		remove_filter( 'use_block_editor_for_post', '__return_false' );
	}

	/**
	 * Block editor without the ACF datastore: the block editor never stores field
	 * values on autosaves, so previews resolve the published values (the
	 * long-standing block editor limitation; title/content still preview).
	 */
	public function testBlockEditorWithoutDatastoreResolvesPublishedValues() {
		// The 'post' post type uses the block editor by default.
		wp_set_current_user( $this->admin );

		$preview = graphql( $this->get_preview_request() );

		codecept_debug( $preview );

		$this->assertArrayNotHasKey( 'errors', $preview );
		$this->assertSame( 'published value', $preview['data']['post']['previewContextFields']['previewText'], 'Without the datastore, block editor previews resolve published ACF values' );
	}

	/**
	 * Block editor with the ACF Block Editor Datastore enabled (ACF PRO 6.8.1+):
	 * field values are saved to autosaves through the editor's REST flow, so
	 * previews resolve them from the revision. Simulated by enabling the setting
	 * and seeding the autosave meta the way the datastore stores it.
	 */
	public function testBlockEditorWithDatastoreResolvesAutosaveValues() {
		if ( ! function_exists( 'acf_is_pro' ) || ! acf_is_pro() || ! is_string( acf_get_setting( 'version' ) ) || version_compare( acf_get_setting( 'version' ), '6.8.1', '<' ) ) {
			$this->markTestSkipped( 'The ACF Block Editor Datastore requires ACF PRO 6.8.1+.' );
		}

		add_filter( 'acf/settings/enable_datastore', '__return_true' );
		wp_set_current_user( $this->admin );

		$preview = graphql( $this->get_preview_request() );

		remove_filter( 'acf/settings/enable_datastore', '__return_true' );

		codecept_debug( $preview );

		$this->assertArrayNotHasKey( 'errors', $preview );
		$this->assertSame( 'previewed value', $preview['data']['post']['previewContextFields']['previewText'], 'With the datastore, block editor previews resolve the autosave ACF values' );
	}

	/**
	 * An unauthenticated request carrying preview context must resolve only
	 * published ACF values, identically to a request without context.
	 */
	public function testUnauthenticatedPreviewContextResolvesPublishedAcfValues() {
		add_filter( 'use_block_editor_for_post', '__return_false' );
		wp_set_current_user( 0 );

		$preview = graphql( $this->get_preview_request() );

		codecept_debug( $preview );

		$this->assertArrayNotHasKey( 'errors', $preview );
		$this->assertSame( 'published value', $preview['data']['post']['previewContextFields']['previewText'], 'Preview context must never expose unpublished ACF values to unauthorized viewers' );

		remove_filter( 'use_block_editor_for_post', '__return_false' );
	}
}

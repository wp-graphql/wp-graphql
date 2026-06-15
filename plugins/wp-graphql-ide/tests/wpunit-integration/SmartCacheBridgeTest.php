<?php
/**
 * Contract-level coverage of `SmartCacheBridge`.
 *
 * The bridge is the integration glue between the IDE and Smart Cache:
 *
 * - Filters Smart Cache's `register_post_type_args` / `register_taxonomy_args`
 *   to add `show_in_rest` (Smart Cache only sets `show_in_graphql`).
 * - Adds page-attributes / custom-fields / excerpt support to
 *   `graphql_document` so the IDE can use `menu_order` for reorder and
 *   surface `meta` to REST.
 * - Registers `_graphql_ide_variables` / `_graphql_ide_headers` post meta
 *   on `graphql_document` with JSON sanitization and the IDE auth gate.
 * - Adds `variables` / `headers` to the `GraphqlDocument` GraphQL type
 *   (read field + Create/Update input fields).
 * - Persists the mutation inputs back to post meta after
 *   `createGraphqlDocument` / `updateGraphqlDocument`.
 *
 * Each of these is a silent failure mode: a Smart Cache major bump that
 * changes the filter args shape, a typo in a meta key, a missing
 * register_graphql_field call — none of it errors loudly, but every one
 * of them breaks the IDE's saved-document flow. This file pins each
 * surface so the breakage shows up in CI instead of in the wild.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE\Integration;

class SmartCacheBridgeTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// REST exposure filters
	// ---------------------------------------------------------------

	public function test_graphql_document_post_type_is_exposed_to_rest() {
		// Smart Cache registers it with `show_in_graphql: true` but
		// without `show_in_rest`. The bridge's filter must flip that
		// on, otherwise the IDE's REST client can't fetch / mutate
		// saved docs and the Saved Queries panel is silently empty.
		$post_type = get_post_type_object( 'graphql_document' );

		$this->assertNotNull( $post_type, '`graphql_document` post type must be registered.' );
		$this->assertTrue(
			(bool) $post_type->show_in_rest,
			'`graphql_document` must be REST-exposed by the bridge.'
		);
	}

	public function test_graphql_document_supports_the_features_the_ide_needs() {
		// `page-attributes` is how the IDE reorders saved docs (menu_order).
		// `custom-fields` surfaces post_meta to REST as a writable `meta`
		// field. `excerpt` is the legacy description surface. All three
		// are additive on top of whatever supports Smart Cache shipped.
		$this->assertTrue( post_type_supports( 'graphql_document', 'page-attributes' ) );
		$this->assertTrue( post_type_supports( 'graphql_document', 'custom-fields' ) );
		$this->assertTrue( post_type_supports( 'graphql_document', 'excerpt' ) );
	}

	/**
	 * @dataProvider provide_ide_consumed_taxonomies
	 */
	public function test_each_ide_consumed_taxonomy_is_exposed_to_rest( string $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		$this->assertNotFalse( $tax, sprintf( '`%s` taxonomy must be registered.', $taxonomy ) );
		$this->assertTrue(
			(bool) $tax->show_in_rest,
			sprintf( '`%s` must be REST-exposed by the bridge.', $taxonomy )
		);
	}

	public function provide_ide_consumed_taxonomies(): array {
		return [
			'alias / queryId names'         => [ 'graphql_query_alias' ],
			'document grant (allow/deny)'   => [ 'graphql_document_grant' ],
			'document HTTP max-age'         => [ 'graphql_document_http_maxage' ],
			'document collections / groups' => [ 'graphql_document_group' ],
		];
	}

	public function test_unrelated_taxonomies_are_not_touched_by_the_bridge_filter() {
		// Smoke-check: the filter is taxonomy-scoped, so registering a
		// fresh taxonomy without `show_in_rest` should NOT be flipped on.
		register_taxonomy( 'bridge_test_unrelated', 'post', [
			'public'       => false,
			'hierarchical' => false,
		] );
		$tax = get_taxonomy( 'bridge_test_unrelated' );
		$this->assertFalse( (bool) $tax->show_in_rest );
		unregister_taxonomy( 'bridge_test_unrelated' );
	}

	// ---------------------------------------------------------------
	// Meta registration on `graphql_document`
	//
	// Registration is proven by the sanitizer behavior below: an
	// unregistered key would round-trip arbitrary strings unchanged,
	// so the "invalid JSON drops to empty string" test only passes
	// when the bridge's `register_post_meta` calls fired with the IDE's
	// `$sanitize_json` callback attached.
	// ---------------------------------------------------------------

	public function test_ide_meta_round_trips_valid_json_through_the_sanitizer() {
		$admin   = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$post_id = $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $admin,
			'post_content' => 'query Qmeta { posts { nodes { id } } }',
		] );

		$valid = '{"first":5}';
		update_post_meta( $post_id, '_graphql_ide_variables', $valid );
		$this->assertSame( $valid, get_post_meta( $post_id, '_graphql_ide_variables', true ) );
	}

	public function test_ide_meta_sanitizer_drops_invalid_json_to_empty_string() {
		// The bridge's sanitize_callback runs JSON parse + empties on
		// failure. Without this guard a malformed payload would land
		// in storage and break the IDE's parse on the next read.
		$admin   = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$post_id = $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $admin,
			'post_content' => 'query Qmeta2 { posts { nodes { id } } }',
		] );

		update_post_meta( $post_id, '_graphql_ide_variables', '{not json' );
		$this->assertSame( '', get_post_meta( $post_id, '_graphql_ide_variables', true ) );
	}

	// ---------------------------------------------------------------
	// GraphQL schema fields on `GraphqlDocument`
	// ---------------------------------------------------------------

	public function test_graphql_document_type_exposes_variables_and_headers_read_fields() {
		// The schema is built lazily; force it via `get_schema()` so we
		// can introspect the registered fields.
		$schema = \WPGraphQL::get_schema();
		$type   = $schema->getType( 'GraphqlDocument' );
		$this->assertNotNull( $type, '`GraphqlDocument` type must exist in the schema.' );

		$fields = $type->getFields();
		$this->assertArrayHasKey( 'variables', $fields, '`variables` read field must be registered.' );
		$this->assertArrayHasKey( 'headers', $fields, '`headers` read field must be registered.' );
	}

	/**
	 * @dataProvider provide_input_type_field_combinations
	 */
	public function test_create_and_update_inputs_accept_variables_and_headers( string $input_type, string $field ) {
		// The schema is built lazily and cached the first time anything
		// (other tests, plugins) calls `graphql()`. `graphql_register_types`
		// fires once per schema build — re-firing it here without flushing
		// the cache would no-op. Flush + rebuild so the bridge's field
		// registrations are guaranteed to be in the schema we introspect.
		\WPGraphQL::clear_schema();

		$result = graphql( [
			'query' => sprintf(
				'{ __type(name: "%s") { inputFields { name } } }',
				$input_type
			),
		] );

		$names = array_column(
			$result['data']['__type']['inputFields'] ?? [],
			'name'
		);
		$this->assertContains(
			$field,
			$names,
			sprintf( '`%s` must accept `%s` on the bridge.', $input_type, $field )
		);
	}

	public function provide_input_type_field_combinations(): array {
		// WPGraphQL's mutation input types are named `Create{Type}Input`
		// and `Update{Type}Input`; the bridge wires `register_graphql_field`
		// against both names. Update inputs require the global `id` field
		// to address an existing record.
		return [
			'Create accepts variables' => [ 'CreateGraphqlDocumentInput', 'variables' ],
			'Create accepts headers'   => [ 'CreateGraphqlDocumentInput', 'headers' ],
			'Update accepts variables' => [ 'UpdateGraphqlDocumentInput', 'variables' ],
			'Update accepts headers'   => [ 'UpdateGraphqlDocumentInput', 'headers' ],
		];
	}

	public function test_input_type_names_match_what_the_bridge_registers_against() {
		// Sanity check: if WPGraphQL ever renames the auto-generated
		// input types (e.g. inserts `WithId`), the bridge's
		// `register_graphql_field( 'UpdateGraphqlDocumentInput', ... )`
		// calls silently no-op. This proves the *type* exists; the
		// dataProvider above proves the *fields* are wired.
		foreach ( [ 'CreateGraphqlDocumentInput', 'UpdateGraphqlDocumentInput' ] as $name ) {
			$result = graphql( [
				'query' => sprintf( '{ __type(name: "%s") { name kind } }', $name ),
			] );
			$this->assertSame(
				$name,
				$result['data']['__type']['name'] ?? null,
				sprintf(
					'`%s` input type must exist — if WPGraphQL renamed the auto-generated mutation input, the bridge needs updating to match.',
					$name
				)
			);
		}
	}

	// ---------------------------------------------------------------
	// save_ide_inputs_after_mutation
	// ---------------------------------------------------------------

	public function test_save_ide_inputs_after_mutation_persists_variables_and_headers() {
		// Mirrors what `graphql_mutation_response` would feed in after
		// `createGraphqlDocument` resolves. Asserts the bridge writes
		// both meta keys to the freshly-created post.
		$admin   = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$post_id = $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $admin,
			'post_content' => 'query Qbridge { posts { nodes { id } } }',
		] );

		\WPGraphQLIDE\SmartCacheBridge::save_ide_inputs_after_mutation(
			[ 'postObjectId' => $post_id ],
			[
				'variables' => '{"first":3}',
				'headers'   => '{"X-Test":"yes"}',
			],
			[],
			null,
			null,
			'createGraphqlDocument'
		);

		$this->assertSame(
			'{"first":3}',
			get_post_meta( $post_id, '_graphql_ide_variables', true )
		);
		$this->assertSame(
			'{"X-Test":"yes"}',
			get_post_meta( $post_id, '_graphql_ide_headers', true )
		);
	}

	public function test_save_ide_inputs_after_mutation_skips_unrelated_mutations() {
		// Bridge should only act on the two named mutations — a future
		// Smart Cache update or third-party mutation must not get
		// silent IDE meta writes on top of its post.
		$admin   = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$post_id = $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $admin,
			'post_content' => 'query Qskip { posts { nodes { id } } }',
		] );

		\WPGraphQLIDE\SmartCacheBridge::save_ide_inputs_after_mutation(
			[ 'postObjectId' => $post_id ],
			[ 'variables' => '{"first":99}' ],
			[],
			null,
			null,
			'updateSomeOtherThing'
		);

		$this->assertSame( '', get_post_meta( $post_id, '_graphql_ide_variables', true ) );
	}

	public function test_save_ide_inputs_after_mutation_no_ops_without_a_post_id() {
		// `postObjectId` can be missing on error paths. The bridge must
		// short-circuit cleanly — no fatal, no orphaned write to
		// post id 0. Reaching the assertion at all means we didn't
		// fatal; the return type is `void` so there's no value to
		// compare beyond "we got here".
		\WPGraphQLIDE\SmartCacheBridge::save_ide_inputs_after_mutation(
			[],
			[ 'variables' => '{"first":1}' ],
			[],
			null,
			null,
			'createGraphqlDocument'
		);

		$this->assertTrue( true, 'Bridge must early-return without fatal on missing postObjectId.' );
	}
}

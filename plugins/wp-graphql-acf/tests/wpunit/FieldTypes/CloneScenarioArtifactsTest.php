<?php
/**
 * Smoke test that every JSON artifact under tests/_data/clone-scenarios/
 * imports as a valid set of ACF Field Groups and produces a valid schema.
 *
 * This test exists so that adding or modifying a scenario artifact triggers
 * an obvious failure if the JSON breaks ACF import or schema build, rather
 * than waiting until a downstream test happens to import it.
 *
 * Each artifact also gets a per-scenario sanity assertion (a few key type
 * names and field presence checks) so the smoke test catches regressions
 * specific to the behavior the artifact is supposed to isolate.
 */

class CloneScenarioArtifactsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		\WPGraphQL\Acf\Utils::clear_field_type_registry();
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	private function skip_if_not_acf_pro(): void {
		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active; clone scenarios all require Pro.' );
		}
	}

	private function load_artifact( string $filename ): void {
		$path = dirname( __DIR__, 2 ) . '/_data/clone-scenarios/' . $filename;
		$this->assertFileExists( $path, "Artifact not found: {$filename}" );

		$json = json_decode( (string) file_get_contents( $path ), true );
		$this->assertIsArray( $json, "Artifact is not valid JSON: {$filename}" );

		foreach ( $json as $field_group ) {
			$this->assertIsArray( $field_group, "Field group entry is not an array in {$filename}" );
			$this->assertArrayHasKey( 'key', $field_group, "Field group missing 'key' in {$filename}" );
			acf_add_local_field_group( $field_group );
		}
	}

	private function assertSchemaIsValid(): void {
		$schema = \WPGraphQL::get_schema();
		try {
			$schema->assertValid();
		} catch ( \Throwable $e ) {
			$this->fail( 'GraphQL schema is invalid: ' . $e->getMessage() );
		}
		$this->addToAssertionCount( 1 );
	}

	private function introspect_type( string $type_name ): ?array {
		$query  = 'query GetType($name: String!) {
			__type(name: $name) {
				name
				kind
				interfaces { name }
				fields { name type { kind name ofType { kind name } } }
			}
		}';
		$result = $this->graphql([
			'query'     => $query,
			'variables' => [ 'name' => $type_name ],
		]);
		return $result['data']['__type'] ?? null;
	}

	public function testCherryPickedVsWholeGroupMatrix(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'cherry-picked-vs-whole-group-matrix.json' );
		$this->assertSchemaIsValid();

		$parent = $this->introspect_type( 'CloneMatrixParent' );
		$this->assertNotNull( $parent );

		$fields     = array_map( static fn( $f ) => $f['name'], $parent['fields'] ?? [] );
		$interfaces = array_map( static fn( $i ) => $i['name'], $parent['interfaces'] ?? [] );

		// Whole-group clones apply the source's _Fields interface.
		$this->assertContains( 'CloneMatrixSource_Fields', $interfaces );

		// Cherry-picked seamless splices in the cherry-picked field as a direct field on the parent.
		$this->assertContains( 'sectionLinks', $fields, 'Cherry-picked seamless should expose sectionLinks directly.' );

		// Prefixed cherry-pick gets its own prefixed object type.
		$this->assertContains( 'cherryPickedGroup', $fields );
	}

	public function testPrefixedCloneInFlexLayout(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'prefixed-clone-in-flex-layout.json' );
		$this->assertSchemaIsValid();

		$layout = $this->introspect_type( 'PrefixedFlexParentFlexibleSomeLayoutLayout' );
		$this->assertNotNull( $layout, 'Flex layout type should exist' );

		$fields = array_map( static fn( $f ) => $f['name'], $layout['fields'] ?? [] );
		$this->assertContains( 'yo', $fields, 'Prefixed clone field "yo" should appear on the layout type.' );
		$this->assertNotContains( 'title', $fields, 'Cloned source field "title" should NOT leak onto the layout type.' );
	}

	public function testNestedCloneWithGroupSubfield(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'nested-clone-with-group-subfield.json' );
		$this->assertSchemaIsValid();
	}

	public function testCloneInGroupImagePermutations(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'clone-in-group-image-permutations.json' );
		$this->assertSchemaIsValid();

		$hero = $this->introspect_type( 'MediaHero' );
		$this->assertNotNull( $hero, 'MediaHero type should exist' );

		$fields = array_map( static fn( $f ) => $f['name'], $hero['fields'] ?? [] );
		foreach ( [ 'mediaAllFieldsSeamless', 'mediaIndividualFieldsSeamless', 'mediaAllFieldsGroup', 'mediaIndividualFieldsGroup' ] as $expected ) {
			$this->assertContains( $expected, $fields, "MediaHero should expose {$expected}" );
		}
	}

	public function testTopVsNestedDisplayMatrix(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'top-vs-nested-display-matrix.json' );
		$this->assertSchemaIsValid();
	}

	public function testCyclicalCloneAB(): void {
		$this->skip_if_not_acf_pro();
		// The cyclical-clone artifact is a known-broken reproduction for
		// https://github.com/wp-graphql/wpgraphql-acf/issues/140 — building
		// the schema for it OOMs the process (SIGKILL/exit 137). Running it
		// inside the suite nukes everything that follows. Keep the artifact
		// available for manual reproduction in admin / wp-cli, but skip the
		// automated run until #140 has an actual fix.
		$this->markTestSkipped( 'Reproduces wp-graphql/wpgraphql-acf#140 (OOM on cyclical clone). Un-skip once #140 is fixed; artifact is still useful for manual repro.' );
	}

	public function testListAndNonNullWrappedClone(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'list-and-nonnull-wrapped-clone.json' );
		$this->assertSchemaIsValid();

		$parent = $this->introspect_type( 'WrappedParent' );
		$this->assertNotNull( $parent );

		$interfaces = array_map( static fn( $i ) => $i['name'], $parent['interfaces'] ?? [] );
		$this->assertContains( 'WrappedSource_Fields', $interfaces, 'Whole-group seamless clone should apply source _Fields interface.' );
	}

	/**
	 * Mirrors the exact field-group shape described in
	 * https://github.com/wp-graphql/wpgraphql-acf/issues/258:
	 *
	 *   topLevelGroup (active, on post)
	 *     ├─ textField (plain text)
	 *     ├─ wholeMiddleClone — whole-group seamless clone of middleLevelGroup
	 *     ├─ cherryBottomClone — cherry-picked seamless clone of bottomLevelGroup.sectionLinks (THE bug)
	 *     └─ groupWorkaround (group)
	 *           └─ nestedCherryClone — same cherry-pick, wrapped in a group (the reporter's workaround)
	 *
	 * Post-fix expectations on TopLevelGroup:
	 *  - textField, sectionTitle, sectionLinks all present as flat fields
	 *  - MiddleLevelGroup_Fields interface implemented (whole-group seamless)
	 *  - BottomLevelGroup_Fields interface NOT implemented (cherry-pick has no source-group interface)
	 *  - sectionSummary (a sibling field of sectionLinks in BottomLevelGroup that was NOT cherry-picked) must NOT leak
	 *  - groupWorkaround.sectionLinks resolves (workaround still works)
	 */
	public function testIssue258ExactRepro(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'issue-258-exact-repro.json' );
		$this->assertSchemaIsValid();

		$top = $this->introspect_type( 'TopLevelGroup' );
		$this->assertNotNull( $top, 'TopLevelGroup should exist' );

		$fields     = array_map( static fn( $f ) => $f['name'], $top['fields'] ?? [] );
		$interfaces = array_map( static fn( $i ) => $i['name'], $top['interfaces'] ?? [] );

		// Plain field, untouched by clone behavior.
		$this->assertContains( 'textField', $fields, 'Plain text field should remain on TopLevelGroup.' );

		// Whole-group seamless clone exposes its source's fields via interface inheritance.
		$this->assertContains( 'MiddleLevelGroup_Fields', $interfaces, 'TopLevelGroup should implement MiddleLevelGroup_Fields from the whole-group seamless clone.' );
		$this->assertContains( 'sectionTitle', $fields, 'sectionTitle should appear on TopLevelGroup via the MiddleLevelGroup_Fields interface.' );

		// THE #258 bug case: cherry-picked seamless splice should expose sectionLinks directly.
		$this->assertContains( 'sectionLinks', $fields, '#258: cherry-picked sectionLinks must appear as a flat field on TopLevelGroup.' );

		// Negative assertions — confirms the cherry-pick is precisely scoped:
		// - the source group's _Fields interface is NOT applied (no whole-group cloning of bottomLevelGroup)
		// - sectionSummary (a sibling of sectionLinks in bottomLevelGroup that we did NOT cherry-pick) must NOT leak
		$this->assertNotContains( 'BottomLevelGroup_Fields', $interfaces, 'Cherry-pick must not pull in the source group\'s _Fields interface.' );
		$this->assertNotContains( 'sectionSummary', $fields, 'Only the cherry-picked field (sectionLinks) should appear — sectionSummary must not leak.' );

		// The reporter's workaround (wrap the broken clone in a group field) should also still work.
		$workaround = $this->introspect_type( 'TopLevelGroupGroupWorkaround' );
		$this->assertNotNull( $workaround, 'TopLevelGroupGroupWorkaround (the group sub-field) should exist.' );
		$workaround_fields = array_map( static fn( $f ) => $f['name'], $workaround['fields'] ?? [] );
		$this->assertContains( 'sectionLinks', $workaround_fields, 'Cherry-picked sectionLinks inside the group sub-field should also be exposed (workaround path).' );
	}

	public function testSameSourceClonedMultipleDepths(): void {
		$this->skip_if_not_acf_pro();
		$this->load_artifact( 'same-source-cloned-multiple-depths.json' );
		$this->assertSchemaIsValid();

		$parent = $this->introspect_type( 'MultiDepthParent' );
		$this->assertNotNull( $parent );

		$interfaces = array_map( static fn( $i ) => $i['name'], $parent['interfaces'] ?? [] );
		// MultiDepthSource_Fields should appear from the top-level seamless clone.
		$this->assertContains( 'MultiDepthSource_Fields', $interfaces );

		// Interface should appear exactly once even though the source is cloned in three positions
		// (interface deduplication on the parent type itself).
		$source_iface_count = count( array_filter( $interfaces, static fn( $i ) => 'MultiDepthSource_Fields' === $i ) );
		$this->assertSame( 1, $source_iface_count, 'MultiDepthSource_Fields interface should be deduped on MultiDepthParent' );
	}
}

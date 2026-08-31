<?php

/**
 * Tests that setting groups are Nodes: they expose a globally unique `id`,
 * implement the `Node` interface, and resolve through the `node(id:)` field
 * via the `setting_group` loader.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/2459
 */
class SettingGroupNodeQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		parent::tearDown();
		WPGraphQL::clear_schema();
	}

	/**
	 * The generalSettings group should expose a globally unique id derived
	 * from the `setting_group` type and the group key.
	 *
	 * @throws \Exception
	 */
	public function testGeneralSettingsExposesGlobalId() {
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'general' );

		$query = '
			query {
				generalSettings {
					id
				}
			}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $global_id, $actual['data']['generalSettings']['id'] );
	}

	/**
	 * A group seeded purely from in-memory shims (permalink) should expose an
	 * id the same way as a group backed by registered settings.
	 *
	 * @throws \Exception
	 */
	public function testPermalinkSettingsExposesGlobalId() {
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'permalink' );

		$query = '
			query {
				permalinkSettings {
					id
				}
			}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $global_id, $actual['data']['permalinkSettings']['id'] );
	}

	/**
	 * A setting group's global id should round-trip through the node(id:)
	 * field, resolving to the group's type with its fields intact.
	 *
	 * @throws \Exception
	 */
	public function testSettingGroupResolvesThroughNodeField() {
		update_option( 'blogname', 'Setting Group Node Test' );

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'general' );

		$query = '
			query GetSettingGroupNode( $id: ID! ) {
				node( id: $id ) {
					__typename
					id
					... on GeneralSettings {
						title
					}
				}
			}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $global_id,
				],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'GeneralSettings', $actual['data']['node']['__typename'] );
		$this->assertSame( $global_id, $actual['data']['node']['id'] );
		$this->assertSame( 'Setting Group Node Test', $actual['data']['node']['title'] );
	}

	/**
	 * The node(id:) field with an unknown group key should resolve to null, not error.
	 *
	 * @throws \Exception
	 */
	public function testNodeQueryForUnknownSettingGroupReturnsNull() {
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'setting_group', 'notARealGroup' );

		$query = '
			query GetSettingGroupNode( $id: ID! ) {
				node( id: $id ) {
					__typename
					id
				}
			}
		';

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => $global_id,
				],
			]
		);

		$this->assertNull( $actual['data']['node'] );
	}

	/**
	 * Setting group object types should implement the Node interface.
	 *
	 * @throws \Exception
	 */
	public function testSettingGroupTypesImplementNodeInterface() {
		$query = '
			query {
				general: __type( name: "GeneralSettings" ) {
					interfaces {
						name
					}
				}
				permalink: __type( name: "PermalinkSettings" ) {
					interfaces {
						name
					}
				}
			}
		';

		$actual = graphql( compact( 'query' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$general_interfaces   = array_column( $actual['data']['general']['interfaces'], 'name' );
		$permalink_interfaces = array_column( $actual['data']['permalink']['interfaces'], 'name' );

		$this->assertContains( 'Node', $general_interfaces );
		$this->assertContains( 'Node', $permalink_interfaces );
	}
}

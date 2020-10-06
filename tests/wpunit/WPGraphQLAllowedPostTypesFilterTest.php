<?php

class WPGraphQLAllowedPostTypesFilterTest extends Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	/**
	 * @var \WpunitTester
	 */
	protected $tester;

	public function setUp(): void {
		// Before...
		parent::setUp();

		// Your set up methods here.
	}

	public function tearDown(): void {
		// Your tear down methods here.

		// Then...
		parent::tearDown();
	}

	// Tests
	public function testByPassingPostObjectSchemaTypeDefinition() {
		$post_id  = $this->factory()->post->create( [
			'post_title'   => 'test post',
			'post_content' => 'test content',
			'post_type'    => 'bootstrap_ctm_type',
		] );
		$relay_id = $this->toRelayId( 'post', $post_id );
		$post     = get_post( $post_id );

		\codecept_debug( $post_id );
		$query = '
			query ($id: ID!, $idType: CustomSchemaTypeIdType) {
				customSchemaType(id: $id, idType: $idType) {
					id
					databaseId
					title
					content
				}
			}
		';

		/**
		 * Assertion One
		 *
		 * Query Custom Post-type with custom type definition.
		 */
		$variables = [
			'id'     => $post_id,
			'idType' => 'DATABASE_ID',
		];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedObject( 'customSchemaType.id', $relay_id ),
			$this->expectedObject( 'customSchemaType.databaseId', "$post_id" ),
			$this->expectedObject( 'customSchemaType.title', '¯\_(ツ)_/¯test post' ),
			$this->expectedObject( 'customSchemaType.content', '¯\_(ツ)_/¯test content' ),
		];

		$this->assertQuerySuccessful( $response, $expected );
	}

	public function testByPassingPostObjectRootQueryDefinition() {
		$query = '
			query ($id: ID!) {
				customRootQuery(id: $id) {
					title
					content
				}
			}
		';

		/**
		 * Assertion One
		 *
		 * Query Custom Post-type using the custom query and secret password.
		 */
		$variables = [ 'id' => 'thesecretpassword' ];
		$response  = $this->graphql( compact( 'query', 'variables' ) );
		$expected  = [
			$this->expectedObject( 'customRootQuery.title', 'test master post' ),
			$this->expectedObject(
				'customRootQuery.content',
				html_entity_decode( apply_filters( 'the_content', 'Master of All' ) )
			),
		];

		$this->assertQuerySuccessful( $response, $expected );
	}

	public function testByPassingPostObjectRootConnectionDefinition() {

	}
}

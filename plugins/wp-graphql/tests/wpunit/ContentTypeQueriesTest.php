<?php

class ContentTypeQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
	}

	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function get_query() {
		return '
			query contentType( $id: ID!, $idType: ContentTypeIdTypeEnum ) {
				contentType( id: $id, idType: $idType ) {
					name
					graphqlSingleName
				}
			}
		';
	}

	public function testContentTypeQueryByName() {
		wp_set_current_user( $this->admin );

		$variables = [
			'id'     => 'post',
			'idType' => 'NAME',
		];

		$actual = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => $variables,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'post', $actual['data']['contentType']['name'] );
	}

	public function testContentTypeQueryByEnumName() {
		wp_set_current_user( $this->admin );

		// The schema exposes content types as ContentTypeEnum names (POST for
		// the type registered as post), so the enum-style name must resolve
		// the same content type as the raw registered name.
		$variables = [
			'id'     => 'POST',
			'idType' => 'NAME',
		];

		$actual = $this->graphql(
			[
				'query'     => $this->get_query(),
				'variables' => $variables,
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'post', $actual['data']['contentType']['name'] );
	}
}

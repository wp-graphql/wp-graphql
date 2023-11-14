<?php

class MenuItemConnectionResolverTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// before
		parent::setUp();

		// your set up methods here
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		// your tear down methods here
		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}

	public function testMenuItemConnectionResolverWithNoMenuItem() {

		$query = '
		{
			menuItems {
				nodes {
				id
				}
			}
			}
		';

		$actual = do_graphql_request( $query );
		$this->assertEmpty( $actual['data']['menuItems']['nodes'] );
		$this->assertArrayNotHasKey( 'errors', $actual );
	}
}

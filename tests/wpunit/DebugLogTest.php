<?php
class DebugLogTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create([
			'role' => 'administrator',
		]);
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testDebugLogShowsLogs() {

		wp_set_current_user( $this->admin );
		graphql_debug( 'Test message', [ 'type' => 'TEST' ]  );
		$query = '{posts{nodes{id}}}';
		$actual = graphql([ 'query' => $query ]);
		codecept_debug( $actual );
		$messages = isset( $actual['extensions']['debug'] ) ? $actual['extensions']['debug'] : null;
		$this->assertNotEmpty( $messages );
		$key = array_search('TEST', array_column( $messages, 'type'));
		$this->assertEquals( 'TEST', $messages[ $key ]['type'] );

	}

}

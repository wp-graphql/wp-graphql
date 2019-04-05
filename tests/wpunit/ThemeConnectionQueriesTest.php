<?php

class ThemeConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;

	public function setUp() {
		// before
		parent::setUp();
		$this->current_time     = strtotime( 'now' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown() {
		// your tear down methods here

		// then
		parent::tearDown();
	}

	/**
	 * testThemesQuery
	 * @dataProvider dataProviderUser
	 * This tests querying for themes to ensure that we're getting back a proper connection
	 */
	public function testThemesQuery( $user ) {

		$query = '
		{
		  themes{
		    edges{
		      node{
		        id
		        name
		      }
		    }
		    nodes {
		      id
		    }
		  }
		}
		';

		if ( ! empty( $user ) ) {
			$current_user = $this->admin;
			$return_count = 3;
		} else {
			$current_user = 0;
			$return_count = 1;
		}

		wp_set_current_user( $current_user );
		$actual = do_graphql_request( $query );

		/**
		 * We don't really care what the specifics are because the default theme could change at any time
		 * and we don't care to maintain the exact match, we just want to make sure we are
		 * properly getting a theme back in the query
		 */
		$this->assertNotEmpty( $actual['data']['themes']['edges'] );
		$this->assertNotEmpty( $actual['data']['themes']['edges'][0]['node']['id'] );
		$this->assertNotEmpty( $actual['data']['themes']['edges'][0]['node']['name'] );
		$this->assertNotEmpty( $actual['data']['themes']['nodes'][0]['id'] );
		$this->assertEquals( $actual['data']['themes']['nodes'][0]['id'], $actual['data']['themes']['edges'][0]['node']['id'] );
		$this->assertCount( $return_count, $actual['data']['themes']['edges'] );

		foreach ( $actual['data']['themes']['edges'] as $key => $edge ) {
			$this->assertEquals( $actual['data']['themes']['nodes'][ $key ]['id'], $edge['node']['id'] );
		}

	}

	public function dataProviderUser() {
		return [
			[
				'user' => 'admin'
			],
			[
				'user' => '',
			]
		];
	}
}
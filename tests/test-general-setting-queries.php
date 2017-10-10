<?php

/**
 * WPGraphQL Test generalSetting Queries
 *
 * Test the WPGraphQL generalSetting Queries for the proper user permissions and fields
 *
 * @package WPGraphQL
 *
 */
class WP_GraphQL_Test_General_Setting_Queries extends WP_UnitTestCase {

	/**
	 * This function is run before each method
	 */
	public function setUp() {
		parent::setUp();

		$this->admin      = $this->factory->user->create( [
			'role' => 'administrator',
		] );
		$this->admin_name = 'User ' . $this->admin;

	}

	/**
	 * This function is run after each method
	 *
	 * @access public
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Method for testing queries provided a $general_setting
	 * parameter is passed
	 *
	 * @param $general_setting
	 * @access public
	 */
	public function generalSettingQuery( $general_setting ) {
		/**
		 * Set the admin user
		 * Set the query and input to the global id for the $general_setting
		 * Make the request
		 * Then return the request
		 */
		wp_set_current_user( $this->admin );
		$setting_global_id =\GraphQLRelay\Relay::toGlobalId('generalSetting', $general_setting );
		$query = "
			query {
				generalSetting(id: \"{$setting_global_id}\") {
			        id
			        name
			        value
			  }
			}
		";
		$actual = do_graphql_request( $query );
		return $actual;

	}

	/**
	 * Test the adminEmail general setting query
	 *
	 * @access public
	 * @return void
	 */
	public function testAdminEmailQuery() {
		$actual = $this->generalSettingQuery( 'adminEmail' );
		$general_setting = $actual['data']['generalSetting'];
		$this->assertNotEmpty( $general_setting );
		$this->assertTrue( is_string( $general_setting['value'] ) );
	}

	/**
	 * Test the generalSettings query, this should return all of the site's
	 * general settings
	 *
	 * @access public
	 * @return void
	 */
	public function testGeneralSettingsQuery() {

		/**
		 * Set the admin user
		 * Set the query and input to the global id for the $general_setting
		 * Make the request
		 * Then return the request
		 */
		wp_set_current_user( $this->admin );
		$query = "
			query {
				generalSettings {
				    edges {
				        node {
				            id
				            name
				            value
				        }
				    }
				}
			}
		";
		$actual = do_graphql_request( $query );

		$actual_general_settings = $actual['data']['generalSettings']['edges'];

		$expected = [
			0 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'adminEmail' ),
					'name' => 'adminEmail',
					'value' => 'admin@example.org',
				],
			],
			1 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'siteDescription' ),
					'name' => 'siteDescription',
				    'value' => 'Just another WordPress site',
				],
			],
			2 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'siteName' ),
					'name' => 'siteName',
					'value' => 'Test Blog',
				],
			],
			3 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'commentRegistration' ),
					'name' => 'commentRegistration',
					'value' => '0',
				],
			],
			4 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'dateFormat' ),
					'name' => 'dateFormat',
					'value' => 'F j, Y',
				],
			],
			5 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'defaultRole' ),
					'name' => 'defaultRole',
					'value' => 'subscriber',
				],
			],
			6 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'gmtOffset' ),
					'name' => 'gmtOffset',
					'value' => '0',
				],
			],
			7 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'home' ),
					'name' => 'home',
					'value' => 'http://example.org',
				],
			],
			8 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'siteUrl' ),
					'name' => 'siteUrl',
					'value' => 'http://example.org',
				],
			],
			9 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'startOfWeek' ),
					'name' => 'startOfWeek',
					'value' => '1',
				],
			],
			10 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'timeFormat' ),
					'name' => 'timeFormat',
					'value' => 'g:i a',
				],
			],
			11 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'timezoneString' ),
					'name' => 'timezoneString',
					'value' => null,
				],
			],
			12 => [
				'node' => [
					'id' => \GraphQLRelay\Relay::toGlobalId( 'generalSetting', 'usersCanRegister' ),
					'name' => 'usersCanRegister',
					'value' => '0',
				],
			],
		];

		$this->assertEquals( $actual_general_settings, $expected );
	}

}

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
	 * Test the
	 *
	 * @param $general_setting
	 * @access public
	 */
	public function generalSettingQuery( $general_setting ) {

		$setting_global_id =\GraphQLRelay\Relay::toGlobalId('generalSetting', $general_setting );

		$query = "
		query {
			generalSetting(id: \"{$setting_global_id}\") {
		        id
		        name
		        stringValue
		  }
		}
		";

		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $query );

		return $actual;

	}

	public function testAdminEmailQuery() {
		$actual = $this->generalSettingQuery( 'adminEmail' );
		$general_setting = $actual['data']['generalSetting'];

		$this->assertNotEmpty( $general_setting );
		var_dump( $general_setting );
		$this->assertTrue( is_string( $general_setting['stringValue'] ) );
	}

	public function testSiteDescriptionQuery() {
		$actual = $this->generalSettingQuery( 'siteDescription' );
		$general_setting = $actual['data']['generalSetting'];

		$this->assertNotEmpty( $general_setting );
		var_dump( $general_setting );
		$this->assertTrue( is_string( $general_setting['stringValue'] ) );

	}

}

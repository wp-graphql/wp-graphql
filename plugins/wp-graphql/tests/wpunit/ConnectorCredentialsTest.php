<?php

use WPGraphQL\Data\ConnectorCredentials;

/**
 * The masking and write-back rules for connector credential settings.
 *
 * The read and write behavior is covered end to end in SettingsVisibilityTest.
 * These cover the shapes that test can't reach through the Schema, such as the
 * application password credentials WordPress 7.1 stores as an array.
 */
class ConnectorCredentialsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private const OPTION = 'wpgraphql_test_connector_credentials';

	public function tearDown(): void {
		delete_option( self::OPTION );
		parent::tearDown();
	}

	public function testMaskApiKeyMatchesCoreFormat(): void {
		$bullet = "\u{2022}";

		$this->assertSame( '', ConnectorCredentials::mask_api_key( '' ) );
		$this->assertSame( $bullet, ConnectorCredentials::mask_api_key( 'a' ) );
		$this->assertSame( str_repeat( $bullet, 4 ), ConnectorCredentials::mask_api_key( 'abcd' ) );
		$this->assertSame( $bullet . 'bcde', ConnectorCredentials::mask_api_key( 'abcde' ) );
		$this->assertSame( str_repeat( $bullet, 16 ) . 'wxyz', ConnectorCredentials::mask_api_key( str_repeat( 'k', 16 ) . 'wxyz' ) );
		$this->assertSame( str_repeat( $bullet, 16 ) . 'wxyz', ConnectorCredentials::mask_api_key( str_repeat( 'k', 40 ) . 'wxyz' ) );
	}

	public function testMaskValueNullsApiKeyValuesItCannotMask(): void {
		// No credential stored.
		$this->assertNull( ConnectorCredentials::mask_value( null, 'api_key' ) );
		$this->assertFalse( ConnectorCredentials::mask_value( false, 'api_key' ) );

		// Values that carry a key but aren't a string.
		$this->assertNull( ConnectorCredentials::mask_value( 1234567890, 'api_key' ) );
		$this->assertNull( ConnectorCredentials::mask_value( 12.5, 'api_key' ) );
		$this->assertNull( ConnectorCredentials::mask_value( true, 'api_key' ) );
		$this->assertNull( ConnectorCredentials::mask_value( [ 'key' => 'sk-live-1234' ], 'api_key' ) );
		$this->assertNull( ConnectorCredentials::mask_value( (object) [ 'key' => 'sk-live-1234' ], 'api_key' ) );
	}

	public function testMaskValueNullsApplicationPasswordValuesItCannotMask(): void {
		$this->assertNull( ConnectorCredentials::mask_value( null, 'application_password' ) );
		$this->assertFalse( ConnectorCredentials::mask_value( false, 'application_password' ) );
		$this->assertNull( ConnectorCredentials::mask_value( 'abcd efgh ijkl mnop', 'application_password' ) );
		$this->assertNull( ConnectorCredentials::mask_value( (object) [ 'password' => 'abcd efgh ijkl mnop' ], 'application_password' ) );

		// A non-string password is still masked.
		$this->assertSame(
			[
				'username' => 'bot',
				'password' => str_repeat( "\u{2022}", 16 ),
			],
			ConnectorCredentials::mask_value(
				[
					'username' => 'bot',
					'password' => 12345678,
				],
				'application_password'
			)
		);
	}

	public function testMaskValueMasksApplicationPasswordOnly(): void {
		$this->assertSame(
			[
				'username' => 'bot',
				'password' => str_repeat( "\u{2022}", 16 ),
			],
			ConnectorCredentials::mask_value(
				[
					'username' => 'bot',
					'password' => 'abcd efgh ijkl mnop',
				],
				'application_password'
			)
		);

		$empty = [
			'username' => '',
			'password' => '',
		];
		$this->assertSame( $empty, ConnectorCredentials::mask_value( $empty, 'application_password' ) );
	}

	public function testPrepareIncomingApiKey(): void {
		update_option( self::OPTION, 'sk-stored-key-1234' );

		$masked = ConnectorCredentials::mask_api_key( 'sk-stored-key-1234' );

		$this->assertFalse( ConnectorCredentials::prepare_incoming_value( self::OPTION, 'api_key', $masked )['write'] );

		// Any masked value is treated as unchanged, not only the mask of the
		// stored key, since what a client read may have come from a filter.
		$this->assertFalse( ConnectorCredentials::prepare_incoming_value( self::OPTION, 'api_key', ConnectorCredentials::mask_api_key( 'sk-other-key-9999' ) )['write'] );
		$this->assertFalse( ConnectorCredentials::prepare_incoming_value( self::OPTION, 'api_key', ConnectorCredentials::mask_api_key( 'abcd' ) )['write'] );
		$this->assertSame( 'sk-stored-key-1234', get_option( self::OPTION ) );

		$new = ConnectorCredentials::prepare_incoming_value( self::OPTION, 'api_key', 'sk-new-key-5678' );
		$this->assertTrue( $new['write'] );
		$this->assertSame( 'sk-new-key-5678', $new['value'] );

		// Clearing the key is still allowed.
		$cleared = ConnectorCredentials::prepare_incoming_value( self::OPTION, 'api_key', '' );
		$this->assertTrue( $cleared['write'] );
		$this->assertSame( '', $cleared['value'] );
	}

	public function testPrepareIncomingApplicationPasswordKeepsStoredPassword(): void {
		update_option(
			self::OPTION,
			[
				'username' => 'bot',
				'password' => 'abcd efgh ijkl mnop',
			]
		);

		$prepared = ConnectorCredentials::prepare_incoming_value(
			self::OPTION,
			'application_password',
			[
				'username' => 'renamed-bot',
				'password' => str_repeat( "\u{2022}", 16 ),
			]
		);

		$this->assertTrue( $prepared['write'] );
		$this->assertSame(
			[
				'username' => 'renamed-bot',
				'password' => 'abcd efgh ijkl mnop',
			],
			$prepared['value']
		);
	}

	public function testSettingNamesComeFromApiKeyConnectors(): void {
		if ( ! function_exists( 'wp_get_connectors' ) || ! class_exists( 'WP_Connector_Registry' ) || null === WP_Connector_Registry::get_instance() ) {
			$this->markTestSkipped( 'The connectors API requires WordPress 7.0 or later.' );
		}

		$registry = WP_Connector_Registry::get_instance();
		$registry->register(
			'wpgraphql_test_names',
			[
				'name'           => 'WPGraphQL Test Names',
				'type'           => 'spam_filtering',
				'authentication' => [
					'method'       => 'api_key',
					'setting_name' => self::OPTION,
				],
			]
		);
		$registry->register(
			'wpgraphql_test_no_auth',
			[
				'name'           => 'WPGraphQL Test No Auth',
				'type'           => 'spam_filtering',
				'authentication' => [
					'method' => 'none',
				],
			]
		);

		$names = ConnectorCredentials::get_setting_names();

		$registry->unregister( 'wpgraphql_test_names' );
		$registry->unregister( 'wpgraphql_test_no_auth' );

		$this->assertSame( 'api_key', $names[ self::OPTION ] ?? null );
		// The names are read from the registry each time, not cached.
		$this->assertArrayNotHasKey( self::OPTION, ConnectorCredentials::get_setting_names() );
		foreach ( $names as $method ) {
			$this->assertContains( $method, [ 'api_key', 'application_password' ] );
		}
	}
}

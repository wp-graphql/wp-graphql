<?php
/**
 * Tests for the copy of Smart Cache's usage data sent to telemetry.wpgraphql.com.
 */
class TelemetryMirrorTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The copy sent to telemetry.wpgraphql.com leaves out the site's admin email address and an
	 * administrator's name, and Appsero's own request is sent unchanged.
	 */
	public function testTheTelemetryCopyLeavesOutTheAdminEmailAndName(): void {
		$sent    = [];
		$capture = static function ( $preempt, $args, $url ) use ( &$sent ) {
			if ( false !== strpos( $url, 'api.appsero.com' ) || false !== strpos( $url, 'telemetry.wpgraphql.com' ) ) {
				$sent[ $url ] = $args['body'] ?? null;
			}

			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $capture, 0, 3 );

		// The plugin's own hash, so its mirror sends a copy of the check-in.
		$client = new \Appsero\Client( '66f03878-3df1-40d7-8be9-0069994480d4', 'WPGraphQL Smart Cache', WPGRAPHQL_SMART_CACHE_PLUGIN_DIR . 'wp-graphql-smart-cache.php' );
		$client->insights()->send_tracking_data( true );

		remove_filter( 'pre_http_request', $capture, 0 );

		$this->assertSame(
			[
				'https://api.appsero.com/track',
				'https://telemetry.wpgraphql.com/api/appsero/track',
			],
			array_keys( $sent )
		);

		$appsero = $sent['https://api.appsero.com/track'];
		$copy    = $sent['https://telemetry.wpgraphql.com/api/appsero/track'];

		$this->assertIsArray( $appsero );
		$this->assertSame( get_option( 'admin_email' ), $appsero['admin_email'] );
		$this->assertArrayHasKey( 'first_name', $appsero );
		$this->assertArrayHasKey( 'last_name', $appsero );

		// Everything else in the copy is the same as what Appsero received.
		unset( $appsero['admin_email'], $appsero['first_name'], $appsero['last_name'] );
		$this->assertSame( $appsero, $copy );
	}
}

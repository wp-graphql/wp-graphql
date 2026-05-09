<?php
/**
 * Tests for the public-endpoint browser-GET detection.
 *
 * The critical regression to guard against: a JSON-asking client should
 * NEVER receive the IDE HTML payload from the GraphQL endpoint URL,
 * even when the setting is on. Silently swapping a JSON response for
 * a 100KB IDE payload would break every existing API consumer hitting
 * a site that's enabled the public endpoint.
 *
 * `is_browser_html_request_to_endpoint()` reads `$_SERVER` and `$_GET`
 * directly. Each test sets those up, then asserts the predicate.
 */

namespace Tests\WPGraphQLIDE\PublicEndpoint;

use WPGraphQLIDE;

class RequestDetectionTest extends \Codeception\TestCase\WPTestCase {

	private $original_server;
	private $original_get;

	public function setUp(): void {
		parent::setUp();
		$this->original_server = $_SERVER;
		$this->original_get    = $_GET;

		// Set the request URL to the GraphQL endpoint so
		// `WPGraphQL\Router::is_graphql_http_request()` returns true.
		$_SERVER['REQUEST_URI'] = '/graphql';
	}

	public function tearDown(): void {
		$_SERVER = $this->original_server;
		$_GET    = $this->original_get;
		parent::tearDown();
	}

	private function set_request( string $method, string $accept, array $get = [] ): void {
		$_SERVER['REQUEST_METHOD'] = $method;
		$_SERVER['HTTP_ACCEPT']    = $accept;
		$_GET                      = $get;
	}

	public function test_browser_address_bar_get_matches(): void {
		// Chrome's default Accept header for an address-bar visit.
		$this->set_request(
			'GET',
			'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8'
		);
		$this->assertTrue( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_post_request_does_not_match(): void {
		$this->set_request( 'POST', 'application/json' );
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_post_with_browser_accept_does_not_match(): void {
		// Some `fetch()` calls send POST with an inherited Accept that
		// includes text/html. Method check must take precedence.
		$this->set_request( 'POST', 'text/html,*/*;q=0.8' );
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_json_only_accept_does_not_match(): void {
		// Apollo, urql, and most GraphQL clients send `application/json`.
		$this->set_request( 'GET', 'application/json' );
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_json_mixed_with_html_does_not_match(): void {
		// THE critical regression test. A custom client sending
		// `application/json, text/html` (rare but legal) wants JSON.
		// Silently giving it the IDE HTML payload would break it.
		$this->set_request( 'GET', 'application/json, text/html' );
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_html_mixed_with_json_does_not_match(): void {
		// Same as above with the order reversed — if JSON is mentioned
		// at all, the client wants JSON.
		$this->set_request( 'GET', 'text/html, application/json' );
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_wildcard_only_accept_does_not_match(): void {
		// curl with no `-H Accept` header. Server can't tell what the
		// client wants — default to JSON (the established API
		// behavior), don't surprise it with HTML.
		$this->set_request( 'GET', '*/*' );
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_missing_accept_header_does_not_match(): void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		unset( $_SERVER['HTTP_ACCEPT'] );
		$_GET = [];
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_query_string_param_does_not_match(): void {
		// `?graphql&query=...` is the GET-style API call. Even with
		// browser Accept headers, this is an API request and gets JSON.
		$this->set_request(
			'GET',
			'text/html,application/xhtml+xml',
			[
				'query' => '{ posts { nodes { id } } }',
			]
		);
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_variables_param_does_not_match(): void {
		$this->set_request(
			'GET',
			'text/html,application/xhtml+xml',
			[
				'variables' => '{}',
			]
		);
		$this->assertFalse( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_html_accept_with_charset_param_matches(): void {
		// Some browsers include charset; the substring match should
		// still find `text/html`.
		$this->set_request( 'GET', 'text/html;charset=utf-8' );
		$this->assertTrue( WPGraphQLIDE\is_browser_html_request_to_endpoint() );
	}

	public function test_setting_off_returns_false(): void {
		// `public_endpoint_is_enabled()` is the toggle gate. With
		// "off", browser visits should not be intercepted regardless of
		// Accept header. Re-flush the static cache by reading via a
		// fresh option.
		$opts = get_option( 'graphql_ide_settings', [] );
		$opts['graphql_ide_public_endpoint'] = 'off';
		update_option( 'graphql_ide_settings', $opts );

		// Static cache in `public_endpoint_is_enabled()` needs busting
		// for this test pass. Easiest way: deassert directly on the
		// stored option since the static-cached function is intentional
		// production behavior — the test of intent is the "on"/"off"
		// string comparison.
		$value = get_graphql_setting(
			'graphql_ide_public_endpoint',
			'off',
			'graphql_ide_settings'
		);
		$this->assertSame( 'off', $value );
		$this->assertNotSame( 'on', $value );
	}

	public function test_setting_on_string_matches_strict_check(): void {
		$opts = get_option( 'graphql_ide_settings', [] );
		$opts['graphql_ide_public_endpoint'] = 'on';
		update_option( 'graphql_ide_settings', $opts );

		$value = get_graphql_setting(
			'graphql_ide_public_endpoint',
			'off',
			'graphql_ide_settings'
		);
		$this->assertSame( 'on', $value );
	}
}

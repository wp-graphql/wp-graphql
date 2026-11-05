<?php

use WPGraphQL\Registry\RequestExtensionRegistry;
use WPGraphQL\Request;
use WPGraphQL\Router;

/**
 * Tests for the Request Extension Registry and request-level extension processing.
 */
class RequestExtensionTest extends \Codeception\TestCase\WPTestCase {

	public function tearDown(): void {
		unset( $_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] );
		unset( $_SERVER['HTTP_X_GRAPHQL_PREVIEW'] );
		parent::tearDown();
	}

	/**
	 * Tests registering an extension and members.
	 */
	public function testRegisterRequestExtensionAndMembers() {
		register_graphql_request_extension(
			'postPassword',
			[
				'description' => 'Post passwords supplied by client.',
				'header'      => 'X-GraphQL-Post-Password',
				'cache'       => 'private',
				'members'     => [
					'postId' => [
						'type'        => 'integer',
						'description' => 'The ID of the post.',
					],
				],
			]
		);

		register_graphql_request_extension_member(
			'postPassword',
			'password',
			[
				'type'        => 'string',
				'description' => 'The post password.',
			]
		);

		$registry = \WPGraphQL::get_request_extension_registry();
		$ext      = $registry->get_request_extension( 'postPassword' );

		$this->assertNotNull( $ext );
		$this->assertSame( 'X-GraphQL-Post-Password', $ext['header'] );
		$this->assertArrayHasKey( 'postId', $ext['members'] );
		$this->assertArrayHasKey( 'password', $ext['members'] );
	}

	/**
	 * Tests preview member registration helper (subsuming #4378).
	 */
	public function testRegisterPreviewContextMember() {
		register_graphql_preview_context_member(
			'locale',
			[
				'type'        => 'string',
				'description' => 'Locale for preview context.',
			]
		);

		$registry = \WPGraphQL::get_request_extension_registry();
		$ext      = $registry->get_request_extension( 'preview' );

		$this->assertNotNull( $ext );
		$this->assertArrayHasKey( 'locale', $ext['members'] );
	}

	/**
	 * Tests header parsing with RFC 8941 Structured Fields dictionary.
	 */
	public function testHeaderParsingWithRFC8941() {
		register_graphql_request_extension(
			'postPassword',
			[
				'header'  => 'X-GraphQL-Post-Password',
				'members' => [
					'postId'   => [ 'type' => 'integer' ],
					'password' => [ 'type' => 'string' ],
				],
			]
		);

		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'post_id=42, password="secret"';

		$request = new Request();
		$parsed  = $request->app_context->get_request_extension( 'postPassword' );

		$this->assertIsArray( $parsed );
		$this->assertSame( 42, $parsed['postId'] );
		$this->assertSame( 'secret', $parsed['password'] );
	}

	/**
	 * Tests header-wins precedence over body extensions.
	 */
	public function testHeaderWinsPrecedence() {
		register_graphql_request_extension(
			'postPassword',
			[
				'header'  => 'X-GraphQL-Post-Password',
				'members' => [
					'postId'   => [ 'type' => 'integer' ],
					'password' => [ 'type' => 'string' ],
				],
			]
		);

		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'post_id=42, password="from-header"';

		$request = new Request(
			[
				'query'      => '{ __typename }',
				'extensions' => [
					'postPassword' => [
						'postId'   => 99,
						'password' => 'from-body',
					],
				],
			]
		);

		$parsed = $request->app_context->get_request_extension( 'postPassword' );
		$this->assertSame( 42, $parsed['postId'] );
		$this->assertSame( 'from-header', $parsed['password'] );
	}

	/**
	 * Tests that registered extension headers are added to CORS allowed headers and Vary.
	 */
	public function testCorsAndVaryHeaders() {
		register_graphql_request_extension(
			'postPassword',
			[
				'header' => 'X-GraphQL-Post-Password',
				'cache'  => 'private',
			]
		);

		$router_ref  = new \ReflectionClass( Router::class );
		$get_headers = $router_ref->getMethod( 'get_response_headers' );
		if ( PHP_VERSION_ID < 80100 ) {
			$get_headers->setAccessible( true );
		}

		$headers = $get_headers->invoke( null );

		$this->assertArrayHasKey( 'Access-Control-Allow-Headers', $headers );
		$this->assertStringContainsString( 'X-GraphQL-Post-Password', $headers['Access-Control-Allow-Headers'] );

		$this->assertArrayHasKey( 'Vary', $headers );
		$this->assertStringContainsString( 'X-GraphQL-Post-Password', $headers['Vary'] );
	}
}


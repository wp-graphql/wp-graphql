<?php

use WPGraphQL\Request;
use WPGraphQL\Router;

/**
 * Tests for the request extension registry and request-level extension processing.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4457
 */
class RequestExtensionTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var int
	 */
	private $admin;

	/**
	 * @var int
	 */
	private $post;

	public function setUp(): void {
		parent::setUp();

		$this->reset_registry();
		WPGraphQL::clear_schema();

		$this->admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->post  = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $this->admin,
				'post_title'  => 'Published Title',
			]
		);

		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_field(
					'RootQuery',
					'requestExtension',
					[
						'type'        => 'String',
						'description' => 'The parsed request extension namespace, as JSON.',
						'args'        => [
							'name' => [ 'type' => [ 'non_null' => 'String' ] ],
						],
						'resolve'     => static function ( $root, array $args, \WPGraphQL\AppContext $context ) {
							return wp_json_encode( $context->get_request_extension( $args['name'] ) );
						},
					]
				);
			}
		);
	}

	public function tearDown(): void {
		unset( $_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'], $_SERVER['HTTP_X_GRAPHQL_PREVIEW'], $_SERVER['HTTP_X_GRAPHQL_LOCALE'] );

		$router  = new \ReflectionProperty( Router::class, 'request' );
		$router->setAccessible( true );
		$router->setValue( null, null );

		$this->reset_registry();
		WPGraphQL::clear_schema();
		wp_delete_post( $this->post, true );
		wp_delete_user( $this->admin );

		parent::tearDown();
	}

	/**
	 * The registry is static; reset it so every test starts from core's namespaces only.
	 */
	private function reset_registry(): void {
		$registry = new \ReflectionProperty( WPGraphQL::class, 'request_extension_registry' );
		$registry->setAccessible( true );
		$registry->setValue( null, null );
	}

	/**
	 * Registers the namespace a post-password feature would (see #930).
	 *
	 * @param array<string,mixed> $overrides Config overrides.
	 */
	private function register_post_password_extension( array $overrides = [] ): void {
		register_graphql_request_extension(
			'postPassword',
			array_merge(
				[
					'description' => 'Post passwords supplied by the client.',
					'header'      => 'X-GraphQL-Post-Password',
					'cache'       => 'no-store',
					'members'     => [
						'postId'   => [
							'type'     => 'integer',
							'sanitize' => static function ( $value ) {
								return $value > 0 ? $value : null;
							},
						],
						'password' => [
							'type'     => 'string',
							'sanitize' => static function ( $value ) {
								return $value;
							},
						],
					],
				],
				$overrides
			)
		);
	}

	/**
	 * Queries the parsed request extension namespace.
	 *
	 * @param string                   $name       The namespace.
	 * @param array<string,mixed>|null $extensions The request `extensions`, if any.
	 *
	 * @return array<string,mixed> The GraphQL response.
	 */
	private function query_request_extension( string $name, ?array $extensions = null ): array {
		$request = [
			'query'     => 'query( $name: String! ) { requestExtension( name: $name ) }',
			'variables' => [ 'name' => $name ],
		];

		if ( null !== $extensions ) {
			$request['extensions'] = $extensions;
		}

		return $this->graphql( $request );
	}

	/**
	 * @param array<string,mixed> $response The GraphQL response.
	 *
	 * @return mixed The decoded request extension.
	 */
	private function get_request_extension_from_response( array $response ) {
		$this->assertArrayNotHasKey( 'errors', $response );

		return json_decode( $response['data']['requestExtension'], true );
	}

	/**
	 * @param array<string,mixed> $response The GraphQL response.
	 *
	 * @return string[] The debug message types.
	 */
	private function get_debug_types( array $response ): array {
		return wp_list_pluck( $response['extensions']['debug'] ?? [], 'type' );
	}

	/**
	 * Returns the response headers for the given request.
	 *
	 * @param \WPGraphQL\Request|null $request The request.
	 *
	 * @return array<string,string>
	 */
	private function get_response_headers( ?Request $request ): array {
		$router = new \ReflectionClass( Router::class );

		$request_property = $router->getProperty( 'request' );
		$request_property->setAccessible( true );
		$request_property->setValue( null, $request );

		$get_headers = $router->getMethod( 'get_response_headers' );
		$get_headers->setAccessible( true );

		return $get_headers->invoke( null );
	}

	public function testRegisterRequestExtensionAndMembers() {
		$this->register_post_password_extension();

		register_graphql_request_extension_member(
			'postPassword',
			'unlockAll',
			[
				'type'        => 'boolean',
				'description' => 'Whether to unlock every post the password matches.',
				'sanitize'    => static function ( $value ) {
					return $value;
				},
			]
		);

		$extension = WPGraphQL::get_request_extension_registry()->get_extension( 'postPassword' );

		$this->assertNotNull( $extension );
		$this->assertSame( 'X-GraphQL-Post-Password', $extension['header'] );
		$this->assertSame( 'no-store', $extension['cache'] );
		$this->assertSame( [ 'postId', 'password', 'unlockAll' ], array_keys( $extension['members'] ) );
		$this->assertSame( 'post_id', $extension['members']['postId']['header_key'], 'The header key is derived from the camelCase name' );
		$this->assertSame( 'unlock_all', $extension['members']['unlockAll']['header_key'] );
	}

	/**
	 * register_graphql_preview_context_member() registers a member of core's `preview` namespace.
	 */
	public function testRegisterPreviewContextMemberAddsToThePreviewNamespace() {
		register_graphql_preview_context_member(
			'locale',
			[
				'type'        => 'string',
				'description' => 'Locale for preview context.',
				'sanitize'    => 'sanitize_text_field',
			]
		);

		$extension = WPGraphQL::get_request_extension_registry()->get_extension( 'preview' );

		$this->assertNotNull( $extension );
		$this->assertArrayHasKey( 'locale', $extension['members'] );
		$this->assertFalse( $extension['members']['locale']['core'] );
	}

	/**
	 * Registrations made through the access functions wait for the registry to be read, and
	 * the action fires once, when it first is; a member may be registered ahead of its
	 * namespace.
	 */
	public function testRegistrationsWaitForTheRegistryAndTheActionFiresOnce() {
		$fired = 0;
		add_action(
			'graphql_register_request_extensions',
			static function ( $registry ) use ( &$fired ) {
				++$fired;
				$registry->register_member(
					'postPassword',
					'unlockAll',
					[
						'type'     => 'boolean',
						'sanitize' => static function ( $value ) {
							return $value;
						},
					]
				);
			}
		);

		// Registered after the action callback above, which registers a member of it.
		$this->register_post_password_extension();

		$this->assertSame( 0, $fired, 'Registering does not read the registry' );

		$registry = WPGraphQL::get_request_extension_registry();
		WPGraphQL::get_request_extension_registry();

		$this->assertSame( 1, $fired );
		$this->assertSame( [ 'postId', 'password', 'unlockAll' ], array_keys( $registry->get_extension( 'postPassword' )['members'] ), 'A member registered ahead of its namespace is added when the namespace registers' );
	}

	/**
	 * The header is parsed as an RFC 8941 dictionary, keyed by the derived header keys.
	 */
	public function testHeaderParsingWithRFC8941() {
		$this->register_post_password_extension();

		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = sprintf( 'post_id=%d, password="secret"', $this->post );

		$this->assertSame(
			[
				'postId'   => $this->post,
				'password' => 'secret',
			],
			$this->get_request_extension_from_response( $this->query_request_extension( 'postPassword' ) )
		);
	}

	/**
	 * The header takes precedence over the request `extensions`; without it, the
	 * `extensions` object applies, and a malformed header falls back to it.
	 */
	public function testHeaderWinsPrecedence() {
		$this->register_post_password_extension();

		$extensions = [
			'postPassword' => [
				'postId'   => 99,
				'password' => 'from-body',
			],
		];

		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'post_id=42, password="from-header"';

		$this->assertSame(
			[
				'postId'   => 42,
				'password' => 'from-header',
			],
			$this->get_request_extension_from_response( $this->query_request_extension( 'postPassword', $extensions ) )
		);

		// A trailing comma is an RFC 8941 parse error: the header is discarded entirely.
		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'post_id=42,';

		$actual = $this->query_request_extension( 'postPassword', $extensions );
		$this->assertSame( $extensions['postPassword'], $this->get_request_extension_from_response( $actual ) );
		$this->assertContains( 'REQUEST_EXTENSION_MALFORMED', $this->get_debug_types( $actual ) );

		unset( $_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] );

		$this->assertSame( $extensions['postPassword'], $this->get_request_extension_from_response( $this->query_request_extension( 'postPassword', $extensions ) ) );
		$this->assertNull( $this->get_request_extension_from_response( $this->query_request_extension( 'postPassword' ) ), 'A request without the namespace does not carry it' );
	}

	/**
	 * Registered headers are added to the CORS allowed headers and to Vary, once each.
	 */
	public function testCorsAndVaryHeaders() {
		$this->register_post_password_extension();
		register_graphql_request_extension(
			'locale',
			[
				'header'  => 'X-GraphQL-Locale',
				'members' => [],
			]
		);
		register_graphql_request_extension( 'abTest', [ 'members' => [] ] );

		$headers = $this->get_response_headers( null );

		$allow_headers = explode( ', ', $headers['Access-Control-Allow-Headers'] );
		$this->assertContains( 'X-GraphQL-Preview', $allow_headers );
		$this->assertContains( 'X-GraphQL-Post-Password', $allow_headers );
		$this->assertContains( 'X-GraphQL-Locale', $allow_headers );
		$this->assertSame( array_unique( $allow_headers ), $allow_headers, 'Each header is allowed once' );

		$vary = explode( ', ', $headers['Vary'] );
		$this->assertContains( 'X-GraphQL-Preview', $vary );
		$this->assertContains( 'X-GraphQL-Post-Password', $vary );
		$this->assertContains( 'X-GraphQL-Locale', $vary );
		$this->assertSame( array_unique( $vary ), $vary, 'Each header is listed once' );
	}

	/**
	 * A response to a request carrying a `no-store` namespace is not stored by any cache; a
	 * `vary` namespace, or a request without the namespace, is not forced to no-store.
	 */
	public function testNoStoreNamespaceForbidsStoringTheResponse() {
		$this->register_post_password_extension();
		register_graphql_request_extension(
			'locale',
			[
				'header'  => 'X-GraphQL-Locale',
				'cache'   => 'vary',
				'members' => [
					'code' => [
						'type'     => 'string',
						'sanitize' => 'sanitize_text_field',
					],
				],
			]
		);

		$execute = function ( array $params ): Request {
			$request = new Request( $params );
			$request->execute();
			return $request;
		};

		$headers = $this->get_response_headers( $execute( [ 'query' => '{ __typename }' ] ) );
		$this->assertTrue( ! isset( $headers['Cache-Control'] ) || false === strpos( $headers['Cache-Control'], 'no-store' ), 'A request without the namespace is not forced to no-store' );

		$_SERVER['HTTP_X_GRAPHQL_LOCALE'] = 'code="fr_FR"';
		$headers                          = $this->get_response_headers( $execute( [ 'query' => '{ __typename }' ] ) );
		$this->assertTrue( ! isset( $headers['Cache-Control'] ) || false === strpos( $headers['Cache-Control'], 'no-store' ), 'A `vary` namespace is not forced to no-store' );

		$headers = $this->get_response_headers(
			$execute(
				[
					'query'      => '{ __typename }',
					'extensions' => [ 'postPassword' => [ 'password' => 'secret' ] ],
				]
			)
		);
		$this->assertSame( 'no-store, private', $headers['Cache-Control'] );
	}

	/**
	 * Fix 1: members of `preview` registered through the registry (by either access function,
	 * or on the registry action) reach `previewResolve`, because AppContext::$preview is
	 * built from the same parsed values as the `preview` request extension.
	 */
	public function testPreviewMembersRegisteredThroughTheRegistryReachPreviewResolve() {
		register_graphql_request_extension_member(
			'preview',
			'translationLocale',
			[
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			]
		);
		add_action(
			'graphql_register_request_extensions',
			static function ( $registry ) {
				$registry->register_member(
					'preview',
					'variant',
					[
						'type'     => 'integer',
						'sanitize' => 'absint',
					]
				);
			}
		);

		$preview_context = null;
		add_action(
			'graphql_register_types',
			static function () use ( &$preview_context ) {
				register_graphql_field(
					'Post',
					'previewedMembers',
					[
						'type'           => 'String',
						'resolve'        => static function () {
							return 'published';
						},
						'previewResolve' => static function ( $post, $args, $context, $info, array $preview ) use ( &$preview_context ) {
							$preview_context = $context->get_request_extension( 'preview' );
							return wp_json_encode( array_intersect_key( $preview, array_flip( [ 'translationLocale', 'variant' ] ) ) );
						},
					]
				);
			}
		);

		wp_set_current_user( $this->admin );

		$query = 'query( $id: ID! ) { post( id: $id, idType: DATABASE_ID ) { previewedMembers } }';

		$_SERVER['HTTP_X_GRAPHQL_PREVIEW'] = sprintf( 'database_id=%d, translation_locale="fr_FR", variant=2', $this->post );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [ 'id' => $this->post ],
			]
		);

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame(
			[
				'translationLocale' => 'fr_FR',
				'variant'           => 2,
			],
			json_decode( $actual['data']['post']['previewedMembers'], true )
		);
		$this->assertIsArray( $preview_context );
		$this->assertSame( $this->post, $preview_context['databaseId'], 'The `preview` request extension is the preview context' );
		$this->assertSame( 'fr_FR', $preview_context['translationLocale'] );
	}

	/**
	 * Fix 2: keys no member registered are dropped from both transports, never copied
	 * through unsanitized.
	 */
	public function testUnregisteredKeysAreDropped() {
		$this->register_post_password_extension();

		$parsed = $this->get_request_extension_from_response(
			$this->query_request_extension(
				'postPassword',
				[
					'postPassword' => [
						'password' => 'secret',
						'injected' => '<script>alert(1)</script>',
						'post_id'  => 7,
					],
				]
			)
		);

		$this->assertSame(
			[
				'postId'   => null,
				'password' => 'secret',
			],
			$parsed,
			'Only registered members, under their member names, are kept'
		);

		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'password="secret", injected="<script>"';

		$this->assertSame(
			[
				'postId'   => null,
				'password' => 'secret',
			],
			$this->get_request_extension_from_response( $this->query_request_extension( 'postPassword' ) )
		);

		// A body carrying only unregistered keys does not carry the namespace at all.
		unset( $_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] );

		$this->assertNull( $this->get_request_extension_from_response( $this->query_request_extension( 'postPassword', [ 'postPassword' => [ 'injected' => 'x' ] ] ) ) );

		// The same holds for the preview context.
		wp_set_current_user( $this->admin );
		$request = new Request(
			[
				'query'      => '{ __typename }',
				'extensions' => [
					'preview' => [
						'databaseId' => $this->post,
						'injected'   => '<script>',
					],
				],
			]
		);
		$request->execute();

		$this->assertIsArray( $request->app_context->preview );
		$this->assertArrayNotHasKey( 'injected', $request->app_context->preview );
	}

	/**
	 * Fix 3: both transports are type-checked against the declared type with no coercion;
	 * an `integer` member does not keep a numeric or non-numeric string.
	 */
	public function testBothTransportsAreTypeCheckedWithoutCoercion() {
		$this->register_post_password_extension();
		register_graphql_request_extension_member(
			'postPassword',
			'unlockAll',
			[
				'type'     => 'boolean',
				'sanitize' => static function ( $value ) {
					return $value;
				},
			]
		);

		$invalid = [
			'postId'    => null,
			'password'  => null,
			'unlockAll' => null,
		];

		// JSON: a non-numeric string, a numeric string, a number and a string boolean.
		$actual = $this->query_request_extension(
			'postPassword',
			[
				'postPassword' => [
					'postId'    => '42abc',
					'password'  => 42,
					'unlockAll' => 'true',
				],
			]
		);
		$this->assertSame( $invalid, $this->get_request_extension_from_response( $actual ) );
		$this->assertContains( 'REQUEST_EXTENSION_MEMBER_INVALID', $this->get_debug_types( $actual ) );

		$this->assertSame(
			$invalid,
			$this->get_request_extension_from_response( $this->query_request_extension( 'postPassword', [ 'postPassword' => [ 'postId' => '42' ] ] ) ),
			'A numeric string is not coerced to an integer'
		);

		// Header: a String where an Integer is declared, a Token where a String is, an Integer where a Boolean is.
		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'post_id="42", password=secret, unlock_all=1';

		$actual = $this->query_request_extension( 'postPassword' );
		$this->assertNull( $this->get_request_extension_from_response( $actual ), 'A header with no usable member is ignored, and nothing else carries the namespace' );
		$this->assertContains( 'REQUEST_EXTENSION_MEMBER_INVALID', $this->get_debug_types( $actual ) );

		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'post_id=42, password="secret", unlock_all';

		$this->assertSame(
			[
				'postId'    => 42,
				'password'  => 'secret',
				'unlockAll' => true,
			],
			$this->get_request_extension_from_response( $this->query_request_extension( 'postPassword' ) )
		);
	}

	/**
	 * Fix 4: the raw header is parsed first, and each value is then sanitized by its own
	 * member's sanitizer, so String values survive intact (whole-header
	 * `sanitize_text_field()` would strip `%xx` sequences and tag-like text).
	 */
	public function testHeaderIsParsedRawThenSanitizedPerMember() {
		$this->register_post_password_extension(
			[
				'members' => [
					'password' => [
						'type'     => 'string',
						'sanitize' => static function ( $value ) {
							return $value;
						},
					],
					'hint'     => [
						'type'     => 'string',
						'sanitize' => 'sanitize_text_field',
					],
				],
			]
		);

		$_SERVER['HTTP_X_GRAPHQL_POST_PASSWORD'] = 'password="100%25 <b>off</b>", hint="<b>bold</b> hint"';

		$this->assertSame(
			[
				'password' => '100%25 <b>off</b>',
				'hint'     => 'bold hint',
			],
			$this->get_request_extension_from_response( $this->query_request_extension( 'postPassword' ) )
		);
	}

	/**
	 * Parsing the raw header keeps the preview header working as before: core's members are
	 * sanitized individually, and tabs between members (which the whole-header
	 * `sanitize_text_field()` used to turn into spaces) are optional whitespace RFC 8941
	 * allows there.
	 */
	public function testPreviewHeaderKeepsWorkingWhenParsedRaw() {
		wp_set_current_user( $this->admin );

		$_SERVER['HTTP_X_GRAPHQL_PREVIEW'] = sprintf( "database_id=%d,\tfeatured_image_database_id=0 ,\tnonce=\"<b>abc</b>\"", $this->post );

		$request = new Request( [ 'query' => '{ __typename }' ] );
		$request->execute();

		$this->assertIsArray( $request->app_context->preview );
		$this->assertSame( $this->post, $request->app_context->preview['databaseId'] );
		$this->assertSame( 0, $request->app_context->preview['featuredImageDatabaseId'] );
		$this->assertSame( 'abc', $request->app_context->preview['nonce'], 'The nonce is still sanitized as text' );
		$this->assertSame( $request->app_context->preview, $request->app_context->get_request_extension( 'preview' ) );
	}

	/**
	 * Fix 5: core's namespace and members are reserved, the first registration wins, and
	 * every refusal surfaces a debug notice saying why.
	 */
	public function testCoreNamespacesAndMembersAreReservedAndTheFirstRegistrationWins() {
		$sanitize = static function ( $value ) {
			return $value;
		};

		// Re-registering `preview` can't change its header or drop `no-store`.
		register_graphql_request_extension(
			'preview',
			[
				'header'  => 'X-Not-Preview',
				'cache'   => 'vary',
				'members' => [],
			]
		);
		// No member registration can replace core's members.
		register_graphql_request_extension_member( 'preview', 'databaseId', [ 'type' => 'string', 'sanitize' => $sanitize ] );
		register_graphql_preview_context_member( 'revisionDatabaseId', [ 'type' => 'integer', 'sanitize' => $sanitize ] );
		register_graphql_preview_context_member( 'postId', [ 'type' => 'integer', 'header_key' => 'database_id', 'sanitize' => $sanitize ] );

		// First wins for namespaces, headers and members.
		$this->register_post_password_extension();
		$this->register_post_password_extension( [ 'cache' => 'vary' ] );
		register_graphql_request_extension( 'otherPassword', [ 'header' => 'x-graphql-post-password' ] );
		register_graphql_request_extension( 'previewHeader', [ 'header' => 'X-GraphQL-Preview' ] );
		register_graphql_request_extension( 'authorization', [ 'header' => 'Authorization' ] );
		register_graphql_request_extension_member( 'postPassword', 'password', [ 'type' => 'integer', 'sanitize' => $sanitize ] );

		// Invalid configs.
		register_graphql_request_extension( 'snake_case', [] );
		register_graphql_request_extension( 'badCache', [ 'cache' => 'forever' ] );
		register_graphql_request_extension( 'badHeader', [ 'header' => 'X GraphQL' ] );

		$registry = WPGraphQL::get_request_extension_registry();
		$preview  = $registry->get_extension( 'preview' );

		$this->assertSame( 'X-GraphQL-Preview', $preview['header'] );
		$this->assertSame( 'no-store', $preview['cache'] );
		$this->assertSame( [ 'integer', 'string' ], $preview['members']['databaseId']['types'] );
		$this->assertTrue( $preview['members']['databaseId']['core'] );
		$this->assertSame( [ 'databaseId', 'featuredImageDatabaseId', 'postFormat', 'nonce' ], array_keys( $preview['members'] ) );

		$this->assertSame( [ 'preview', 'postPassword' ], array_keys( $registry->get_extensions() ) );
		$this->assertSame( 'no-store', $registry->get_extension( 'postPassword' )['cache'], 'The first registration wins' );
		$this->assertSame( [ 'integer' ], $registry->get_extension( 'postPassword' )['members']['postId']['types'] );
		$this->assertSame( [ 'string' ], $registry->get_extension( 'postPassword' )['members']['password']['types'] );

		// The preview context keeps working, and a response carrying it is still no-store.
		wp_set_current_user( $this->admin );
		$request = new Request(
			[
				'query'      => '{ __typename }',
				'extensions' => [ 'preview' => [ 'databaseId' => (string) $this->post ] ],
			]
		);
		$actual = $request->execute();

		$this->assertSame( $this->post, $request->app_context->preview['databaseId'], 'Core keeps its lenient databaseId profile' );
		$this->assertSame( 'no-store, private', $this->get_response_headers( $request )['Cache-Control'] );

		$debug = $actual['extensions']['debug'] ?? [];
		$types = wp_list_pluck( $debug, 'type' );
		$this->assertContains( 'REQUEST_EXTENSION_NOT_REGISTERED', $types );
		$this->assertContains( 'PREVIEW_CONTEXT_MEMBER_NOT_REGISTERED', $types );
		$this->assertContains( 'REQUEST_EXTENSION_MEMBER_NOT_REGISTERED', $types );

		$messages = implode( "\n", wp_list_pluck( $debug, 'message' ) );
		$this->assertStringContainsString( 'The request extension namespace `preview` was not registered: it is reserved by WPGraphQL core.', $messages );
		$this->assertStringContainsString( 'The preview context member `databaseId` was not registered: it is reserved by WPGraphQL core.', $messages );
		$this->assertStringContainsString( 'The preview context member `revisionDatabaseId` was not registered: it is reserved by WPGraphQL core.', $messages );
		$this->assertStringContainsString( 'The preview context member `postId` was not registered: its header key is reserved by WPGraphQL core.', $messages );
		$this->assertStringContainsString( 'The request extension namespace `postPassword` was not registered: a namespace with the same name is already registered.', $messages );
		$this->assertStringContainsString( 'The request extension namespace `otherPassword` was not registered: its header is reserved by WPGraphQL core or already used by another namespace.', $messages );
		$this->assertStringContainsString( 'The request extension namespace `previewHeader` was not registered', $messages );
		$this->assertStringContainsString( 'The request extension namespace `authorization` was not registered', $messages );
		$this->assertStringContainsString( 'The request extension member `password` of `postPassword` was not registered: a member with the same name or header key is already registered.', $messages );
		$this->assertStringContainsString( 'The request extension namespace `snake_case` was not registered: the name must be camelCase', $messages );
		$this->assertStringContainsString( 'The request extension namespace `badCache` was not registered: the `cache` rule must be `vary` or `no-store`.', $messages );
		$this->assertStringContainsString( 'The request extension namespace `badHeader` was not registered: the `header` must be', $messages );
	}

	/**
	 * Fix 6 (registration timing): a namespace or member registered after the request read
	 * the registry is reported as too late, and is in time for the next request; a member
	 * whose namespace is never registered is reported too.
	 */
	public function testLateAndOrphanedRegistrationsSurfaceNotices() {
		register_graphql_request_extension_member(
			'neverRegistered',
			'value',
			[
				'type'     => 'string',
				'sanitize' => 'sanitize_text_field',
			]
		);
		add_action(
			'graphql_register_types',
			function () {
				$this->register_post_password_extension();
			}
		);

		$extensions = [ 'postPassword' => [ 'password' => 'secret' ] ];

		$actual = $this->query_request_extension( 'postPassword', $extensions );

		$this->assertNull( $this->get_request_extension_from_response( $actual ), 'A namespace registered during the schema build is not parsed for that request' );
		$types = $this->get_debug_types( $actual );
		$this->assertContains( 'REQUEST_EXTENSION_REGISTERED_LATE', $types );
		$this->assertContains( 'REQUEST_EXTENSION_MEMBER_NOT_REGISTERED', $types, 'A member of a namespace that was never registered is reported' );

		// The next request parses it.
		$this->assertSame(
			[
				'postId'   => null,
				'password' => 'secret',
			],
			$this->get_request_extension_from_response( $this->query_request_extension( 'postPassword', $extensions ) )
		);
	}
}

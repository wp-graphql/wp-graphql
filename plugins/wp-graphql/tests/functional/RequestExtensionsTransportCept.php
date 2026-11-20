<?php
/**
 * Functional tests for registered request extension namespaces over real HTTP:
 * CORS preflight advertising, the Vary axis, Cache-Control for `no-store`
 * namespaces, header parsing (raw, then per member), header precedence over the
 * request `extensions`, type checks with no coercion, unregistered keys being
 * dropped, batch semantics, and an extension's preview context member.
 *
 * The namespaces come from tests/_data/plugins/request-extensions-fixture.php,
 * which this test activates for its duration (and deactivates afterwards).
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4457
 */

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test registered request extension namespaces over HTTP' );

$options_table = $I->grabPrefixedTableNameFor( 'options' );
$fixture       = basename( dirname( __DIR__, 2 ) ) . '/tests/_data/plugins/request-extensions-fixture.php';

// Read and write the raw option rather than with haveOptionInDatabase(), which would
// delete `active_plugins` after the test and leave WPGraphQL itself inactive.
$active_plugins = $I->grabFromDatabase( $options_table, 'option_value', [ 'option_name' => 'active_plugins' ] );
$I->updateInDatabase(
	$options_table,
	[ 'option_value' => serialize( array_merge( (array) unserialize( (string) $active_plugins ), [ $fixture ] ) ) ], // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- WordPress stores the option serialized.
	[ 'option_name' => 'active_plugins' ]
);

/**
 * Sends a query for a namespace and returns the decoded field value.
 *
 * @param \FunctionalTester        $I          The tester.
 * @param string                   $name       The namespace.
 * @param array<string,mixed>|null $extensions The request `extensions`, if any.
 *
 * @return mixed
 */
$query_namespace = static function ( FunctionalTester $I, string $name, ?array $extensions = null ) {
	$body = [ 'query' => sprintf( '{ requestExtensionFixture( name: "%s" ) }', $name ) ];

	if ( null !== $extensions ) {
		$body['extensions'] = $extensions;
	}

	$I->haveHttpHeader( 'Content-Type', 'application/json' );
	$I->sendPOST( TEST_GRAPHQL_ENDPOINT, json_encode( $body ) );
	$I->seeResponseCodeIs( 200 );

	$response = json_decode( $I->grabResponse(), true );
	$I->assertArrayNotHasKey( 'errors', $response );

	return json_decode( $response['data']['requestExtensionFixture'], true );
};

$is_no_store = static function ( FunctionalTester $I ): bool {
	return false !== strpos( (string) $I->grabHttpHeader( 'Cache-Control' ), 'no-store' );
};

try {
	/**
	 * TEST 1: The CORS preflight allows every registered header, and responses vary on them.
	 */
	$I->wantTo( 'verify the preflight allows registered headers and responses vary on them' );

	$I->haveHttpHeader( 'Content-Type', 'application/json' );
	$I->sendOPTIONS( TEST_GRAPHQL_ENDPOINT );
	$I->seeResponseCodeIs( 200 );

	// Header lists may come back joined with or without spaces after the commas.
	$allow_headers = preg_split( '/\s*,\s*/', (string) $I->grabHttpHeader( 'Access-Control-Allow-Headers' ) );
	$I->assertContains( 'X-GraphQL-Preview', $allow_headers );
	$I->assertContains( 'X-GraphQL-Post-Password', $allow_headers );
	$I->assertContains( 'X-GraphQL-Locale', $allow_headers );

	$vary = preg_split( '/\s*,\s*/', (string) $I->grabHttpHeader( 'Vary' ) );
	$I->assertContains( 'X-GraphQL-Preview', $vary );
	$I->assertContains( 'X-GraphQL-Post-Password', $vary );
	$I->assertContains( 'X-GraphQL-Locale', $vary );

	/**
	 * TEST 2: A header is parsed raw, then each member is sanitized by its own sanitizer, so a
	 * String keeps `%xx` sequences and tag-like text; unregistered keys are dropped; and a
	 * `no-store` namespace makes the response no-store.
	 */
	$I->wantTo( 'verify a registered header is parsed raw, keeps only registered members, and is no-store' );

	$I->haveHttpHeader( 'X-GraphQL-Post-Password', 'post_id=5, password="100%25 <b>off</b>", injected="x"' );

	$I->assertSame(
		[
			'postId'   => 5,
			'password' => '100%25 <b>off</b>',
		],
		$query_namespace( $I, 'postPassword' )
	);
	$I->assertSame( 'no-store, private', $I->grabHttpHeader( 'Cache-Control' ) );

	/**
	 * TEST 3: The header takes precedence over the request `extensions`.
	 */
	$I->wantTo( 'verify the header wins over the request extensions' );

	$I->haveHttpHeader( 'X-GraphQL-Post-Password', 'post_id=5, password="from-header"' );

	$I->assertSame(
		[
			'postId'   => 5,
			'password' => 'from-header',
		],
		$query_namespace( $I, 'postPassword', [ 'postPassword' => [ 'password' => 'from-body' ] ] )
	);

	/**
	 * TEST 4: A malformed header is discarded in its entirety, and the request `extensions`
	 * apply instead; values there are type-checked with no coercion, and keys no member
	 * registered are dropped.
	 */
	$I->wantTo( 'verify a malformed header falls back to the extensions, type-checked' );

	$I->haveHttpHeader( 'X-GraphQL-Post-Password', 'post_id=5,' );

	$I->assertSame(
		[
			'postId'   => null,
			'password' => 'from-body',
		],
		$query_namespace(
			$I,
			'postPassword',
			[
				'postPassword' => [
					'postId'   => '5',
					'password' => 'from-body',
					'injected' => '<script>alert(1)</script>',
				],
			]
		),
		'A numeric string is not coerced to an integer member, and a key no member registered is dropped'
	);
	$I->assertTrue( $is_no_store( $I ), 'The request still carries the namespace (from the extensions)' );

	$I->deleteHeader( 'X-GraphQL-Post-Password' );

	/**
	 * TEST 5: Without the namespace, nothing is carried and no-store is not forced; a `vary`
	 * namespace does not force no-store either.
	 */
	$I->wantTo( 'verify only a no-store namespace the request carries forces no-store' );

	$I->assertNull( $query_namespace( $I, 'postPassword' ) );
	$I->assertFalse( $is_no_store( $I ) );

	$I->haveHttpHeader( 'X-GraphQL-Locale', 'code="fr_FR"' );
	$I->assertSame( [ 'code' => 'fr_FR' ], $query_namespace( $I, 'locale' ) );
	$I->assertFalse( $is_no_store( $I ), 'A `vary` namespace is not no-store' );
	$I->deleteHeader( 'X-GraphQL-Locale' );

	/**
	 * TEST 6: In a batch, per-operation `extensions` are ignored, and the header applies to
	 * every operation.
	 */
	$I->wantTo( 'verify batch semantics for a registered namespace' );

	$I->haveOptionInDatabase( 'graphql_general_settings', [ 'batch_queries_enabled' => 'on' ] );

	$operation = [
		'query'      => '{ requestExtensionFixture( name: "postPassword" ) }',
		'extensions' => [ 'postPassword' => [ 'password' => 'from-body' ] ],
	];

	$I->haveHttpHeader( 'Content-Type', 'application/json' );
	$I->sendPOST( TEST_GRAPHQL_ENDPOINT, json_encode( [ $operation, $operation ] ) );
	$I->seeResponseCodeIs( 200 );

	foreach ( json_decode( $I->grabResponse(), true ) as $result ) {
		$I->assertArrayNotHasKey( 'errors', $result );
		$I->assertNull( json_decode( $result['data']['requestExtensionFixture'], true ), 'Per-operation extensions are ignored in a batch' );
	}

	$I->haveHttpHeader( 'X-GraphQL-Post-Password', 'password="from-header"' );
	$I->sendPOST( TEST_GRAPHQL_ENDPOINT, json_encode( [ $operation, $operation ] ) );
	$I->seeResponseCodeIs( 200 );

	foreach ( json_decode( $I->grabResponse(), true ) as $result ) {
		$I->assertSame(
			[
				'postId'   => null,
				'password' => 'from-header',
			],
			json_decode( $result['data']['requestExtensionFixture'], true ),
			'The header applies to every operation in the batch'
		);
	}

	$I->deleteHeader( 'X-GraphQL-Post-Password' );

	/**
	 * TEST 7: An extension's preview context member travels in the X-GraphQL-Preview header,
	 * alongside core's keys.
	 */
	$I->wantTo( 'verify an extension preview member arrives with the preview context over HTTP' );

	$post_id = $I->havePostInDatabase(
		[
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Published Title',
		]
	);

	$I->haveHttpHeader( 'X-GraphQL-Preview', sprintf( 'database_id=%d, translation_locale="fr_FR", unknown="x"', $post_id ) );

	$preview = $query_namespace( $I, 'preview' );

	$I->assertSame( $post_id, $preview['databaseId'] );
	$I->assertSame( 'fr_FR', $preview['translationLocale'] );
	$I->assertArrayNotHasKey( 'unknown', $preview );
	$I->assertSame( 'no-store, private', $I->grabHttpHeader( 'Cache-Control' ) );

	$I->deleteHeader( 'X-GraphQL-Preview' );
} finally {
	$I->updateInDatabase( $options_table, [ 'option_value' => $active_plugins ], [ 'option_name' => 'active_plugins' ] );
}

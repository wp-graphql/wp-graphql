<?php

/**
 * Class PluginCompatibilityTest
 *
 * Various tests to check for compatibility with other plugins in the ecosystem
 */
class PluginCompatibilityTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		// before
		parent::setUp();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
	}

	public function tearDown(): void {

		// then
		parent::tearDown();
	}

	public function testAmpWpCompatibility() {

		add_filter(
			'parse_request',
			static function ( \WP $wp ) {
				return $wp;
			}
		);

		$post_id = $this->factory()->post->create(
			[
				'post_type'   => 'post',
				'post_status' => 'publish',
			]
		);

		$slug = get_post( $post_id )->post_name;

		$query  = '
		query PostBySlug( $slug: ID! ) {
			post( id: $slug idType: SLUG ) {
				databaseId
			}
		}
		';
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [ 'slug' => $slug ],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.databaseId', $post_id ),
			]
		);
	}

	public function testWpmlCompatibility() {
		$original_is_graphql_request = \WPGraphQL::is_graphql_request();

		try {
			\WPGraphQL::set_is_graphql_request( false );

			// Test a `false` value results in `false`
			$boolean_value  = false;
			$boolean_result = apply_filters( 'wpml_is_redirected', $boolean_value );
			$this->assertIsBool( $boolean_result );
			$this->assertSame( $boolean_result, $boolean_value );

			// Test a `string` value results in `string`
			$string_value  = 'https://example.com/redirect-target';
			$string_result = apply_filters( 'wpml_is_redirected', $string_value );
			$this->assertIsString( $string_result );
			$this->assertSame( $string_result, $string_value );

			// Test an actual graphql_request results in `false`, regardless the input value
			\WPGraphQL::set_is_graphql_request( true );
			$boolean_result_real_request = apply_filters( 'wpml_is_redirected', $boolean_value );
			$this->assertIsBool( $boolean_result_real_request );
			$this->assertSame( $boolean_result_real_request, false );

			$string_result_real_request = apply_filters( 'wpml_is_redirected', $string_value );
			$this->assertIsBool( $string_result_real_request );
			$this->assertSame( $string_result_real_request, false );
		} finally {
			\WPGraphQL::set_is_graphql_request( $original_is_graphql_request );
		}
	}
}

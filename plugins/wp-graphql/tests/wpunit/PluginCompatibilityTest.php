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
}

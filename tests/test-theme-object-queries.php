<?php
/**
 * WPGraphQL Test Theme Object Queries
 * This tests theme queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */

/**
 * Tests theme object queries.
 */
class WP_GraphQL_Test_Theme_Object_Queries extends WP_UnitTestCase {
	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * testThemeQuery
	 *
	 * This tests creating a single theme with data and retrieving said theme via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testThemeQuery() {

		/**
		 * Create a theme
		 */
		$theme_slug = 'twentyseventeen';

		/**
		 * Create the global ID based on the theme_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'theme', $theme_slug );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			theme(id: \"{$global_id}\") {
				author
				authorUri
				description
				id
				name
				screenshot
				slug
				tags
				themeUri
				version
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		$screenshot = $actual['data']['theme']['screenshot'];
		$this->assertTrue( is_string( $screenshot ) || null === $screenshot );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'theme' => [
					'author' => null,
					'authorUri' => 'https://wordpress.org/',
					'description' => null,
					'id' => $global_id,
					'name' => 'Twenty Seventeen',
					'screenshot' => $screenshot,
					'slug' => 'twentyseventeen',
					'tags' => null,
					'themeUri' => 'https://wordpress.org/themes/twentyseventeen/',
					'version' => null,
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * testThemeQueryWhereThemeDoesNotExist
	 *
	 * Tests a query for non existant theme.
	 *
	 * @since 0.0.5
	 */
	public function testThemeQueryWhereThemeDoesNotExist() {
		/**
		 * Create the global ID based on the theme_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'theme', 'doesNotExist' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			theme(id: \"{$global_id}\") {
				slug
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'theme' => null,
			],
			'errors' => [
				[
					'message' => 'No theme was found with the stylesheet: doesNotExist',
					'locations' => [
						[
							'line' => 3,
							'column' => 4,
						],
					],
					'path' => [
						'theme',
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}
}

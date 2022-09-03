<?php

class ThemeObjectQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	/**
	 * @var WP_Theme
	 */
	public $active_theme;

	public function setUp(): void {
		parent::setUp();

		$themes = wp_get_themes();

		codecept_debug( $themes );

		$this->active_theme = $themes[ array_key_first( $themes ) ]->get_stylesheet();
		update_option( 'template', $this->active_theme );
		update_option( 'stylesheet', $this->active_theme );

		$this->admin = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * This tests creating a single theme with data and retrieving said theme via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testThemeQuery() {

		/**
		 * Create the global ID based on the theme_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'theme', $this->active_theme );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = '
		query testThemeQuery( $id:ID! ) {
			theme(id: $id ) {
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
		}';

		$variables = [
			'id' => $global_id,
		];

		/**
		 * Run the GraphQL query
		 */
		wp_set_current_user( $this->admin );
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( $actual );

		$screenshot = $actual['data']['theme']['screenshot'];
		$this->assertTrue( is_string( $screenshot ) || null === $screenshot );

		$theme = wp_get_theme( $this->active_theme );
		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'theme' => [
				'author'      => $theme->author,
				'authorUri'   => 'https://wordpress.org/',
				'description' => $theme->description,
				'id'          => $global_id,
				'name'        => $theme->get( 'Name' ),
				'screenshot'  => $theme->get_screenshot(),
				'slug'        => $theme->get_stylesheet(),
				'tags'        => $theme->tags,
				'themeUri'    => $theme->get( 'ThemeURI' ),
				'version'     => $theme->version,
			],
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

	/**
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
		$query = '
		query testThemeQueryWhereThemeDoesNotExist( $id:ID! ) {
			theme(id: $id ) {
				slug
			}
		}';

		$variables = [
			'id' => $global_id,
		];

		/**
		 * Run the GraphQL query
		 */
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected_errors = [
			[
				'message'    => 'No theme was found with the stylesheet: doesNotExist',
				'locations'  => [
					[
						'line'   => 3,
						'column' => 4,
					],
				],
				'path'       => [
					'theme',
				],
				'extensions' => [
					'category' => 'user',
				],
			],
		];

		$this->assertEquals( $expected_errors, $actual['errors'] );
	}

}

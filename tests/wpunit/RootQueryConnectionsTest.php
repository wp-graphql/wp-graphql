<?php

class RootQueryConnectionsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	public function setUp(): void {
		// before
		parent::setUp();

		$this->clearSchema();

		$this->admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		// your set up methods here
		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		// then
		parent::tearDown();
	}

	public function testRootQueryToContentTypeConnection() {

		$query = '
		{
			contentTypes {
				nodes {
					__typename
					name
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'contentTypes.nodes',
					[
						'__typename' => 'ContentType',
						'name'       => 'post',
					]
				),
			]
		);
	}

	public function testRootQueryToPluginsConnection() {

		$query = '
		{
			plugins {
				nodes {
					__typename
				}
			}
		}
		';

		wp_set_current_user( $this->admin );

		$actual = graphql( [ 'query' => $query ] );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'plugins.nodes',
					[
						'__typename' => 'Plugin',
					]
				),
			]
		);

		wp_set_current_user( 0 );

		$actual = graphql( [ 'query' => $query ] );

		$this->assertQuerySuccessful(
			$actual,
			[
				/**
				 * @todo 'nodes' should return null.
				 */
				$this->expectedField( 'plugins.nodes', self::IS_FALSY ),
			]
		);
	}

	public function testRootQueryToRegisteredScriptsConnection() {

		$query = '
		{
			registeredScripts {
				nodes {
					__typename
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'registeredScripts.nodes',
					[
						'__typename' => 'EnqueuedScript',
					]
				),
			]
		);
	}

	public function testRootQueryToRegisteredStylesheetsConnection() {

		$query = '
		{
			registeredStylesheets {
				nodes {
					__typename
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'registeredStylesheets.nodes',
					[
						'__typename' => 'EnqueuedStylesheet',
					]
				),
			]
		);
	}

	public function testRootQueryToThemesConnection() {

		$query = '
		{
			themes {
				nodes {
					__typename
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		codecept_debug( $actual );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'themes.nodes',
					[
						'__typename' => 'Theme',
					]
				),
			]
		);
	}

	public function testRootQueryToUserRolesConnection() {

		$query = '
		{
			userRoles {
				nodes {
					__typename
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		$this->assertQuerySuccessful(
			$actual,
			[
				/**
				 * @todo 'nodes' should return null.
				 */
				$this->expectedField( 'userRoles.nodes', self::IS_FALSY ),
			]
		);
	}
}

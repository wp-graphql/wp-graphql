<?php

class PageByUriTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $page;
	public $user;

	public function setUp(): void {
		parent::setUp();

		$this->user = $this->factory()->user->create(
			[
				'role'       => 'administrator',
				'user_login' => 'queryPagebyUriTestUser',
			]
		);

		$this->page = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Test PageByUriTest',
				'post_author' => $this->user,
			]
		);

		update_option( 'permalink_structure', '/posts/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->init();
		flush_rewrite_rules();
		WPGraphQL::show_in_graphql();
		$this->clearSchema();
	}

	public function tearDown(): void {
		$this->clearSchema();
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		wp_delete_post( $this->page, true );
		wp_delete_user( $this->user, false );

		parent::tearDown();
	}

	function testPageByUriWithCustomPermalinks() {

		$query = '
		query GET_PAGE_BY_URI( $uri: ID! ) {
			page(id: $uri, idType: URI) {
				__typename
				id
				slug
				databaseId
				uri
			}
		}
		';

		flush_rewrite_rules( true );
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => '/non-existent-page',
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'page', self::IS_NULL ),
			]
		);

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'uri' => get_permalink( $this->page ),
				],
			]
		);

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'page.__typename', ucfirst( get_post_type_object( 'page' )->graphql_single_name ) ),
				$this->expectedField( 'page.databaseId', $this->page ),
				$this->expectedField( 'page.uri', str_ireplace( home_url(), '', get_permalink( $this->page ) ) ),
			]
		);
	}

	public function testQueryPageForPostsByUriReturnsNull() {

		$query = '
		query GetPageByUri($id:ID!) {
			page(id: $id, idType: URI) {
				__typename
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => '/' . get_post( $this->page )->post_name,
				],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'page.__typename', 'Page' ),
			]
		);

		// set the page as the page_for_posts
		update_option( 'page_for_posts', $this->page );
		update_option( 'show_on_front', 'page' );

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => '/' . get_post( $this->page )->post_name,
				],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'page', self::IS_NULL ),
			]
		);
	}
}

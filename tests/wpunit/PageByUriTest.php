<?php

class NodeByUriTest extends \Codeception\TestCase\WPTestCase {

	public $page;
	public $user;

	public function setUp(): void {

        global $wp_rewrite;
		update_option( 'permalink_structure', '/posts/%postname%/' );
		create_initial_taxonomies();
		$GLOBALS['wp_rewrite']->init();
		flush_rewrite_rules();
		WPGraphQL::show_in_graphql();

        $this->user = $this->factory()->user->create([
			'role' => 'administrator',
		]);

        $this->page = $this->factory()->post->create( [
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Test Page',
			'post_author' => $this->user
		] );
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

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => '/non-existent-page'
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull($actual['data']['page']);

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'uri' => get_permalink( $this->page )
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( ucfirst( get_post_type_object( 'page' )->graphql_single_name ), $actual['data']['page']['__typename'] );
		$this->assertSame( $this->page, $actual['data']['page']['databaseId'] );
		$this->assertSame( str_ireplace( home_url(), '', get_permalink( $this->page ) ), $actual['data']['page']['uri'] );
	}
}


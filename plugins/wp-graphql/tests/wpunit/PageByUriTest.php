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

	/**
	 * A typed `idType: URI` field must resolve a hierarchical page the same way
	 * nodeByUri does. A partial or wrong-hierarchy URI must return null (not an
	 * arbitrary page), and a same-slug-different-parent URI must resolve the
	 * correct node.
	 *
	 * Previously the typed field pre-seeded `post_type` into the query vars, so a
	 * non-matching path turned the request into an unbounded query and returned
	 * an arbitrary page, while nodeByUri correctly returned null.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3042
	 */
	public function testTypedUriFieldIsConsistentWithNodeByUriForHierarchy() {
		$parent_a = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Parent A',
				'post_name'   => 'parent-a',
				'post_author' => $this->user,
			]
		);
		$child_a  = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Dup Child A',
				'post_name'   => 'dup',
				'post_parent' => $parent_a,
				'post_author' => $this->user,
			]
		);
		$parent_b = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Parent B',
				'post_name'   => 'parent-b',
				'post_author' => $this->user,
			]
		);
		$child_b  = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Dup Child B',
				'post_name'   => 'dup',
				'post_parent' => $parent_b,
				'post_author' => $this->user,
			]
		);

		flush_rewrite_rules( true );

		// Sanity: both children share the same slug under different parents.
		$this->assertSame( 'dup', get_post( $child_a )->post_name );
		$this->assertSame( 'dup', get_post( $child_b )->post_name );

		$typed = '
		query ($uri: ID!) {
			page(id: $uri, idType: URI) { databaseId }
		}';
		$node  = '
		query ($uri: String!) {
			nodeByUri(uri: $uri) { ... on Page { databaseId } }
		}';

		$resolve_typed = function ( $uri ) use ( $typed ) {
			$res = $this->graphql( [ 'query' => $typed, 'variables' => [ 'uri' => $uri ] ] );
			$this->assertArrayNotHasKey( 'errors', $res );
			return $res['data']['page'];
		};
		$resolve_node  = function ( $uri ) use ( $node ) {
			$res = $this->graphql( [ 'query' => $node, 'variables' => [ 'uri' => $uri ] ] );
			$this->assertArrayNotHasKey( 'errors', $res );
			return $res['data']['nodeByUri'];
		};

		// A partial / wrong-hierarchy URI must be null for BOTH resolvers.
		$this->assertNull( $resolve_typed( '/dup/' ), 'Typed idType:URI must not over-resolve a partial path.' );
		$this->assertNull( $resolve_node( '/dup/' ), 'nodeByUri must not resolve a partial path.' );

		// The correct full path for each child must resolve that specific child
		// for BOTH resolvers (same slug, different parent).
		$uri_a = wp_make_link_relative( get_permalink( $child_a ) );
		$uri_b = wp_make_link_relative( get_permalink( $child_b ) );

		$this->assertSame( $child_a, $resolve_typed( $uri_a )['databaseId'] );
		$this->assertSame( $child_a, $resolve_node( $uri_a )['databaseId'] );
		$this->assertSame( $child_b, $resolve_typed( $uri_b )['databaseId'] );
		$this->assertSame( $child_b, $resolve_node( $uri_b )['databaseId'] );
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

<?php

class RevisionTest extends \Codeception\TestCase\WPTestCase {

	public $admin;
	public $subscriber;

	public function setUp(): void {

		$this->admin = $this->factory()->user->create([
			'role' => 'administrator'
		]);

		$this->subscriber = $this->factory()->user->create([
			'role' => 'subscriber'
		]);

		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test querying revisions as an admin
	 * @throws Exception
	 */
	public function testQueryRootRevisionsAsPublicUser() {

		wp_set_current_user( 0 );

		/**
		 * Create a post
		 */
		$post_id = $this->factory()->post->create( [
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => 'Test',
			'post_author' =>  $this->admin,
		] );

		/**
		 * Revise the post
		 */
		$this->factory()->post->update_object( $post_id, [
			'post_content' => 'Revised Test'
		] );


		$query = '
		query RootRevisions {
			revisions {
				nodes {
					__typename
					...on Post {
					    id
						postId
						title
						content
					}
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		codecept_debug( $actual );

		/**
		 * This query should not return any revisions because
		 * the user doesn't have permission to see them
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['revisions']['nodes'] );

	}

	/**
	 * Test querying revisions as an admin
	 * @throws Exception
	 */
	public function testQueryRootRevisionsAsAdmin() {

		wp_set_current_user( $this->admin );

		/**
		 * Create a post
		 */
		$post_id = $this->factory()->post->create( [
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => 'Test',
			'post_author' =>  $this->admin,
		] );

		/**
		 * Revise the post
		 */
		$this->factory()->post->update_object( $post_id, [
			'post_content' => 'Revised Test'
		] );


		$query = '
		query RootRevisions {
			revisions {
				nodes {
					__typename
					...on Post {
					    id
						postId
						title
						content
						revisionOf {
						  node {
							__typename
							...on Post {
							  postId
							}
						  }
						}
					}
				}
			}
		}
		';

		$actual = graphql( [ 'query' => $query ] );

		codecept_debug( $actual );

		/**
		 * This query should NOT error, because the user is asking for
		 * things they don't have permission to ask for
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );

		// Type of revision should be Post
		$this->assertEquals( 'Post', $actual['data']['revisions']['nodes'][0]['__typename'] );

		// Type of Parent should be Post
		$this->assertEquals( 'Post', $actual['data']['revisions']['nodes'][0]['revisionOf']['node']['__typename'] );

		// postId of parent should be ID of post we revised
		$this->assertEquals( $post_id, $actual['data']['revisions']['nodes'][0]['revisionOf']['node']['postId'] );
	}

	/**
	 * Query revisions of Posts
	 *
	 * @throws Exception
	 */
	public function testQueryRevisionsOfPost() {

		wp_set_current_user( $this->admin );

		/**
		 * Create a post
		 */
		$post_id = $this->factory()->post->create( [
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_content' => 'Test',
			'post_author' =>  $this->admin,
		] );

		/**
		 * Revise the post
		 */
		$this->factory()->post->update_object( $post_id, [
			'post_content' => 'Revised Test'
		] );


		$query = '
		query PostBy ($postId: Int) {
			postBy(postId: $postId) {
			    __typename
			    id
				postId
				title
				content
				revisions {
					nodes {
						__typename
						revisionOf {
						  node {
							__typename
							...on Post {
							  postId
							}
						  }
						}
					}
				}
			}
		}
		';

		$actual = graphql( [
			'query' => $query,
			'variables' => [
				'postId' => $post_id,
			]
		] );

		codecept_debug( $actual );

		/**
		 * This query should NOT error, because the user is asking for
		 * things they don't have permission to ask for
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );

		// Type of revision should be Post
		$this->assertEquals( 'Post', $actual['data']['postBy']['__typename'] );

		// Type of revision should be Post
		$this->assertEquals( 'Post', $actual['data']['postBy']['revisions']['nodes'][0]['__typename'] );

		// postId of parent of the revision should be ID of post we revised
		$this->assertEquals( $post_id, $actual['data']['postBy']['revisions']['nodes'][0]['revisionOf']['node']['postId'] );

	}


}

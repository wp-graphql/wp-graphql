<?php

class CustomPostTypeTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		parent::setUp();

	}

	public function tearDown() {
		parent::tearDown();

	}

	/**
	 * @throws Exception
	 */
	public function testQueryCustomPostType() {

		$id = $this->factory()->post->create([
			'post_type' => 'bootstrap_cpt',
			'post_status' => 'publish',
			'post_title' => 'Test'
		]);

		codecept_debug( WPGraphQL::get_allowed_post_types() );

		$query = '
		query GET_CUSTOM_POSTS( $id: Int ) {
		  bootstrapPostBy( bootstrapPostId: $id ) {
		    bootstrapPostId
		  }
		  bootstrapPosts {
		    nodes {
		      bootstrapPostId
		    }
		    edges {
		      node {
		        bootstrapPostId
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $id
			]
		]);

		codecept_debug( $actual );
		$this->assertEquals( $id, $actual['data']['bootstrapPostBy']['bootstrapPostId']);
		$this->assertEquals( $id, $actual['data']['bootstrapPosts']['nodes'][0]['bootstrapPostId']);
		$this->assertEquals( $id, $actual['data']['bootstrapPosts']['edges'][0]['node']['bootstrapPostId']);

	}

}

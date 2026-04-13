<?php
class TemplateLocationsTest extends \Codeception\TestCase\WPTestCase {

	public $group_key;
	public $post_id;
	public $test_image;
	public $tag_id;
	public $comment_id;

	public function setUp(): void {
		parent::setUp();
		$this->group_key = __CLASS__;
		WPGraphQL::clear_schema();

		$this->post_id = $this->factory()->post->create( [
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Test',
			'post_content' => 'test',
		] );

	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testBasicQuery() {
		$query  = '{ posts { nodes { id, template { __typename } } } }';
		$actual = graphql( [ 'query' => $query ] );
		codecept_debug( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	// @todo: WPGraphQL needs to update to use get_page_templates() in order
	// for testing to work properly for assigning fields to

}

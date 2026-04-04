<?php
class ExplicitOptionsTest extends \Codeception\TestCase\WPTestCase {

	public $group_key;

	public function setUp(): void {
		$this->group_key = __CLASS__;
		WPGraphQL::clear_schema();
		parent::setUp();
	}

	public function tearDown(): void {
		acf_remove_local_field_group( $this->group_key );
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function register_acf_field_group( $config = [] ) {

		$defaults = [
			'key'                   => $this->group_key,
			'title'                 => 'Post Object Fields',
			'fields'                => [],
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					],
				],
			],
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'postFields',
			'graphql_types'      => [ 'Post' ]
		];

		acf_add_local_field_group( array_merge( $defaults, $config ) );

	}

	public function register_acf_field( $config = [] ) {

		$defaults = [
			'parent'            => 'group_key',
			'key'               => 'field_5d7812fd000a4',
			'label'             => 'Text',
			'name'              => 'text',
			'type'              => 'text',
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
			'show_in_graphql'   => 1,
			'default_value'     => '',
			'placeholder'       => '',
			'prepend'           => '',
			'append'            => '',
			'maxlength'         => '',
		];

		acf_add_local_field( array_merge( $defaults, $config ) );
	}

	/**
	 * Test Explicit Options
	 * Register an acf group field and enable it for Post and CPT, but disable for Page
	 * Check if the acf field is queriable for each post types
	 */
	public function testExplicitOptions() {

		$cpt_name = 'acf_cpt';
		// register a custom post type.
		register_post_type(
			$cpt_name,
			[
				'show_in_graphql'     => true,
				'graphql_single_name' => 'acfCpt',
				'graphql_plural_name' => 'acfCpts',
				'public'              => true,
			]
		);

		$group_key          = 'group_for_page_and_cpt';
		$group_graphql_name = 'pageCptFields';

		// register a field group for Post and custom post type $cpt_name.
		$this->register_acf_field_group(
			[
				'key'                => $group_key,
				'graphql_field_name' => $group_graphql_name,
				'graphql_types'   => [ 'Post', 'acfCpt' ],
			]
		);

		$this->register_acf_field(
			[
				'parent' => $group_key,
				'type'   => 'text',
				'name'   => 'acf_text_field',
			]
		);

		// create a test post.
		$post_id = $this->factory()->post->create(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Test Post',
				'post_content' => 'test post',
			]
		);

		$expected_text_1 = 'test value1';
		update_field( 'acf_text_field', $expected_text_1, $post_id );

		// create a test page.
		$page_id = $this->factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Test Page',
				'post_content' => 'test page',
			]
		);

		$expected_text_2 = 'test value2';
		update_field( 'acf_text_field', $expected_text_2, $page_id );

		// create a custom post type post.
		$cpt_id = $this->factory()->post->create(
			[
				'post_type'    => $cpt_name,
				'post_status'  => 'publish',
				'post_title'   => 'Test Post',
				'post_content' => 'test post',
			]
		);

		$expected_text_3 = 'test value3';
		update_field( 'acf_text_field', $expected_text_3, $cpt_id );

		// post assert validation.
		$query = sprintf(
			'
			query getPostById( $postId: Int ) {
				postBy( postId: $postId ) {
					id
					%s {
						fieldGroupName
						%s
					}
				}
			}
			',
			$group_graphql_name,
			'acfTextField'
		);

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'postId' => $post_id,
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_text_1, $actual['data']['postBy'][ $group_graphql_name ][ 'acfTextField' ] );

		// page assert validation. it must return errors
		$query = sprintf(
			'
			query getPageById( $pageId: Int ) {
				pageBy( pageId: $pageId ) {
					id
					%s {
						fieldGroupName
						%s
					}
				}
			}
			',
			$group_graphql_name,
			'acfTextField'
		);

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'pageId' => $page_id,
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertSame( 'Cannot query field "pageCptFields" on type "Page".', $actual['errors'][0]['message'] );

		// custom post type assert validation
		$query = sprintf(
			'
			query getAcfCptById( $acfCptId: Int ) {
				acfCptBy( acfCptId: $acfCptId ) {
					id
					%s {
						fieldGroupName
						%s
					}
				}
			}
			',
			$group_graphql_name,
			'acfTextField'
		);

		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'acfCptId' => $cpt_id,
				],
			]
		);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_text_3, $actual['data']['acfCptBy'][ $group_graphql_name ][ 'acfTextField' ] );

		acf_remove_local_field_group( $group_key );

	}

}

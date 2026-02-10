<?php

/**
 * Class LocationRulesTest
 *
 * These tests are intended to test ACF Field groups that are assigned locations
 * but not explicitly assigned "graphql_types"
 */
class LocationRulesTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function setUp(): void {
		$this->clearSchema();
		parent::setUp();

	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testFieldGroupAssignedToPostTypeWithoutGraphqlTypesFieldShowsInSchema() {

		$this->register_acf_field([], [
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'postFieldsTest',
		]);

		$query = '
		{
		  posts {
		    nodes {
		      id
		      title
		      postFieldsTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		acf_remove_local_field_group( $this->group_key );

	}

	public function testFieldGroupAssignedToTagWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'tagFieldsTest',
			'location'              => [
				[
					[
						'param'    => 'taxonomy',
						'operator' => '==',
						'value'    => 'post_tag',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'tagFieldsTest',
		]);


		$query = '
		{
		  tags {
		    nodes {
		      id
		      name
		      tagFieldsTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		acf_remove_local_field_group( 'tagFieldsTest' );

	}

	public function testFieldGroupAssignedToCategoryWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'categoryFieldTest',
			'location'              => [
				[
					[
						'param'    => 'taxonomy',
						'operator' => '==',
						'value'    => 'category',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'categoryFieldTest',
		]);


		$query = '
		{
		  categories {
		    nodes {
		      id
		      name
		      categoryFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		acf_remove_local_field_group( 'tagFieldsTest' );

	}

	public function testFieldGroupAssignedToCommentsWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'commentFieldTest',
			'location'              => [
				[
					[
						'param'    => 'comment',
						'operator' => '==',
						'value'    => 'all',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'commentFieldTest',
		]);


		$query = '
		{
		  comments {
		    nodes {
		      id
		      commentFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		acf_remove_local_field_group( 'commentFieldTest' );

	}

	public function testFieldGroupAssignedToMenusWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'menuFieldTest',
			'location'              => [
				[
					[
						'param'    => 'nav_menu',
						'operator' => '==',
						'value'    => 'all',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'menuFieldTest',
		]);


		$query = '
		{
		  menus {
		    nodes {
		      id
		      menuFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

	}

	public function testFieldGroupAssignedToMenuItemsWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'menuItemFieldTest',
			'location'              => [
				[
					[
						'param'    => 'nav_menu_item',
						'operator' => '==',
						'value'    => 'all',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'menuItemFieldTest',
		]);


		$query = '
		{
		  menuItems {
		    nodes {
		      id
		      menuItemFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual, 'If this fails, see https://github.com/wp-graphql/wp-graphql/issues/1844' );

		acf_remove_local_field_group( 'menuItemFieldTest' );

	}

	public function testFieldGroupAssignedToMediaWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'mediaItemFieldTest',
			'location'              => [
				[
					[
						'param'    => 'attachment',
						'operator' => '==',
						'value'    => 'all',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'mediaItemFieldTest',
		]);


		$query = '
		{
		  mediaItems {
		    nodes {
		      id
		      mediaItemFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		acf_remove_local_field_group( 'mediaItemFieldTest' );

	}

	public function testFieldGroupAssignedToIndividualPostWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'singlePostFieldTest',
			'location'              => [
				[
					[
						'param'    => 'post',
						'operator' => '==',
						'value'    => $this->published_post->ID,
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'singlePostFieldTest',
		]);


		$query = '
		{
		  posts {
		    nodes {
		      id
		      singlePostFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$query = '
		{
		  comments {
		    nodes {
		      id
		      singlePostFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

		acf_remove_local_field_group( 'singlePostFieldTest' );

	}

	public function testFieldGroupAssignedToUserEditWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'userEditFieldTest',
			'location'              => [
				[
					[
						'param'    => 'user_form',
						'operator' => '==',
						'value'    => 'edit',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'userEditFieldTest',
		]);


		$query = '
		{
		  users {
		    nodes {
		      id
		      userEditFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$query = '
		{
		  comments {
		    nodes {
		      id
		      userEditFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

		acf_remove_local_field_group( 'userEditFieldTest' );

	}

	public function testFieldGroupAssignedToUserRegisterWithoutGraphqlTypesFieldShowsInSchema() {

		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'userRegisterFieldTest',
			'location'              => [
				[
					[
						'param'    => 'user_form',
						'operator' => '==',
						'value'    => 'register',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'userRegisterFieldTest',
		]);


		$query = '
		{
		  users {
		    nodes {
		      id
		      userRegisterFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$query = '
		{
		  comments {
		    nodes {
		      id
		      userRegisterFieldTest {
		        fieldGroupName
		      }
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query
		]);

		codecept_debug( $actual );

		$this->assertArrayHasKey( 'errors', $actual );

		acf_remove_local_field_group( 'userRegisterFieldTest' );

	}

	public function testNoErrorIfFieldGroupIsAssignedToOrphanedOptionsPage() {

		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'Only test for ACF Pro' );
		}

		$interfaces = [];

		add_filter( 'wpgraphql/acf/get_all_possible_types/interfaces', function( $default_interfaces ) use ( &$interfaces ) {
			$interfaces = $default_interfaces;
			return $default_interfaces;
		} );

		$options_pages = acf_get_options_pages();

		// Register an ACF Field Group to an orphaned options page location
		$this->register_acf_field_group([
			'key' => 'orphanedOptionsPage',
			'location'              => [
				[
					[
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'non_existing_page',
					],
				],
			],
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'orphanedOptionsPageTest',
		]);

		$all_graphql_types = \WPGraphQL\Acf\Utils::get_all_graphql_types();

		codecept_debug( [
			'$interfaces' => $interfaces,
		]);

		$this->assertTrue( ! array_key_exists( 'AcfOptionsPage', $interfaces ) );

		// Assert there is no options page
		$this->assertTrue( empty( $options_pages ) );

		// Assert that we get an array of graphql_types even if options pages aren't registered
		$this->assertTrue( is_array( $all_graphql_types ) && ! empty( $all_graphql_types ) );

		acf_add_options_page(array(
			'page_title'    => __('Theme General Settings'),
			'menu_title'    => __('Theme Settings'),
			'menu_slug'     => 'theme-general-settings',
			'capability'    => 'edit_posts',
			'redirect'      => false,
			'show_in_graphql' => 1
		));

		$this->clearSchema();
		$options_pages = acf_get_options_pages();
		$all_graphql_types = \WPGraphQL\Acf\Utils::get_all_graphql_types();

		$this->assertTrue( array_key_exists( 'AcfOptionsPage', $interfaces ) );

		// Assert there is no options page
		$this->assertTrue( ! empty( $options_pages ) );

		// Assert that we get an array of graphql_types even if options pages aren't registered
		$this->assertTrue( is_array( $all_graphql_types ) && ! empty( $all_graphql_types ) );

	}

//	public function testFieldGroupAssignedToAcfOptionsPageShowsInSchema() {
//
//		$this->markTestIncomplete();
//		/**
//		 * Register a field group to a specific post type
//		 */
//		$this->register_acf_field_group([
//			'key' => 'settingsFieldsTest',
//			'location'              => [
//				[
//					[
//						'param'    => 'options_page',
//						'operator' => '==',
//						'value'    => 'theme-general-settings',
//					],
//				],
//				[
//					[
//						'param'    => 'options_page',
//						'operator' => '==',
//						'value'    => 'theme-footer-settings',
//					],
//				],
//			],
//			'show_in_graphql'       => 1,
//			'graphql_field_name'    => 'settingsFieldsTest',
//		]);
//
//		$this->register_acf_field([
//			'parent' => 'settingsFieldsTest',
//			'name' => 'text',
//			'key' => 'settingsFieldTextField'
//		]);
//
//		$expected = 'this is a test value for the settings field';
//
//		update_field( 'settingsFieldTextField', $expected, 'option' );
//
//		acf_add_options_page(array(
//			'page_title' 	=> 'Theme General Settings',
//			'menu_title'	=> 'Theme Settings',
//			'menu_slug' 	=> 'theme-general-settings',
//			'capability'	=> 'edit_posts',
//			'redirect'		=> false,
//			'show_in_graphql' => true,
//			'graphql_field_name' => 'ThemeGeneralSettings',
//		));
//
//		acf_add_options_sub_page(array(
//			'page_title' 	=> 'Theme Header Settings',
//			'menu_title'	=> 'Header',
//			'parent_slug'	=> 'theme-general-settings',
//			'menu_slug' 	=> 'theme-header-settings',
//			'show_in_graphql' => true,
//			'graphql_field_name' => 'ThemeHeaderSettings',
//		));
//
//		acf_add_options_sub_page(array(
//			'page_title' 	=> 'Theme Footer Settings',
//			'menu_title'	=> 'Footer',
//			'parent_slug'	=> 'theme-general-settings',
//			'menu_slug' 	=> 'theme-footer-settings',
//			'show_in_graphql' => true,
//			'graphql_field_name' => 'ThemeFooterSettings',
//		));
//
//
//		$query = '
//		{
//		  themeGeneralSettings {
//		    settingsFieldsTest {
//		      __typename
//		      text
//		    }
//		  }
//		  themeFooterSettings {
//		    settingsFieldsTest {
//		      __typename
//		      text
//		    }
//		  }
//		}
//		';
//
//		$actual = graphql([
//			'query' => $query
//		]);
//
//		codecept_debug( $actual );
//
//		$this->assertArrayNotHasKey( 'errors', $actual );
//		$this->assertSame( $expected, $actual['data']['themeGeneralSettings']['settingsFieldsTest']['text'] );
//
//		acf_remove_local_field_group( 'settingsFieldsTest' );
//
//	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql-acf/issues/251
	 * @throws Exception
	 */
	public function testOnlyFieldGroupsSetToShowInGraphqlAreInTheSchema() {



		/**
		 * Register a field group to a specific post type
		 */
		$this->register_acf_field_group([
			'key' => 'doNotShowInGraphQL',
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					],
				],
			],
			'show_in_graphql'       => false,
			'graphql_field_name'    => 'doNotShowInGraphQL',
			'graphql_types'         => [ 'Post' ]
		]);

		$this->register_acf_field_group([
			'key' => 'showInGraphqlTest',
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					],
				],
			],
			'show_in_graphql'       => true,
			'graphql_field_name'    => 'showInGraphqlTest',
			'graphql_types'         => [ 'Post' ]
		]);

		$query = '
		query GetPost($id:ID!) {
		  post(id:$id idType:DATABASE_ID) {
		    databaseId
		    doNotShowInGraphQL {
		      __typename
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $this->published_post->ID,
			],
		]);

		codecept_debug( $actual );

		// doNotShowInGraphQL should not be in the Schema, so this should be an error
		$this->assertArrayHasKey( 'errors', $actual );

		$query = '
		query GetPost($id:ID!) {
		  post(id:$id idType:DATABASE_ID) {
		    databaseId
		    showInGraphqlTest {
		      __typename
		    }
		  }
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'id' => $this->published_post->ID,
			],
		]);

		codecept_debug( $actual );

		// showInGraphqlTest should be queryable against the Post type in the Schema
		$this->assertSame( $this->published_post->ID, $actual['data']['post']['databaseId'] );
		$this->assertSame( 'ShowInGraphqlTest', $actual['data']['post']['showInGraphqlTest']['__typename'] );

		acf_remove_local_field_group( 'doNotShowInGraphQL' );
		acf_remove_local_field_group( 'showInGraphqlTest' );

	}

}

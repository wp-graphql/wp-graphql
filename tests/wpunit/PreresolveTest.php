<?php

class PreresolveTest extends \Codeception\TestCase\WPTestCase {

	public function tearDown(): void {
		parent::tearDown();
		WPGraphQL::clear_schema();
	}

	public function testReplaceString() {
		$pageId = $this->factory()->post->create([
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_title' => 'Test title',
		]);

		add_filter( 'graphql_pre_resolve_field', function( $nil, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver ) {
			if ( 'title' === $field_key ) {
				return 'Replaced title';
			}

			return $nil;
		}, 10, 9);

		$query = '
		query Page( $pageId: ID! ) {
		  page( id: $pageId, idType: DATABASE_ID ) {
			title
		  }
 		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'pageId' => $pageId,
			],
		]);

		codecept_debug( $actual );

		/**
		 * Assert that the page is showing as the front page
		 */
		$this->assertArrayNotHasKey( 'errors', $actual, print_r( $actual, true ) );
		$this->assertEquals( $actual['data']['page']['title'], 'Replaced title' );
	}

	/**
	 * Null should be like any other value
	 */
	public function testPresolveToNull() {
		$pageId = $this->factory()->post->create([
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_title' => 'Test title',
			'post_content' => 'Test content'
		]);

		add_filter( 'graphql_pre_resolve_field', function( $nil, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver ) {
			if ( 'page' === $field_key ) {
				return null;
			}

			return $nil;
		}, 10, 9);

		$query = '
		query Page( $pageId: ID! ) {
		  page( id: $pageId, idType: DATABASE_ID ) {
			content
		  }
 		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'pageId' => $pageId,
			],
		]);

		codecept_debug( $actual );

		/**
		 * Assert that the page is showing as the front page
		 */
		$this->assertArrayNotHasKey( 'errors', $actual, print_r( $actual, true ) );
		$this->assertEquals( $actual['data']['page'], null );
	}

	/**
	 * False should be like any other value
	 */
	public function testPreresolveToFalse() {
		$pageId = $this->factory()->post->create([
			'post_status' => 'publish',
			'post_type' => 'page',
			'post_title' => 'Test Front Page'
		]);


		/**
		 * Set the page as the front page
		 */
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $pageId );

		add_filter( 'graphql_pre_resolve_field', function( $nil, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver ) {
			if ( 'isFrontPage' === $field_key ) {
				return false;
			}

			return $nil;
		}, 10, 9);

		$query = '
		query Page( $pageId: ID! ) {
		  page( id: $pageId, idType: DATABASE_ID ) {
			title
			isFrontPage
		  }
 		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'pageId' => $pageId,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual, print_r( $actual, true ) );

		/**
		 * Preresolved correctly to false
		 */
		$this->assertFalse( $actual['data']['page']['isFrontPage'] );

	}

}

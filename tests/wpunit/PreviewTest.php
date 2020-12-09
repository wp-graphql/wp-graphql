<?php

class PreviewTest extends \Codeception\TestCase\WPTestCase {

	public $post;
	public $preview;
	public $editor;
	public $category;
	public $featured_image;
	public $admin;

	public function setUp(): void {

		$this->admin = $this->factory()->user->create([
			'role' => 'administrator'
		]);

		$this->editor = $this->factory()->user->create([
			'role' => 'editor'
		]);

		$this->category = $this->factory()->term->create( [
			'taxonomy' => 'category',
			'name'     => 'cat test' . uniqid()
		] );

		$this->post = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Published Post',
			'post_content' => 'Published Content',
			'post_author' => $this->admin,
		]);

		wp_set_object_terms( $this->post, $this->category, 'category', false );

		$filename      = ( WPGRAPHQL_PLUGIN_DIR . '/tests/_data/images/test.png' );
		$this->featured_image = $this->factory()->attachment->create_upload_object( $filename );
		update_post_meta( $this->post, '_thumbnail_id', $this->featured_image );


		$this->preview = $this->factory()->post->create([
			'post_status' => 'inherit',
			'post_title' => 'Preview Post',
			'post_content' => 'Preview Content',
			'post_type' => 'revision',
			'post_parent' => $this->post,
			'post_author' => $this->editor,
			'post_date' => date( "Y-m-d H:i:s", strtotime( 'now' ) ),
		]);

		WPGraphQL::clear_schema();
		parent::setUp();

	}

	public function tearDown(): void {
		parent::tearDown();
		wp_delete_post( $this->post, true );
		wp_delete_post( $this->preview, true );
		wp_delete_attachment( $this->featured_image, true );
		wp_delete_user( $this->admin );
		wp_delete_user( $this->editor );
		wp_delete_term( $this->category, 'category' );
		WPGraphQL::clear_schema();
	}

	public function get_query() {

		return '
		query GetPostAndPreview( $id: ID! ) {
		  post( id: $id idType: DATABASE_ID ) {
		    ...PostFields
		    preview {
		      node {
		        ...PostFields
		      }
		    }
		  }
		}
		fragment PostFields on Post {
		  __typename
		  id
		  title
		  content
		  author {
		    node {
		      databaseId
		    }
		  }
		  categories {
		    nodes {
		      databaseId
		    }
		  }
		  tags {
		    nodes {
		      databaseId
		    }
		  }
		  featuredImage {
		    node {
		      databaseId
		    }
		  }
		}
		';

	}

	public function testPreviewReturnsNullForPublicRequest() {

		$actual = graphql([ 'query' => $this->get_query(), 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['post']['preview'] );

	}

	public function testReturnsPreviewNodeForAdminRequest() {

		wp_set_current_user( $this->admin );

		$actual = graphql([ 'query' => $this->get_query(), 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['post']['preview'] );

		add_filter( 'wp_revisions_to_keep', function() {
			return 0;
		} );

		$actual = graphql([ 'query' => $this->get_query(), 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		add_filter( 'wp_revisions_to_keep', function( $default ) {
			return $default;
		} );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNotNull( $actual['data']['post']['preview'] );

	}

	public function testPreviewAuthorMatchesPublishedAuthor() {

		wp_set_current_user( $this->admin );

		$actual = graphql([ 'query' => $this->get_query(), 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->admin, $actual['data']['post']['preview']['node']['author']['node']['databaseId'] );
		$this->assertSame( $this->admin, $actual['data']['post']['author']['node']['databaseId'] );
		$this->assertSame( $this->admin, $actual['data']['post']['preview']['node']['author']['node']['databaseId'] );

	}

	public function testPreviewTermsMatchPublishedTerms() {

		wp_set_current_user( $this->admin );

		$actual = graphql([ 'query' => $this->get_query(), 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		codecept_debug( $this->category );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->category, $actual['data']['post']['preview']['node']['categories']['nodes'][0]['databaseId'] );
		$this->assertSame( $this->category, $actual['data']['post']['categories']['nodes'][0]['databaseId'] );
		$this->assertSame( $actual['data']['post']['categories'], $actual['data']['post']['preview']['node']['categories'] );
		$this->assertSame( $actual['data']['post']['tags'], $actual['data']['post']['preview']['node']['tags'] );
	}

	public function testPreviewFeaturedImageMatchesPublishedFeaturedImage() {

		wp_set_current_user( $this->admin );

		$actual = graphql([ 'query' => $this->get_query(), 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		codecept_debug( $this->category );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $this->featured_image, $actual['data']['post']['preview']['node']['featuredImage']['node']['databaseId'] );
		$this->assertSame( $this->featured_image, $actual['data']['post']['featuredImage']['node']['databaseId'] );
		$this->assertSame( $actual['data']['post']['featuredImage'], $actual['data']['post']['preview']['node']['featuredImage'] );
	}

	public function testMetaOnPreview() {

		WPGraphQL::clear_schema();
		$meta_key = 'metaKey';
		$meta_value = 'metaValue...';
		update_post_meta( $this->post, $meta_key, $meta_value );

		register_graphql_field( 'Post', $meta_key, [
			'type' => 'String',
			'resolve' => function( $post ) use ( $meta_key ) {
				return get_post_meta( $post->ID, $meta_key, true );
			}
		] );

		wp_set_current_user( $this->admin );

		codecept_debug( get_post_meta( $this->preview, $meta_key, true ) );

		$this->assertSame( $meta_value, get_post_meta( $this->post, $meta_key, true ) );
		$this->assertEmpty( get_post_meta( $this->preview, $meta_key, true ) );

		$actual = graphql([ 'query' => '
		query GET_POST( $id:ID! ) {
		 post(id:$id idType: DATABASE_ID) {
		   databaseId
		   metaKey
		   title
		   content 
		   author {
		     node {
		       id
		     }
		   }
		   preview {
		     node {
		       databaseId
		       metaKey
		       title
		       content 
		      author {
		         node {
		           id
		         }
		       }
		     }
		   }
		 }
		}
		', 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		codecept_debug( get_post_meta( $this->preview, $meta_key, true ) );

		$this->assertSame( $meta_value,  $actual['data']['post']['metaKey'] );
		$this->assertSame( $meta_value,  $actual['data']['post']['preview']['node']['metaKey'] );

		$this->assertSame( $meta_value, get_post_meta( $this->post, $meta_key, true ) );
		$this->assertEmpty( get_post_meta( $this->preview, $meta_key, true ) );

		WPGraphQL::clear_schema();

	}

	/**
	 * In this test we want to test that meta for revisions is by default resolved by getting the meta
	 * from the parent node, but using the "graphql_resolve_revision_meta_from_parent" can override that.
	 *
	 * For example, plugins such as ACF that revise meta can use this filter to tell WPGraphQL
	 * to resolve meta from the revision instead of the revision parent.
	 *
	 * This tests that the resolving only affects WPGraphQL requests and not get_post_meta requests
	 * before/after GraphQL resolution.
	 *
	 * @throws Exception
	 */
	public function testRevisedMetaOnPreview() {

		WPGraphQL::clear_schema();
		$published_meta_key = 'publishedMetaKey';
		$published_meta_value = 'published metaValue...';

		// Store meta on the published post
		update_post_meta( $this->post, $published_meta_key, $published_meta_value );
		codecept_debug( get_post_meta( $this->post, $published_meta_key, $published_meta_value ) );

		// Register field for the published meta
		register_graphql_field( 'Post', $published_meta_key, [
			'type' => 'String',
			'resolve' => function( $post ) use ( $published_meta_key ) {
				return get_post_meta( $post->ID, $published_meta_key, true );
			}
		] );

		// Store meta on the preview post
		$revised_meta_key = 'revisedMetaKey';
		$revised_meta_value = 'revised metaValue...';
		codecept_debug( add_metadata( 'post', $this->preview, $revised_meta_key, $revised_meta_value ) );
		codecept_debug( get_post_meta( $this->preview, $revised_meta_key, true ) );

		// Register field for the revised meta
		register_graphql_field( 'Post', $revised_meta_key, [
			'type' => 'String',
			'resolve' => function( $post ) use ( $revised_meta_key ) {
				return get_post_meta( $post->ID, $revised_meta_key, true );
			}
		] );

		// Tell the resolver to resolve using the revision ID instead of the
		// Parent ID for the revisedMetaKey. This means that for the meta_key "revisedMetaKey"
		// WPGraphQL will look for the meta value on the revision post's meta instead of
		// looking for it in the parent's meta, which is default WPGraphQL behavior.
		add_filter( 'graphql_resolve_revision_meta_from_parent', function( $filter_revision_meta, $object_id, $meta_key, $single ) {
			if ( $meta_key === 'revisedMetaKey' ) {
				return false;
			}
			return $filter_revision_meta;
		}, 10, 4 );

		wp_set_current_user( $this->admin );

		codecept_debug( get_post_meta( $this->preview, $published_meta_key, true ) );
		codecept_debug( get_post_meta( $this->preview, $revised_meta_key, true ) );

		$this->assertSame( $published_meta_value, get_post_meta( $this->post, $published_meta_key, true ) );
		$this->assertSame( $revised_meta_value, get_post_meta( $this->preview, $revised_meta_key, true ) );

		$actual = graphql([ 'query' => '
		query GET_POST( $id:ID! ) {
		 post(id:$id idType: DATABASE_ID) {
		   databaseId
		   revisedMetaKey
		   publishedMetaKey
		   title
		   content 
		   author {
		     node {
		       id
		     }
		   }
		   preview {
		     node {
		       databaseId
		       publishedMetaKey
		       revisedMetaKey
		       title
		       content 
		      author {
		         node {
		           id
		         }
		       }
		     }
		   }
		 }
		}
		', 'variables' => [
			'id' => $this->post,
		] ]);

		codecept_debug( $actual );

		codecept_debug( get_post_meta( $this->preview, $revised_meta_key, true ) );

		$this->assertSame( $published_meta_value,  $actual['data']['post']['publishedMetaKey'] );
		$this->assertSame( $published_meta_value,  $actual['data']['post']['preview']['node']['publishedMetaKey'] );

		$this->assertSame( $published_meta_value, get_post_meta( $this->post, $published_meta_key, true ) );
		$this->assertEmpty( get_post_meta( $this->preview, $published_meta_key, true ) );

		$this->assertSame( $revised_meta_value,  $actual['data']['post']['preview']['node']['revisedMetaKey'] );
		$this->assertSame( $revised_meta_value, get_post_meta( $this->preview, $revised_meta_key, true ) );

		WPGraphQL::clear_schema();

	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/1615#issuecomment-741817101
	 */
	public function testMultipleMetaFieldsResolveOnPreviewNodes() {

		WPGraphQL::clear_schema();
		$published_meta_key = 'publishedMetaKey';
		$published_meta_value = 'published metaValue...';

		// Store meta on the published post
		update_post_meta( $this->post, $published_meta_key, $published_meta_value );
		codecept_debug( get_post_meta( $this->post, $published_meta_key, $published_meta_value ) );

		// Register field for the published meta
		register_graphql_field( 'Post', $published_meta_key, [
			'type' => 'String',
			'resolve' => function( $post ) use ( $published_meta_key ) {
				return get_post_meta( $post->ID, $published_meta_key, true );
			}
		] );

		wp_set_current_user( $this->admin );

		// Asking for the meta of a revision directly using the get_post_meta function should
		// get the meta from the revision ID, which should be empty since we didn't set any
		// value
		$this->assertEmpty( get_post_meta( $this->preview, $published_meta_key, true ) );

		$actual = graphql([
			'query' => '
				query GET_POST( $id:ID! ) {
				 post(id:$id idType: DATABASE_ID) {
				   databaseId
				   enclosure
				   publishedMetaKey
				   title
				   content 
				   preview {
				     node {
				       databaseId
				       enclosure
				       publishedMetaKey
				       title
				       content 
				     }
				   }
				 }
				}
			',
			'variables' => [
				'id' => $this->post,
			],
		]);

		codecept_debug( $actual );

	    $this->assertSame( $published_meta_value, $actual['data']['post']['preview']['node']['publishedMetaKey'] );

		// Asking for the meta of a revision directly using the get_post_meta function should
		// get the meta from the revision ID, which should be empty since we didn't set any
		// value
		$this->assertEmpty( get_post_meta( $this->preview, $published_meta_key, true ) );

	}

}

<?php

/**
 * WPGraphQL Test Relay Mutation Schema
 * This tests the mutation schema(s) to make sure they play nice with the
 * Relay Spec (https://facebook.github.io/relay/graphql/mutations.htm)
 *
 * @package WPGraphQL
 */
class WP_GraphQL_Test_Media_Item_Schema extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * This tests to make sure the mutation schema follows the Relay spec
	 *
	 * @see: https://facebook.github.io/relay/graphql/mutations.htm#sec-Introspection
	 */
	public function testRelayMutationSchema() {

		$introspection_query = '
		query testMediaItemSchema{
		  __type(name: "mediaItem") {
		    name
		    kind
		    description
		    fields(includeDeprecated: true) {
		      name
		      isDeprecated
		      deprecationReason
		      type {
		        name
		        kind
		        ... on __Type {
		          fields {
		            name
		            isDeprecated
		            deprecationReason
		          }
		        }
		      }
		    }
		  }
		}
		';

		/**
		 * Run the introspection query
		 */
		$actual = do_graphql_request( $introspection_query, 'testMediaItemSchema' );

		/**
		 * Get the mutationType fields out of the response tree
		 */
		$media_item_type = ! empty( $actual['data']['__type'] ) ? $actual['data']['__type'] : null;

		/**
		 * Verify that the $mutation_type_fields is not empty
		 */
		$this->assertNotEmpty( $media_item_type );

		$this->assertEquals( $media_item_type['name'], 'mediaItem' );
		$this->assertEquals( $media_item_type['kind'], 'OBJECT' );

		/**
		 * The description might change, we just want to test that the type has a description. . .we might want to
		 * add a specific test to make sure the description matches exactly, but I believe documentation will be
		 * somewhat fluidly updated so don't want to fail tests because descriptions are evolving...
		 */
		$this->assertNotEmpty( $media_item_type['description'] );
		$this->assertNotEmpty( $media_item_type['fields'] );

		/**
		 * @todo: Might be good to rethink this a bit?
		 */
		$expected = json_decode( '
		[
	        {
	          "name": "altText",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "author",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "user",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "avatar",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capKey",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capabilities",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "comments",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "description",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "email",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "extraCapabilities",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "firstName",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "id",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "last_name",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "locale",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "mediaItems",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "name",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "nickname",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "pages",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "posts",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "registeredDate",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "roles",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "slug",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "url",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "userId",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "username",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "caption",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "commentCount",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "Int",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "commentStatus",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "comments",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "commentsConnection",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "pageInfo",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "edges",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "content",
	          "isDeprecated": true,
	          "deprecationReason": "Use the description field instead of content",
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "date",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "dateGmt",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "description",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "desiredSlug",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "editLast",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "user",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "avatar",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capKey",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capabilities",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "comments",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "description",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "email",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "extraCapabilities",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "firstName",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "id",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "last_name",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "locale",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "mediaItems",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "name",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "nickname",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "pages",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "posts",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "registeredDate",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "roles",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "slug",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "url",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "userId",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "username",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "editLock",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "mediaItemeditLock",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "editTime",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "user",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "enclosure",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "excerpt",
	          "isDeprecated": true,
	          "deprecationReason": "Use the caption field instead of excerpt",
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "guid",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "id",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": null,
	            "kind": "NON_NULL",
	            "fields": null
	          }
	        },
	        {
	          "name": "link",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "mediaDetails",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "mediaDetails",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "file",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "height",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "meta",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "sizes",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "width",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "mediaItemId",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": null,
	            "kind": "NON_NULL",
	            "fields": null
	          }
	        },
	        {
	          "name": "mediaType",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "menuOrder",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "Int",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "mimeType",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "modified",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "modifiedGmt",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "parent",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "postObjectUnion",
	            "kind": "UNION",
	            "fields": null
	          }
	        },
	        {
	          "name": "pingStatus",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "pinged",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": null,
	            "kind": "LIST",
	            "fields": null
	          }
	        },
	        {
	          "name": "slug",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "sourceUrl",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "status",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "title",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "String",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "toPing",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": null,
	            "kind": "LIST",
	            "fields": null
	          }
	        }
	      ]
		', true );

		$this->assertEquals( $media_item_type['fields'], $expected );

	}

}

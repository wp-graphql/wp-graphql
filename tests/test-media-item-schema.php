<?php

/**
 * WPGraphQL Test Relay Mutation Schema
 * This tests the mutation schema(s) to make sure they play nice with the
 * Relay Spec (https://facebook.github.io/relay/graphql/mutations.htm)
 *
 * @package WPGraphQL
 */
class WP_GraphQL_Test_Media_ITem_Schema extends WP_UnitTestCase {

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
		      description
		      isDeprecated
		      deprecationReason
		      type {
		        name
		        kind
		        ... on __Type {
		          fields {
		            name
		            description
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

		$expected = json_decode( '
		[
	        {
	          "name": "altText",
	          "description": "Alternative text to display when resource is not displayed",
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
	          "description": "The author field will return a queryable User type matching the post&#039;s author.",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "user",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "avatar",
	                "description": "Avatar object for user. The avatar object can be retrieved in different sizes by specifying the size argument.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capKey",
	                "description": "User metadata option name. Usually it will be `wp_capabilities`.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capabilities",
	                "description": "This field is the id of the user. The id of the user matches WP_User->ID field and the value in the ID column for the `users` table in SQL.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "comments",
	                "description": "A collection of comment objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "description",
	                "description": "Description of the user.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "email",
	                "description": "Email of the user. This is equivalent to the WP_User->user_email property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "extraCapabilities",
	                "description": "A complete list of capabilities including capabilities inherited from a role. This is equivalent to the array keys of WP_User-&gt;allcaps.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "firstName",
	                "description": "First name of the user. This is equivalent to the WP_User-&gt;user_first_name property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "id",
	                "description": "The globally unique identifier for the user",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "last_name",
	                "description": "Last name of the user. This is equivalent to the WP_User-&gt;user_last_name property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "locale",
	                "description": "The preferred language locale set for the user. Value derived from get_user_locale().",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "mediaItems",
	                "description": "A collection of mediaItems objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "name",
	                "description": "Display name of the user. This is equivalent to the WP_User-&gt;dispaly_name property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "nickname",
	                "description": "Nickname of the user.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "pages",
	                "description": "A collection of pages objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "posts",
	                "description": "A collection of posts objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "registeredDate",
	                "description": "The date the user registered or was created. The field follows a full ISO8601 date string format.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "roles",
	                "description": "A list of roles that the user has. Roles can be used for querying for certain types of users, but should not be used in permissions checks.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "slug",
	                "description": "The slug for the user. This field is equivalent to WP_User-&gt;user_nicename",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "url",
	                "description": "A website url that is associated with the user.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "userId",
	                "description": "The Id of the user. Equivelant to WP_User->ID",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "username",
	                "description": "Username for the user. This field is equivalent to WP_User->user_login.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "caption",
	          "description": "The caption for the resource",
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
	          "description": "The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatability.",
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
	          "description": "Whether the comments are open or closed for this particular post.",
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
	          "description": "A collection of comment objects",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "commentsConnection",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "pageInfo",
	                "description": "Information to aid in pagination.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "edges",
	                "description": "Information to aid in pagination",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "content",
	          "description": "The content of the post. This is currently just the raw content. An amendment to support rendered content needs to be made.",
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
	          "description": "Post publishing date.",
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
	          "description": "The publishing date set in GMT.",
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
	          "description": "Description of the image (stored as post_content)",
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
	          "description": "The desired slug of the post",
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
	          "description": "The user that most recently edited the object",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "user",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "avatar",
	                "description": "Avatar object for user. The avatar object can be retrieved in different sizes by specifying the size argument.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capKey",
	                "description": "User metadata option name. Usually it will be `wp_capabilities`.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "capabilities",
	                "description": "This field is the id of the user. The id of the user matches WP_User->ID field and the value in the ID column for the `users` table in SQL.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "comments",
	                "description": "A collection of comment objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "description",
	                "description": "Description of the user.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "email",
	                "description": "Email of the user. This is equivalent to the WP_User->user_email property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "extraCapabilities",
	                "description": "A complete list of capabilities including capabilities inherited from a role. This is equivalent to the array keys of WP_User-&gt;allcaps.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "firstName",
	                "description": "First name of the user. This is equivalent to the WP_User-&gt;user_first_name property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "id",
	                "description": "The globally unique identifier for the user",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "last_name",
	                "description": "Last name of the user. This is equivalent to the WP_User-&gt;user_last_name property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "locale",
	                "description": "The preferred language locale set for the user. Value derived from get_user_locale().",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "mediaItems",
	                "description": "A collection of mediaItems objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "name",
	                "description": "Display name of the user. This is equivalent to the WP_User-&gt;dispaly_name property.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "nickname",
	                "description": "Nickname of the user.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "pages",
	                "description": "A collection of pages objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "posts",
	                "description": "A collection of posts objects",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "registeredDate",
	                "description": "The date the user registered or was created. The field follows a full ISO8601 date string format.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "roles",
	                "description": "A list of roles that the user has. Roles can be used for querying for certain types of users, but should not be used in permissions checks.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "slug",
	                "description": "The slug for the user. This field is equivalent to WP_User-&gt;user_nicename",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "url",
	                "description": "A website url that is associated with the user.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "userId",
	                "description": "The Id of the user. Equivelant to WP_User->ID",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "username",
	                "description": "Username for the user. This field is equivalent to WP_User->user_login.",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "editLock",
	          "description": "If a user has edited the object within the past 15 seconds, this will return the user and the time they last edited. Null if the edit lock doesn\'t exist or is greater than 15 seconds",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "mediaItemeditLock",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "editTime",
	                "description": "The time when the object was last edited",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "user",
	                "description": "The user that most recently edited the object",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "enclosure",
	          "description": "The RSS enclosure for the object",
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
	          "description": "The excerpt of the post. This is currently just the raw excerpt. An amendment to support rendered excerpts needs to be made.",
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
	          "description": "The global unique identifier for this post. This currently matches the value stored in WP_Post-&gt;guid and the guid column in the `post_objects` database table.",
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
	          "description": "The globally unique ID for the object",
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
	          "description": "The desired slug of the post",
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
	          "description": "Details about the mediaItem",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "mediaDetails",
	            "kind": "OBJECT",
	            "fields": [
	              {
	                "name": "file",
	                "description": "The height of the mediaItem",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "height",
	                "description": "The height of the mediaItem",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "meta",
	                "description": null,
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "sizes",
	                "description": "The available sizes of the mediaItem",
	                "isDeprecated": false,
	                "deprecationReason": null
	              },
	              {
	                "name": "width",
	                "description": "The width of the mediaItem",
	                "isDeprecated": false,
	                "deprecationReason": null
	              }
	            ]
	          }
	        },
	        {
	          "name": "mediaItemId",
	          "description": "The id field matches the WP_Post-&gt;ID field.",
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
	          "description": "Type of resource",
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
	          "description": "A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.",
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
	          "description": "The mime type of the mediaItem",
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
	          "description": "The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.",
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
	          "description": "The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.",
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
	          "description": "The parent of the object. The parent object can be of various types",
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
	          "description": "Whether the pings are open or closed for this particular post.",
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
	          "description": "URLs that have been pinged.",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "Boolean",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        },
	        {
	          "name": "slug",
	          "description": "The uri slug for the post. This is equivalent to the WP_Post-&gt;post_name field and the post_name column in the database for the `post_objects` table.",
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
	          "description": "Url of the mediaItem",
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
	          "description": "The current status of the object",
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
	          "description": "The title of the post. This is currently just the raw title. An amendment to support rendered title needs to be made.",
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
	          "description": "URLs queued to be pinged.",
	          "isDeprecated": false,
	          "deprecationReason": null,
	          "type": {
	            "name": "Boolean",
	            "kind": "SCALAR",
	            "fields": null
	          }
	        }
	      ]
		', true );

		$this->assertEquals( $media_item_type['fields'], $expected );

	}

}

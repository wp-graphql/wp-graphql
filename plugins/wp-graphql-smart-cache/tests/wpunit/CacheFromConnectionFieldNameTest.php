<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Collection;
use WPGraphQL\SmartCache\Storage\Ephemeral;
use GraphQLRelay\Relay;

class CacheFromConnectionFieldNameTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		// clear schema so that the register connection works
		// enable graphql cache maps
		add_filter( 'wpgraphql_cache_enable_cache_maps', '__return_true' );

		\WPGraphQL::clear_schema();
		parent::setUp();
	}

	public function tearDown(): void {
        \WPGraphQL::clear_schema();
		parent::tearDown();
	}

    // Register new connection type
    // query that specific connection
    // Verify the collection is updated
    public function testRegisterTypeFieldName() {

        add_action( 'graphql_register_types', function() {
            // A post connection that returns posts for specific author/user name
            register_graphql_connection([
                'fromType' => 'RootQuery',
                'toType' => 'Post',
                'fromFieldName' => 'postsByFoo',
                'connectionArgs' => \WPGraphQL\Type\Connection\PostObjects::get_connection_args(),
                'resolve' => function( $source, $args, $context, $info ) {
                    $resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver( $source, $args, $context, $info, 'post' );
                    $resolver->set_query_arg( 'author_name', 'foo' );
                    return $resolver->get_connection();
                }
            ]);
        }, 99 );

        // Create a user for this test
        $user_id = self::factory()->user->create( [
            'role' => 'editor',
            'user_nicename' => 'foo',
        ] );

        // Create a post by the user
        $post_id = self::factory()->post->create( [
            'post_author' => $user_id,
        ] );

        // Run a query specific to the connection created
        $query = "query my_query {
            postsByFoo {
                nodes {
                    id
                    title
                    author {
                        node {
                            slug
                        }
                    }
                }
            }
        }";
        $results = graphql([ 'query' => $query ]);
        $this->assertEquals( 'foo', $results['data']['postsByFoo']['nodes'][0]['author']['node']['slug'] );

        $collection = new Collection();
        // The query hash we expect to be stored/mapped
        $request_key = $collection->build_key( null, $query );
        $actual = $collection->get( 'list:post' );

        $this->assertEquals( [ $request_key ], $actual );
    }
}

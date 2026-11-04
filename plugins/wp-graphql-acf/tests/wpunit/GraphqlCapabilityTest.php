<?php

/**
 * Tests for the `graphql_capability` setting on ACF field groups, fields and options pages.
 *
 * The contract:
 * - Unset (or null, or an empty string) is public: nothing changes for existing registrations.
 * - A capability set on a field group, a field or an options page is checked when it resolves.
 *   A viewer without it gets null, plus a RESTRICTED_FIELD debug message. The schema is the same
 *   for every viewer.
 * - The levels compose as AND: a field group's capability gates every field in it, and a field's
 *   own capability is checked in addition.
 * - Each level has a filter that can change the decision.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4275
 */
class GraphqlCapabilityTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * @var \WP_User
	 */
	public $subscriber;

	/**
	 * A suffix that keeps the keys and slugs of each test unique.
	 *
	 * ACF caches fields by key and keeps options pages for the whole request, so a test that
	 * reuses a key or slug from an earlier test would get the earlier test's config.
	 *
	 * @var string
	 */
	protected $suffix;

	/**
	 * Slugs of the options pages the test registered, removed again in tearDown.
	 *
	 * @var string[]
	 */
	protected $options_page_slugs = [];

	public function setUp(): void {
		WPGraphQL::clear_schema();
		parent::setUp();

		$this->subscriber = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		$this->suffix     = str_replace( '.', '', uniqid( '', true ) );
	}

	/**
	 * A key or slug unique to the current test.
	 *
	 * @param string $name The name to make unique.
	 */
	protected function key( string $name ): string {
		return $name . '_' . $this->suffix;
	}

	public function tearDown(): void {
		remove_all_filters( 'wpgraphql/acf/field_group_should_resolve' );
		remove_all_filters( 'wpgraphql/acf/field_should_resolve' );
		remove_all_filters( 'wpgraphql/acf/options_page_should_resolve' );

		// ACF has no function to remove an options page, and keeps them for the whole request.
		if ( function_exists( 'acf_options_page' ) ) {
			foreach ( $this->options_page_slugs as $slug ) {
				unset( acf_options_page()->pages[ $slug ] );
			}
		}
		wp_delete_user( $this->subscriber->ID );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Register a field group on posts with a public text field and an extra field.
	 *
	 * @param array $group_config  Overrides for the field group config.
	 * @param array $secret_config Overrides for the second ("secret") field config.
	 */
	protected function register_group( array $group_config = [], array $secret_config = [] ): void {
		acf_add_local_field_group(
			array_merge(
				[
					'key'                => $this->key( 'group_capability_test' ),
					'title'              => 'Capability Test Group',
					'show_in_graphql'    => true,
					'graphql_field_name' => 'capabilityTestGroup',
					'fields'             => [
						[
							'key'             => $this->key( 'field_capability_test_tagline' ),
							'name'            => 'tagline',
							'label'           => 'Tagline',
							'type'            => 'text',
							'show_in_graphql' => true,
						],
						array_merge(
							[
								'key'             => $this->key( 'field_capability_test_secret' ),
								'name'            => 'secret',
								'label'           => 'Secret',
								'type'            => 'text',
								'show_in_graphql' => true,
							],
							$secret_config
						),
					],
					'location'           => [
						[
							[
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'post',
							],
						],
					],
				],
				$group_config
			)
		);

		update_field( $this->key( 'field_capability_test_tagline' ), 'Hello World', $this->published_post->ID );
		update_field( $this->key( 'field_capability_test_secret' ), 'sk_live_secret', $this->published_post->ID );
	}

	/**
	 * Execute a query as the given user, with the debug log of earlier requests discarded.
	 *
	 * graphql_debug() queues its message on an action that is never cleared, so without this a
	 * message from an earlier request in the same test would show up in a later one.
	 *
	 * @param \WP_User|null $user      The user to execute the query as. Null for an anonymous request.
	 * @param string        $query     The query.
	 * @param array         $variables The variables.
	 *
	 * @return array
	 */
	protected function query_as( ?\WP_User $user, string $query, array $variables = [] ): array {
		remove_all_actions( 'graphql_get_debug_log' );
		wp_set_current_user( $user ? $user->ID : 0 );

		return $this->graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);
	}

	protected function post_query(): string {
		return '
		query GetPost( $id: ID! ) {
		  post( id: $id, idType: DATABASE_ID ) {
		    databaseId
		    capabilityTestGroup {
		      tagline
		      secret
		    }
		  }
		}
		';
	}

	/**
	 * The RESTRICTED_FIELD debug entries in a response.
	 *
	 * @param array $response The GraphQL response.
	 *
	 * @return array
	 */
	protected function restricted_debug_entries( array $response ): array {
		$debug = $response['extensions']['debug'] ?? [];

		return array_values(
			array_filter(
				$debug,
				static function ( $entry ) {
					return isset( $entry['type'] ) && 'RESTRICTED_FIELD' === $entry['type'];
				}
			)
		);
	}

	public function unsetCapabilityProvider(): array {
		return [
			'not set'      => [ false ],
			'null'         => [ null ],
			'empty string' => [ '' ],
			'whitespace'   => [ '  ' ],
		];
	}

	/**
	 * Examples 1 and 2 of #4275: no capability (or null, or an empty string) is public.
	 *
	 * @dataProvider unsetCapabilityProvider
	 *
	 * @param mixed $capability The capability, or false to leave it out.
	 */
	public function testUnsetCapabilityResolvesForEveryone( $capability ): void {
		$group_config  = [];
		$secret_config = [];

		if ( false !== $capability ) {
			$group_config['graphql_capability']  = $capability;
			$secret_config['graphql_capability'] = $capability;
		}

		$this->register_group( $group_config, $secret_config );

		foreach ( [ null, $this->subscriber, $this->admin ] as $user ) {
			$actual = $this->query_as( $user, $this->post_query(), [ 'id' => $this->published_post->ID ] );

			self::assertQuerySuccessful(
				$actual,
				[
					$this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ),
					$this->expectedField( 'post.capabilityTestGroup.secret', 'sk_live_secret' ),
				]
			);
			$this->assertSame( [], $this->restricted_debug_entries( $actual ) );
		}
	}

	/**
	 * Example 3 of #4275: a capability on a field nulls only that field.
	 */
	public function testFieldCapabilityNullsTheFieldForUsersWithoutIt(): void {
		$this->register_group( [], [ 'graphql_capability' => 'manage_options' ] );

		foreach ( [ null, $this->subscriber, $this->editor ] as $user ) {
			$actual = $this->query_as( $user, $this->post_query(), [ 'id' => $this->published_post->ID ] );

			self::assertQuerySuccessful(
				$actual,
				[
					$this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ),
					$this->expectedField( 'post.capabilityTestGroup.secret', self::IS_NULL ),
				]
			);

			$entries = $this->restricted_debug_entries( $actual );
			$this->assertCount( 1, $entries );
			$this->assertSame( 'CapabilityTestGroup.secret', $entries[0]['field'] );
			$this->assertSame( 'manage_options', $entries[0]['required_capability'] );
			$this->assertStringContainsString( 'manage_options', $entries[0]['message'] );
			$this->assertStringContainsString( 'CapabilityTestGroup.secret', $entries[0]['message'] );
		}

		$actual = $this->query_as( $this->admin, $this->post_query(), [ 'id' => $this->published_post->ID ] );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ),
				$this->expectedField( 'post.capabilityTestGroup.secret', 'sk_live_secret' ),
			]
		);
		$this->assertSame( [], $this->restricted_debug_entries( $actual ) );
	}

	/**
	 * Example 4 of #4275: a capability on a field group nulls the whole group.
	 */
	public function testFieldGroupCapabilityNullsTheGroupForUsersWithoutIt(): void {
		$this->register_group( [ 'graphql_capability' => 'edit_posts' ] );

		foreach ( [ null, $this->subscriber ] as $user ) {
			$actual = $this->query_as( $user, $this->post_query(), [ 'id' => $this->published_post->ID ] );

			self::assertQuerySuccessful(
				$actual,
				[
					$this->expectedField( 'post.databaseId', $this->published_post->ID ),
					$this->expectedField( 'post.capabilityTestGroup', self::IS_NULL ),
				]
			);

			$entries = $this->restricted_debug_entries( $actual );
			$this->assertCount( 1, $entries );
			$this->assertSame( 'Post.capabilityTestGroup', $entries[0]['field'] );
			$this->assertSame( 'edit_posts', $entries[0]['required_capability'] );
		}

		foreach ( [ $this->author, $this->admin ] as $user ) {
			$actual = $this->query_as( $user, $this->post_query(), [ 'id' => $this->published_post->ID ] );

			self::assertQuerySuccessful(
				$actual,
				[
					$this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ),
					$this->expectedField( 'post.capabilityTestGroup.secret', 'sk_live_secret' ),
				]
			);
			$this->assertSame( [], $this->restricted_debug_entries( $actual ) );
		}
	}

	/**
	 * A field group's capability and a field's capability are both checked (AND).
	 */
	public function testFieldGroupAndFieldCapabilitiesCompose(): void {
		$this->register_group( [ 'graphql_capability' => 'edit_posts' ], [ 'graphql_capability' => 'manage_options' ] );

		// No edit_posts: the group is null, so the field isn't reached.
		$actual = $this->query_as( $this->subscriber, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityTestGroup', self::IS_NULL ) ] );

		// edit_posts without manage_options: the group resolves, the gated field doesn't.
		$actual = $this->query_as( $this->author, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ),
				$this->expectedField( 'post.capabilityTestGroup.secret', self::IS_NULL ),
			]
		);

		// Both: everything resolves.
		$actual = $this->query_as( $this->admin, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ),
				$this->expectedField( 'post.capabilityTestGroup.secret', 'sk_live_secret' ),
			]
		);
	}

	/**
	 * A field can't loosen its group: a field without a capability (or with a capability the
	 * viewer has) in a gated group is still unreachable without the group's capability.
	 */
	public function testFieldCannotLoosenItsFieldGroup(): void {
		$this->register_group( [ 'graphql_capability' => 'manage_options' ], [ 'graphql_capability' => 'read' ] );

		$actual = $this->query_as( $this->subscriber, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityTestGroup', self::IS_NULL ) ] );
	}

	/**
	 * Connection fields (image, relationship, etc.) resolve through their own connection
	 * resolver rather than the field's resolver, and are gated too.
	 */
	public function testFieldCapabilityGatesConnectionFields(): void {
		acf_add_local_field_group(
			[
				'key'                => $this->key( 'group_capability_connection' ),
				'title'              => 'Capability Connection Group',
				'show_in_graphql'    => true,
				'graphql_field_name' => 'capabilityConnectionGroup',
				'fields'             => [
					[
						'key'                => $this->key( 'field_capability_image' ),
						'name'               => 'gated_image',
						'label'              => 'Gated Image',
						'type'               => 'image',
						'show_in_graphql'    => true,
						'graphql_capability' => 'manage_options',
					],
				],
				'location'           => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						],
					],
				],
			]
		);

		update_field( $this->key( 'field_capability_image' ), $this->imageId, $this->published_post->ID );

		$query = '
		query GetPost( $id: ID! ) {
		  post( id: $id, idType: DATABASE_ID ) {
		    capabilityConnectionGroup {
		      gatedImage {
		        node {
		          databaseId
		        }
		      }
		    }
		  }
		}
		';

		$actual = $this->query_as( null, $query, [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityConnectionGroup.gatedImage', self::IS_NULL ) ] );
		$this->assertCount( 1, $this->restricted_debug_entries( $actual ) );

		$actual = $this->query_as( $this->admin, $query, [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityConnectionGroup.gatedImage.node.databaseId', $this->imageId ) ] );
	}

	/**
	 * Fields of ACF blocks resolve for a block rather than a node, and are gated by the field
	 * group's capability too.
	 */
	public function testFieldGroupCapabilityGatesAcfBlocks(): void {
		if ( ! $this->is_acf_pro ) {
			$this->markTestSkipped( 'ACF Blocks need ACF PRO (or Secure Custom Fields)' );
		}

		if ( ! defined( 'WPGRAPHQL_CONTENT_BLOCKS_PLUGIN_DIR' ) && ! class_exists( '\WPGraphQLContentBlocks' ) ) {
			$this->markTestSkipped( 'ACF Blocks are only in the schema with WPGraphQL Content Blocks active' );
		}

		// The block registry persists across tests, so only register the block once.
		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'acf/test-block' ) ) {
			acf_register_block_type(
				[
					'name'       => 'test_block',
					'title'      => 'Test Block',
					'post_types' => [ 'post' ],
				]
			);
		}

		acf_add_local_field_group(
			[
				'key'                => $this->key( 'group_capability_block' ),
				'title'              => 'Capability Block Group',
				'show_in_graphql'    => true,
				'graphql_field_name' => 'capabilityBlockGroup',
				'graphql_capability' => 'edit_posts',
				'graphql_types'      => [ 'AcfTestBlock' ],
				'fields'             => [
					[
						'key'             => $this->key( 'field_capability_block_text' ),
						'name'            => 'block_text',
						'label'           => 'Block Text',
						'type'            => 'text',
						'show_in_graphql' => true,
					],
				],
				'location'           => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/test-block',
						],
					],
				],
			]
		);

		$block_data = wp_json_encode(
			[
				'name' => 'acf/test-block',
				'data' => [
					'block_text'  => 'block value',
					'_block_text' => $this->key( 'field_capability_block_text' ),
				],
				'mode' => 'edit',
			]
		);

		$post_id = self::factory()->post->create(
			[
				'post_status'  => 'publish',
				'post_author'  => $this->admin->ID,
				'post_content' => '<!-- wp:acf/test-block ' . $block_data . ' /-->',
			]
		);

		$query = '
		query GetPost( $id: ID! ) {
		  post( id: $id, idType: DATABASE_ID ) {
		    ... on NodeWithEditorBlocks {
		      editorBlocks {
		        __typename
		        ... on WithAcfCapabilityBlockGroup {
		          capabilityBlockGroup {
		            blockText
		          }
		        }
		      }
		    }
		  }
		}
		';

		$actual = $this->query_as( $this->author, $query, [ 'id' => $post_id ] );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'post.editorBlocks',
					[
						'__typename'           => 'AcfTestBlock',
						'capabilityBlockGroup' => [ 'blockText' => 'block value' ],
					]
				),
			]
		);

		$actual = $this->query_as( null, $query, [ 'id' => $post_id ] );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'post.editorBlocks',
					[
						'__typename'           => 'AcfTestBlock',
						'capabilityBlockGroup' => null,
					]
				),
			]
		);
		$this->assertCount( 1, $this->restricted_debug_entries( $actual ) );

		wp_delete_post( $post_id, true );
	}

	/**
	 * A value that isn't a string (a misconfiguration) fails closed rather than open.
	 */
	public function testInvalidCapabilityValueFailsClosed(): void {
		$this->register_group( [], [ 'graphql_capability' => [ 'manage_options' ] ] );

		$actual = $this->query_as( $this->admin, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ),
				$this->expectedField( 'post.capabilityTestGroup.secret', self::IS_NULL ),
			]
		);
		$this->assertCount( 1, $this->restricted_debug_entries( $actual ) );
	}

	/**
	 * The field group filter receives the node, so a site can hide a group based on the node it
	 * belongs to (here, posts in a members-only category), whatever the capability says.
	 */
	public function testFieldGroupFilterReceivesTheNodeAndCanDeny(): void {
		$this->register_group();

		$members_category = self::factory()->category->create( [ 'slug' => 'members-only' ] );
		$protected_post   = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $members_category ],
			]
		);
		update_field( $this->key( 'field_capability_test_tagline' ), 'Members tagline', $protected_post );

		$received = [];
		add_filter(
			'wpgraphql/acf/field_group_should_resolve',
			static function ( $should, $node, $acf_field_group ) use ( &$received ) {
				$received[] = [ $node, $acf_field_group['key'] ?? null ];

				if ( $node instanceof \WPGraphQL\Model\Post && has_term( 'members-only', 'category', $node->databaseId ) ) {
					return false;
				}

				return $should;
			},
			10,
			3
		);

		$actual = $this->query_as( $this->admin, $this->post_query(), [ 'id' => $protected_post ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityTestGroup', self::IS_NULL ) ] );
		$this->assertCount( 1, $this->restricted_debug_entries( $actual ) );

		$this->assertInstanceOf( \WPGraphQL\Model\Post::class, $received[0][0] );
		$this->assertSame( $protected_post, $received[0][0]->databaseId );
		$this->assertSame( $this->key( 'group_capability_test' ), $received[0][1] );

		// A post outside the category is unaffected.
		$actual = $this->query_as( null, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ) ] );

		wp_delete_post( $protected_post, true );
	}

	/**
	 * The field group filter can allow a group its capability would deny.
	 */
	public function testFieldGroupFilterCanAllow(): void {
		$this->register_group( [ 'graphql_capability' => 'manage_options' ] );

		add_filter( 'wpgraphql/acf/field_group_should_resolve', '__return_true' );

		$actual = $this->query_as( null, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityTestGroup.tagline', 'Hello World' ) ] );
		$this->assertSame( [], $this->restricted_debug_entries( $actual ) );
	}

	/**
	 * The field filter receives the field config and can deny or allow a field.
	 */
	public function testFieldFilterCanDenyAndAllow(): void {
		$this->register_group( [], [ 'graphql_capability' => 'manage_options' ] );

		add_filter(
			'wpgraphql/acf/field_should_resolve',
			static function ( $should, $root, $acf_field ) {
				// Deny the public field, allow the gated one.
				if ( 'tagline' === ( $acf_field['name'] ?? null ) ) {
					return false;
				}

				if ( 'secret' === ( $acf_field['name'] ?? null ) ) {
					return true;
				}

				return $should;
			},
			10,
			3
		);

		$actual = $this->query_as( null, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.capabilityTestGroup.tagline', self::IS_NULL ),
				$this->expectedField( 'post.capabilityTestGroup.secret', 'sk_live_secret' ),
			]
		);

		$entries = $this->restricted_debug_entries( $actual );
		$this->assertCount( 1, $entries );
		$this->assertSame( 'CapabilityTestGroup.tagline', $entries[0]['field'] );
		$this->assertArrayNotHasKey( 'required_capability', $entries[0] );
	}

	/**
	 * A non-null field with a capability produces an error for viewers without it, which
	 * cascades to the parent, and registering it emits a debug warning.
	 */
	public function testNonNullFieldWithCapabilityCascadesAndWarns(): void {
		$this->register_group(
			[],
			[
				'graphql_capability' => 'manage_options',
				'graphql_non_null'   => true,
			]
		);

		$actual = $this->query_as( null, $this->post_query(), [ 'id' => $this->published_post->ID ] );

		$this->assertArrayHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['post']['capabilityTestGroup'] );

		$messages = wp_list_pluck( $actual['extensions']['debug'] ?? [], 'message' );
		$warning  = array_filter(
			$messages,
			static function ( $message ) {
				return false !== strpos( $message, 'Non-Null' ) && false !== strpos( $message, 'manage_options' );
			}
		);
		$this->assertNotEmpty( $warning, 'Registering a non-null field with a capability should emit a debug warning.' );

		$actual = $this->query_as( $this->admin, $this->post_query(), [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityTestGroup.secret', 'sk_live_secret' ) ] );
	}

	public function cloneDisplayProvider(): array {
		return [
			'seamless'      => [ false ],
			'prefixed name' => [ true ],
		];
	}

	/**
	 * A field group cloned into another group keeps its capability: its fields are gated
	 * wherever they're cloned to.
	 *
	 * @dataProvider cloneDisplayProvider
	 *
	 * @param bool $prefix_name Whether the clone field prefixes the names of the cloned fields,
	 *                          which adds them to the schema under a field of their own.
	 */
	public function testClonedFieldGroupKeepsItsCapability( bool $prefix_name ): void {
		if ( ! $this->is_acf_pro ) {
			$this->markTestSkipped( 'Clone fields need ACF PRO (or Secure Custom Fields)' );
		}

		acf_add_local_field_group(
			[
				'key'                => $this->key( 'group_capability_cloned' ),
				'title'              => 'Cloned Gated Group',
				'active'             => false,
				'show_in_graphql'    => true,
				'graphql_field_name' => 'clonedGatedGroup',
				'graphql_capability' => 'manage_options',
				'fields'             => [
					[
						'key'             => $this->key( 'field_capability_cloned_text' ),
						'name'            => 'cloned_text',
						'label'           => 'Cloned Text',
						'type'            => 'text',
						'show_in_graphql' => true,
					],
				],
				'location'           => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						],
					],
				],
			]
		);

		acf_add_local_field_group(
			[
				'key'                => $this->key( 'group_capability_host' ),
				'title'              => 'Clone Host Group',
				'show_in_graphql'    => true,
				'graphql_field_name' => 'cloneHostGroup',
				'fields'             => [
					[
						'key'             => $this->key( 'field_capability_host_clone' ),
						'name'            => 'host_clone',
						'label'           => 'Host Clone',
						'type'            => 'clone',
						'clone'           => [ $this->key( 'group_capability_cloned' ) ],
						'display'         => 'seamless',
						'layout'          => 'block',
						'prefix_label'    => 0,
						'prefix_name'     => $prefix_name ? 1 : 0,
						'show_in_graphql' => true,
					],
				],
				'location'           => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						],
					],
				],
			]
		);

		$meta_key = $prefix_name ? 'host_clone_cloned_text' : 'cloned_text';
		update_post_meta( $this->published_post->ID, $meta_key, 'cloned value' );
		update_post_meta( $this->published_post->ID, '_' . $meta_key, $this->key( 'field_capability_host_clone' ) . '_' . $this->key( 'field_capability_cloned_text' ) );

		$path  = $prefix_name ? 'post.cloneHostGroup.hostClone.clonedText' : 'post.cloneHostGroup.clonedText';
		$query = '
		query GetPost( $id: ID! ) {
		  post( id: $id, idType: DATABASE_ID ) {
		    cloneHostGroup {
		      ' . ( $prefix_name ? 'hostClone { clonedText }' : 'clonedText' ) . '
		    }
		  }
		}
		';

		$actual = $this->query_as( $this->admin, $query, [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( $path, 'cloned value' ) ] );

		$actual = $this->query_as( null, $query, [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( $path, self::IS_NULL ) ] );
		$this->assertCount( 1, $this->restricted_debug_entries( $actual ) );
	}

	/**
	 * A field group imported from acf-json keeps the capability on the group and its fields,
	 * keeps it when exported again, and is gated when it resolves.
	 */
	public function testFieldGroupAcfJsonRoundTrip(): void {
		$json = wp_json_encode(
			[
				'key'                => $this->key( 'group_capability_json' ),
				'title'              => 'Capability Json Group',
				'show_in_graphql'    => 1,
				'graphql_field_name' => 'capabilityJsonGroup',
				'graphql_capability' => 'edit_posts',
				'fields'             => [
					[
						'key'             => $this->key( 'field_capability_json_public' ),
						'name'            => 'json_public',
						'label'           => 'Json Public',
						'type'            => 'text',
						'show_in_graphql' => 1,
					],
					[
						'key'                => $this->key( 'field_capability_json_secret' ),
						'name'               => 'json_secret',
						'label'              => 'Json Secret',
						'type'               => 'text',
						'show_in_graphql'    => 1,
						'graphql_capability' => 'manage_options',
					],
				],
				'location'           => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						],
					],
				],
				'active'             => true,
			]
		);

		$imported = acf_import_field_group( json_decode( $json, true ) );
		$this->assertNotEmpty( $imported['ID'] );

		// What ACF stored.
		$stored_group = acf_get_field_group( $this->key( 'group_capability_json' ) );
		$this->assertSame( 'edit_posts', $stored_group['graphql_capability'] );
		$stored_field = acf_get_field( $this->key( 'field_capability_json_secret' ) );
		$this->assertSame( 'manage_options', $stored_field['graphql_capability'] );

		// What ACF exports.
		$export           = acf_prepare_field_group_for_export( $stored_group );
		$export['fields'] = acf_prepare_fields_for_export( acf_get_fields( $stored_group ) );
		$export           = json_decode( (string) wp_json_encode( $export ), true );
		$this->assertSame( 'edit_posts', $export['graphql_capability'] );
		$exported_fields = array_column( $export['fields'], null, 'key' );
		$this->assertSame( 'manage_options', $exported_fields[$this->key( 'field_capability_json_secret' )]['graphql_capability'] );
		$this->assertArrayNotHasKey( 'graphql_capability', $exported_fields[$this->key( 'field_capability_json_public' )] );

		update_field( $this->key( 'field_capability_json_public' ), 'public value', $this->published_post->ID );
		update_field( $this->key( 'field_capability_json_secret' ), 'secret value', $this->published_post->ID );

		$query = '
		query GetPost( $id: ID! ) {
		  post( id: $id, idType: DATABASE_ID ) {
		    capabilityJsonGroup {
		      jsonPublic
		      jsonSecret
		    }
		  }
		}
		';

		$actual = $this->query_as( null, $query, [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityJsonGroup', self::IS_NULL ) ] );

		$actual = $this->query_as( $this->author, $query, [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.capabilityJsonGroup.jsonPublic', 'public value' ),
				$this->expectedField( 'post.capabilityJsonGroup.jsonSecret', self::IS_NULL ),
			]
		);

		$actual = $this->query_as( $this->admin, $query, [ 'id' => $this->published_post->ID ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'post.capabilityJsonGroup.jsonSecret', 'secret value' ) ] );

		acf_delete_field_group( $imported['ID'] );
	}

	/**
	 * Register an options page with a field group on it.
	 *
	 * @param array $config Overrides for the options page config.
	 */
	protected function register_options_page_with_fields( array $config = [] ): void {
		$this->add_options_page(
			array_merge(
				[
					'page_title'      => 'Integration Settings',
					'menu_slug'       => 'integration-settings',
					'capability'      => 'manage_options',
					'show_in_graphql' => true,
				],
				$config
			)
		);

		acf_add_local_field_group(
			[
				'key'                => $this->key( 'group_capability_options' ),
				'title'              => 'Integration Fields',
				'show_in_graphql'    => true,
				'graphql_field_name' => 'integrationFields',
				'fields'             => [
					[
						'key'             => $this->key( 'field_capability_options_endpoint' ),
						'name'            => 'endpoint',
						'label'           => 'Endpoint',
						'type'            => 'text',
						'show_in_graphql' => true,
					],
				],
				'location'           => [
					[
						[
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => $config['menu_slug'] ?? 'integration-settings',
						],
					],
				],
			]
		);

		update_field( $this->key( 'field_capability_options_endpoint' ), 'https://example.com/hook', 'options' );
	}

	/**
	 * Register an options page, to be removed again in tearDown.
	 *
	 * @param array $config The options page config.
	 */
	protected function add_options_page( array $config ): void {
		acf_add_options_page( $config );
		$this->options_page_slugs[] = $config['menu_slug'];
	}

	protected function skip_without_options_pages(): void {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			$this->markTestSkipped( 'ACF Options Pages need ACF PRO (or Secure Custom Fields)' );
		}
	}

	/**
	 * Example 5 of #4275: a capability on an options page nulls the options page node, including
	 * when it's fetched with the node field.
	 */
	public function testOptionsPageCapabilityNullsTheNode(): void {
		$this->skip_without_options_pages();

		$this->register_options_page_with_fields( [ 'graphql_capability' => 'manage_options' ] );

		$query = '
		{
		  integrationSettings {
		    id
		    pageTitle
		    integrationFields {
		      endpoint
		    }
		  }
		}
		';

		$actual = $this->query_as( $this->admin, $query );
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'integrationSettings.pageTitle', 'Integration Settings' ),
				$this->expectedField( 'integrationSettings.integrationFields.endpoint', 'https://example.com/hook' ),
			]
		);
		$this->assertSame( [], $this->restricted_debug_entries( $actual ) );
		$global_id = $actual['data']['integrationSettings']['id'];

		foreach ( [ null, $this->subscriber, $this->editor ] as $user ) {
			$actual = $this->query_as( $user, $query );
			self::assertQuerySuccessful( $actual, [ $this->expectedField( 'integrationSettings', self::IS_NULL ) ] );

			$entries = $this->restricted_debug_entries( $actual );
			$this->assertCount( 1, $entries );
			$this->assertSame( 'manage_options', $entries[0]['required_capability'] );
		}

		// The node field loads the options page by its ID, without going through the root field.
		$node_query = '
		query GetNode( $id: ID! ) {
		  node( id: $id ) {
		    __typename
		    ... on IntegrationSettings {
		      integrationFields {
		        endpoint
		      }
		    }
		  }
		}
		';

		$actual = $this->query_as( null, $node_query, [ 'id' => $global_id ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'node', self::IS_NULL ) ] );

		$actual = $this->query_as( $this->admin, $node_query, [ 'id' => $global_id ] );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'node.integrationFields.endpoint', 'https://example.com/hook' ) ] );
	}

	/**
	 * Example 6 of #4275: a field group without a capability on a gated options page is
	 * unreachable, because the page resolved null. Unset on the page stays public.
	 */
	public function testOptionsPageWithoutCapabilityIsPublic(): void {
		$this->skip_without_options_pages();

		$this->register_options_page_with_fields();

		$actual = $this->query_as( null, '{ integrationSettings { integrationFields { endpoint } } }' );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'integrationSettings.integrationFields.endpoint', 'https://example.com/hook' ) ] );
		$this->assertSame( [], $this->restricted_debug_entries( $actual ) );
	}

	/**
	 * The options page filter can deny a page its capability would allow, and allow one it would deny.
	 */
	public function testOptionsPageFilter(): void {
		$this->skip_without_options_pages();

		$this->register_options_page_with_fields( [ 'graphql_capability' => 'manage_options' ] );

		$received = [];
		add_filter(
			'wpgraphql/acf/options_page_should_resolve',
			static function ( $should, $options_page ) use ( &$received ) {
				$received[] = $options_page['menu_slug'] ?? null;
				return ! $should;
			},
			10,
			2
		);

		$actual = $this->query_as( null, '{ integrationSettings { pageTitle } }' );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'integrationSettings.pageTitle', 'Integration Settings' ) ] );

		$actual = $this->query_as( $this->admin, '{ integrationSettings { pageTitle } }' );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'integrationSettings', self::IS_NULL ) ] );

		$this->assertContains( 'integration-settings', $received );
	}

	/**
	 * ACF keeps unknown args on options pages registered in PHP: if it ever dropped this one, the
	 * page would become readable by everyone, so this guards against that.
	 */
	public function testAcfPreservesGraphqlCapabilityOnProgrammaticOptionsPages(): void {
		$this->skip_without_options_pages();

		$this->add_options_page(
			[
				'page_title'         => 'Preserved Capability',
				'menu_slug'          => 'preserved-capability',
				'show_in_graphql'    => true,
				'graphql_capability' => 'manage_options',
			]
		);

		$pages = acf_get_options_pages();
		$this->assertSame( 'manage_options', $pages['preserved-capability']['graphql_capability'] ?? null );
	}

	/**
	 * Options pages registered in the ACF UI (or from acf-json) are registered with the args ACF
	 * builds from the saved settings, which leave out settings ACF doesn't know. The capability
	 * has to be added back, or it would be dropped and the page would be public.
	 */
	public function testUiOptionsPageRegistrationArgsKeepGraphqlCapability(): void {
		$args = apply_filters(
			'acf/ui_options_page/registration_args',
			[ 'page_title' => 'Ui Page' ],
			[
				'show_in_graphql'    => true,
				'graphql_capability' => 'manage_options',
			]
		);
		$this->assertSame( 'manage_options', $args['graphql_capability'] );

		// An unset or empty capability is left out, as ACF leaves out settings at their defaults.
		$args = apply_filters( 'acf/ui_options_page/registration_args', [ 'page_title' => 'Ui Page' ], [ 'graphql_capability' => '' ] );
		$this->assertArrayNotHasKey( 'graphql_capability', $args );
		$args = apply_filters( 'acf/ui_options_page/registration_args', [ 'page_title' => 'Ui Page' ], [] );
		$this->assertArrayNotHasKey( 'graphql_capability', $args );

		// A value that isn't a valid capability is kept, so it fails closed rather than open.
		$args = apply_filters( 'acf/ui_options_page/registration_args', [ 'page_title' => 'Ui Page' ], [ 'graphql_capability' => [ 'manage_options' ] ] );
		$this->assertSame( [ 'manage_options' ], $args['graphql_capability'] );
		$this->assertFalse( \WPGraphQL\Acf\AccessControl::get_required_capability( $args ) );
	}

	/**
	 * An options page imported from acf-json keeps its capability when stored, exported, and
	 * registered, and is gated when it resolves.
	 */
	public function testOptionsPageAcfJsonRoundTrip(): void {
		$this->skip_without_options_pages();

		if ( ! function_exists( 'acf_import_internal_post_type' ) || ! function_exists( 'acf_get_internal_post_type_instance' ) ) {
			$this->markTestSkipped( 'Options Pages defined in the ACF UI need ACF PRO 6.2+' );
		}

		$json = wp_json_encode(
			[
				'key'                => $this->key( 'ui_options_page_capability' ),
				'title'              => 'Json Settings',
				'page_title'         => 'Json Settings',
				'menu_slug'          => 'json-settings',
				'active'             => true,
				'show_in_graphql'    => 1,
				'graphql_type_name'  => 'JsonSettings',
				'graphql_capability' => 'manage_options',
			]
		);

		$imported = acf_import_internal_post_type( json_decode( $json, true ), 'acf-ui-options-page' );
		$this->assertNotEmpty( $imported['ID'] );

		$instance = acf_get_internal_post_type_instance( 'acf-ui-options-page' );

		// What ACF stored.
		$stored = acf_get_internal_post_type( $this->key( 'ui_options_page_capability' ), 'acf-ui-options-page' );
		$this->assertSame( 'manage_options', $stored['graphql_capability'] );

		// What ACF exports.
		$export = json_decode( (string) wp_json_encode( $instance->prepare_post_for_export( $stored ) ), true );
		$this->assertSame( 'manage_options', $export['graphql_capability'] );

		// What ACF registers the page with.
		$args = $instance->get_options_page_args( $stored );
		$this->assertSame( 'manage_options', $args['graphql_capability'] );

		$this->add_options_page( $args );

		$actual = $this->query_as( null, '{ jsonSettings { pageTitle } }' );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'jsonSettings', self::IS_NULL ) ] );

		$actual = $this->query_as( $this->admin, '{ jsonSettings { pageTitle } }' );
		self::assertQuerySuccessful( $actual, [ $this->expectedField( 'jsonSettings.pageTitle', 'Json Settings' ) ] );

		acf_delete_internal_post_type( $imported['ID'], 'acf-ui-options-page' );
	}
}

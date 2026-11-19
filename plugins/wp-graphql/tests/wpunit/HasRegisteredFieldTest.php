<?php

/**
 * Tests for TypeRegistry::has_registered_field().
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4727
 */
class HasRegisteredFieldTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The type registry the schema was built with, captured on `graphql_register_types`.
	 *
	 * @var ?\WPGraphQL\Registry\TypeRegistry
	 */
	protected $type_registry;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		$this->type_registry = null;
		add_action(
			'graphql_register_types',
			function ( $type_registry ) {
				$this->type_registry = $type_registry;
			}
		);
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Builds the schema and returns the type registry it was built with.
	 */
	protected function build_type_registry(): \WPGraphQL\Registry\TypeRegistry {
		\WPGraphQL::get_schema();
		$this->assertInstanceOf( \WPGraphQL\Registry\TypeRegistry::class, $this->type_registry );

		return $this->type_registry;
	}

	public function testFieldRegisteredWithRegisterGraphqlFieldIsFound(): void {
		register_graphql_field( 'Post', 'testRegisteredField', [ 'type' => 'String' ] );

		$type_registry = $this->build_type_registry();

		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'testRegisteredField' ) );
		$this->assertTrue( $type_registry->has_registered_field( 'post', 'testRegisteredField' ), 'The first letter of the type name is not case-sensitive.' );
		$this->assertFalse( $type_registry->has_registered_field( 'Page', 'testRegisteredField' ), 'Only the type the field was registered to counts.' );
		$this->assertFalse( $type_registry->has_registered_field( 'Post', 'testOtherField' ) );
	}

	public function testFieldsRegisteredWithRegisterGraphqlFieldsAreFound(): void {
		register_graphql_fields(
			'Post',
			[
				'testFirstField'  => [ 'type' => 'String' ],
				'testSecondField' => [ 'type' => 'Int' ],
			]
		);

		$type_registry = $this->build_type_registry();

		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'testFirstField' ) );
		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'testSecondField' ) );
	}

	public function testFieldIsFoundByItsFormattedName(): void {
		register_graphql_field( 'Post', 'test_snake_field', [ 'type' => 'String' ] );
		register_graphql_field(
			'Post',
			'test_underscore_field',
			[
				'type'                  => 'String',
				'allowFieldUnderscores' => true,
			]
		);

		$type_registry = $this->build_type_registry();

		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'testSnakeField' ) );
		$this->assertFalse( $type_registry->has_registered_field( 'Post', 'test_snake_field' ) );
		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'test_underscore_field' ), 'allowFieldUnderscores keeps the underscores.' );

		// The record has to match the schema, or the lookup gives the wrong answer.
		$fields = \WPGraphQL::get_schema()->getType( 'Post' )->getFields();
		$this->assertArrayHasKey( 'testSnakeField', $fields );
		$this->assertArrayHasKey( 'test_underscore_field', $fields );
	}

	public function testConnectionFieldIsFound(): void {
		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'Post',
				'fromFieldName' => 'testRegisteredConnection',
				'resolve'       => static function () {
					return null;
				},
			]
		);

		$type_registry = $this->build_type_registry();

		$this->assertTrue( $type_registry->has_registered_field( 'RootQuery', 'testRegisteredConnection' ) );
	}

	public function testDeregisteredFieldIsNotFound(): void {
		register_graphql_field( 'Post', 'testDeregisteredField', [ 'type' => 'String' ] );
		deregister_graphql_field( 'Post', 'testDeregisteredField' );

		$type_registry = $this->build_type_registry();

		$this->assertFalse( $type_registry->has_registered_field( 'Post', 'testDeregisteredField' ) );
		$this->assertArrayNotHasKey( 'testDeregisteredField', \WPGraphQL::get_schema()->getType( 'Post' )->getFields() );
	}

	public function testFieldRegisteredAfterBeingDeregisteredIsFound(): void {
		// Filters run in the order they're added, so a registration after a deregistration stays.
		add_action(
			'graphql_register_types',
			static function ( $type_registry ) {
				$type_registry->deregister_field( 'Post', 'testReregisteredField' );
				$type_registry->register_field( 'Post', 'testReregisteredField', [ 'type' => 'String' ] );
			}
		);

		$type_registry = $this->build_type_registry();

		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'testReregisteredField' ) );
		$this->assertArrayHasKey( 'testReregisteredField', \WPGraphQL::get_schema()->getType( 'Post' )->getFields() );
	}

	/**
	 * The fields filters run under the type name with a lowercase first letter, then with an
	 * uppercase one, so a deregistration under the lowercase name runs before a registration under
	 * the uppercase name, whatever order the calls were made in.
	 */
	public function testDeregisteringWithADifferentFirstLetterFollowsTheFilterOrder(): void {
		register_graphql_field( 'Post', 'testKeptField', [ 'type' => 'String' ] );
		deregister_graphql_field( 'post', 'testKeptField' );

		register_graphql_field( 'post', 'testRemovedField', [ 'type' => 'String' ] );
		deregister_graphql_field( 'Post', 'testRemovedField' );

		$type_registry = $this->build_type_registry();
		$fields        = \WPGraphQL::get_schema()->getType( 'Post' )->getFields();

		$this->assertArrayHasKey( 'testKeptField', $fields );
		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'testKeptField' ) );

		$this->assertArrayNotHasKey( 'testRemovedField', $fields );
		$this->assertFalse( $type_registry->has_registered_field( 'Post', 'testRemovedField' ) );
	}

	/**
	 * Only the first letter of the type name can differ: a registration under any other casing is
	 * never applied to the type, so it doesn't count.
	 */
	public function testRegistrationWithOtherCasingIsNotFound(): void {
		register_graphql_field( 'rootquery', 'testMiscasedField', [ 'type' => 'String' ] );

		$type_registry = $this->build_type_registry();

		$this->assertArrayNotHasKey( 'testMiscasedField', \WPGraphQL::get_schema()->getType( 'RootQuery' )->getFields() );
		$this->assertFalse( $type_registry->has_registered_field( 'RootQuery', 'testMiscasedField' ) );
	}

	/**
	 * A registration still counts when the field is dropped as the type is built, as documented.
	 */
	public function testRegistrationDroppedWhenTheTypeIsBuiltIsFound(): void {
		register_graphql_field( 'Post', 'testFieldWithoutType', [ 'description' => 'No type' ] );

		$type_registry = $this->build_type_registry();

		$this->assertArrayNotHasKey( 'testFieldWithoutType', \WPGraphQL::get_schema()->getType( 'Post' )->getFields() );
		$this->assertTrue( $type_registry->has_registered_field( 'Post', 'testFieldWithoutType' ) );
	}

	/**
	 * Fields registered from a graphql_register_types callback are deferred to
	 * graphql_register_types_late, and are still visible when the type's interfaces are decided.
	 */
	public function testLateRegistrationIsFoundWhenInterfacesAreDecided(): void {
		add_action(
			'graphql_register_types',
			static function () {
				register_graphql_field( 'Post', 'testLateField', [ 'type' => 'String' ] );
			}
		);

		$found_when_deciding_interfaces = null;
		add_filter(
			'graphql_type_interfaces',
			static function ( $interfaces, $config, $wp_type ) use ( &$found_when_deciding_interfaces ) {
				if ( 'Post' === ( $config['name'] ?? '' ) ) {
					$found_when_deciding_interfaces = $wp_type->type_registry->has_registered_field( 'Post', 'testLateField' );
				}
				return $interfaces;
			},
			10,
			3
		);

		$this->build_type_registry();
		\WPGraphQL::get_schema()->getType( 'Post' )->getInterfaces();

		$this->assertTrue( $found_when_deciding_interfaces );
	}

	public function testFieldsFromInterfacesAreNotCounted(): void {
		$type_registry = $this->build_type_registry();

		// Post gets `title` from the NodeWithTitle interface, not from a registration on Post.
		$this->assertArrayHasKey( 'title', \WPGraphQL::get_schema()->getType( 'Post' )->getFields() );
		$this->assertFalse( $type_registry->has_registered_field( 'Post', 'title' ) );
	}

	/**
	 * Code that adds an interface to types can use the lookup to skip the types where one of the
	 * interface's field names is already registered, instead of the interface's field replacing the
	 * registered one.
	 */
	public function testInterfaceCanSkipTypesWhereItsFieldNameIsRegistered(): void {
		register_graphql_field(
			'Post',
			'testSeo',
			[
				'type'    => 'String',
				'resolve' => static function () {
					return 'registered field';
				},
			]
		);

		register_graphql_interface_type(
			'TestWithSeo',
			[
				'fields' => [
					'testSeo' => [
						'type'    => 'Int',
						'resolve' => static function () {
							return 42;
						},
					],
				],
			]
		);

		add_filter(
			'graphql_type_interfaces',
			static function ( $interfaces, $config, $wp_type ) {
				if ( ! in_array( $config['name'] ?? '', [ 'Post', 'Page' ], true ) ) {
					return $interfaces;
				}

				if ( $wp_type->type_registry->has_registered_field( $config['name'], 'testSeo' ) ) {
					return $interfaces;
				}

				$interfaces[] = 'TestWithSeo';
				return $interfaces;
			},
			10,
			3
		);

		$schema = \WPGraphQL::get_schema();
		$schema->assertValid();

		$post_interfaces = array_map( static fn ( $implemented ) => $implemented->name, $schema->getType( 'Post' )->getInterfaces() );
		$page_interfaces = array_map( static fn ( $implemented ) => $implemented->name, $schema->getType( 'Page' )->getInterfaces() );
		$this->assertNotContains( 'TestWithSeo', $post_interfaces );
		$this->assertContains( 'TestWithSeo', $page_interfaces );

		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$page_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_type'   => 'page',
			]
		);

		$actual = $this->graphql(
			[
				'query'     => 'query ($postId: ID!, $pageId: ID!) {
					post(id: $postId, idType: DATABASE_ID) { testSeo }
					page(id: $pageId, idType: DATABASE_ID) { testSeo }
				}',
				'variables' => [
					'postId' => $post_id,
					'pageId' => $page_id,
				],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.testSeo', 'registered field' ),
				$this->expectedField( 'page.testSeo', 42 ),
			]
		);

		// Debug messages must be in the response, or the check below would pass without checking anything.
		$this->assertArrayHasKey( 'debug', $actual['extensions'] );

		$duplicates = array_filter(
			$actual['extensions']['debug'],
			static fn ( $message ) => 'DUPLICATE_FIELD' === ( $message['type'] ?? '' ) && 'testSeo' === ( $message['field_name'] ?? '' )
		);
		$this->assertEmpty( $duplicates, 'The registered field should not be reported as a duplicate.' );
	}
}

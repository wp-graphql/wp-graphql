<?php

class RegisterInterfaceToTypeTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		// before
		parent::setUp();
		// your set up methods here
		$this->clearSchema();
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		// then
		parent::tearDown();
	}

	/**
	 * This test ensures that the `register_graphql_interfaces_to_types()` method
	 * works as expected.
	 *
	 * @throws \Exception
	 */
	public function testRegisterInterfaceToTypesAddsInterfacesToTypes() {

		register_graphql_object_type(
			'TestType',
			[
				'fields' => [
					'a' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_interface_type(
			'TestInterface',
			[
				'fields' => [
					'b' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_interfaces_to_types( [ 'TestInterface' ], [ 'TestType' ] );

		$type_name = 'TestType';

		$query = '
		query GetType($name:String!) {
			__type(name: $name) {
				name
				kind
				interfaces {
					name
				}
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => $type_name,
				],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode( '__type.interfaces', [ 'name' => 'TestInterface' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'a' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'b' ] ),
			]
		);
	}

	/**
	 * This test ensures that the `register_graphql_interfaces_to_types()` method
	 * works as expected.
	 *
	 * @throws \Exception
	 */
	public function testRegisterInterfaceToInterfaceAddsInterfacesToTypes() {

		register_graphql_object_type(
			'TestType',
			[
				'fields' => [
					'a' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_interface_type(
			'TestInterface',
			[
				'fields' => [
					'b' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_interface_type(
			'TestInterfaceTwo',
			[
				'fields' => [
					'c' => [
						'type' => 'String',
					],
				],
			]
		);

		register_graphql_interfaces_to_types( [ 'TestInterfaceTwo' ], [ 'TestInterface' ] );
		register_graphql_interfaces_to_types( [ 'TestInterfaceTwo', 'TestInterface' ], [ 'TestType' ] );

		$type_name = 'TestType';

		$query = '
		query GetType($name:String!) {
			__type(name: $name) {
				name
				kind
				interfaces {
					name
				}
				fields {
					name
				}
			}
		}
		';

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => $type_name,
				],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode( '__type.interfaces', [ 'name' => 'TestInterface' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'a' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'b' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'c' ] ),
			]
		);

		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'name' => 'TestInterface',
				],
			]
		);

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode( '__type.interfaces', [ 'name' => 'TestInterfaceTwo' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'b' ] ),
				$this->expectedNode( '__type.fields', [ 'name' => 'c' ] ),
			]
		);
	}
}

<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\EmailAddress;

class EmailAddressTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		// Enable debug mode
		add_filter( 'graphql_debug', '__return_true', 99999 );

		parent::setUp();

		$this->clearSchema();

		// Register test fields
		add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
	}

	public function tearDown(): void {
		// Clean up by removing our test fields
		remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );

		// Remove debug mode filter
		remove_filter( 'graphql_debug', '__return_true', 99999 );

		parent::tearDown();
	}

	public function register_test_fields(): void {
		// Register a field that resolves to an email
		register_graphql_field( 'RootQuery', 'testEmailField', [
			'type' => 'EmailAddress',
			'description' => 'Test field that returns an email address',
			'resolve' => static function () {
				return 'test@example.com';
			},
		]);

		// Register a field that resolves to an invalid email
		register_graphql_field( 'RootQuery', 'testInvalidEmailField', [
			'type' => 'EmailAddress',
			'description' => 'Test field that returns an invalid email address',
			'resolve' => static function () {
				return 'not-an-email';
			},
		]);

		// Register a mutation that accepts an email address
		register_graphql_mutation( 'testEmailMutation', [
			'inputFields' => [
				'email' => [ 'type' => 'EmailAddress' ],
			],
			'outputFields' => [
				'email' => [ 'type' => 'EmailAddress' ],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [ 'email' => $input['email'] ];
			},
		]);

		// This field seems to be missing from the file I read, but the tests use it.
		register_graphql_field(
			'RootQuery',
			'testEmail',
			[
				'type'        => 'EmailAddress',
				'args'        => [
					'input' => [
						'type' => 'EmailAddress',
					],
				],
				'resolve'     => static function ( $root, $args ) {
					return ! empty( $args['input'] ) ? $args['input'] : null;
				},
			]
		);
	}

	public function testSerializeValidEmail() {
		$this->assertEquals( 'test@example.com', EmailAddress::serialize( 'test@example.com' ) );
	}

	public function testSerializeInvalidEmail() {
		$this->expectException( \GraphQL\Error\InvariantViolation::class );
		EmailAddress::serialize( 'not-an-email' );
	}

	public function testSerializeNonString() {
		$this->expectException( \GraphQL\Error\InvariantViolation::class );
		EmailAddress::serialize( 123 );
	}

	public function testParseValueValidEmail() {
		$this->assertEquals( 'test@example.com', EmailAddress::parseValue( 'test@example.com' ) );
	}

	public function testParseValueInvalidEmail() {
		$this->expectException( \GraphQL\Error\Error::class );
		EmailAddress::parseValue( 'not-an-email' );
	}

	public function testParseValueNonString() {
		$this->expectException( \GraphQL\Error\Error::class );
		EmailAddress::parseValue( 123 );
	}

	/**
	 * Test querying a field that returns a valid email address
	 */
	public function testQueryValidEmail(): void {
		$query = '
		query {
			testEmailField
		}
		';

		$response = $this->graphql(['query' => $query]);

		$this->assertArrayNotHasKey('errors', $response);
		$this->assertEquals('test@example.com', $response['data']['testEmailField']);
	}

	/**
	 * Test querying a field that returns an invalid email address
	 */
	public function testQueryInvalidEmail() {
		$query  = '
			query {
				testEmail( input: "not-an-email" )
			}
		';

		$result = graphql( [ 'query' => $query ] );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'Value is not a valid email address', $result['errors'][0]['message'] );
	}

	public function testQueryInvalidEmailField() {
		$query = 'query { testInvalidEmailField }';
		$result = graphql( [ 'query' => $query ] );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertStringContainsString( 'not-an-email', $result['errors'][0]['extensions']['debugMessage'] );
	}

	/**
	 * Test querying a field that returns an empty email address
	 */
	public function testQueryEmptyEmailField() {
		$query = '
			query {
				testEmail( input: "" )
			}
		';

		$result = graphql( [ 'query' => $query ] );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayNotHasKey( 'data', $result );
		$this->assertStringContainsString( 'Value is not a valid email address', $result['errors'][0]['message'] );
	}

	/**
	 * Test mutation with valid email input
	 */
	public function testMutationWithValidEmail(): void {
		$mutation = '
		mutation TestEmailMutation($email: EmailAddress!) {
			testEmailMutation(input: { email: $email }) {
				email
			}
		}
		';

		$variables = [
			'email' => 'test@example.com',
		];

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => $variables,
		]);

		$this->assertArrayNotHasKey('errors', $response);
		$this->assertEquals('test@example.com', $response['data']['testEmailMutation']['email']);
	}

	/**
	 * Test mutation with invalid email input
	 */
	public function testMutationWithInvalidEmail(): void {
		$mutation = '
		mutation TestEmailMutation($email: EmailAddress!) {
			testEmailMutation(input: { email: $email }) {
				email
			}
		}
		';

		$variables = [
			'email' => 'not-an-email',
		];

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => $variables,
		]);

		$this->assertArrayHasKey('errors', $response);
		$this->assertStringContainsString('valid email address', $response['errors'][0]['message']);
	}

	/**
	 * Test mutation with non-string email input
	 */
	public function testMutationWithNonStringEmail(): void {
		$mutation = '
		mutation TestEmailMutation($email: EmailAddress!) {
			testEmailMutation(input: { email: $email }) {
				email
			}
		}
		';

		$variables = [
			'email' => 123,
		];

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => $variables,
		]);

		$this->assertArrayHasKey('errors', $response);
		$this->assertStringContainsString('must be a string', $response['errors'][0]['message']);
	}
}
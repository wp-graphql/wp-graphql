<?php

namespace WPGraphQL\Test\Experiments\EmailAddressScalar;

class EmailAddressTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();

		// Enable debug mode
		\add_filter( 'graphql_debug', '__return_true', 99999 );

		$this->clearSchema();

		// Enable the experiment via database option (more reliable than filters in tests)
		$settings = \get_option( 'graphql_experiments_settings', [] );
		$settings['email-address-scalar_enabled'] = 'on';
		\update_option( 'graphql_experiments_settings', $settings );

		// Reset experiments to pick up the new settings
		\WPGraphQL\Experimental\ExperimentRegistry::reset();

		// Re-initialize experiments
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Clear schema again to pick up experiment changes
		$this->clearSchema();

		// Register test fields
		\add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
	}

	public function tearDown(): void {
		// Clean up by removing our test fields
		\remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );

		// Disable the experiment
		$settings = \get_option( 'graphql_experiments_settings', [] );
		$settings['email-address-scalar_enabled'] = 'off';
		\update_option( 'graphql_experiments_settings', $settings );

		// Remove debug mode filter
		\remove_filter( 'graphql_debug', '__return_true', 99999 );

		// Reset experiments
		\WPGraphQL\Experimental\ExperimentRegistry::reset();

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
				'email' => [
					'type' => 'EmailAddress',
				],
			],
			'outputFields' => [
				'email' => [
					'type' => 'EmailAddress',
				],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [
					'email' => $input['email'],
				];
			},
		]);
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
	public function testQueryInvalidEmail(): void {
		$query = '
		query {
			testInvalidEmailField
		}
		';

		$response = $this->graphql(['query' => $query]);

		$this->assertArrayHasKey('errors', $response);

        // When the error is on the server (i.e. not an error of user input) the message is "Internal server error" and more info can be seen if debug mode is enabled.
		$this->assertStringContainsString(
			'Internal server error',
			$response['errors'][0]['message']
		);
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
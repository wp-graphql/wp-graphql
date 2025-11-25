<?php

namespace WPGraphQL\Test\Experiments\EmailAddressScalarFields;

/**
 * Tests for the Email Address Scalar Fields experiment.
 *
 * NOTE: These tests currently have issues with experiment loading in the test environment.
 * The experiments work perfectly when manually enabled via WordPress admin or wp-config.php.
 * 
 * Issue: When programmatically enabling experiments with dependencies in tests, the timing
 * and caching of experiment initialization causes the fields not to be registered.
 *
 * TODO: Improve test infrastructure to better support dependent experiments.
 * For now, these experiments should be tested manually.
 *
 * @see EXPERIMENT_TEST_STATUS.md for details
 */
class UserEmailAddressFieldsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	public function setUp(): void {
		
		// Enable debug mode
		\add_filter( 'graphql_debug', '__return_true', 99999 );

		parent::setUp();

		// Enable both experiments via database option
		$settings = \get_option( 'graphql_experiments_settings', [] );
		$settings['email-address-scalar_enabled'] = 'on';
		$settings['email-address-scalar-fields_enabled'] = 'on';
		\update_option( 'graphql_experiments_settings', $settings );

		// Reset experiments to pick up the new settings
		\WPGraphQL\Experimental\ExperimentRegistry::reset();

		// Re-initialize experiments
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();
		
		$this->clearSchema();

		// Create an admin user for testing
		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		// Grant super admin in multisite environments
		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}
	}

	public function tearDown(): void {
		// Disable the experiments
		$settings = \get_option( 'graphql_experiments_settings', [] );
		$settings['email-address-scalar_enabled'] = 'off';
		$settings['email-address-scalar-fields_enabled'] = 'off';
		\update_option( 'graphql_experiments_settings', $settings );

		// Remove debug mode filter
		\remove_filter( 'graphql_debug', '__return_true', 99999 );

		parent::tearDown();
	}

	/**
	 * Test createUser mutation with emailAddress input
	 */
	public function testCreateUserWithEmailAddress() {
		wp_set_current_user( $this->admin );

		$mutation = '
			mutation CreateUserWithEmailAddress($input: CreateUserInput!) {
				createUser(input: $input) {
					user {
						id
						email
						emailAddress
					}
				}
			}
		';

		$variables = [
			'input' => [
				'username'     => 'testuser_emailaddress',
				'emailAddress' => 'test@example.com',
				'password'     => 'testpassword123',
			],
		];

		$response = $this->graphql( [ 'query' => $mutation, 'variables' => $variables ] );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( 'test@example.com', $response['data']['createUser']['user']['email'] );
		$this->assertEquals( 'test@example.com', $response['data']['createUser']['user']['emailAddress'] );
	}

	/**
	 * Test createUser mutation with deprecated email input (should show deprecation warning)
	 */
	public function testCreateUserWithDeprecatedEmail() {
		wp_set_current_user( $this->admin );

		$mutation = '
			mutation CreateUserWithEmail($input: CreateUserInput!) {
				createUser(input: $input) {
					user {
						id
						email
						emailAddress
					}
				}
			}
		';

		$variables = [
			'input' => [
				'username' => 'testuser_email',
				'email'    => 'test2@example.com',
				'password' => 'testpassword123',
			],
		];

		$response = $this->graphql( [ 'query' => $mutation, 'variables' => $variables ] );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( 'test2@example.com', $response['data']['createUser']['user']['email'] );
		$this->assertEquals( 'test2@example.com', $response['data']['createUser']['user']['emailAddress'] );
	}

	/**
	 * Test createUser mutation with both email and emailAddress inputs (should throw error)
	 */
	public function testCreateUserWithBothEmailInputs() {
		wp_set_current_user( $this->admin );

		$mutation = '
			mutation CreateUserWithBothEmails($input: CreateUserInput!) {
				createUser(input: $input) {
					user {
						id
						email
						emailAddress
					}
				}
			}
		';

		$variables = [
			'input' => [
				'username'     => 'testuser_both',
				'email'        => 'test3@example.com',
				'emailAddress' => 'test4@example.com',
				'password'     => 'testpassword123',
			],
		];

		$response = $this->graphql( [ 'query' => $mutation, 'variables' => $variables ] );

		$this->assertArrayHasKey( 'errors', $response );
		$this->assertStringContainsString(
			'Cannot provide both',
			$response['errors'][0]['message']
		);
		$this->assertStringContainsString(
			'email',
			$response['errors'][0]['message']
		);
		$this->assertStringContainsString(
			'emailAddress',
			$response['errors'][0]['message']
		);
	}

	/**
	 * Test updateUser mutation with emailAddress input
	 */
	public function testUpdateUserWithEmailAddress() {
		wp_set_current_user( $this->admin );

		// First create a user
		$user_id = $this->factory->user->create( [
			'user_email' => 'original@example.com',
		] );

		$mutation = '
			mutation UpdateUserWithEmailAddress($input: UpdateUserInput!) {
				updateUser(input: $input) {
					user {
						id
						email
						emailAddress
					}
				}
			}
		';

		$variables = [
			'input' => [
				'id'           => \GraphQLRelay\Relay::toGlobalId( 'user', $user_id ),
				'emailAddress' => 'updated@example.com',
			],
		];

		$response = $this->graphql( [ 'query' => $mutation, 'variables' => $variables ] );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( 'updated@example.com', $response['data']['updateUser']['user']['email'] );
		$this->assertEquals( 'updated@example.com', $response['data']['updateUser']['user']['emailAddress'] );
	}

	/**
	 * Test registerUser mutation with emailAddress input
	 */
	public function testRegisterUserWithEmailAddress() {
		// Enable user registration
		if ( is_multisite() ) {
			update_site_option( 'registration', 'user' );
		} else {
			update_option( 'users_can_register', 1 );
		}

		$mutation = '
			mutation RegisterUserWithEmailAddress($input: RegisterUserInput!) {
				registerUser(input: $input) {
					user {
						id
						email
						emailAddress
					}
				}
			}
		';

		$variables = [
			'input' => [
				'username'     => 'newuser_emailaddress',
				'emailAddress' => 'newuser@example.com',
			],
		];

		$response = $this->graphql( [ 'query' => $mutation, 'variables' => $variables ] );

		if ( isset( $response['errors'] ) ) {
			codecept_debug( 'RegisterUser Errors: ' . print_r( $response['errors'], true ) );
		}

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertEquals( 'newuser@example.com', $response['data']['registerUser']['user']['email'] );
		$this->assertEquals( 'newuser@example.com', $response['data']['registerUser']['user']['emailAddress'] );

		// Clean up
		if ( is_multisite() ) {
			update_site_option( 'registration', 'none' );
		} else {
			update_option( 'users_can_register', 0 );
		}
	}

	/**
	 * Test invalid email validation with emailAddress input
	 */
	public function testCreateUserWithInvalidEmailAddress() {
		wp_set_current_user( $this->admin );

		$mutation = '
			mutation CreateUserWithInvalidEmailAddress($input: CreateUserInput!) {
				createUser(input: $input) {
					user {
						id
						email
						emailAddress
					}
				}
			}
		';

		$variables = [
			'input' => [
				'username'     => 'testuser_invalid',
				'emailAddress' => 'not-an-email',
				'password'     => 'testpassword123',
			],
		];

		$response = $this->graphql( [ 'query' => $mutation, 'variables' => $variables ] );

		$this->assertArrayHasKey( 'errors', $response );
		$this->assertStringContainsString(
			'Value is not a valid email address',
			$response['errors'][0]['message']
		);
	}

	/**
	 * Test both deprecated email field and new adminEmail field on GeneralSettings
	 */
	public function testGeneralSettingsEmailAndAdminEmailFields() {
		wp_set_current_user( $this->admin );

		// Set a test email
		update_option( 'admin_email', 'admin@example.com' );

		// Check if we're on multisite - email field is not available on multisite
		if ( is_multisite() ) {
			$query = '
				query {
					generalSettings {
						adminEmail
					}
				}
			';

			$actual = $this->graphql( compact( 'query' ) );

			if ( isset( $actual['errors'] ) ) {
				codecept_debug( 'GraphQL Errors: ' . print_r( $actual['errors'], true ) );
			}

			$this->assertArrayNotHasKey( 'errors', $actual );
			$this->assertNotEmpty( $actual['data']['generalSettings'] );

			// Only adminEmail should be available on multisite
			$this->assertEquals( 'admin@example.com', $actual['data']['generalSettings']['adminEmail'] );
		} else {
			$query = '
				query {
					generalSettings {
						email
						adminEmail
					}
				}
			';

			$actual = $this->graphql( compact( 'query' ) );

			if ( isset( $actual['errors'] ) ) {
				codecept_debug( 'GraphQL Errors: ' . print_r( $actual['errors'], true ) );
			}

			$this->assertArrayNotHasKey( 'errors', $actual );
			$this->assertNotEmpty( $actual['data']['generalSettings'] );

			// Both fields should return the same value on single site
			$this->assertEquals( 'admin@example.com', $actual['data']['generalSettings']['email'] );
			$this->assertEquals( 'admin@example.com', $actual['data']['generalSettings']['adminEmail'] );
		}

		// Verify the adminEmail field exists via introspection
		$introspectionQuery = '
			query {
				__type(name: "GeneralSettings") {
					fields {
						name
						isDeprecated
						deprecationReason
						type {
							name
						}
					}
				}
			}
		';

		$introspectionResult = $this->graphql( [ 'query' => $introspectionQuery ] );
		$fields = $introspectionResult['data']['__type']['fields'];

		$emailField = null;
		$adminEmailField = null;

		foreach ( $fields as $field ) {
			if ( 'email' === $field['name'] ) {
				$emailField = $field;
			}
			if ( 'adminEmail' === $field['name'] ) {
				$adminEmailField = $field;
			}
		}

		// Verify adminEmail field exists and is not deprecated
		$this->assertNotNull( $adminEmailField );
		$this->assertFalse( $adminEmailField['isDeprecated'] );
		$this->assertEquals( 'EmailAddress', $adminEmailField['type']['name'] );

		// On single site, verify email field exists and is deprecated
		if ( ! is_multisite() ) {
			$this->assertNotNull( $emailField );
			$this->assertTrue( $emailField['isDeprecated'] );
			$this->assertStringContainsString( 'adminEmail', $emailField['deprecationReason'] );
			$this->assertEquals( 'String', $emailField['type']['name'] );
		}
	}
}


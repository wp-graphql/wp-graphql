# Creating Experiments

This guide shows you how to create experiments for WPGraphQL, whether for contributing to core or for learning purposes.

## Prerequisites

Before creating an experiment, you should understand:

- PHP and object-oriented programming
- GraphQL concepts (types, fields, resolvers)
- WPGraphQL's type registration system
- WordPress plugin development basics
- PHPUnit for testing

## Experiment Anatomy

Every experiment consists of:

1. **Experiment Class**: Extends `AbstractExperiment`
2. **Configuration**: Title, description, and metadata
3. **Initialization**: Where you register GraphQL types/fields
4. **Tests**: Comprehensive test coverage
5. **Documentation**: Inline and external docs

## Step-by-Step Guide

### Step 1: Create the Experiment Class

Create a new file in `src/Experimental/Experiment/`:

```php
<?php
/**
 * Email Address Scalar Experiment
 *
 * @package WPGraphQL\Experimental\Experiment
 * @since 2.0.0
 */

namespace WPGraphQL\Experimental\Experiment;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * Class EmailAddressScalarExperiment
 *
 * Adds a custom EmailAddress scalar type for better validation
 * and type safety when working with email fields.
 */
class EmailAddressScalarExperiment extends AbstractExperiment {

    /**
     * Define the unique slug for this experiment.
     *
     * This slug is used for:
     * - Settings storage (email_address_scalar_enabled)
     * - Identifying the experiment in code
     * - Filtering and hooks
     *
     * @return string
     */
    protected static function slug(): string {
        return 'email_address_scalar';
    }

    /**
     * Define the experiment configuration.
     *
     * @return array{title:string,description:string,deprecationMessage?:string}
     */
    protected function config(): array {
        return [
            'title'       => __( 'Email Address Scalar', 'wp-graphql' ),
            'description' => __(
                'Adds an EmailAddress scalar type that validates email addresses and provides better type safety for email fields in the schema.',
                'wp-graphql'
            ),
        ];
    }

    /**
     * Initialize the experiment.
     *
     * This is where you hook into WordPress/WPGraphQL to register your features.
     * This method only runs when the experiment is active.
     *
     * @return void
     */
    protected function init(): void {
        // Register types during the graphql_register_types action
        add_action( 'graphql_register_types', [ $this, 'register_types' ] );

        // Add additional hooks as needed
        add_filter( 'graphql_user_fields', [ $this, 'modify_user_fields' ] );
    }

    /**
     * Register the EmailAddress scalar type.
     *
     * @return void
     */
    public function register_types(): void {
        register_graphql_scalar( 'EmailAddress', [
            'description' => __( 'A valid email address', 'wp-graphql' ),

            // Serialize value when sending to client
            'serialize'   => function( $value ) {
                if ( ! is_string( $value ) ) {
                    throw new \GraphQL\Error\UserError(
                        __( 'EmailAddress must be a string', 'wp-graphql' )
                    );
                }

                if ( ! is_email( $value ) ) {
                    throw new \GraphQL\Error\UserError(
                        sprintf(
                            __( '"%s" is not a valid email address', 'wp-graphql' ),
                            $value
                        )
                    );
                }

                return sanitize_email( $value );
            },

            // Parse value from client input
            'parseValue'  => function( $value ) {
                if ( ! is_string( $value ) ) {
                    throw new \GraphQL\Error\UserError(
                        __( 'EmailAddress must be a string', 'wp-graphql' )
                    );
                }

                if ( ! is_email( $value ) ) {
                    throw new \GraphQL\Error\UserError(
                        sprintf(
                            __( '"%s" is not a valid email address', 'wp-graphql' ),
                            $value
                        )
                    );
                }

                return sanitize_email( $value );
            },

            // Parse literal value from query
            'parseLiteral' => function( $ast ) {
                if ( ! isset( $ast->value ) || ! is_string( $ast->value ) ) {
                    throw new \GraphQL\Error\UserError(
                        __( 'EmailAddress must be a string', 'wp-graphql' )
                    );
                }

                if ( ! is_email( $ast->value ) ) {
                    throw new \GraphQL\Error\UserError(
                        sprintf(
                            __( '"%s" is not a valid email address', 'wp-graphql' ),
                            $ast->value
                        )
                    );
                }

                return sanitize_email( $ast->value );
            },
        ] );
    }

    /**
     * Modify user fields to use EmailAddress scalar.
     *
     * @param array $fields The user fields
     * @return array
     */
    public function modify_user_fields( array $fields ): array {
        if ( isset( $fields['email'] ) ) {
            $fields['email']['type'] = 'EmailAddress';
        }

        return $fields;
    }
}
```

### Step 2: Register the Experiment

Add your experiment to `src/Experimental/ExperimentRegistry.php`:

```php
protected function register_experiments(): void {
    $registry = [
        TestExperiment::get_slug()                => TestExperiment::class,
        EmailAddressScalarExperiment::get_slug() => EmailAddressScalarExperiment::class,
    ];

    /**
     * Filters the list of registered experiment classes.
     *
     * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry
     */
    self::$registry = apply_filters( 'graphql_experiments_registered_classes', $registry );

    /**
     * Fires after experiments are registered.
     *
     * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry
     */
    do_action( 'graphql_experiments_registered', self::$registry );
}
```

### Step 3: Write Comprehensive Tests

Create `tests/wpunit/EmailAddressScalarExperimentTest.php`:

```php
<?php

/**
 * Tests for the EmailAddress Scalar Experiment
 */
class EmailAddressScalarExperimentTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

    public function setUp(): void {
        parent::setUp();
        $this->clearSchema();

        // Clear experiment settings
        update_option( 'graphql_experiments_settings', [] );

        // Reset the registry
        \WPGraphQL\Experimental\ExperimentRegistry::reset();
    }

    public function tearDown(): void {
        update_option( 'graphql_experiments_settings', [] );
        \WPGraphQL\Experimental\ExperimentRegistry::reset();
        parent::tearDown();
    }

    /**
     * Test that the EmailAddress scalar type exists when experiment is enabled
     */
    public function testEmailAddressScalarExistsWhenEnabled() {
        // Enable the experiment
        update_option( 'graphql_experiments_settings', [
            'email_address_scalar_enabled' => 'on'
        ] );

        // Initialize registry
        $registry = new \WPGraphQL\Experimental\ExperimentRegistry();
        $registry->init();

        // Query for the scalar type
        $query = '
            query IntrospectEmailAddress {
                __type(name: "EmailAddress") {
                    name
                    kind
                    description
                }
            }
        ';

        $result = graphql( [ 'query' => $query ] );

        $this->assertArrayNotHasKey( 'errors', $result );
        $this->assertEquals( 'EmailAddress', $result['data']['__type']['name'] );
        $this->assertEquals( 'SCALAR', $result['data']['__type']['kind'] );
    }

    /**
     * Test that EmailAddress scalar doesn't exist when experiment is disabled
     */
    public function testEmailAddressScalarNotPresentWhenDisabled() {
        // Don't enable the experiment
        $registry = new \WPGraphQL\Experimental\ExperimentRegistry();
        $registry->init();

        $query = '
            query IntrospectEmailAddress {
                __type(name: "EmailAddress") {
                    name
                }
            }
        ';

        $result = graphql( [ 'query' => $query ] );

        $this->assertArrayNotHasKey( 'errors', $result );
        $this->assertNull( $result['data']['__type'] );
    }

    /**
     * Test that valid email addresses are accepted
     */
    public function testValidEmailAddressesAreAccepted() {
        update_option( 'graphql_experiments_settings', [
            'email_address_scalar_enabled' => 'on'
        ] );

        $registry = new \WPGraphQL\Experimental\ExperimentRegistry();
        $registry->init();

        $valid_emails = [
            'test@example.com',
            'user.name@example.co.uk',
            'first+last@test.org',
        ];

        foreach ( $valid_emails as $email ) {
            // Test query with email variable
            $query = '
                query GetUser($email: EmailAddress!) {
                    # Your test query here
                }
            ';

            $result = graphql([
                'query' => $query,
                'variables' => [ 'email' => $email ]
            ]);

            // Assert no errors for valid email
            $this->assertArrayNotHasKey( 'errors', $result, "Failed for email: $email" );
        }
    }

    /**
     * Test that invalid email addresses are rejected
     */
    public function testInvalidEmailAddressesAreRejected() {
        update_option( 'graphql_experiments_settings', [
            'email_address_scalar_enabled' => 'on'
        ] );

        $registry = new \WPGraphQL\Experimental\ExperimentRegistry();
        $registry->init();

        $invalid_emails = [
            'notanemail',
            'missing@domain',
            '@example.com',
            'spaces in@email.com',
        ];

        foreach ( $invalid_emails as $email ) {
            $query = '
                query GetUser($email: EmailAddress!) {
                    # Your test query here
                }
            ';

            $result = graphql([
                'query' => $query,
                'variables' => [ 'email' => $email ]
            ]);

            // Assert errors for invalid email
            $this->assertArrayHasKey( 'errors', $result, "Should have failed for: $email" );
        }
    }

    /**
     * Test that user email field uses EmailAddress scalar
     */
    public function testUserEmailFieldUsesEmailAddressScalar() {
        update_option( 'graphql_experiments_settings', [
            'email_address_scalar_enabled' => 'on'
        ] );

        $registry = new \WPGraphQL\Experimental\ExperimentRegistry();
        $registry->init();

        $query = '
            query IntrospectUserEmailField {
                __type(name: "User") {
                    fields {
                        name
                        type {
                            name
                            kind
                        }
                    }
                }
            }
        ';

        $result = graphql( [ 'query' => $query ] );

        $this->assertArrayNotHasKey( 'errors', $result );

        // Find the email field
        $email_field = null;
        foreach ( $result['data']['__type']['fields'] as $field ) {
            if ( $field['name'] === 'email' ) {
                $email_field = $field;
                break;
            }
        }

        $this->assertNotNull( $email_field );
        $this->assertEquals( 'EmailAddress', $email_field['type']['name'] );
    }
}
```

### Step 4: Document the Experiment

Add inline documentation:

```php
/**
 * Email Address Scalar Experiment
 *
 * This experiment adds a custom EmailAddress scalar type to the GraphQL schema.
 *
 * ## What it does:
 * - Adds an `EmailAddress` scalar type that validates email addresses
 * - Updates the User.email field to use the EmailAddress scalar
 * - Provides better type safety and validation for email fields
 *
 * ## Why it exists:
 * String types don't provide validation for email addresses, which can lead to
 * invalid data being accepted. This experiment validates emails at the GraphQL
 * layer, providing immediate feedback to clients.
 *
 * ## Known limitations:
 * - Only validates format, not deliverability
 * - Uses WordPress's is_email() function which may differ from HTML5 validation
 * - Breaking change if graduating: String -> EmailAddress type change
 *
 * ## Provide feedback:
 * https://github.com/wp-graphql/wp-graphql/discussions/xxxx
 *
 * @package WPGraphQL\Experimental\Experiment
 * @since 2.0.0
 */
```

## Advanced Patterns

### Pattern 1: Experiment Dependencies

If your experiment depends on another experiment:

```php
class AdvancedExperiment extends AbstractExperiment {

    protected static function slug(): string {
        return 'advanced_feature';
    }

    protected function config(): array {
        return [
            'title' => __( 'Advanced Feature', 'wp-graphql' ),
            'description' => __( 'Advanced feature that requires EmailAddress scalar', 'wp-graphql' ),
        ];
    }

    protected function init(): void {
        // Check if dependency is active
        if ( ! \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'email_address_scalar' ) ) {
            // Log warning or handle gracefully
            graphql_debug(
                __( 'Advanced Feature experiment requires EmailAddress Scalar experiment to be enabled', 'wp-graphql' ),
                [ 'experiment' => static::slug() ]
            );
            return;
        }

        // Proceed with initialization
        add_action( 'graphql_register_types', [ $this, 'register_types' ] );
    }
}
```

### Pattern 2: Feature Flag Within Experiment

```php
class ConfigurableExperiment extends AbstractExperiment {

    protected function init(): void {
        add_action( 'graphql_register_types', [ $this, 'register_types' ] );

        // Allow filtering specific features within the experiment
        if ( apply_filters( 'graphql_experiment_feature_x_enabled', true ) ) {
            add_filter( 'graphql_some_hook', [ $this, 'feature_x' ] );
        }
    }
}
```

### Pattern 3: Deprecating an Experiment

When marking an experiment for deprecation:

```php
protected function config(): array {
    return [
        'title'              => __( 'Email Address Scalar', 'wp-graphql' ),
        'description'        => __( 'Adds EmailAddress scalar type...', 'wp-graphql' ),
        'deprecationMessage' => __(
            'This experiment has graduated to core and will be removed in v3.0.0. The EmailAddress scalar is now always available.',
            'wp-graphql'
        ),
    ];
}
```

## Testing Checklist

Before submitting an experiment, ensure:

- [ ] **Unit tests** cover all public methods
- [ ] **Integration tests** verify schema changes
- [ ] **Tests pass** when experiment is enabled
- [ ] **Tests pass** when experiment is disabled
- [ ] **Edge cases** are tested (null values, invalid input, etc.)
- [ ] **Error messages** are clear and helpful
- [ ] **Performance** is acceptable (no N+1 queries, etc.)
- [ ] **Conflicts** with other experiments are handled
- [ ] **Test isolation** works (uses `ExperimentRegistry::reset()`)

## Common Mistakes to Avoid

### ❌ Don't: Modify Core Files Directly

```php
// BAD: Modifying core type registration
// File: src/Type/ObjectType/User.php
register_graphql_object_type( 'User', [
    'fields' => [
        'emailAddress' => [
            'type' => 'EmailAddress', // Don't modify core!
        ]
    ]
] );
```

```php
// GOOD: Use filters in your experiment
add_filter( 'graphql_User_fields', function( array $fields ): array {
    if ( isset( $fields['email'] ) ) {
        $fields['emailAddress'] = [
            'type' => 'EmailAddress',
        ];
    }
    return $fields;
}
```

### ❌ Don't: Skip the init() Method

```php
// BAD: Registering directly in constructor
public function __construct() {
    parent::__construct();
    register_graphql_type( ... ); // Too early!
}
```

```php
// GOOD: Use init() method
protected function init(): void {
    add_action( 'graphql_register_types', [ $this, 'register_types' ] );
}
```

## Debugging Tips

### Enable Debug Mode

```php
// In wp-config.php
define( 'GRAPHQL_DEBUG', true );
```

or in the WPGraphQL > Settings.

### Log Experiment Activity

Use graphql_debug to help log data at various breakpoints. Remove the debugging when not actively debugging.

```php
protected function init(): void {
    graphql_debug(
        sprintf( 'Experiment "%s" initialized', static::get_slug() ),
        [ 'experiment' => static::get_slug() ]
    );

    add_action( 'graphql_register_types', [ $this, 'register_types' ] );
}
```

### Verify Experiment is Active

```php
// In WordPress console or temporary code
$active = \WPGraphQL\Experimental\ExperimentRegistry::get_active_experiments();
wp_send_json( array_keys( $active ) );
```

## Next Steps

- [Contributing Experiments](/docs/experiments-contributing) - Submit your experiment to WPGraphQL core
- [Using Experiments](/docs/experiments-using) - Learn how users will interact with your experiment
- [Testing Guide](/docs/testing) - Comprehensive testing documentation

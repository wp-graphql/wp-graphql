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

> **📝 Important**: Each experiment should include a `README.md` file in its directory. This README serves as the official documentation for the experiment and is automatically linked in admin notices when the experiment is activated or deactivated. If missing, a debug warning will be logged (when `GRAPHQL_DEBUG` is enabled).

## Step-by-Step Guide

### Step 1: Create the Experiment Directory and Files

Each experiment should live in its own directory with at least two files:
- The main experiment PHP class
- A README.md file documenting the experiment

Create a new directory in `src/Experimental/Experiment/EmailAddressScalarExperiment/`:

```bash
mkdir -p src/Experimental/Experiment/EmailAddressScalarExperiment
```

Create the main experiment class file `EmailAddressScalarExperiment.php`:

```php
<?php
/**
 * Email Address Scalar Experiment
 *
 * @package WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment
 * @since 2.0.0
 */

namespace WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment;

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

### Step 4: Create the Experiment README

Each experiment **must** include a README.md file in its directory. This file should document:

- What the experiment does
- Schema changes it introduces
- Usage examples
- Dependencies (if any)
- Known limitations
- Migration notes (if applicable)

Create `src/Experimental/Experiment/EmailAddressScalarExperiment/README.md`:

```markdown
# Email Address Scalar Experiment

**Status**: Active  
**Slug**: `email_address_scalar`  
**Since**: 2.0.0

## Overview

This experiment adds a custom EmailAddress scalar type to the GraphQL schema for better validation and type safety when working with email fields.

## What It Does

When activated, this experiment:

1. Registers an `EmailAddress` scalar type that validates email addresses
2. Updates the `User.email` field to use the EmailAddress scalar
3. Provides better type safety and validation for email fields

## Schema Changes

### New Types

**`EmailAddress` (Scalar)**
- Validates email format using WordPress's `is_email()` function
- Sanitizes email addresses before returning to clients
- Throws validation errors for invalid email formats

### Modified Fields

**`User.email`**
- **Before**: `String`
- **After**: `EmailAddress`

### Example Query

\```graphql
query GetUser($id: ID!) {
  user(id: $id) {
    email # Now returns EmailAddress type with validation
  }
}
\```

## Usage

See the main [Using Experiments](/docs/experiments-using.md) guide for general instructions.

## Dependencies

**Required Dependencies**: None  
**Optional Dependencies**: None

## Known Limitations

- Only validates format, not email deliverability
- Uses WordPress's `is_email()` which may differ from HTML5 validation
- Breaking change if graduating: `String` → `EmailAddress` type change

## Feedback

Please provide feedback on this experiment:
- GitHub: https://github.com/wp-graphql/wp-graphql/issues

## References

- [What are Experiments?](/docs/experiments.md)
- [Creating Experiments](/docs/experiments-creating.md)
\```

### Step 5: Add Inline Documentation to the Class

Add inline documentation to your experiment class:

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
 * @package WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment
 * @since 2.0.0
 */
```

## Experiment Directory Structure

Each experiment should be organized in its own directory with at least two required files:

```
src/Experimental/Experiment/
├── AbstractExperiment.php                    # Base class (don't modify)
├── YourExperiment/
│   ├── YourExperiment.php                   # Main experiment class (required)
│   ├── README.md                            # Documentation (required)
│   └── helpers/                             # Optional: Additional files
│       └── validation.php
├── TestExperiment/
│   ├── TestExperiment.php
│   └── README.md
└── TestDependantExperiment/
    ├── TestDependantExperiment.php
    └── README.md
```

### Required Files

1. **`{ExperimentName}.php`** - The main experiment class
2. **`README.md`** - Documentation for the experiment (automatically linked in activation messages)

### Benefits of This Structure

- **Organization**: All files related to an experiment are grouped together
- **Documentation**: README.md provides detailed documentation and is automatically linked when experiments are activated/deactivated
- **Scalability**: Experiments can include additional files (helpers, assets, tests, etc.)
- **Discoverability**: Easy to find all files and documentation for an experiment

### README.md Requirements

Each experiment's README.md should include:

- **Overview**: What the experiment does
- **Schema Changes**: New types, fields, or modifications
- **Usage Examples**: GraphQL queries showing the experiment in action
- **Dependencies**: Required and optional dependencies (if any)
- **Known Limitations**: Current constraints or issues
- **Status**: Active, Deprecated, or Graduated

See the existing test experiments for examples:
- `src/Experimental/Experiment/TestExperiment/README.md`
- `src/Experimental/Experiment/TestDependantExperiment/README.md`
- `src/Experimental/Experiment/TestOptionalDependencyExperiment/README.md`

## Advanced Patterns

### Pattern 1: Experiment Dependencies

WPGraphQL's Experiments API supports both **required** and **optional** dependencies. This allows experiments to depend on other experiments and ensures proper activation/deactivation behavior.

#### Required Dependencies

Required dependencies must be active for the experiment to function. If a required dependency is missing, the experiment will be disabled in the admin UI and won't load.

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

    /**
     * Define experiment dependencies.
     *
     * @return array{required?:array<string>,optional?:array<string>}
     */
    public function get_dependencies(): array {
        return [
            'required' => [ 'email_address_scalar' ],
            'optional' => [],
        ];
    }

    protected function init(): void {
        // The experiment will only reach this point if all required dependencies are active
        add_action( 'graphql_register_types', [ $this, 'register_types' ] );
    }
}
```

#### Optional Dependencies

Optional dependencies enhance functionality but aren't required for the experiment to work. The experiment can adapt its behavior based on whether optional dependencies are available.

```php
class FlexibleExperiment extends AbstractExperiment {

    protected static function slug(): string {
        return 'flexible_feature';
    }

    protected function config(): array {
        return [
            'title' => __( 'Flexible Feature', 'wp-graphql' ),
            'description' => __( 'A feature that works independently but is enhanced by other experiments', 'wp-graphql' ),
        ];
    }

    /**
     * Define experiment dependencies.
     *
     * @return array{required?:array<string>,optional?:array<string>}
     */
    public function get_dependencies(): array {
        return [
            'required' => [],
            'optional' => [ 'email_address_scalar', 'advanced_feature' ],
        ];
    }

    protected function init(): void {
        add_action( 'graphql_register_types', [ $this, 'register_types' ] );
    }

    public function register_types(): void {
        // Check if optional dependencies are active and adapt behavior
        $email_scalar_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'email_address_scalar' );
        $advanced_feature_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'advanced_feature' );

        if ( $email_scalar_active ) {
            // Register enhanced fields that use EmailAddress scalar
            register_graphql_field( 'RootQuery', 'enhancedEmailField', [
                'type' => 'EmailAddress',
                'resolve' => function() {
                    return 'enhanced@example.com';
                },
            ] );
        } else {
            // Register basic fields that use String type
            register_graphql_field( 'RootQuery', 'basicEmailField', [
                'type' => 'String',
                'resolve' => function() {
                    return 'basic@example.com';
                },
            ] );
        }
    }
}
```

#### Dependency Behavior

- **Required Dependencies**: If any required dependency is inactive, the experiment is disabled in the admin UI and won't load
- **Optional Dependencies**: The experiment works independently but can check for optional dependencies and adapt its behavior
- **Cascading Deactivation**: When a dependency is deactivated, all experiments that depend on it are also deactivated
- **UI Feedback**: The admin UI shows dependency status with visual indicators (🔗 for required, ✨ for optional)

#### Real Examples

See the test experiments for working examples:

- **`TestExperiment`**: No dependencies (base experiment)
- **`TestDependantExperiment`**: Requires `TestExperiment` (demonstrates required dependencies)
- **`TestOptionalDependencyExperiment`**: Optionally depends on `TestExperiment` (demonstrates optional dependencies)

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

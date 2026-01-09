# Test Dependant Experiment

**Status**: Active  
**Slug**: `test-dependant-experiment`  
**Since**: 2.3.8

## Overview

The Test Dependant Experiment demonstrates how experiments can depend on other experiments. This experiment requires the TestExperiment to be active and shows how the dependency system works in the WPGraphQL Experiments API.

## Purpose

This experiment serves to:

- **Demonstrate Dependencies**: Show how to declare required dependencies
- **Validate Dependency System**: Test that required dependencies are enforced
- **Provide Examples**: Give developers a working example of experiment dependencies
- **Show Cascading Behavior**: Illustrate how deactivating a dependency affects dependent experiments

## What It Does

When activated (and when TestExperiment is also active), this experiment:

1. Registers a `testDependantExperiment` field on the `RootQuery` type
2. Returns a string that indicates the experiment and its dependency are active
3. Demonstrates how dependent experiments can use functionality from their dependencies

## Schema Changes

### New Fields

**`RootQuery.testDependantExperiment`**
- **Type**: `String`
- **Description**: A test field that demonstrates experiment dependencies.
- **Resolver**: Returns a string showing the experiment is working with its dependency

### Example Query

```graphql
query {
  testDependantExperiment
}
```

**Response:**
```json
{
  "data": {
    "testDependantExperiment": "This is a dependent experiment! (Dependency: TestExperiment is active)"
  }
}
```

## Usage

### Enabling the Experiment

**Via WordPress Admin:**
1. First, enable **Test Experiment** (required dependency)
2. Navigate to **GraphQL > Settings > Experiments**
3. Check the box next to "Test Dependant Experiment"
4. Click **Save Changes**

**Note**: You cannot enable this experiment without first enabling TestExperiment.

**Via wp-config.php:**
```php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'test_experiment'           => true,  // Required dependency
    'test-dependant-experiment' => true,
] );
```

**Via Code:**
```php
// Both must be enabled
add_filter( 'wp_graphql_experiment_test_experiment_enabled', '__return_true' );
add_filter( 'wp_graphql_experiment_test-dependant-experiment_enabled', '__return_true' );
```

## Dependencies

### Required Dependencies

- **`test_experiment`** (TestExperiment): Provides base functionality

This experiment **cannot** be activated without its required dependency being active.

### Optional Dependencies

None

### Dependency Behavior

**Activation:**
- If TestExperiment is not active, this experiment cannot be enabled
- The admin UI will show a message indicating the missing dependency
- The checkbox will be disabled in the settings

**Deactivation:**
- If TestExperiment is deactivated, this experiment is automatically deactivated
- Users will see a notice explaining the cascading deactivation

## Use Cases

This is a demonstration experiment showing dependency patterns. Real-world use cases include:

### Example: Advanced Feature Depending on Base Feature

```php
class AdvancedEmailExperiment extends AbstractExperiment {
    public function get_dependencies(): array {
        return [
            'required' => [ 'email_address_scalar' ],
        ];
    }
}
```

### Example: Feature Enhancement

```php
class EmailValidationRulesExperiment extends AbstractExperiment {
    public function get_dependencies(): array {
        return [
            'required' => [ 'email_address_scalar' ],
        ];
    }
}
```

## Known Limitations

None - this is a demonstration experiment.

## Breaking Changes

None planned - this experiment will remain as a dependency example.

## Graduation Plan

This experiment will **not** graduate to core. It exists only for demonstration and testing purposes.

## Related Experiments

- **TestExperiment**: This experiment's required dependency
- **TestOptionalDependencyExperiment**: Another example showing optional dependencies

## For Developers

### File Structure

```
TestDependantExperiment/
├── TestDependantExperiment.php  # Main experiment class
└── README.md                    # This file
```

### Declaring Dependencies

```php
/**
 * Define experiment dependencies.
 */
public function get_dependencies(): array {
    return [
        'required' => [ 'test_experiment' ],
        'optional' => [],
    ];
}
```

### How It Works

1. **Registration**: Dependencies are declared in `get_dependencies()`
2. **Validation**: The ExperimentRegistry checks dependencies during loading
3. **Enforcement**: Experiments with missing required dependencies won't load
4. **UI Feedback**: Admin UI shows dependency status and blocks activation if needed

### Testing Dependencies

```php
// Test that experiment requires dependency
$experiment = new TestDependantExperiment();
$dependencies = $experiment->get_dependencies();
$this->assertContains( 'test_experiment', $dependencies['required'] );

// Test that experiment won't load without dependency
update_option( 'graphql_experiments_settings', [
    'test-dependant-experiment_enabled' => 'on',
    // test_experiment NOT enabled
] );

$is_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 
    'test-dependant-experiment' 
);
$this->assertFalse( $is_active ); // Should be false due to missing dependency
```

## Admin UI Behavior

### When Viewing in Settings

**If TestExperiment is Active:**
- ✅ Checkbox is enabled
- 🔗 Shows indicator for required dependency
- 💡 Shows message: "Required experiments: Test Experiment"

**If TestExperiment is Inactive:**
- ❌ Checkbox is disabled
- 🚫 Shows warning: "This experiment cannot be activated because required experiments are missing or inactive: Test Experiment"

## Feedback & Support

This is a demonstration experiment. For questions about experiment dependencies:

- **Documentation**: [Creating Experiments - Dependencies](/docs/experiments-creating.md#pattern-1-experiment-dependencies)
- **GitHub**: [WPGraphQL Issues](https://github.com/wp-graphql/wp-graphql/issues)
- **Slack**: [WPGraphQL Community](https://wpgraphql.com/community)

## References

- [What are Experiments?](/docs/experiments.md)
- [Using Experiments](/docs/experiments-using.md)
- [Creating Experiments](/docs/experiments-creating.md)
- [Experiment Dependencies Pattern](/docs/experiments-creating.md#pattern-1-experiment-dependencies)


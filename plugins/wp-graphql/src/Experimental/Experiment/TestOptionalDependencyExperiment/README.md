# Test Optional Dependency Experiment

**Status**: Active  
**Slug**: `test-optional-dependency-experiment`  
**Since**: 2.3.8

## Overview

The Test Optional Dependency Experiment demonstrates how experiments can have optional dependencies that enhance functionality but aren't required. This experiment works independently but provides better features when TestExperiment is also active.

## Purpose

This experiment serves to:

- **Demonstrate Optional Dependencies**: Show how to declare optional (recommended) dependencies
- **Show Graceful Degradation**: Illustrate how experiments can adapt based on available dependencies
- **Provide Flexibility**: Give developers a pattern for creating experiments that enhance each other
- **Validate Optional Behavior**: Test that optional dependencies are recommended but not enforced

## What It Does

When activated, this experiment:

1. Registers a `testOptionalDependency` field on the `RootQuery` type
2. Returns different responses based on whether TestExperiment is active:
   - **Enhanced**: If TestExperiment is active, returns enhanced functionality message
   - **Basic**: If TestExperiment is not active, returns basic functionality message
3. Demonstrates runtime checking of optional dependencies

## Schema Changes

### New Fields

**`RootQuery.testOptionalDependency`**
- **Type**: `String`
- **Description**: A test field that demonstrates optional dependencies.
- **Resolver**: Returns different messages based on optional dependency availability

### Example Queries

**When TestExperiment is Active (Enhanced Mode):**
```graphql
query {
  testOptionalDependency
}
```

**Response:**
```json
{
  "data": {
    "testOptionalDependency": "Enhanced functionality: TestExperiment is active! This provides additional features."
  }
}
```

**When TestExperiment is Not Active (Basic Mode):**
```graphql
query {
  testOptionalDependency
}
```

**Response:**
```json
{
  "data": {
    "testOptionalDependency": "Basic functionality: TestExperiment is not active. This still works, but with limited features."
  }
}
```

## Usage

### Enabling the Experiment

**Via WordPress Admin:**
1. Navigate to **GraphQL > Settings > Experiments**
2. Check the box next to "Test Optional Dependency Experiment"
3. Click **Save Changes**
4. (Optionally) Enable **Test Experiment** for enhanced functionality

**Note**: You can enable this experiment without TestExperiment - it will work with basic functionality.

**Via wp-config.php:**
```php
// Basic functionality
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'test-optional-dependency-experiment' => true,
] );

// Enhanced functionality (with optional dependency)
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'test_experiment'                     => true,  // Optional dependency
    'test-optional-dependency-experiment' => true,
] );
```

## Dependencies

### Required Dependencies

None - this experiment works independently.

### Optional Dependencies

- **`test_experiment`** (TestExperiment): Provides enhanced functionality when active

### Dependency Behavior

**Optional Dependencies Are Recommended, Not Required:**
- The experiment can be activated with or without TestExperiment
- Admin UI shows a recommendation to enable optional dependencies
- If optional dependency is later deactivated, this experiment continues working (with basic functionality)

## Use Cases

Optional dependencies are useful for:

### Example 1: Feature Enhancement

```php
class EmailBulkValidationExperiment extends AbstractExperiment {
    public function get_dependencies(): array {
        return [
            'required' => [],
            'optional' => [ 'email_address_scalar' ], // Better validation if available
        ];
    }
    
    protected function init(): void {
        add_action( 'graphql_register_types', [ $this, 'register_types' ] );
    }
    
    public function register_types(): void {
        // Check if optional dependency is active
        $has_email_scalar = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active(
            'email_address_scalar'
        );
        
        if ( $has_email_scalar ) {
            // Use EmailAddress scalar for better validation
            register_graphql_field( 'RootMutation', 'validateEmails', [
                'type' => [ 'list_of' => 'EmailAddress' ],
                // ... enhanced implementation
            ] );
        } else {
            // Fall back to String type
            register_graphql_field( 'RootMutation', 'validateEmails', [
                'type' => [ 'list_of' => 'String' ],
                // ... basic implementation
            ] );
        }
    }
}
```

### Example 2: Performance Optimization

```php
class CachingExperiment extends AbstractExperiment {
    public function get_dependencies(): array {
        return [
            'required' => [],
            'optional' => [ 'query_complexity_analysis' ], // Better caching decisions
        ];
    }
}
```

### Example 3: Cross-Feature Integration

```php
class AdvancedSearchExperiment extends AbstractExperiment {
    public function get_dependencies(): array {
        return [
            'required' => [],
            'optional' => [ 'custom_filters', 'faceted_search' ], // Enhanced if available
        ];
    }
}
```

## Known Limitations

None - this is a demonstration experiment.

## Breaking Changes

None planned - this experiment will remain as an optional dependency example.

## Graduation Plan

This experiment will **not** graduate to core. It exists only for demonstration and testing purposes.

## Related Experiments

- **TestExperiment**: This experiment's optional dependency
- **TestDependantExperiment**: Another example showing required dependencies

## For Developers

### File Structure

```
TestOptionalDependencyExperiment/
├── TestOptionalDependencyExperiment.php  # Main experiment class
└── README.md                             # This file
```

### Declaring Optional Dependencies

```php
/**
 * Define experiment dependencies.
 */
public function get_dependencies(): array {
    return [
        'required' => [],
        'optional' => [ 'test_experiment' ],
    ];
}
```

### Checking Optional Dependencies at Runtime

```php
protected function init(): void {
    add_action( 'graphql_register_types', [ $this, 'register_field' ] );
}

public function register_field(): void {
    register_graphql_field(
        'RootQuery',
        'myField',
        [
            'type'    => 'String',
            'resolve' => function() {
                // Check if optional dependency is available
                $enhanced = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active(
                    'test_experiment'
                );
                
                if ( $enhanced ) {
                    return 'Enhanced functionality!';
                }
                
                return 'Basic functionality.';
            },
        ]
    );
}
```

### Testing Optional Dependencies

```php
// Test basic functionality without optional dependency
update_option( 'graphql_experiments_settings', [
    'test-optional-dependency-experiment_enabled' => 'on',
    // test_experiment NOT enabled
] );

$query = '{ testOptionalDependency }';
$result = graphql( [ 'query' => $query ] );
$this->assertStringContains( 'Basic functionality', $result['data']['testOptionalDependency'] );

// Test enhanced functionality with optional dependency
update_option( 'graphql_experiments_settings', [
    'test-optional-dependency-experiment_enabled' => 'on',
    'test_experiment_enabled' => 'on', // Optional dependency enabled
] );

$result = graphql( [ 'query' => $query ] );
$this->assertStringContains( 'Enhanced functionality', $result['data']['testOptionalDependency'] );
```

## Admin UI Behavior

### When Viewing in Settings

**Always Enabled:**
- ✅ Checkbox is always enabled (no required dependencies)
- ✨ Shows indicator for optional dependency
- 💡 Shows recommendation: "Enhanced functionality available with: Test Experiment"

**Visual Indicators:**
- If TestExperiment is active: Shows "Enhanced functionality available with: Test Experiment"
- If TestExperiment is not active: Shows "Enhanced functionality available with: Test Experiment (not active)"

## Best Practices for Optional Dependencies

### ✅ Do:

- Check for optional dependencies at runtime
- Provide graceful degradation when optional dependencies are missing
- Document the enhanced vs. basic behavior in your README
- Test both modes (with and without optional dependencies)

### ❌ Don't:

- Assume optional dependencies are always available
- Fail or throw errors when optional dependencies are missing
- Make optional dependencies secretly required (use `required` instead)
- Create circular optional dependencies

## Feedback & Support

This is a demonstration experiment. For questions about optional dependencies:

- **Documentation**: [Creating Experiments - Dependencies](/docs/experiments-creating.md#optional-dependencies)
- **GitHub**: [WPGraphQL Issues](https://github.com/wp-graphql/wp-graphql/issues)
- **Slack**: [WPGraphQL Community](https://wpgraphql.com/community)

## References

- [What are Experiments?](/docs/experiments.md)
- [Using Experiments](/docs/experiments-using.md)
- [Creating Experiments](/docs/experiments-creating.md)
- [Optional Dependencies Pattern](/docs/experiments-creating.md#optional-dependencies)


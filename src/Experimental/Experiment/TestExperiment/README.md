# Test Experiment

**Status**: Active  
**Slug**: `test_experiment`  
**Since**: 2.3.8

## Overview

The Test Experiment is a simple demonstration experiment that shows how the WPGraphQL Experiments API works. When enabled, this experiment adds a single `testExperiment` field to the RootQuery type.

## Purpose

This experiment serves multiple purposes:

- **Educational**: A working example for developers learning to create experiments
- **Testing**: A test fixture for validating the Experiments API functionality
- **Safe exploration**: A harmless way for users to try enabling/disabling experiments

## What It Does

When activated, this experiment:

1. Registers a `testExperiment` field on the `RootQuery` type
2. Returns a simple string value: `"This is a test field for the Test Experiment."`
3. Demonstrates the basic structure and lifecycle of an experiment

## Schema Changes

### New Fields

**`RootQuery.testExperiment`**
- **Type**: `String`
- **Description**: A test field for the Test Experiment.
- **Resolver**: Returns a static string

### Example Query

```graphql
query {
  testExperiment
}
```

**Response:**
```json
{
  "data": {
    "testExperiment": "This is a test field for the Test Experiment."
  }
}
```

## Usage

### Enabling the Experiment

**Via WordPress Admin:**
1. Navigate to **GraphQL > Settings > Experiments**
2. Check the box next to "Test Experiment"
3. Click **Save Changes**

**Via wp-config.php:**
```php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'test_experiment' => true,
] );
```

**Via Code:**
```php
add_filter( 'wp_graphql_experiment_test_experiment_enabled', '__return_true' );
```

## Use Cases

This is a demonstration experiment with no real-world use cases. It exists solely to:

- Show developers how to structure an experiment
- Provide a safe experiment for users to test the activation/deactivation flow
- Serve as a dependency for other test experiments

## Dependencies

**Required Dependencies**: None  
**Optional Dependencies**: None

## Known Limitations

None - this is a simple demonstration experiment.

## Breaking Changes

None planned - this experiment will likely remain as a simple example.

## Graduation Plan

This experiment will **not** graduate to core. It exists only for demonstration and testing purposes.

## Related Experiments

- **TestDependantExperiment**: Depends on this experiment (demonstrates required dependencies)
- **TestOptionalDependencyExperiment**: Optionally depends on this experiment (demonstrates optional dependencies)

## For Developers

### File Structure

```
TestExperiment/
├── TestExperiment.php  # Main experiment class
└── README.md           # This file
```

### Key Methods

```php
// Experiment slug
protected static function slug(): string {
    return 'test_experiment';
}

// Experiment configuration
protected function config(): array {
    return [
        'title'       => 'Test Experiment',
        'description' => 'A test experiment for WPGraphQL...',
    ];
}

// Initialization hook
protected function init(): void {
    add_action( 'graphql_register_types', [ $this, 'register_field' ] );
}
```

### Testing

```php
// Check if the experiment is active
$is_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test_experiment' );

// Query for the field
$query = '{ testExperiment }';
$result = graphql( [ 'query' => $query ] );
```

## Feedback & Support

This is a demonstration experiment. For questions about creating your own experiments:

- **Documentation**: [Creating Experiments](/docs/experiments-creating.md)
- **GitHub**: [WPGraphQL Issues](https://github.com/wp-graphql/wp-graphql/issues)
- **Slack**: [WPGraphQL Community](https://wpgraphql.com/community)

## References

- [What are Experiments?](/docs/experiments.md)
- [Using Experiments](/docs/experiments-using.md)
- [Creating Experiments](/docs/experiments-creating.md)
- [Contributing Experiments](/docs/experiments-contributing.md)


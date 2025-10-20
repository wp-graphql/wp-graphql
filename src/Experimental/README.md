# WPGraphQL Experiments API

WPGraphQL Experiments API is an API that allows us to experiment with bleeding-edge features inside the WPGraphQL plugin.

## Current Experiments

| Name | Slug | Description | Docs |
|------|------|-------------|------|
| Test Experiment | `test_experiment` | A Test experiment that registers the `RootQuery.testExperiment` field to the schema | [README](Experiment/TestExperiment/README.md) |
| Test Dependant Experiment | `test-dependant-experiment` | Demonstrates required experiment dependencies | [README](Experiment/TestDependantExperiment/README.md) |
| Test Optional Dependency Experiment | `test-optional-dependency-experiment` | Demonstrates optional experiment dependencies | [README](Experiment/TestOptionalDependencyExperiment/README.md) |

## How it works

Experiments are registered by extending the `WPGraphQL\Experimental\Experiment\AbstractExperiment` class. Each experiment should be organized in its own directory with at least two files:

### Directory Structure

```
src/Experimental/Experiment/
├── AbstractExperiment.php       # Base class for all experiments
├── YourExperiment/
│   ├── YourExperiment.php      # Main experiment class (required)
│   └── README.md               # Documentation (required)
└── TestExperiment/
    ├── TestExperiment.php
    └── README.md
```

### Required Methods

Each experiment class must implement:

- `slug()`: The unique slug for the experiment
- `config()`: The experiment configuration (title, description)
- `init()`: The initialization method where you add filters, actions, etc.

### Optional Methods

- `get_dependencies()`: Define required and optional experiment dependencies
- `get_activation_message()`: Custom message shown when experiment is activated
- `get_deactivation_message()`: Custom message shown when experiment is deactivated

## Enabling an experiment

Experiments can be enabled via the WPGraphQL settings page, under the `Experiments 🧪` tab.

Alternatively, individual experiments can be toggled programmatically using the `GRAPHQL_EXPERIMENTAL_FEATURES` constant, which is an array of experiment slugs as keys and the boolean state as values.

```php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
		'my-experiment' => true, // Will be enabled.
		'another-experiment' => false, // Will be disabled.
] );
```

Experiments can be disabled altogether by setting the `GRAPHQL_EXPERIMENTAL_FEATURES` constant to an empty array or `false`.

```php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', false );
```

You can also manually enable a specific experiment with the `wp_graphql_experiment_enabled` or `wp_graphql_experiment_{$slug}_enabled` filters

```php
add_filter( 'wp_graphql_experiment_my-experiment_enabled', '__return_true' );

// Or
add_filter( 'wp_graphql_experiment_enabled' , function( $enabled, $slug ) {
	if ( 'test-experiment' === $slug ) {
		return true;
	}
	return $enabled;
}, 10, 2 );

```

# WPGraphQL Experiments API

WPGraphQL Experiments API is an API that allows us to experiment with bleeding-edge features inside the WPGraphQL plugin.

## Current Experiments

| Name | Slug | Description |
|------|------|-------------|
| Test Experiment | `test_experiment` | A Test experiment that registers the `RootQuery.testExperiment` field to the schema |

## How it works

Experiments are registered by extending the `WPGraphQL\Experimental\Experiment\AbstractExperiment` class. This class has a few methods that need to be implemented:

- `slug()`: The unique slug for the experiment.
- `config()`: The experiment configuration. Consists of the `title` and `description` used in the Admin UI toggle.
- `init()`: The method that is called when the experiment is initialized. This is the entrypoint to the experiment's functionality, i.e. where you would add filters, actions, etc.

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

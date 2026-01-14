<!--
🚨 Please review the guidelines for contributing to this repository: https://github.com/wp-graphql/wp-graphql/blob/master/docs/CONTRIBUTING.md

### Your checklist for this new experiment pull request
- [ ] Make sure your PR title follows Conventional Commit standards (use `feat(experiments):` prefix). See: https://www.conventionalcommits.org/en/v1.0.0/#specification
- [ ] Make sure you are making a pull request against the **master branch** (left side). Also you should start *your branch* off *our master*.
- [ ] Make sure you are requesting to pull request from a **topic/feature/bugfix branch** (right side). Don't pull request from your master!
- [ ] Experiment has been approved via GitHub issue (link to proposal)
- [ ] Experiment class extends AbstractExperiment
- [ ] Experiment is registered in ExperimentRegistry
- [ ] All new functionality is covered by tests
- [ ] Experiment can be enabled/disabled without errors
- [ ] No breaking changes to core when experiment is disabled
-->

## What new experiment does this implement? Explain your changes.

<!--
Please provide a clear description of:
- What experiment this implements
- Link to the approved experiment proposal issue
- What new functionality this adds
- How users will interact with it
- What changes to the GraphQL schema (if any)
-->

## Related Experiment Proposal

<!--
### Link to the approved experiment proposal
**Issue**: #XXXX (link to the GitHub issue where this experiment was proposed and approved)

### Experiment Details
- **Experiment Slug**: `experiment-slug-name`
- **Experiment Title**: "Human Readable Title"
- **Experiment Description**: Brief description of what this experiment does
-->

## Implementation Summary

<!--
### Experiment Class
- [ ] Created `{ExperimentName}Experiment` class extending `AbstractExperiment`
- [ ] Implemented required methods (`get_config()`, `init()`, etc.)
- [ ] Added proper PHPDoc documentation
- [ ] Registered in `ExperimentRegistry::register_experiments()`

### Core Changes
- [ ] New GraphQL types/fields (when experiment is enabled)
- [ ] New resolvers/loaders
- [ ] New mutations (if applicable)
- [ ] Configuration options
- [ ] Database changes (if any)

### Schema Changes (when enabled)
```graphql
# New types that appear when experiment is active
type NewType {
  field: String
}

# New fields on existing types
type ExistingType {
  newField: String
}
```
-->

## Testing Strategy

<!--
### Test Coverage
- [ ] Unit tests for experiment class
- [ ] Integration tests for GraphQL queries (when enabled)
- [ ] Tests for experiment activation/deactivation
- [ ] Edge case testing
- [ ] Performance testing (if applicable)
- [ ] Tests verify no core functionality breaks when disabled

### Test Examples
```graphql
# Example queries that work when experiment is enabled
query TestExperimentFeature {
  # ...
}
```
-->

## Experiment Lifecycle

<!--
### Activation/Deactivation
- [ ] Experiment can be enabled via WordPress admin
- [ ] Experiment can be enabled via `GRAPHQL_EXPERIMENTAL_FEATURES` constant
- [ ] Experiment can be enabled via `graphql_experimental_features_override` filter
- [ ] Disabling experiment returns site to stable behavior
- [ ] No errors when toggling experiment on/off

### Breaking Changes
Since experiments can have breaking changes, document any:
- [ ] Schema changes that break existing queries
- [ ] Behavior changes that affect existing functionality
- [ ] Migration path for users (if applicable)
-->

## Documentation Updates

<!--
### Documentation Changes
- [ ] Inline code documentation (PHPDoc blocks)
- [ ] Experiment README (if applicable)
- [ ] User-facing documentation for enabling/using the experiment
- [ ] Examples of GraphQL queries
- [ ] Known limitations or caveats

### Future Considerations
- [ ] Graduation plan (how this might become a core feature)
- [ ] Deprecation plan (if this might be removed)
- [ ] Community feedback collection strategy
-->

## Additional Context

<!--
Please add any additional context that would be helpful:
- Screenshots of the experiment in action (GraphiQL IDE, admin settings)
- Performance impact analysis
- Security considerations
- Dependencies on other experiments (if any)
- Community feedback received during proposal phase
-->

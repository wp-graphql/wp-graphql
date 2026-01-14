<!--
🚨 Please review the guidelines for contributing to this repository: https://github.com/wp-graphql/wp-graphql/blob/master/docs/CONTRIBUTING.md

### Your checklist for this experiment update pull request
- [ ] Make sure your PR title follows Conventional Commit standards (use `feat(experiments):` or `fix(experiments):` prefix). See: https://www.conventionalcommits.org/en/v1.0.0/#specification
- [ ] Make sure you are making a pull request against the **master branch** (left side). Also you should start *your branch* off *our master*.
- [ ] Make sure you are requesting to pull request from a **topic/feature/bugfix branch** (right side). Don't pull request from your master!
- [ ] Changes are backwards compatible with existing experiment usage
- [ ] All new functionality is covered by tests
- [ ] Existing tests still pass
- [ ] Experiment can still be enabled/disabled without errors
- [ ] No breaking changes to core when experiment is disabled
-->

## What experiment does this update? Explain your changes.

<!--
Please provide a clear description of:
- Which existing experiment this updates
- What changes you're making
- Why these changes are needed
- How this affects existing users of the experiment
-->

## Related Experiment

<!--
### Link to the original experiment
**Original PR**: #XXXX (link to the PR where this experiment was first implemented)
**Experiment Slug**: `existing-experiment-slug`
**Experiment Title**: "Existing Experiment Title"

### Change Justification
- [ ] Bug fix for existing experiment functionality
- [ ] Performance improvement
- [ ] New feature within the experiment
- [ ] API refinement based on community feedback
- [ ] Breaking change (documented and justified)
-->

## Changes Made

<!--
### What Changed
- [ ] Experiment class modifications
- [ ] New GraphQL types/fields
- [ ] Modified existing GraphQL types/fields
- [ ] New resolvers/loaders
- [ ] Modified existing resolvers/loaders
- [ ] Configuration changes
- [ ] Documentation updates

### Breaking Changes (if any)
Since experiments can have breaking changes, document any:
- [ ] Schema changes that break existing queries
- [ ] Behavior changes that affect existing functionality
- [ ] Migration path for users
- [ ] Deprecation timeline (if applicable)
-->

## Backwards Compatibility

<!--
### Compatibility Strategy
- [ ] Changes are backwards compatible
- [ ] Breaking changes are clearly documented
- [ ] Migration guide provided (if breaking changes)
- [ ] Deprecation warnings added (if applicable)
- [ ] Grace period provided for breaking changes

### User Impact
How will this affect users who currently have this experiment enabled?
-->

## Testing Strategy

<!--
### Test Coverage
- [ ] Unit tests for modified experiment functionality
- [ ] Integration tests for GraphQL queries
- [ ] Tests for backwards compatibility
- [ ] Tests for breaking changes (if any)
- [ ] Regression tests to prevent issues from returning
- [ ] Tests verify no core functionality breaks when disabled

### Test Examples
```graphql
# Example queries that work with the updated experiment
query TestUpdatedExperimentFeature {
  # ...
}
```
-->

## Documentation Updates

<!--
### Documentation Changes
- [ ] Updated inline code documentation (PHPDoc blocks)
- [ ] Updated user-facing documentation
- [ ] Updated GraphQL query examples
- [ ] Updated known limitations or caveats
- [ ] Migration guide (if breaking changes)

### Community Communication
- [ ] Breaking changes announced in release notes
- [ ] Migration timeline communicated
- [ ] Community feedback incorporated
-->

## Additional Context

<!--
Please add any additional context that would be helpful:
- Screenshots showing before/after behavior
- Performance impact analysis
- Security considerations
- Community feedback that led to these changes
- Future plans for this experiment
-->

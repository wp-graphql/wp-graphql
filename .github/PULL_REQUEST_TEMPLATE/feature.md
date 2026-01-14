<!--
🚨 Please review the guidelines for contributing to this repository: https://github.com/wp-graphql/wp-graphql/blob/master/docs/CONTRIBUTING.md

### Your checklist for this feature pull request
- [ ] Make sure your PR title follows Conventional Commit standards (use `feat:` prefix). See: https://www.conventionalcommits.org/en/v1.0.0/#specification
- [ ] Make sure you are making a pull request against the **master branch** (left side). Also you should start *your branch* off *our master*.
- [ ] Make sure you are requesting to pull request from a **topic/feature/bugfix branch** (right side). Don't pull request from your master!
- [ ] Feature has been discussed and approved (link to issue/discussion)
- [ ] All new functionality is covered by tests
- [ ] Documentation has been updated (if applicable)
- [ ] No breaking changes (or breaking changes are documented and justified)
-->

## What feature does this implement? Explain your changes.

<!--
Please provide a clear description of:
- What new functionality this adds
- Why this feature is needed
- How users will interact with it
- What changes to the GraphQL schema (if any)
-->

## Does this close any currently open issues?

<!--
### Write "closes #{issue number}" or "relates to #{issue number}"
### see: https://docs.github.com/en/issues/tracking-your-work-with-issues/linking-a-pull-request-to-an-issue#linking-a-pull-request-to-an-issue-using-a-keyword
-->

## Feature Design

<!--
### Schema Changes
If this adds new types, fields, or mutations, please document them:

```graphql
# New types
type NewType {
  field: String
}

# New fields on existing types
type ExistingType {
  newField: String
}

# New mutations
type Mutation {
  newMutation(input: NewInput!): NewPayload
}
```

### User Experience
How will users discover and use this feature?
-->

## Implementation Details

<!--
### Key Components Added/Modified
- [ ] New GraphQL types/fields
- [ ] New resolvers
- [ ] New data loaders
- [ ] New mutations
- [ ] Database changes (if any)
- [ ] Configuration options (if any)

### Performance Considerations
- [ ] Database query optimization
- [ ] Caching strategy
- [ ] Memory usage impact
- [ ] Query complexity analysis
-->

## Testing Strategy

<!--
### Test Coverage
- [ ] Unit tests for new resolvers/loaders
- [ ] Integration tests for GraphQL queries
- [ ] Edge case testing
- [ ] Performance testing (if applicable)
- [ ] Backwards compatibility testing

### Test Examples
```graphql
# Example queries that should work
query TestNewFeature {
  # ...
}
```
-->

## Documentation Updates

<!--
### Documentation Changes
- [ ] README updates (if applicable)
- [ ] Code documentation (PHPDoc blocks)
- [ ] User-facing documentation
- [ ] Migration guide (if breaking changes)
- [ ] Changelog entry

### Breaking Changes
If this introduces breaking changes, please document:
- What is changing
- Why the change is necessary
- Migration path for users
- Timeline for deprecation (if applicable)
-->

## Additional Context

<!--
Please add any additional context that would be helpful:
- Screenshots of the feature in action
- Performance benchmarks
- Security considerations
- Future enhancement possibilities
- Related features or dependencies
-->

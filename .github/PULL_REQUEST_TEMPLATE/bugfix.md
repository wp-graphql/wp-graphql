<!--
🚨 Please review the guidelines for contributing to this repository: https://github.com/wp-graphql/wp-graphql/blob/develop/.github/CONTRIBUTING.md

### Your checklist for this bugfix pull request
- [ ] Make sure your PR title follows Conventional Commit standards (use `fix:` prefix). See: https://www.conventionalcommits.org/en/v1.0.0/#specification
- [ ] Make sure you are making a pull request against the **develop branch** (left side). Also you should start *your branch* off *our develop*.
- [ ] Make sure you are requesting to pull request from a **topic/feature/bugfix branch** (right side). Don't pull request from your master!
- [ ] Include a failing test that reproduces the bug (if possible)
- [ ] Include the fix that makes the test pass
- [ ] All existing tests should still pass
- [ ] Add any new tests needed to prevent regression
-->

## What bug does this fix? Explain your changes.

<!--
Please provide a clear description of:
- What the bug was
- How it was manifesting (error messages, unexpected behavior, etc.)
- What the root cause was
- How your fix addresses it
-->

## Does this close any currently open issues?

<!--
### Write "closes #{issue number}"
### see: https://docs.github.com/en/issues/tracking-your-work-with-issues/linking-a-pull-request-to-an-issue#linking-a-pull-request-to-an-issue-using-a-keyword
-->

## Testing Strategy

<!--
For bugfixes, we prefer to see the following commit structure when possible:

1. **First commit**: Add a failing test that reproduces the bug
2. **Second commit**: Implement the fix that makes the test pass
3. **Additional commits**: Any cleanup, documentation, or additional tests

This approach proves the bug exists and that your fix actually resolves it.
-->

### Test Results

- [ ] **Failing test added**: Commit that demonstrates the bug with a failing test
- [ ] **Fix implemented**: Commit that resolves the bug and makes tests pass
- [ ] **All tests passing**: All existing tests continue to pass
- [ ] **Regression tests**: Added tests to prevent the bug from returning

### Test Links (if applicable)

<!--
If you have CI/CD test results, please link them here:
- Failing tests: [link to failing CI run]
- Passing tests: [link to passing CI run]
-->

## Before/After Examples

<!--
If applicable, show the difference between before and after your fix:

### Before (Buggy Behavior):
```graphql
# Example query that was failing
query {
  # ...
}
# Result: Error or unexpected behavior
```

### After (Fixed Behavior):
```graphql
# Same query now works correctly
query {
  # ...
}
# Result: Expected behavior
```
-->

## Additional Context

<!--
Please add any additional context that would be helpful:
- Screenshots of the GraphiQL IDE showing the bug/fix
- Error logs or stack traces
- Performance impact (if any)
- Breaking changes (if any)
- Migration notes (if needed)
-->

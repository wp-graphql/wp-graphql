# Experiment Proposal: Email Address Scalar Fields

## Experiment Title  
Email Address Scalar Fields

## Problem Statement

**Who experiences this problem:**
All WPGraphQL users working with User, Commenter, CommentAuthor, and GeneralSettings types.

**When they experience it:**
- Querying user email addresses (User.email)
- Accessing comment author emails (Commenter.email, CommentAuthor.email)
- Retrieving site admin email (GeneralSettings.email)
- Creating/updating users with email inputs

**Why current solutions are inadequate:**
Currently, all email fields in WPGraphQL are String types:
- No automatic validation at the GraphQL layer
- No type safety for email addresses
- Tools and IDEs can't understand these are email fields
- Inconsistent with the semantic meaning of the data
- Developers must implement custom validation

While WordPress validates emails internally, this validation isn't exposed through the GraphQL schema, creating a disconnect between WordPress's data model and the GraphQL representation.

## Proposed Solution

Update core WPGraphQL types to use the `EmailAddress` scalar for email fields:

**New Fields (using EmailAddress scalar):**
- `User.emailAddress` → EmailAddress
- `Commenter.emailAddress` → EmailAddress  
- `CommentAuthor.emailAddress` → EmailAddress
- `GeneralSettings.adminEmail` → EmailAddress
- `CommentToCommenterConnectionEdge.emailAddress` → EmailAddress

**New Mutation Inputs:**
- `CreateUserInput.emailAddress` → EmailAddress
- `UpdateUserInput.emailAddress` → EmailAddress
- `RegisterUserInput.emailAddress` → EmailAddress

**Deprecated Fields (maintained for backward compatibility):**
- `User.email` → String (deprecated)
- `Commenter.email` → String (deprecated)
- `CommentAuthor.email` → String (deprecated)
- `GeneralSettings.email` → String (deprecated)
- Mutation inputs `.email` → String (deprecated)

**How Users Will Interact:**
```graphql
# New way (recommended)
query {
  user(id: "dXNlcjox") {
    emailAddress  # EmailAddress scalar with validation
  }
}

# Old way (still works, deprecated)
query {
  user(id: "dXNlcjox") {
    email  # String type, shows deprecation warning
  }
}

# Mutations accept both inputs
mutation {
  createUser(input: {
    username: "newuser"
    emailAddress: "user@example.com"  # Validated
  }) {
    user { emailAddress }
  }
}
```

## Why an Experiment?

**Breaking Change Considerations:**
While technically non-breaking (old fields remain), this represents a significant schema change that needs validation:
- Introduces dual-field pattern (old + new)
- Changes field types for a commonly-used data type
- Affects multiple core types simultaneously  
- Requires migration path for consumers

**Community Input Needed On:**
1. **Deprecation Strategy**: Is the dual-field approach acceptable?
2. **Migration Timeline**: How long should deprecated fields remain?
3. **Error Handling**: Are validation errors appropriate for these fields?
4. **Performance**: Impact of validation on large user/comment datasets?
5. **Backward Compatibility**: Does this break any common use cases?

**Design Decisions:**
- Should both inputs be accepted in mutations indefinitely?
- Should we throw errors if both old and new fields are provided?
- How should we handle the GeneralSettings.email → adminEmail rename?
- Should deprecation warnings be visible by default?

## Implementation Plan

- [x] Create EmailAddressScalarFieldsExperiment class
- [x] Add emailAddress fields to User, Commenter, CommentAuthor types
- [x] Add adminEmail field to GeneralSettings
- [x] Add emailAddress inputs to user mutations
- [x] Implement deprecated field handling with warnings
- [x] Add dual-input support with conflict detection
- [x] Write comprehensive tests
- [x] Document migration path  
- [x] Create experiment README with examples
- [ ] Gather community feedback on deprecation approach
- [ ] Test with popular WPGraphQL extensions
- [ ] Monitor performance impact
- [ ] Collect migration stories from early adopters

## Success Criteria

**For Graduation:**
1. **Community Adoption**: 10+ sites successfully using the new fields in production
2. **Migration Success**: Community successfully migrates without issues
3. **Extension Compatibility**: Major extensions work with new fields
4. **Performance**: No significant performance degradation reported
5. **Consensus**: Agreement that dual-field deprecation is the right approach
6. **Stability**: No critical issues over 3+ releases

**Metrics to Track:**
- Number of sites using the experiment
- Deprecation warning frequency (indicates migration progress)
- Performance benchmarks (query times)
- Community feedback sentiment
- Extension compatibility reports

## Migration Plan

**Phase 1: Experiment (Now)**
- Both old and new fields available
- Deprecation warnings with GRAPHQL_DEBUG
- Community testing and feedback

**Phase 2: If Graduated (Future Release)**
- New fields become official
- Old fields remain with deprecation notices
- Documentation emphasizes new fields
- Extensions encouraged to update

**Phase 3: Deprecation (WPGraphQL 3.0)**
- Old email String fields removed
- Only EmailAddress fields remain
- Clear upgrade guide provided

**Migration Steps for Consumers:**
1. Enable experiments to test
2. Update queries to use `emailAddress` fields
3. Update `GeneralSettings.email` → `GeneralSettings.adminEmail`
4. Update mutation inputs to use `emailAddress`
5. Test thoroughly
6. Deploy when ready

**Migration Helper:**
```php
// Helper method for handling both inputs
$email = EmailAddressScalarFieldsExperiment::resolve_email_input( $input );
```

## Open Questions

1. **Naming**: Is `adminEmail` better than `adminEmailAddress` for GeneralSettings?
2. **Transition Period**: How many releases should deprecated fields remain?
3. **Error vs Warning**: Should using deprecated fields be an error or just a warning?
4. **Mutation Conflict**: Current behavior throws error if both email/emailAddress provided - is this correct?
5. **Model Changes**: Should we update User/CommentAuthor models to use EmailAddress properties?
6. **Extension Impact**: Which popular extensions need updates?

## Feedback Requested

**From the Community:**
- **Does this solve a real problem for you?** Do you need type-safe email fields?
- **Migration concerns?** Is the dual-field approach acceptable?
- **Breaking changes?** Would this break your implementation?
- **Timeline?** How long do you need to migrate?
- **Extensions?** Which extensions need to support this?

**Testing Needed:**
- Test with headless WordPress setups
- Test with WPGraphQL extensions (WooCommerce, ACF, etc.)
- Test with large user/comment databases
- Test migration from old to new fields
- Benchmark performance impact

## Related

- Depends on: "Email Address Scalar" experiment (must be active)
- PR #3423: Original implementation
- Experiments API PR #3428

## Dependencies

**Required Experiments:**
- `email-address-scalar` (provides the EmailAddress scalar type)

This experiment will not load unless the base scalar experiment is active.

## Checklist

- [x] I have searched for similar experiment proposals
- [x] This is a proposed core feature that needs real-world validation
- [x] This involves breaking changes that need community feedback  
- [x] This is a controversial feature that needs community input (deprecation strategy)
- [x] This needs testing at scale (performance with large datasets)

---

**Status**: ✅ Implementation Complete, Ready for Community Feedback  
**Branch**: `scalar/email`  
**Experiment Slug**: `email-address-scalar-fields`  
**Depends On**: `email-address-scalar`


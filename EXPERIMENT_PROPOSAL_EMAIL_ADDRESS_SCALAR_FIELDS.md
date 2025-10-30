# Experiment Proposal: Email Address Scalar Fields

> 📖 **See also**: [PR #3423](https://github.com/wp-graphql/wp-graphql/pull/3423) - Original implementation with additional context

## Experiment Title  
Email Address Scalar Fields

## Problem Statement

**The Problem:**

WPGraphQL currently represents all email addresses as generic `String` types across core types (User, Commenter, CommentAuthor, GeneralSettings). This loses critical semantic meaning because email addresses are not arbitrary strings - they are structured data with well-defined international standards ([RFC 5322](https://tools.ietf.org/html/rfc5322), [HTML Email Specification](https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address)).

By using `String` instead of a dedicated `EmailAddress` scalar type, the schema fails to communicate that these fields conform to email address standards, preventing tools and clients from leveraging this domain knowledge for validation, code generation, and enhanced user experiences.

**Who This Affects:**

- **API Consumers**: All WPGraphQL users querying User, Commenter, CommentAuthor, or GeneralSettings types
- **Frontend Developers**: Building forms and interfaces that handle email addresses
- **Mobile App Developers**: Creating native apps that need email input fields
- **Extension Authors**: Creating plugins that extend WPGraphQL with email-related functionality

**Where This Manifests:**

- Querying user email addresses (`User.email`)
- Accessing comment author emails (`Commenter.email`, `CommentAuthor.email`)
- Retrieving site admin email (`GeneralSettings.email`)
- Creating/updating users with email inputs (`CreateUserInput.email`, etc.)

**Why Generic Strings Are Inadequate:**

**Semantic Information Loss:**
- **No Type Safety**: A `String` type accepts any text - "hello world" is as valid as "user@example.com" from the schema's perspective
- **Lost Domain Knowledge**: The schema doesn't communicate that these fields expect email addresses, forcing developers to read documentation or inspect field names
- **Tooling Blind Spots**: IDEs, code generators, and API explorers can't distinguish email fields from other strings, missing opportunities for specialized validation, formatting, and UI generation
- **Validation Disconnect**: While WordPress validates emails internally using `is_email()`, this validation is invisible at the GraphQL layer, creating a disconnect between WordPress's data model and the API schema

**Real-World Impact:**
- Frontend developers must implement their own email validation (duplicating WordPress's logic)
- Mobile apps can't automatically show email-specific keyboards
- TypeScript generators produce generic `string` types instead of branded email types
- API documentation tools can't provide email-specific guidance
- GraphQL clients can't provide email-specific input validation

## Proposed Solution

Custom scalars exist precisely to encode domain-specific knowledge into the schema itself. An `EmailAddress` scalar communicates "this field conforms to email address standards" in a machine-readable way that tools can leverage, while also providing a natural enforcement point for validation rules.

This experiment updates core WPGraphQL types to use the `EmailAddress` scalar for email fields:

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

## Hypothesis

**We believe that:**
> WPGraphQL users want core email fields (User, Commenter, CommentAuthor, GeneralSettings) to use the validated `EmailAddress` scalar instead of generic `String` types, and that our dual-field deprecation strategy (keeping old `email` String fields while adding new `emailAddress` fields) provides an acceptable migration path.

**We will know we're right when:**
- 10+ sites successfully use the new fields in production
- Community successfully migrates from old to new fields without major issues
- Major WPGraphQL extensions work with the new fields
- Positive feedback on the deprecation approach
- No significant performance degradation reported
- Community consensus that the migration timeline is reasonable

**We will know we're wrong when:**
- Community rejects the dual-field deprecation approach
- Migration burden is too high for users
- Timing conflicts with extension update cycles
- Field naming (e.g., `adminEmail` vs `adminEmailAddress`) causes confusion
- Performance issues emerge with large user/comment datasets
- The schema changes are too disruptive regardless of backward compatibility

## Benefits for API Consumers

**Type Safety:**
- Invalid emails rejected at the GraphQL layer (before WordPress processing)
- Consistent validation across all email fields
- Better error messages for invalid data

**Developer Experience:**
- **IDE Support**: Email fields properly typed in generated code (TypeScript, etc.)
- **Mobile Apps**: Automatic email keyboard on native inputs
- **Form Libraries**: Built-in email validation without custom code
- **API Documentation**: Clear indication that field expects email format

**Example: Before vs After for TypeScript Code Generation**

Before (String):
```typescript
interface User {
  email: string;  // ❌ No validation, just a string
}

// Developers must add their own validation
const isValidEmail = (email: string) => { /* custom logic */ };
```

After (EmailAddress):
```typescript
interface User {
  emailAddress: EmailAddress;  // ✅ Type-safe email
}

// Email type with built-in validation
type EmailAddress = string & { __emailAddress: true };
// Validation happens at GraphQL layer
```

## Why an Experiment?

**Is This Breaking?**
**No** - This is fully backward compatible. All existing queries and mutations continue to work exactly as before, with optional deprecation warnings in debug mode.

**Schema Change Considerations:**
While technically non-breaking (old fields remain), this represents a significant schema evolution that needs community validation:
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
- [x] Add CommentToCommenterConnectionEdge.emailAddress field
- [x] Add emailAddress inputs to user mutations (create, update, register)
- [x] Implement deprecated field handling with warnings
- [x] Add dual-input support with conflict detection
- [x] Add input normalization (copies emailAddress → email for WordPress)
- [x] Write comprehensive tests (59 tests, 194 assertions total)
- [x] Document migration path  
- [x] Create experiment README with examples
- [x] Pass all code quality checks (PHPCS, PHPStan)
- [ ] Gather community feedback on deprecation approach
- [ ] Test with popular WPGraphQL extensions
- [ ] Monitor performance impact
- [ ] Collect migration stories from early adopters

**Implementation Status:**
✅ **Complete and Ready for Testing**
- All unit tests passing (includes experiment-specific tests)
- Code quality verified (PHPCS & PHPStan)
- Full backward compatibility maintained
- Deprecation warnings implemented
- Located in: `src/Experimental/Experiment/EmailAddressScalarFieldsExperiment/`

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

**Backward Compatibility Strategy:**

The experiment implements dual-field support with graceful deprecation:

- **Queries**: Both `email` (String) and `emailAddress` (EmailAddress) fields available
- **Mutations**: Accept both `email` and `emailAddress` inputs
  - If both provided → throws error with helpful message
  - If only `emailAddress` provided → uses that value
  - If only deprecated `email` provided → works but logs deprecation warning
- **Deprecation Warnings**: Only shown when `GRAPHQL_DEBUG` is enabled
- **Future Removal**: Deprecated fields marked for removal in WPGraphQL 3.0

**Example Migration:**

```graphql
# Step 1: Current query (works today, will work during experiment)
query {
  user(id: "dXNlcjox") {
    email  # String
  }
  generalSettings {
    email  # String
  }
}

# Step 2: With experiment enabled (both work)
query {
  user(id: "dXNlcjox") {
    email  # String (deprecated, still works)
    emailAddress  # EmailAddress (new, recommended)
  }
  generalSettings {
    email  # String (deprecated)
    adminEmail  # EmailAddress (new)
  }
}

# Step 3: Migrated query (recommended)
query {
  user(id: "dXNlcjox") {
    emailAddress  # EmailAddress
  }
  generalSettings {
    adminEmail  # EmailAddress
  }
}
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


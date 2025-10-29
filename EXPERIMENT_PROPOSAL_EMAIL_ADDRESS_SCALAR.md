# Experiment Proposal: Email Address Scalar

## Experiment Title
Email Address Scalar

## Problem Statement

**Who experiences this problem:**  
GraphQL API consumers, WordPress plugin developers, and frontend applications that work with email addresses.

**When they experience it:**  
When querying or mutating email addresses in WPGraphQL, there's no built-in validation or type safety. Email fields are currently String types, which means:
- Invalid email addresses can be stored without validation
- Type information is lost (tools don't know it's an email)
- Developers must implement their own validation logic
- Inconsistent validation across different implementations

**Why current solutions are inadequate:**  
The String type provides no semantic meaning or validation. While WordPress validates emails internally (via `is_email()`), this validation isn't exposed at the GraphQL layer, leading to potential data quality issues and requiring each consumer to implement their own email validation.

## Proposed Solution

Add an `EmailAddress` scalar type to WPGraphQL that:
- Validates email format using WordPress's built-in `is_email()` function
- Sanitizes email values using `sanitize_email()`  
- Provides proper type information in the GraphQL schema
- Makes email validation consistent across all queries and mutations

**Schema Changes:**
```graphql
scalar EmailAddress
```

**What Users Will Do:**
```graphql
# Register fields using the scalar
register_graphql_field( 'MyType', 'email', [
    'type' => 'EmailAddress',
    'description' => 'Email address',
] );

# Use in queries/mutations - automatic validation
mutation {
  updateUser(input: {
    email: "user@example.com"  # Validated automatically
  }) {
    user { email }
  }
}
```

## Why an Experiment?

**Needs Validation At Scale:**
- We need to verify that WordPress's `is_email()` function covers all real-world use cases
- Need to test performance impact of validation on large datasets
- Want community feedback on whether this validation is too strict or too lenient

**Design Decisions Needing Input:**
- Should we allow filter hooks to customize validation rules?
- Should we provide a `specifiedByURL` for the email format specification?
- Should validation happen on serialize, parseValue, parseLiteral, or all three?

**Not Breaking** (when standalone):
- Adding a new scalar type doesn't break existing functionality
- Existing String fields remain unchanged

## Implementation Plan

- [x] Create EmailAddressScalarExperiment class
- [x] Implement EmailAddress scalar with validation/sanitization
- [x] Register scalar type in schema  
- [x] Add serialize, parseValue, and parseLiteral methods
- [x] Write comprehensive unit tests
- [x] Document scalar usage and behavior
- [x] Create experiment README with examples
- [ ] Gather community feedback
- [ ] Monitor performance in production use

## Success Criteria

**For Graduation:**
1. **Community Validation**: Positive feedback from 5+ production implementations over 2+ releases
2. **Stability**: No critical bugs or validation issues reported
3. **Performance**: Validation overhead < 1ms per field resolution
4. **Coverage**: Validation handles international email formats correctly
5. **Consensus**: Community agrees the validation rules are appropriate

**Metrics to Track:**
- Number of sites using the experiment
- Validation error rates
- Performance benchmarks
- Community feedback sentiment

## Migration Plan

**If Graduated:**

Since this experiment only adds a new scalar type without changing existing fields, migration is opt-in:

1. **Documentation**: Provide examples of using EmailAddress scalar in custom types
2. **Best Practices**: Recommend EmailAddress for new email fields
3. **Extension Support**: Help popular extensions adopt the scalar
4. **No Breaking Changes**: Existing String email fields remain unchanged

**Note**: See separate proposal for "Email Address Scalar Fields" experiment which would update core types to use this scalar.

## Open Questions

1. **International Support**: Does WordPress's `is_email()` handle all international domain formats (IDN, punycode)?
2. **Custom Validation**: Should we provide filters to allow stricter/looser validation?
3. **Error Messages**: Should validation errors be more specific (e.g., "missing @" vs "invalid email")?
4. **Performance**: Should we cache validation results within a request?
5. **Specification URL**: Should we add `specifiedByURL` pointing to RFC 5322 or similar?

## Feedback Requested

**From the Community:**
- Does WordPress's `is_email()` validation work for your use cases?
- Are there email formats that should be valid but aren't?
- Have you encountered performance issues with email validation?
- Would you use this scalar in your custom types?
- Should validation be configurable via filters?

**Testing Needed:**
- Test with international domain names (IDN)
- Test with plus-addressing (user+tag@domain.com)
- Test with various TLD lengths
- Benchmark performance with large result sets

## Related

- PR #3423: Original EmailAddress Scalar implementation
- Separate Proposal: "Email Address Scalar Fields" (depends on this)

## Checklist

- [x] I have searched for similar experiment proposals
- [x] This is a proposed core feature that needs real-world validation
- [x] This needs community input on validation rules
- [x] This needs testing at scale for performance

---

**Status**: ✅ Implementation Complete, Ready for Community Feedback  
**Branch**: `scalar/email`  
**Experiment Slug**: `email-address-scalar`


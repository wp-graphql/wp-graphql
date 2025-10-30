# Experiment Proposal: Email Address Scalar

> 📖 **See also**: [PR #3423](https://github.com/wp-graphql/wp-graphql/pull/3423) - Original implementation with additional context

## Experiment Title
Email Address Scalar

## Problem Statement

**The Problem:**

WPGraphQL lacks a validated scalar type for email addresses. Extension authors and developers building custom types must use generic `String` types for email fields, which provides no validation, no semantic meaning, and no type safety at the GraphQL layer.

Email addresses are structured data conforming to international standards ([RFC 5322](https://tools.ietf.org/html/rfc5322), [HTML Email Specification](https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address)), but without a dedicated scalar type, the GraphQL schema can't communicate this constraint to tooling, clients, or developers. This creates a validation gap: WordPress validates emails internally using `is_email()`, but this validation is invisible to the GraphQL API layer.

**Who This Affects:**

- **Extension Authors**: Building WPGraphQL extensions (WooCommerce, ACF, etc.) that need email fields in custom types
- **Plugin Developers**: Creating WordPress plugins that extend the GraphQL schema with email-related functionality
- **API Consumers**: Applications querying or mutating email data through WPGraphQL
- **Frontend Developers**: Building interfaces that need to validate email input before submission

**Where This Manifests:**

- Registering custom GraphQL types with email fields
- Creating mutations that accept email input
- Building extensions that need type-safe email handling
- Generating TypeScript/Flow types from the schema
- Providing email-specific validation and UI components

**Why Generic Strings Are Inadequate:**

**Validation Gap:**
- GraphQL layer accepts any string value (no validation at the API boundary)
- Validation only happens deeper in WordPress (if at all)
- Invalid emails can pass through GraphQL validation and cause errors downstream
- Each implementation must duplicate validation logic

**Lost Semantic Meaning:**
- Schema doesn't indicate fields expect email format
- Tools can't provide email-specific features (validation, keyboard types, etc.)
- Code generators produce generic `string` types instead of email types
- No standardization across custom types and extensions

**Developer Burden:**
- Must implement custom validation for every email field
- Inconsistent validation logic across different extensions
- Can't leverage WordPress's battle-tested `is_email()` function at GraphQL layer
- Each developer solving the same problem independently

**Ecosystem Fragmentation:**
- Extensions and projects resort to registering their own `EmailAddress` scalar via `register_graphql_scalar()`
- Different implementations with varying validation rules create inconsistency
- Potential naming conflicts when multiple plugins register the same scalar type
- No standardization across the WPGraphQL ecosystem
- Wasted effort as everyone reimplements the same functionality
- Breaking changes if an extension's custom scalar conflicts with a future core implementation

## Proposed Solution

Add an `EmailAddress` scalar type to WPGraphQL that:
- Validates email format using WordPress's built-in `is_email()` function
- Sanitizes email values using `sanitize_email()`  
- Provides proper type information in the GraphQL schema
- Makes email validation consistent across all queries and mutations
- Includes `specifiedByURL` linking to the [HTML email specification](https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address)

**Schema Changes:**
```graphql
"""
The EmailAddress scalar type represents a valid email address, conforming to the HTML specification and RFC 5322.
"""
scalar EmailAddress @specifiedBy(url: "https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address")
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

## Hypothesis

**We believe that:**
> Extension authors and developers building custom types want a validated `EmailAddress` scalar that uses WordPress's native email validation (`is_email()`) and provides semantic type information through GraphQL introspection, enabling better tooling support and type safety.

**We will know we're right when:**
- 5+ production implementations provide positive feedback over 2+ releases
- No critical bugs or validation issues are reported
- Extension authors adopt it for their custom email fields
- Community agrees WordPress's `is_email()` validation is appropriate
- Performance overhead is negligible (< 1ms per field resolution)

**We will know we're wrong when:**
- WordPress's `is_email()` validation is too strict or too lenient for real-world use cases
- Performance impact is unacceptable
- Community prefers alternative validation approaches
- The scalar creates more problems than it solves

## Schema Introspection & Tooling Benefits

The `EmailAddress` scalar provides rich semantic information through GraphQL's introspection system, enabling tooling to understand that a field represents an email address rather than a generic string.

> **Note:** While the GraphQL ecosystem continues to evolve support for advanced scalar features like `@specifiedBy` directives ([graphql-php#1140](https://github.com/webonyx/graphql-php/issues/1140)) and `@oneOf` input types ([GraphiQL#3768](https://github.com/graphql/graphiql/pull/3768)), the foundational introspection capabilities already enable significant tooling improvements. Even basic scalar type information allows IDEs, code generators, and API explorers to provide better developer experiences today, with even richer functionality coming as the ecosystem matures.

### Before (String Type)

```graphql
# Introspection Query
{
  __type(name: "MyType") {
    fields {
      name
      type {
        name
        description
      }
    }
  }
}

# Result - No semantic meaning for email fields
{
  "data": {
    "__type": {
      "fields": [
        {
          "name": "email",
          "type": {
            "name": "String",  # ❌ Generic string - tooling can't infer email validation
            "description": null
          }
        }
      ]
    }
  }
}
```

### After (EmailAddress Scalar)

```graphql
# Introspection Query  
{
  __type(name: "MyType") {
    fields {
      name
      type {
        name
        description
        specifiedByURL
      }
    }
  }
}

# Result - Rich semantic information
{
  "data": {
    "__type": {
      "fields": [
        {
          "name": "email", 
          "type": {
            "name": "EmailAddress",  # ✅ Semantic type information
            "description": "The EmailAddress scalar type represents a valid email address, conforming to the HTML specification and RFC 5322.",
            "specifiedByURL": "https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address"
          }
        }
      ]
    }
  }
}
```

### Tooling Benefits

**GraphQL IDEs & Explorers:**
- 🎯 **GraphiQL/Apollo Studio**: Can provide email-specific input validation and formatting
- 🎯 **Schema Documentation**: Automatically shows email format requirements and validation rules
- 🎯 **Type Hints**: IDEs can provide email-specific autocomplete and validation

**Code Generation Tools:**
- 🎯 **TypeScript/Flow**: Generate proper email validation types instead of generic strings
- 🎯 **Mobile SDKs**: Can generate email input fields with appropriate keyboard types
- 🎯 **Form Libraries**: Automatically apply email validation rules

**API Testing Tools:**
- 🎯 **Postman/Insomnia**: Recognize email fields and provide validation
- 🎯 **Test Generators**: Can create realistic test data for email fields

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
- [x] Write comprehensive unit tests (59 tests, 194 assertions)
- [x] Document scalar usage and behavior
- [x] Create experiment README with examples
- [x] Add `specifiedByURL` for email specification
- [x] Pass all code quality checks (PHPCS, PHPStan)
- [ ] Gather community feedback
- [ ] Monitor performance in production use

**Implementation Status:**
✅ **Complete and Ready for Testing**
- All unit tests passing
- Code quality verified (PHPCS & PHPStan)
- Comprehensive documentation
- Located in: `src/Experimental/Experiment/EmailAddressScalarExperiment/`

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

## Testing the Scalar

**Quick Test Setup:**

Add this to your `functions.php` or plugin:

```php
add_action(
    'graphql_register_types',
    function() {
        // Register a test field that returns a valid email
        register_graphql_field(
            'RootQuery',
            'testEmailField',
            [
                'type'        => 'EmailAddress',
                'description' => 'Test field that returns a valid email address',
                'resolve'     => static function () {
                    return 'test@example.com';
                },
            ]
        );

        // Register a test field that tries to return an invalid email
        register_graphql_field(
            'RootQuery',
            'testInvalidEmailField',
            [
                'type'        => 'EmailAddress',
                'description' => 'Test field that returns an invalid email address',
                'resolve'     => static function () {
                    return 'not-an-email';
                },
            ]
        );

        // Register a mutation that accepts an email address
        register_graphql_mutation(
            'testEmailMutation',
            [
                'inputFields'         => [
                    'email' => [
                        'type' => 'EmailAddress',
                    ],
                ],
                'outputFields'        => [
                    'email' => [
                        'type' => 'EmailAddress',
                    ],
                ],
                'mutateAndGetPayload' => static function ( $input ) {
                    return [
                        'email' => $input['email'],
                    ];
                },
            ]
        );
    }
);
```

**Test Queries:**
- Query `testEmailField` → returns email address, no error
- Query `testInvalidEmailField` → returns GraphQL error with validation message
- Execute `testEmailMutation` with valid email → success
- Execute `testEmailMutation` with invalid email → GraphQL error

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


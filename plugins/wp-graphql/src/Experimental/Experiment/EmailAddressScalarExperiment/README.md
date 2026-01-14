# Email Address Scalar Experiment

**Status**: Experimental  
**Slug**: `email-address-scalar`  
**Since**: 2.5.0

## Overview

The Email Address Scalar experiment introduces a custom `EmailAddress` scalar type that provides automatic validation and sanitization for email addresses using WordPress's built-in `is_email()` and `sanitize_email()` functions.

## Purpose

This experiment allows developers to use a dedicated EmailAddress scalar type in their custom fields, providing:

- **Automatic Validation**: Email addresses are validated using WordPress's proven `is_email()` function
- **Type Safety**: GraphQL schema clearly indicates email address fields
- **Consistent Sanitization**: All email values are sanitized using `sanitize_email()`
- **Better Tooling**: IDEs and GraphQL tools can understand the semantic meaning of email fields

## What It Does

When activated, this experiment:

1. Registers the `EmailAddress` scalar type to the GraphQL schema
2. Makes the scalar available for use in custom field registrations
3. Provides validation and sanitization for all fields using the scalar

**Note**: This experiment only registers the scalar type itself. To use the scalar in core WPGraphQL fields (like `User.emailAddress`), enable the companion experiment: `email-address-scalar-fields`.

## Schema Changes

### New Scalar Types

**`EmailAddress`**
- **Description**: Represents a valid email address, conforming to the HTML specification and RFC 5322
- **Validation**: Uses WordPress `is_email()` function
- **Sanitization**: Uses WordPress `sanitize_email()` function
- **Specified By**: https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address

## Usage

### Enabling the Experiment

**Via WordPress Admin:**
1. Navigate to **GraphQL > Settings > Experiments**
2. Check the box next to "Email Address Scalar"
3. Click **Save Changes**

**Via wp-config.php:**
```php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'email-address-scalar' => true,
] );
```

**Via Code:**
```php
add_filter( 'wp_graphql_experiment_email_address_scalar_enabled', '__return_true' );
```

### Using in Custom Fields

Once enabled, you can use the `EmailAddress` scalar in your custom field registrations:

```php
// Register a custom email field on the User type
add_action( 'graphql_register_types', function() {
    register_graphql_field( 'User', 'workEmail', [
        'type'        => 'EmailAddress',
        'description' => 'The user\'s work email address',
        'resolve'     => function( $user ) {
            return get_user_meta( $user->ID, 'work_email', true );
        }
    ] );
} );
```

**Query Example:**
```graphql
query GetUser($id: ID!) {
  user(id: $id) {
    id
    name
    workEmail # Custom field using EmailAddress scalar
  }
}
```

**Response:**
```json
{
  "data": {
    "user": {
      "id": "dXNlcjox",
      "name": "John Doe",
      "workEmail": "john.doe@company.com"
    }
  }
}
```

### Using in Custom Mutations

You can also use the `EmailAddress` scalar in mutation inputs:

```php
register_graphql_mutation( 'updateUserWorkEmail', [
    'inputFields' => [
        'userId' => [
            'type' => [ 'non_null' => 'ID' ],
        ],
        'workEmail' => [
            'type'        => 'EmailAddress',
            'description' => 'The new work email address',
        ],
    ],
    'outputFields' => [
        'success' => [ 'type' => 'Boolean' ],
        'email'   => [ 'type' => 'EmailAddress' ],
    ],
    'mutateAndGetPayload' => function( $input ) {
        $user_id = absint( $input['userId'] );
        update_user_meta( $user_id, 'work_email', $input['workEmail'] );
        
        return [
            'success' => true,
            'email'   => $input['workEmail'],
        ];
    },
] );
```

**Mutation Example:**
```graphql
mutation UpdateWorkEmail($userId: ID!, $workEmail: EmailAddress!) {
  updateUserWorkEmail(input: { userId: $userId, workEmail: $workEmail }) {
    success
    email
  }
}
```

## Validation Behavior

### Valid Email Addresses

The scalar accepts any email address that passes WordPress's `is_email()` validation:

```graphql
# ✅ Valid
"user@example.com"
"firstname.lastname@example.com"
"user+tag@example.co.uk"
"test_user@subdomain.example.com"
```

### Invalid Email Addresses

The scalar will reject invalid email addresses with a clear error message:

```graphql
# ❌ Invalid - No @ symbol
"notanemail"

# ❌ Invalid - Missing domain
"user@"

# ❌ Invalid - Invalid characters
"user name@example.com"

# ❌ Invalid - Not a string
123
```

**Error Response:**
```json
{
  "errors": [
    {
      "message": "Value is not a valid email address: notanemail"
    }
  ]
}
```

## Use Cases

### Extension Developers

Extension authors can use this scalar to ensure email validation consistency across their custom fields:

```php
// Custom post type with email field
register_graphql_field( 'Contact', 'email', [
    'type'    => 'EmailAddress',
    'resolve' => function( $contact ) {
        return get_post_meta( $contact->ID, 'contact_email', true );
    }
] );
```

### Custom User Metadata

Add validated email fields for additional user contact information:

```php
register_graphql_field( 'User', 'alternateEmail', [
    'type'    => 'EmailAddress',
    'resolve' => function( $user ) {
        return get_user_meta( $user->ID, 'alternate_email', true );
    }
] );
```

### Form Submissions

Validate email addresses in custom form submission mutations:

```php
register_graphql_mutation( 'submitContactForm', [
    'inputFields' => [
        'email'   => [ 'type' => 'EmailAddress' ],
        'message' => [ 'type' => 'String' ],
    ],
    // ... rest of mutation
] );
```

## Dependencies

**Required Dependencies**: None  
**Optional Dependencies**: None

**Related Experiments**:
- **email-address-scalar-fields**: Adds `emailAddress` fields to core WPGraphQL types (User, Commenter, etc.) using this scalar

## Known Limitations

- Uses WordPress's `is_email()` function, which may have different validation rules than other email validators
- Email validation does not verify that the email address actually exists or can receive mail
- Does not support internationalized domain names (IDN) unless WordPress core adds support

## Breaking Changes

**Current**: None - This is a purely additive change. No existing fields are modified.

**If Graduated**: If this experiment graduates to core:
- The `EmailAddress` scalar will become a permanent part of the WPGraphQL schema
- Any custom fields using this scalar will continue to work without changes
- The experiment activation/deactivation will no longer be available

## Graduation Plan

For this experiment to graduate to core, we need:

1. **Community Validation**: Positive feedback from at least 5 production implementations
2. **Stability**: No major bugs or validation issues reported over 2+ releases
3. **Performance**: Confirmed that validation doesn't introduce performance concerns
4. **Consensus**: Community agreement that WordPress's email validation is sufficient
5. **Adoption**: Extension developers actively using the scalar

## For Developers

### File Structure

```
EmailAddressScalarExperiment/
├── EmailAddressScalarExperiment.php  # Main experiment class
├── EmailAddress.php                   # Scalar type implementation
└── README.md                          # This file
```

### Key Methods

The `EmailAddress` class provides three key methods required by GraphQL:

```php
// Serialize value when sending to client
EmailAddress::serialize( $value ): string

// Parse value from client input (variables)
EmailAddress::parseValue( $value ): string

// Parse literal value from query (hardcoded)
EmailAddress::parseLiteral( $valueNode, $variables ): string
```

All three methods:
- Validate the email using `is_email()`
- Sanitize the email using `sanitize_email()`
- Throw descriptive errors for invalid inputs

### Testing

Check if the experiment is active:

```php
$is_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 
    'email-address-scalar' 
);
```

Test the scalar in queries:

```php
$query = '
    query {
        user(id: "dXNlcjox") {
            customEmailField
        }
    }
';
$result = graphql( [ 'query' => $query ] );
```

## Feedback & Support

We'd love to hear your feedback on this experiment:

- **What works well?** Share your success stories
- **What doesn't work?** Report issues or edge cases
- **What's missing?** Suggest improvements or additional features
- **Should this graduate?** Let us know if this should become a core feature

**Provide Feedback**:
- **GitHub Issues**: [WPGraphQL Issues](https://github.com/wp-graphql/wp-graphql/issues)
- **GitHub Discussions**: [Experiment Feedback](https://github.com/wp-graphql/wp-graphql/discussions)
- **Slack**: [WPGraphQL Community](https://wpgraphql.com/community)

## References

- [What are Experiments?](/docs/experiments.md)
- [Using Experiments](/docs/experiments-using.md)
- [Creating Experiments](/docs/experiments-creating.md)
- [WordPress is_email() Function](https://developer.wordpress.org/reference/functions/is_email/)
- [WordPress sanitize_email() Function](https://developer.wordpress.org/reference/functions/sanitize_email/)
- [HTML Email Specification](https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address)


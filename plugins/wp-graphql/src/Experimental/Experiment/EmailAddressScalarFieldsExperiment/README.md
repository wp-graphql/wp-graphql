# Email Address Scalar Fields Experiment

**Status**: Experimental  
**Slug**: `email-address-scalar-fields`  
**Since**: 2.5.0

## Overview

The Email Address Scalar Fields experiment adds `emailAddress` fields using the `EmailAddress` scalar type to core WPGraphQL types including User, Commenter, CommentAuthor, and GeneralSettings. It also updates mutation inputs to accept EmailAddress values.

## Purpose

This experiment provides:

- **Type Safety**: Email fields use the EmailAddress scalar instead of generic String
- **Automatic Validation**: All email inputs are validated using WordPress's `is_email()` function
- **Better Tooling**: GraphQL tools and IDEs can understand that fields represent email addresses
- **Backward Compatibility**: Existing `email` String fields remain functional with deprecation warnings

## What It Does

When activated, this experiment:

1. Adds `emailAddress` fields to User, Commenter, CommentAuthor types
2. Adds `adminEmail` field to GeneralSettings
3. Adds `emailAddress` input fields to user mutation inputs
4. Maintains deprecated `email` String fields for backward compatibility
5. Validates all email inputs using the EmailAddress scalar

## Dependencies

**Required**: This experiment requires the `email-address-scalar` experiment to be active.

If you try to activate this experiment without `email-address-scalar`, it will not load.

## Schema Changes

### New Fields

**`User.emailAddress`**
- **Type**: `EmailAddress`
- **Description**: Email address of the user
- **Replaces**: `User.email` (String, now deprecated)

**`Commenter.emailAddress`**
- **Type**: `EmailAddress`  
- **Description**: Email address of the comment author (restricted to moderators)
- **Replaces**: `Commenter.email` (String, now deprecated)

**`CommentAuthor.emailAddress`**
- **Type**: `EmailAddress`
- **Description**: Email address of the comment author (restricted to moderators)  
- **Replaces**: `CommentAuthor.email` (String, now deprecated)

**`GeneralSettings.adminEmail`**
- **Type**: `EmailAddress`
- **Description**: Email address of the site administrator (restricted to admins)
- **Replaces**: `GeneralSettings.email` (String, now deprecated)

**`CommentToCommenterConnectionEdge.emailAddress`**
- **Type**: `EmailAddress`
- **Description**: Email address for the comment author in this connection

### New Input Fields

**`CreateUserInput.emailAddress`**
- **Type**: `EmailAddress`
- **Description**: The user's email address
- **Replaces**: `CreateUserInput.email` (String, now deprecated)

**`UpdateUserInput.emailAddress`**
- **Type**: `EmailAddress`
- **Description**: The user's email address  
- **Replaces**: `UpdateUserInput.email` (String, now deprecated)

**`RegisterUserInput.emailAddress`**
- **Type**: `EmailAddress`
- **Description**: The user's email address
- **Replaces**: `RegisterUserInput.email` (String, now deprecated)

### Deprecated Fields

All existing `email` String fields remain functional but show deprecation warnings:

- `User.email` → Use `User.emailAddress`
- `Commenter.email` → Use `Commenter.emailAddress`
- `CommentAuthor.email` → Use `CommentAuthor.emailAddress`
- `GeneralSettings.email` → Use `GeneralSettings.adminEmail`
- `CommentToCommenterConnectionEdge.email` → Use `.emailAddress`
- `CreateUserInput.email` → Use `.emailAddress`
- `UpdateUserInput.email` → Use `.emailAddress`
- `RegisterUserInput.email` → Use `.emailAddress`

## Usage

### Enabling the Experiment

**Prerequisites**: First enable the `email-address-scalar` experiment.

**Via WordPress Admin:**
1. Navigate to **GraphQL > Settings > Experiments**
2. Enable "Email Address Scalar" (if not already enabled)
3. Enable "Email Address Scalar Fields"
4. Click **Save Changes**

**Via wp-config.php:**
```php
define( 'GRAPHQL_EXPERIMENTAL_FEATURES', [
    'email-address-scalar'        => true,
    'email-address-scalar-fields' => true,
] );
```

**Via Code:**
```php
add_filter( 'wp_graphql_experiment_email_address_scalar_enabled', '__return_true' );
add_filter( 'wp_graphql_experiment_email_address_scalar_fields_enabled', '__return_true' );
```

### Query Examples

**Query User Email:**
```graphql
query GetUser($id: ID!) {
  user(id: $id) {
    id
    name
    emailAddress  # New field using EmailAddress scalar
    email         # Deprecated but still works
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
      "emailAddress": "john@example.com",
      "email": "john@example.com"
    }
  }
}
```

**Query Comment Author Email:**
```graphql
query GetComments {
  comments(first: 5) {
    nodes {
      id
      content
      author {
        node {
          name
          emailAddress  # Only visible to moderators
        }
      }
    }
  }
}
```

**Query Site Admin Email:**
```graphql
query GetSiteSettings {
  generalSettings {
    title
    url
    adminEmail  # Only visible to site administrators
    email       # Deprecated but still works
  }
}
```

### Mutation Examples

**Create User with Email:**
```graphql
mutation CreateUser($input: CreateUserInput!) {
  createUser(input: $input) {
    user {
      id
      name
      emailAddress
    }
  }
}
```

**Variables:**
```json
{
  "input": {
    "username": "newuser",
    "emailAddress": "newuser@example.com",
    "password": "securePassword123"
  }
}
```

**Update User Email:**
```graphql
mutation UpdateUser($input: UpdateUserInput!) {
  updateUser(input: $input) {
    user {
      id
      emailAddress
    }
  }
}
```

**Variables:**
```json
{
  "input": {
    "id": "dXNlcjox",
    "emailAddress": "updated@example.com"
  }
}
```

**Register New User:**
```graphql
mutation RegisterUser($input: RegisterUserInput!) {
  registerUser(input: $input) {
    user {
      id
      name
      emailAddress
    }
  }
}
```

**Variables:**
```json
{
  "input": {
    "username": "registereduser",
    "emailAddress": "user@example.com"
  }
}
```

## Backward Compatibility

### Dual Input Support

Mutations accept both `email` (deprecated) and `emailAddress` (new) inputs:

```graphql
# ✅ New way (recommended)
mutation {
  createUser(input: {
    username: "user1"
    emailAddress: "user1@example.com"
  }) {
    user { id }
  }
}

# ✅ Old way (deprecated but still works)
mutation {
  createUser(input: {
    username: "user2"
    email: "user2@example.com"
  }) {
    user { id }
  }
}
```

### Conflict Detection

If both `email` and `emailAddress` are provided, an error is thrown:

```graphql
# ❌ Error: Cannot provide both fields
mutation {
  createUser(input: {
    username: "user3"
    email: "old@example.com"
    emailAddress: "new@example.com"
  }) {
    user { id }
  }
}
```

**Error Response:**
```json
{
  "errors": [
    {
      "message": "Cannot provide both \"email\" and \"emailAddress\" fields. Please use \"emailAddress\" as \"email\" is deprecated."
    }
  ]
}
```

### Deprecation Warnings

When deprecated `email` fields are queried or used in mutations (with `GRAPHQL_DEBUG` enabled), warnings are logged:

```
WPGraphQL: The field "User.email" is deprecated since version 2.5.0 and will be removed in 3.0. Use "User.emailAddress" instead.
```

## Migration Guide

### For Query Consumers

**Step 1**: Update your queries to use new `emailAddress` fields:

```graphql
# Before
query {
  user(id: "...") {
    email
  }
}

# After
query {
  user(id: "...") {
    emailAddress
  }
}
```

**Step 2**: Update GeneralSettings queries:

```graphql
# Before
query {
  generalSettings {
    email
  }
}

# After  
query {
  generalSettings {
    adminEmail
  }
}
```

### For Mutation Consumers

**Step 1**: Update mutation inputs to use `emailAddress`:

```graphql
# Before
mutation {
  createUser(input: {
    username: "user"
    email: "user@example.com"
  }) { ... }
}

# After
mutation {
  createUser(input: {
    username: "user"
    emailAddress: "user@example.com"
  }) { ... }
}
```

### For Extension Developers

If your extension uses `UserMutation::prepare_user_object()` or handles user email inputs, update to use the helper method:

```php
use WPGraphQL\Experimental\Experiment\EmailAddressScalarFieldsExperiment\EmailAddressScalarFieldsExperiment;

// Resolve email from either field
$email = EmailAddressScalarFieldsExperiment::resolve_email_input( $input );
```

## Known Limitations

- Requires both experiments to be active (`email-address-scalar` and `email-address-scalar-fields`)
- Deprecated fields will be removed in WPGraphQL v3.0
- Email validation is limited to WordPress's `is_email()` function capabilities

## Breaking Changes

**Current**: None - This is fully backward compatible. All existing queries and mutations continue to work with optional deprecation warnings.

**Future (v3.0)**: If graduated, deprecated `email` String fields will be removed:
- `User.email` → Removed
- `Commenter.email` → Removed  
- `CommentAuthor.email` → Removed
- `GeneralSettings.email` → Removed
- Mutation inputs `email` → Removed

## Graduation Plan

For this experiment to graduate to core:

1. **Community Validation**: Positive feedback from 5+ production implementations
2. **Stability**: No breaking issues over 2+ releases
3. **Migration Success**: Community successfully migrates from deprecated fields
4. **Consensus**: Agreement on the dual-field deprecation approach
5. **Performance**: No significant performance concerns reported

**If Graduated**:
- New `emailAddress` fields become permanent
- Deprecated `email` fields remain until v3.0 with clear migration path
- Helper methods move to core utilities

## Use Cases

### Building a User Directory

```graphql
query GetAllUsers {
  users(first: 100) {
    nodes {
      id
      name
      emailAddress  # Validated email addresses
    }
  }
}
```

### Comment Moderation

```graphql
query GetPendingComments {
  comments(where: { status: HOLD }) {
    nodes {
      id
      content
      author {
        node {
          name
          emailAddress  # For contacting comment authors
        }
      }
    }
  }
}
```

### Site Administration

```graphql
query GetSiteConfig {
  generalSettings {
    title
    url
    adminEmail  # Site administrator contact
  }
}
```

## For Developers

### Helper Method

The experiment provides a static helper method for resolving email inputs:

```php
EmailAddressScalarFieldsExperiment::resolve_email_input( $input );
```

This method:
- Checks for both `email` and `emailAddress` in input
- Throws error if both are provided
- Logs deprecation warning if `email` is used
- Returns the appropriate email value

### Integrating with Custom Mutations

```php
use WPGraphQL\Experimental\Experiment\EmailAddressScalarFieldsExperiment\EmailAddressScalarFieldsExperiment;

register_graphql_mutation( 'customUserMutation', [
    'inputFields' => [
        'emailAddress' => [ 'type' => 'EmailAddress' ],
    ],
    'mutateAndGetPayload' => function( $input ) {
        // Use the helper to support both fields
        $email = EmailAddressScalarFieldsExperiment::resolve_email_input( $input );
        
        // Email is already validated by the scalar
        // Process the mutation...
    }
] );
```

### Testing

Check if experiments are active:

```php
$scalar_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 
    'email-address-scalar' 
);
$fields_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 
    'email-address-scalar-fields' 
);
```

## Feedback & Support

We need your feedback to determine if this should graduate to core:

**What to Test**:
- Query `emailAddress` fields on various types
- Use `emailAddress` in mutations
- Verify validation works correctly
- Check backward compatibility with `email` fields
- Test performance with large datasets

**Provide Feedback**:
- **GitHub Issues**: [Report bugs or issues](https://github.com/wp-graphql/wp-graphql/issues)
- **GitHub Discussions**: [Share feedback and suggestions](https://github.com/wp-graphql/wp-graphql/discussions)
- **Slack**: [Join the community](https://wpgraphql.com/community)

## References

- [Email Address Scalar Experiment](../EmailAddressScalarExperiment/README.md)
- [What are Experiments?](/docs/experiments.md)
- [Using Experiments](/docs/experiments-using.md)
- [WPGraphQL Deprecation Policy](/docs/upgrading.md)


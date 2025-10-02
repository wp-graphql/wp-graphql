---
uri: "/docs/scalar-email-address/"
title: "EmailAddress Scalar"
---

# EmailAddress Scalar

The `EmailAddress` scalar provides email validation and sanitization using WordPress's native `is_email()` and `sanitize_email()` functions.

## Why EmailAddress is a Scalar

Email addresses are perfect candidates for custom scalars because:

- **Universal Validation** - Email format rules are consistent everywhere
- **Type Safety** - Tools can understand this represents an email address
- **WordPress Integration** - Leverages WordPress's proven email handling
- **No Field-Specific Logic** - Unlike HTML or dates, emails don't need formatting options
- **Cross-API Consistency** - Same validation rules across queries and mutations

## Features

- **Automatic Validation** - Uses WordPress `is_email()` for format checking
- **Sanitization** - Applies WordPress `sanitize_email()` for clean output
- **Clear Error Messages** - Helpful validation errors for developers
- **Seamless Integration** - Works with existing WordPress email workflows
- **Type Safety** - GraphQL tools understand this is an email address

## Usage in Queries

### User Email Addresses

```graphql
query GetUser($id: ID!) {
  user(id: $id) {
    id
    name
    emailAddress # EmailAddress scalar - validated and sanitized
    email # String (deprecated) - for backward compatibility
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

### Settings

```graphql
query GetSiteSettings {
  generalSettings {
    title
    adminEmail # EmailAddress scalar - requires admin privileges
    email # String (deprecated) - for backward compatibility
  }
}
```

**Response:**

```json
{
  "data": {
    "generalSettings": {
      "title": "My WordPress Site",
      "adminEmail": "admin@example.com",
      "email": "admin@example.com"
    }
  }
}
```

### Comment Authors

```graphql
query GetComments {
  comments {
    nodes {
      id
      content
      author {
        node {
          name
          emailAddress # EmailAddress scalar - requires moderation privileges
          email # String (deprecated)
        }
      }
    }
  }
}
```

**Response (for users with comment moderation privileges):**

```json
{
  "data": {
    "comments": {
      "nodes": [
        {
          "id": "Y29tbWVudDox",
          "content": "Great post!",
          "author": {
            "node": {
              "name": "Jane Smith",
              "emailAddress": "jane@example.com",
              "email": "jane@example.com"
            }
          }
        }
      ]
    }
  }
}
```

## Usage in Mutations

### Creating Users

```graphql
mutation CreateUser($input: CreateUserInput!) {
  createUser(input: $input) {
    user {
      id
      username
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
    "password": "securepassword123"
  }
}
```

### User Registration

```graphql
mutation RegisterUser($input: RegisterUserInput!) {
  registerUser(input: $input) {
    user {
      id
      username
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
    "emailAddress": "registered@example.com"
  }
}
```

### Updating Users

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

## Validation & Error Handling

The EmailAddress scalar provides clear validation errors:

### Invalid Email Format

**Mutation:**

```graphql
mutation CreateUser($input: CreateUserInput!) {
  createUser(input: $input) {
    user {
      emailAddress
    }
  }
}
```

**Variables:**

```json
{
  "input": {
    "username": "testuser",
    "emailAddress": "not-an-email",
    "password": "password123"
  }
}
```

**Error Response:**

```json
{
  "errors": [
    {
      "message": "Email address must be a string. Received: \"not-an-email\"",
      "locations": [{ "line": 3, "column": 5 }],
      "path": ["createUser"]
    }
  ]
}
```

### Non-String Input

**Variables:**

```json
{
  "input": {
    "username": "testuser",
    "emailAddress": 12345,
    "password": "password123"
  }
}
```

**Error Response:**

```json
{
  "errors": [
    {
      "message": "Email address must be a string. Received: 12345",
      "locations": [{ "line": 3, "column": 5 }],
      "path": ["createUser"]
    }
  ]
}
```

## Migration Guide

### Deprecation Timeline

The EmailAddress scalar introduces new fields while maintaining backward compatibility:

- **Current**: Both `email` (String) and `emailAddress` (EmailAddress) fields available
- **Deprecated**: `email` fields marked as deprecated with warnings
- **Future (v3.0)**: `email` fields will be removed

### Migrating Queries

**Before (deprecated):**

```graphql
{
  user(id: "dXNlcjox") {
    email # String - deprecated
  }
}
```

**After (recommended):**

```graphql
{
  user(id: "dXNlcjox") {
    emailAddress # EmailAddress - enhanced validation
  }
}
```

### Migrating Mutations

**Before (deprecated):**

```graphql
mutation CreateUser($input: CreateUserInput!) {
  createUser(input: $input) {
    user { id }
  }
}

# Variables:
{
  "input": {
    "username": "user",
    "email": "user@example.com"  # String - deprecated
  }
}
```

**After (recommended):**

```graphql
mutation CreateUser($input: CreateUserInput!) {
  createUser(input: $input) {
    user { id }
  }
}

# Variables:
{
  "input": {
    "username": "user",
    "emailAddress": "user@example.com"  # EmailAddress - enhanced
  }
}
```

### Handling Both Inputs

If you provide both `email` and `emailAddress` in the same mutation, you'll get an error:

```json
{
  "errors": [
    {
      "message": "Cannot provide both \"email\" and \"emailAddress\" fields. Please use \"emailAddress\" as \"email\" is deprecated."
    }
  ]
}
```

## Capability Restrictions

Some email fields have capability restrictions for privacy and security:

### Administrative Email Addresses

```graphql
{
  generalSettings {
    adminEmail # Requires 'manage_options' capability
  }
}
```

**Field Description:** "Email address of the site administrator. Only visible to users with administrative privileges."

### Comment Author Email Addresses

```graphql
{
  comments {
    nodes {
      author {
        node {
          emailAddress # Requires 'moderate_comments' capability
        }
      }
    }
  }
}
```

**Field Description:** "Email address of the comment author. Only visible to users with comment moderation privileges."

### Error for Insufficient Permissions

```json
{
  "errors": [
    {
      "message": "Sorry, you do not have permission to view this setting."
    }
  ]
}
```

## Schema Introspection

The EmailAddress scalar provides rich introspection information:

```graphql
query IntrospectEmailAddress {
  __type(name: "EmailAddress") {
    name
    description
    kind
  }
}
```

**Response:**

```json
{
  "data": {
    "__type": {
      "name": "EmailAddress",
      "description": "A field whose value conforms to the standard internet email address format as specified in RFC822: https://www.w3.org/Protocols/rfc822/.",
      "kind": "SCALAR"
    }
  }
}
```

### Field Introspection

```graphql
query IntrospectUserFields {
  __type(name: "User") {
    fields {
      name
      type {
        name
      }
      isDeprecated
      deprecationReason
    }
  }
}
```

**Response (partial):**

```json
{
  "data": {
    "__type": {
      "fields": [
        {
          "name": "email",
          "type": { "name": "String" },
          "isDeprecated": true,
          "deprecationReason": "Deprecated in favor of the `emailAddress` field for better validation and type safety."
        },
        {
          "name": "emailAddress",
          "type": { "name": "EmailAddress" },
          "isDeprecated": false,
          "deprecationReason": null
        }
      ]
    }
  }
}
```

## Implementation Details

The EmailAddress scalar is implemented using WordPress's proven email handling:

### Validation

```php
// Uses WordPress's is_email() function
if (!is_email($value)) {
    throw new Error('Invalid email address format');
}
```

### Sanitization

```php
// Uses WordPress's sanitize_email() function
return sanitize_email($value);
```

### Integration Points

- **User Registration** - Validates email during user creation
- **Settings** - Ensures admin email is properly formatted
- **Comments** - Validates commenter email addresses
- **Mutations** - Provides consistent validation across all user operations

## Related Documentation

- [Custom Scalars Overview](/docs/custom-scalars/)
- [Users](/docs/users/)
- [Settings](/docs/settings/)
- [Comments](/docs/comments/)
- [GraphQL Mutations](/docs/graphql-mutations/)

# Custom Scalars

This directory contains custom scalars that are used in the GraphQL schema.

## What are Custom Scalars?

Custom Scalars are used to define the data type of a field in the GraphQL schema. They are used to ensure that the data passed to and from the GraphQL API is of the correct type.

Custom Scalars differentiate GraphQL from REST and other APIs in that they provide more clarity and control over the data types of the data passed to and from the API.

For example, a REST API might return a string for a field, but GraphQL can return an HTML Scalar for that field.

This provides more clarity and the self-documenting Schema is a more valuable artifact for the clients interacting with the API.

## Frequently Asked Questions

### When should I use a Custom Scalar?

Custom Scalars are ideal when you need to:

- Validate specific data formats (like emails, URLs, or dates)
- Handle specialized data types (like HTML content or JSON strings)
- Ensure data consistency across your application
- Provide better documentation and type safety for your API

### What Custom Scalars are included in WPGraphQL?

#### Currently Implemented

WPGraphQL includes these Custom Scalars:

| Scalar           | Description                                                                  |
| ---------------- | ---------------------------------------------------------------------------- |
| `Date`           | Stores **date-only** values (`Y-m-d`).                                       |
| `DateTime`       | Stores **date/time** values (`Y-m-d H:i:sZ`, ISO 8601 format).               |
| `EmailAddress`   | Enforces **valid email formats**.                                            |
| `Color`          | Represents color values (supports HEX, RGB, and RGBA).                       |
| `HTML`           | Represents **sanitized HTML content**.                                       |
| `JSON`           | Represents **JSON-encoded data** (useful for meta values, block attributes). |
| `Locale`         | Stores **WordPress locale identifiers** (`en_US`, `fr_FR`).                  |
| `NonEmptyString` | Ensures **non-empty text values**.                                           |
| `Slug`           | Enforces **WordPress-style slugs** (`my-post-title`).                        |
| `Time`           | Stores **time-only** values (`HH:MM:SS`).                                    |
| `Timezone`       | Stores **timezone identifiers** (`America/New_York`).                        |
| `URI`            | Stores **WordPress URIs** (`/my-post/`).                                     |
| `URL`            | Ensures **valid full URLs**.                                                 |

#### Considering for Later Implementation

These scalars might be implemented in the future if clear use cases emerge:

- `AccountNumber`
- `BigInt`
- `DID`
- `GID`
- `IBAN`
- `IPv4`
- `IPv6`
- `ISBN`
- `MAC`
- `NegativeFloat`
- `NegativeInt`
- `NonNegativeFloat`
- `NonNegativeInt`
- `NonPositiveFloat`
- `NonPositiveInt`
- `Port`
- `RoutingNumber`
- `SafeString`
- `USCurrency`
- `UUID`
- `Void`

### How do I create a Custom Scalar?

Custom Scalars can be created by:

1. Using the `register_graphql_scalar()` function within the `graphql_register_types` action
2. Implementing `serialize()` and `parseValue()` methods

Example:

```php
add_action( 'graphql_register_types', function() {
    register_graphql_scalar( 'MyCustomScalar', [
        'description' => ( 'Description of the scalar', 'your-textdomain' ),
        'serialize' => function($value) {
            // Logic to serialize the value for output
            return $value;
        },
        'parseValue' => function($value) {
            // Logic to parse the input value
            return $value;
        }
    ]);
});
```

### Date

A scalar that handles date values, represented as strings in the `Y-m-d` format. This is distinct from the `DateTime` scalar, which includes time information.

#### Usage

The `Date` scalar is ideal for fields that only need to store a date, without a time component. For example, it could be used for a post's publish date if the time is irrelevant.

Example:

```graphql
query GetPostPublishDate {
  post(id: "cG9zdDo0Mg==") {
    # Returns the post's publish date, formatted as "2023-10-27"
    date
  }
}
```

#### Future Directives

In the future, we may introduce a `@formatDate` directive to allow for more flexible date formatting directly within the query.

Example of potential future usage:

```graphql
query GetFormattedPostPublishDate {
  post(id: "cG9zdDo0Mg==") {
    # Would return the date formatted as "October 27, 2023"
    date @formatDate(format: "F j, Y")
  }
}
```

### EmailAddress

A scalar type that validates and sanitizes email addresses according to WordPress standards.

#### Usage

Currently used in:

- `User.emailAddress` field (replacing `email` field in v3.0.0)
- `User.email` field (deprecated, will be removed in v3.0.0)
- `registerUser.emailAddress` field (replacing `email` field in v3.0.0)
- `Comment.authorEmail` field

#### Migration Note

The `registerUser.email` field is deprecated and will be removed in v3.0.0. Use `registerUser.emailAddress` instead, which provides proper email validation. Note that in v3.0.0, the `emailAddress` field will also become non-nullable.

Example of current usage:

```graphql
mutation RegisterUser {
  registerUser(
    input: {
      username: "testuser"
      # Use emailAddress for proper validation
      emailAddress: "valid@email.com"
      # email field is deprecated, will be removed in v3.0.0
      # email: "valid@email.com"
    }
  ) {
    user {
      email
    }
  }
}
```

#### Implementation Details

The EmailAddress scalar:

- Validates email format using WordPress's `is_email()` function
- Sanitizes email using WordPress's `sanitize_email()` function
- Provides clear error messages for invalid inputs

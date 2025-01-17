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

- `Currency`: For standardized currency codes (ISO 4217 format, useful for ecommerce fields, product prices)
- `DatabaseId`: For WordPress database IDs (positive integers, used for posts, terms, users, comments)
- `Date`: For date values without time (post dates, event dates, custom date fields)
- `DateTime`: For date/time values (post dates, comment dates, user registration dates)
- `DateTimeISO`: For ISO 8601 formatted date/time strings (standardized date/time interchange)
- `Duration`: For time duration values (video lengths, event durations, time-based content)
- `EmailAddress`: For email addresses (user emails, comment author emails, admin email settings)
- `HexColorCode`: For HEX color values (useful for color picker fields)
- `HTML`: For sanitized HTML content (post content, excerpts, comments, user descriptions)
- `JSON`: For JSON-encoded strings (block attributes, meta values, widget instances, serialized data)
- `JWT`: For JSON Web Tokens (authentication tokens, API credentials)
- `Latitude`: For geographic latitude values (location data, map coordinates)
- `Locale`: For language and region identifiers (site locale, content translations)
- `Longitude`: For geographic longitude values (location data, map coordinates)
- `Markdown`: For Markdown formatted content (documentation, formatted text fields)
- `NonEmptyString`: For required text fields (particularly useful for ACF required fields)
- `PhoneNumber`: For standardized phone number formats (contact information, user profiles)
- `PositiveInt`: For positive integer values (number fields with minimum value of 1)
- `PostalCode`: For postal/zip code validation (address information, shipping data)
- `RGB`: For RGB color values (color settings, theme customization)
- `RGBA`: For RGBA color values with alpha channel (color settings with transparency)
- `SemVer`: For semantic version numbers (plugin versions, theme versions)
- `Slug`: For WordPress URL-friendly slugs (post slugs, term slugs, category/tag slugs)
- `Time`: For time values without dates (scheduling, opening hours)
- `Timezone`: For timezone identifiers (site timezone, event timezones)
- `Timestamp`: For Unix timestamp values (internal date storage, date comparisons)
- `URI`: For URI/path values (post URIs, term URIs, page URIs)
- `URL`: For full URLs (post links, term links, media URLs, avatar URLs, site URL)

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


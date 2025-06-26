# GraphQL Scalar Type Conventions

This document outlines the conventions and best practices for creating custom GraphQL Scalar types within WPGraphQL. Following these guidelines ensures consistency, predictability, and easier maintenance across the codebase.

GraphQL includes a default set of scalar types: `Int`, `Float`, `String`, `Boolean`, and `ID`. While powerful, there are often cases where more specific scalar types are needed to precisely define a schema and validate data.

The primary purposes of custom scalars are to:

1.  **Communicate Intent**: Reduce ambiguity in the schema by clearly communicating what kind of data to expect. For example, a field like `ageInYears` should clearly only accept a positive integer. A custom `PositiveInt` scalar enforces this.
2.  **Enforce Contracts**: Provide run-time type checking for both incoming and outgoing data. This tightens the contract between the client and server, leading to more robust and predictable APIs.

As discussed in the [GraphQL Scalars documentation](https://the-guild.dev/graphql/scalars/docs), using custom scalars helps create precise, type-safe GraphQL schemas.

## Key Responsibilities of a Scalar

### Validation

A custom scalar can encapsulate validation logic. For instance, instead of validating an email address format on the client and again on the server, an `EmailAddress` scalar can perform this validation within the GraphQL layer. This prevents invalid data from ever reaching your business logic and reduces code duplication.

### Serialization and Parsing

Scalars handle the transformation of data between its server-side representation and its JSON-compatible representation for transport.

- **Serialization**: The `serialize` method prepares server-side data for the client. For example, it might take a PHP `DateTime` object and format it into an ISO 8601 string.
- **Parsing**: The `parseValue` and `parseLiteral` methods take a value from a client query (from variables or as a literal in the query string) and prepare it for use on the server. For example, it might take an ISO 8601 string and convert it into a `DateTime` object for your resolvers to use.

## Error Handling

Proper error handling is crucial for providing clear feedback. Different types of errors should be thrown depending on the context.

### Server-Side Data (Serialization)

When serializing data from the server, if the data is in an unexpected format, the `serialize` method should throw a `\GraphQL\Error\InvariantViolation`. This indicates a problem with the data on the server, not with the client's query.

**Example:**

```php
public static function serialize( $value ) {
    if ( ! is_valid_server_value( $value ) ) {
        throw new \GraphQL\Error\InvariantViolation(
            'Value from server is not in a valid format.'
        );
    }
    // ... serialization logic
}
```

### Client-Side Input (Parsing)

When parsing input from a client's query, if the value is invalid, the `parseValue` and `parseLiteral` methods should throw a `\GraphQL\Error\Error`. This indicates a problem with the client's request.

**Example:**

```php
public static function parseValue( $value ) {
    if ( ! is_valid_client_input( $value ) ) {
        throw new \GraphQL\Error\Error(
            'Invalid value provided for scalar.'
        );
    }
    // ... parsing logic
}
```

## Implementation Guidelines

- **Class Naming:** The PHP class name should match the name of the scalar in the GraphQL schema (e.g., a `Date` scalar is implemented in the `Date` class).
- **Registration:** Scalars should be registered to the GraphQL schema using the `register_graphql_scalar` function, typically within a static `register_scalar` method inside the class.
- **Single Responsibility:** A scalar should be responsible for validating and coercing one specific type of data.
- **Clear Descriptions:** Provide a clear and concise description of the scalar's purpose and format. This is exposed in the GraphQL schema and is invaluable for API consumers.
- **Specification URL:** If the scalar adheres to a formal specification (e.g., an RFC), include a link to it in the `specifiedByURL` property during registration. This provides an authoritative reference for the scalar's behavior.
- **Timezones:** When dealing with date/time scalars, always be explicit about timezone handling. Whenever possible, respect the site's configured timezone (`get_option( 'timezone_string' )`) and fall back to UTC.

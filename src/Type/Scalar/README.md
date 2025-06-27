# Custom Scalars

This document outlines the conventions and best practices for creating custom GraphQL Scalar types within WPGraphQL. Following these guidelines ensures consistency, predictability, and easier maintenance across the codebase.

## What are Custom Scalars?

GraphQL includes a default set of scalar types: `Int`, `Float`, `String`, `Boolean`, and `ID`. While powerful, there are often cases where more specific scalar types are needed to precisely define a schema and validate data.

The primary purposes of custom scalars are to:

1.  **Communicate Intent**: Reduce ambiguity in the schema by clearly communicating what kind of data to expect. For example, a field like `ageInYears` should clearly only accept a positive integer or zero. A custom `NonNegativeInt` scalar enforces this.
2.  **Enforce Contracts**: Provide run-time type checking for both incoming and outgoing data. This tightens the contract between the client and server, leading to more robust and predictable APIs.

As discussed in the [GraphQL Scalars documentation](https://the-guild.dev/graphql/scalars/docs), using custom scalars helps create precise, type-safe GraphQL schemas.

Custom Scalars differentiate GraphQL from REST and other APIs in that they provide more clarity and control over the data types of the data passed to and from the API. For example, a REST API might return a string for a field, but GraphQL can return an HTML Scalar for that field. This provides more clarity and the self-documenting Schema is a more valuable artifact for the clients interacting with the API.

## Frequently Asked Questions

### When should I use a Custom Scalar?

Custom Scalars are ideal when you need to:

- Validate specific data formats (like emails, URLs, or dates)
- Handle specialized data types (like HTML content or JSON strings)
- Ensure data consistency across your application
- Provide better documentation and type safety for your API

### What Custom Scalars are included in WPGraphQL?

Below is a list of scalars implemented in WPGraphQL. Each scalar has a dedicated document detailing its purpose, validation rules, and usage examples.

- [Color](./Color.md)
- [Date](./Date.md)
- [DateTime](./DateTime.md)
- [EmailAddress](./EmailAddress.md)
- [HTML](./HTML.md)
- [JSON](./JSON.md)
- [Locale](./Locale.md)
- [NegativeInt](./NegativeInt.md)
- [NonNegativeInt](./NonNegativeInt.md)
- [NonPositiveInt](./NonPositiveInt.md)
- [NonEmptyString](./NonEmptyString.md)
- [PositiveInt](./PositiveInt.md)
- [Slug](./Slug.md)
- [Time](./Time.md)
- [Timezone](./Timezone.md)
- [URI](./URI.md)
- [URL](./URL.md)

### Considering for Later Implementation

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
- `Port`
- `RoutingNumber`
- `SafeString`
- `USCurrency`
- `UUID`

## Authoring Custom Scalars

The following sections provide conventions and best practices for creating custom GraphQL Scalar types within WPGraphQL.

### How do I create a Custom Scalar?

Custom Scalars should be implemented as a PHP Class. They can be created by:

1.  Creating a new Class for the Scalar.
2.  Implementing `serialize()`, `parseValue()`, and `parseLiteral()` methods within the class.
3.  Registering the scalar using the `register_graphql_scalar()` function, typically by calling a static `register_scalar()` method on the Scalar's class from the `graphql_register_types` action.

**Note on Naming Conventions:** The `graphql-php` library requires the method names `parseValue` and `parseLiteral`. While these do not follow WordPress's `snake_case` naming convention, we adhere to the library's standard for clarity and compatibility. We use `phpcs:ignore` directives to prevent linting errors for these specific methods.

**Example:**

```php
class MyCustomScalar {
    public static function serialize( $value ) {
        // Logic to serialize the value for output
    }
    public static function parseValue( $value ) {
        // Logic to parse the input value from a query variable
    }
    public static function parseLiteral( $valueNode, ?array $variables = null ) {
        // Logic to parse the literal value from the query AST
    }
    public static function register_scalar() {
        register_graphql_scalar( 'MyCustomScalar', [
            'description' => __( 'Description of the scalar', 'your-textdomain' ),
            'serialize' => [ self::class, 'serialize' ],
            'parseValue' => [ self::class, 'parseValue' ],
            'parseLiteral' => [ self::class, 'parseLiteral' ],
        ]);
    }
}

add_action( 'graphql_register_types', ['MyCustomScalar', 'register_scalar'] );
```

### Key Responsibilities of a Scalar

#### Validation

A custom scalar can encapsulate validation logic. For instance, instead of validating an email address format on the client and again on the server, an `EmailAddress` scalar can perform this validation within the GraphQL layer. This prevents invalid data from ever reaching your business logic and reduces code duplication.

#### Serialization and Parsing

Scalars handle the transformation of data between its server-side representation and its JSON-compatible representation for transport.

- **Serialization**: The `serialize` method prepares server-side data for the client. For example, it might take a PHP `DateTime` object and format it into an ISO 8601 string.
- **Parsing**: The `parseValue` and `parseLiteral` methods take a value from a client query (from variables or as a literal in the query string) and prepare it for use on the server. For example, it might take an ISO 8601 string and convert it into a `DateTime` object for your resolvers to use.

### Error Handling

Proper error handling is crucial for providing clear feedback. Different types of errors should be thrown depending on the context.

#### Server-Side Data (Serialization)

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

#### Client-Side Input (Parsing)

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

### Implementation Guidelines

- **Class Naming:** The PHP class name should match the name of the scalar in the GraphQL schema (e.g., a `Date` scalar is implemented in the `Date` class).
- **Single Responsibility:** A scalar should be responsible for validating and coercing one specific type of data.
- **Clear Descriptions:** Provide a clear and concise description of the scalar's purpose and format. This is exposed in the GraphQL schema and is invaluable for API consumers.
- **Specification URL:** If the scalar adheres to a formal specification (e.g., an RFC), include a link to it in the `specifiedByURL` property during registration. This provides an authoritative reference for the scalar's behavior.
- **Timezones:** When dealing with date/time scalars, always be explicit about timezone handling. Whenever possible, respect the site's configured timezone (`get_option( 'timezone_string' )`) and fall back to UTC.

### Testing Custom Scalars

Consistent and thorough testing is crucial for ensuring custom scalars are robust and reliable. When adding a new scalar, follow these testing guidelines, modeled after `DateTest.php` and `EmailAddressTest.php`.

#### Test File Location and Naming

- Test files should be located in `tests/wpunit/Type/Scalar/`.
- The file name should correspond to the scalar name, e.g., `MyScalarTest.php`.

#### Test Fields and Mutations

To avoid conflicts with actual schema fields, **all fields and mutations registered for testing purposes must be prefixed with `test`**.

- **Example Field:** `testMyScalarField`
- **Example Mutation:** `testMyScalarMutation`

This is a critical convention to prevent tests from failing when a real field using the scalar is added to the schema.

#### Core Test Cases

Your test class should include the following checks:

1.  **Serialization:**

    - Unit test the `serialize()` method directly with valid values to ensure they are formatted correctly.
    - Test invalid values (e.g., `null`, malformed strings, incorrect data types) to ensure they are handled gracefully (typically by returning `null`).

2.  **Value Parsing:**

    - Unit test the `parseValue()` method with valid and invalid input to ensure it either accepts the value or throws a `\GraphQL\Error\Error`.

3.  **Literal Parsing:**

    - Unit test the `parseLiteral()` method, which handles values hardcoded in the query document.

4.  **Integration Tests:**

    - **Query Test:** Register a test field that returns a value, and write a GraphQL query to assert that the serialized output is correct.
    - **Mutation Test:** Register a test mutation that accepts the scalar as input. Write a GraphQL mutation to assert that valid input is accepted and invalid input results in a GraphQL error.

5.  **Real-World Data:**
    - When a scalar is intended to be used with specific WordPress data (e.g., `post_date`), create that data in your tests and query it through the scalar to ensure it handles real-world formats and values correctly.

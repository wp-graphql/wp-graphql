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

Below is a list of scalars implemented in WPGraphQL. Each scalar has a dedicated document detailing its purpose, validation rules, and usage examples.

- [Color](./Color.md)
- [Date](./Date.md)
- [DateTime](./DateTime.md)
- [EmailAddress](./EmailAddress.md)
- [HTML](./HTML.md)
- [JSON](./JSON.md)
- [Locale](./Locale.md)
- [NonEmptyString](./NonEmptyString.md)
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
2. Implementing `serialize()`, `parseValue()`, and `parseLiteral()` methods.

**Note on Naming Conventions:** The `graphql-php` library requires the method names `parseValue` and `parseLiteral`. While these do not follow WordPress's `snake_case` naming convention, we adhere to the library's standard for clarity and compatibility. We use `phpcs:ignore` directives to prevent linting errors for these specific methods.

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
        },
		'parseLiteral' => function($valueNode) {
			// Logic to parse the literal value from the query AST
			return $valueNode->value;
		}
    ]);
});
```

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

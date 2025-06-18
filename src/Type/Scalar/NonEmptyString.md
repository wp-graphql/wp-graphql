# NonEmptyString Scalar

The `NonEmptyString` scalar type represents a string that must contain at least one non-whitespace character. It is used to enforce that a string value is not just present, but also meaningful.

## Validation

The scalar trims the input string and checks if it is empty. If the string is empty or contains only whitespace characters, the scalar will throw a GraphQL error.

## Behavior

- **Serialization:** It serializes a valid, non-empty string for output. It will throw an error if the value from the server is an empty or whitespace-only string.
- **Value Parsing:** It parses and validates an input value from a query variable.
- **Literal Parsing:** It parses and validates a literal value from a query string.

This scalar is strict and will always throw an error for invalid input, ensuring data integrity for fields that require a meaningful string value.

## Why use NonEmptyString?

As outlined in the [Custom Scalars RFC](https://github.com/wp-graphql/wp-graphql/issues/3313), a key goal of custom scalars is to provide field-specific validation and semantic meaning.

Standard `String` fields can be empty, forcing developers to add validation logic to every resolver that requires a non-empty value (e.g., checking if a post title is blank).

By using the `NonEmptyString` scalar, this validation is handled automatically at the type level. This leads to:

- **Reduced Code Duplication:** Eliminates the need for repetitive checks in business logic.
- **A More Self-Documenting Schema:** Clearly communicates to API clients that a field cannot be empty.
- **Improved Data Integrity:** Enforces the non-empty constraint at the edge of the graph, ensuring invalid data never reaches your resolvers.

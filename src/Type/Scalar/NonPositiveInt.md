# NonPositiveInt Scalar

The `NonPositiveInt` scalar type represents an integer with a value of 0 or less.

## Format

The `NonPositiveInt` scalar must be an integer.

## Behavior

- **Serialization:** When a `NonPositiveInt` is sent from the server to the client, it will be validated to ensure it is an integer with a value of 0 or less. If the value from the server is invalid, a `\GraphQL\Error\InvariantViolation` will be thrown.
- **Value Parsing:** When used as an input argument, the scalar will accept an integer with a value of 0 or less. If the value is invalid, a `\GraphQL\Error\Error` will be thrown.
- **Literal Parsing:** When used in a GraphQL query string, the scalar will accept an integer with a a value of 0 or less. If the value is invalid, a `\GraphQL\Error\Error` will be thrown.

## Error Handling

If an invalid value is passed to the scalar, it will throw an error, ensuring that queries cannot complete with unexpected or corrupt data.

# DateTime Scalar

The `DateTime` scalar type represents a date and time in the [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) format. It is used for fields that need to store a complete date and time, including timezone information.

## Format

The `DateTime` scalar uses the `Y-m-d\TH:i:s\Z` format. This is a specific profile of the ISO 8601 standard and represents a UTC time. For example: `2024-01-20T14:45:00Z`.

## Behavior

- **Serialization:** It serializes a date-time string from various formats into the strict `Y-m-d\TH:i:s\Z` format for output. For example, a MySQL `DATETIME` string like `2024-01-20 14:45:00` will be converted to `2024-01-20T14:45:00Z`. It returns `null` for invalid or empty inputs.
- **Value Parsing:** When used as an input argument, the scalar will only accept a string that strictly conforms to the `Y-m-d\TH:i:s\Z` format. It will throw an error for any other format.
- **Literal Parsing:** When used in a GraphQL query string, it behaves the same as `parseValue`.

This strictness on input ensures data consistency and predictability for mutations and queries that filter by date and time.

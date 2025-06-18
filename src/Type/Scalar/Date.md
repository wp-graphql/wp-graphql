# Date Scalar

The `Date` scalar type represents a date in `Y-m-d` format, such as `2024-01-20`.

## Format

The `Date` scalar exclusively uses the `Y-m-d` format. This format is a specific profile of the [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) standard for representing dates.

## Behavior

- **Serialization:** When a date is sent from the server to the client, it will be formatted as a `Y-m-d` string. The scalar is designed to work with MySQL `datetime` fields (like `post_date` and `post_modified`) and will correctly serialize the date portion.
- **Value Parsing:** When used as an input argument, the scalar will accept a `Y-m-d` formatted string.
- **Literal Parsing:** When used in a GraphQL query string, the scalar will accept a `Y-m-d` formatted string.

## Error Handling

If the value provided from the database or from a client input cannot be parsed into a valid `Y-m-d` date, the scalar will return `null`. It will not throw an error, ensuring that queries can complete even if some date fields contain unexpected or corrupt data.

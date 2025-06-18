# URI Scalar

The `URI` scalar type represents a Uniform Resource Identifier, which is a relative path within a site. It is used for fields that should contain a path-like string, such as `/about-us` or `/category/news/`.

## Validation

The `URI` scalar validates that an input string is a valid relative path.

A string is considered a valid URI if it:

- Is a string.
- Starts with a forward slash (`/`).

It does **not** validate that the path actually exists on the site, only that the format is correct.

- If an empty string or `null` is provided, the scalar will return `null` without throwing an error. This allows for optional URI fields.
- If a value is provided that does not start with a `/` (such as a full URL or a random string), the scalar will throw a GraphQL error.

## Behavior

- **Serialization:** It serializes a valid URI string for output.
- **Value Parsing:** It parses and validates an input value from a query variable.
- **Literal Parsing:** It parses and validates a literal value from a query string.

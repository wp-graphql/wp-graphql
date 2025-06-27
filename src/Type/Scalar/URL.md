# URL Scalar

The `URL` scalar type represents a valid, sanitized URL. It is used for fields that must contain a full and safe URL.

## Validation and Sanitization

The `URL` scalar uses the WordPress `esc_url_raw()` function for both validation and sanitization. This function ensures that the URL is well-formed and does not contain any unsafe protocols (like `javascript:`).

- If a value is provided that is not a valid URL (e.g., a random string, a URL with an unsafe protocol), the scalar will throw a GraphQL error.
- If an empty string or `null` is provided, the scalar will return `null` without throwing an error. This allows for optional URL fields.

## Behavior

- **Serialization:** It sanitizes and returns a valid URL string for output.
- **Value Parsing:** It sanitizes and validates an input value from a query variable.
- **Literal Parsing:** It sanitizes and validates a literal value from a query string.

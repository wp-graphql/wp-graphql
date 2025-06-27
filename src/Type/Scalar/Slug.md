# Slug Scalar

The `Slug` scalar type represents a WordPress slug, which is a sanitized, URL-friendly string. Slugs are typically used in URLs to identify resources in a human-readable way.

## Validation

The `Slug` scalar validates that an input string is already in a valid slug format. It uses WordPress's `sanitize_title()` function as the benchmark for validity.

A string is considered a valid slug if it:

- Is in lowercase.
- Contains only letters, numbers, and hyphens (`-`).
- Has no spaces or other special characters.

If an input string does not match its sanitized version (i.e., `sanitize_title( $input ) !== $input`), the scalar will throw a GraphQL error.

## Behavior

- **Serialization:** It serializes a valid slug string for output. It will throw an error if the value from the server is not a valid slug.
- **Value Parsing:** It parses and validates an input value from a query variable.
- **Literal Parsing:** It parses and validates a literal value from a query string.

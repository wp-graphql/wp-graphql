# EmailAddress Scalar

The `EmailAddress` scalar type represents a valid email address. It ensures that any value passed as an `EmailAddress` conforms to the expected email format before being accepted by the GraphQL schema.

## Validation

The `EmailAddress` scalar validates input against the [RFC 5322](https://www.rfc-editor.org/rfc/rfc5322) standard for email address format.

### Implementation

While the public contract adheres to RFC 5322, the underlying implementation uses the WordPress `is_email()` function. This function provides robust and well-tested validation that is consistent with how the rest of a WordPress application handles email addresses.

## Behavior

- **Serialization:** It serializes a valid email address string for output.
- **Value Parsing:** It parses and validates an input value, throwing an error if the value is not a valid email format.
- **Literal Parsing:** It parses and validates a literal value from a query string, throwing an error if the value is not a valid email format.

Unlike the `Date` scalar, which can return `null` for invalid formats, the `EmailAddress` scalar is strict. It will raise a GraphQL error for invalid email inputs, as they are typically provided by a client and indicate a client-side error.

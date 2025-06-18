# JSON Scalar

The `JSON` scalar type represents a JSON value as specified by [ECMA-404](http://www.ecma-international.org/publications/standards/Ecma-404.htm). It is a versatile type that can represent any value that can be represented in JSON, including objects, arrays, strings, numbers, booleans, and null.

This is particularly useful for fields that handle unstructured or dynamic data, such as Gutenberg block attributes or custom meta fields.

## Behavior

- **Serialization:** It passes the value from the resolver through without modification. The GraphQL server is then responsible for the final JSON encoding of the entire result.
- **Value Parsing (Variables):** It accepts any valid JSON value from a query variable and passes it to the resolver as a PHP associative array or scalar value.
- **Literal Parsing (Inline):** It can parse a JSON object or array provided directly (inline) in a GraphQL query string. The scalar converts the query's Abstract Syntax Tree (AST) for the object or list into a PHP associative array.

### Example

You can use a JSON scalar for a mutation with either a variable:

```graphql
mutation UpdateBlock($attributes: JSON!) {
  updateBlock(input: { attributes: $attributes }) {
    attributes
  }
}
```

Variables:

```json
{
  "attributes": {
    "color": "blue",
    "align": "center"
  }
}
```

Or with an inline literal value:

```graphql
mutation {
  updateBlock(input: { attributes: { color: "blue", align: "center" } }) {
    attributes
  }
}
```

## A Note on Usage and Best Practices

The `JSON` scalar is a powerful tool, but it should be used with care. One of the primary benefits of GraphQL is its strongly-typed schema, which provides clarity, predictability, and self-documentation for API clients.

Returning unstructured `JSON` can be seen as an anti-pattern because it bypasses this type safety, similar to having an API endpoint that just returns a raw `string` and requires the client to parse it.

For this reason, **WPGraphQL core will generally avoid adding fields that return the `JSON` type.** We will always prefer to model data with specific, strongly-typed fields whenever possible.

However, we provide the `JSON` scalar as a utility for extension developers. There are valid use cases where data is inherently unstructured or dynamic, such as with Gutenberg block attributes or certain types of metadata. In these scenarios, the `JSON` scalar offers a standardized way to expose that data through the API.

**Recommendation:** Before using the `JSON` scalar, consider if the data could be modeled with a structured `ObjectType` or `InputType`. Use `JSON` as a fallback for truly dynamic or unstructured data.

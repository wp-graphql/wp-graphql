# Locale

The `Locale` scalar type represents a WordPress locale identifier, such as `en_US` or `de_DE`.

This scalar is essential for ensuring that locale codes used in your GraphQL schema correspond to languages that are actually available in your WordPress installation.

## Format & Validation

The `Locale` scalar accepts a string representing a valid WordPress locale.

Validation is performed dynamically by checking against the locales returned by the native WordPress function `get_available_languages()`. This function scans the `wp-content/languages` directory for installed language files.

- `en_US` is always considered a valid locale, as it is the WordPress default.
- Other examples include: `es_ES`, `fr_FR`, `ja`, `zh_CN`, etc.

This approach ensures that the scalar only accepts locales that the target WordPress site can actually use.

## Usage

When used as input, the value is validated against the list of available locales.

### Example Input

```graphql
mutation {
  testLocaleMutation(input: { locale: "en_GB" }) {
    locale
  }
}
```

### Example Output

```json
{
  "data": {
    "testLocaleMutation": {
      "locale": "en_GB"
    }
  }
}
```

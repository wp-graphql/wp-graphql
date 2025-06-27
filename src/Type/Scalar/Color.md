# Color

The `Color` scalar type represents a color value. It can be used for input and output.

Values can be in `HEX`, `RGB`, or `RGBA` format.

## Formats

- **HEX**: 3, 6, or 8-digit hexadecimal color codes, prefixed with `#`.
  - `#f00` (3-digit)
  - `#ff0000` (6-digit)
  - `#ff0000ff` (8-digit with alpha)
- **RGB**: `rgb()` function format.
  - `rgb(255, 0, 0)`
- **RGBA**: `rgba()` function format.
  - `rgba(255, 0, 0, 1)`
  - `rgba(255, 0, 0, 0.5)`

## Usage

When used as input, the value will be validated to ensure it's a valid color string in one of the accepted formats. When used as output, the value is passed through as a string.

### Example Input

```graphql
mutation {
  testColorMutation(input: { color: "rgba(100, 200, 50, 0.7)" }) {
    color
  }
}
```

### Example Output

```json
{
  "data": {
    "testColorMutation": {
      "color": "rgba(100, 200, 50, 0.7)"
    }
  }
}
```

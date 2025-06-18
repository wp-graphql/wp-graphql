# Time

The `Time` scalar type represents a time-only value in a `HH:MM:SS` format.

This is useful for fields that store a time of day, without any date information.

## Format

The `Time` scalar accepts a string in the 24-hour `HH:MM:SS` format.

- `14:30:00` (2:30 PM)
- `23:59:59` (one second before midnight)
- `00:00:00` (midnight)

## Usage

When used as input, the value will be validated to ensure it's a valid time string in the `HH:MM:SS` format.

### Example Input

```graphql
mutation {
  testTimeMutation(input: { time: "09:15:45" }) {
    time
  }
}
```

### Example Output

```json
{
  "data": {
    "testTimeMutation": {
      "time": "09:15:45"
    }
  }
}
```

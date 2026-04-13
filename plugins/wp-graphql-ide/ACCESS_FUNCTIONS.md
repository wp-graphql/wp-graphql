# Access Functions

Access Functions are functions that abstract several layers of complexity and provide simpler ways
to accomplish common tasks. They are often used to provide a more readable and maintainable codebase.

The concept is modeled around core WordPress functions like register_post_type, register_taxonomy, etc, where
the function signature is simple and the function itself abstracts the complexity of the underlying APIs.

## Table of Contents

### registerDocumentEditorToolbarButton

Registers a new toolbar button for the document editor.

This function allows registering a new editor toolbar button with the following parameters:

- `name` (string): The name of the button to register.
- `config` (Object): The configuration object for the button.
- `priority` (number, optional): The priority for the button, with lower numbers meaning higher priority. Default is 10.

Example usage:

```js
const { registerDocumentEditorToolbarButton } = window.WPGraphQLIDE;

registerDocumentEditorToolbarButton( 'toggle-auth', toggleAuthButton, 1 );
```

![Screenshot of the GraphiQL IDE highlighting the Toolbar buttons within the Document Editor region.](https://private-user-images.githubusercontent.com/1260765/332127315-2395c3c8-1915-4a24-b64e-35ebe16e674f.png?jwt=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJnaXRodWIuY29tIiwiYXVkIjoicmF3LmdpdGh1YnVzZXJjb250ZW50LmNvbSIsImtleSI6ImtleTUiLCJleHAiOjE3MTYyMjE0OTgsIm5iZiI6MTcxNjIyMTE5OCwicGF0aCI6Ii8xMjYwNzY1LzMzMjEyNzMxNS0yMzk1YzNjOC0xOTE1LTRhMjQtYjY0ZS0zNWViZTE2ZTY3NGYucG5nP1gtQW16LUFsZ29yaXRobT1BV1M0LUhNQUMtU0hBMjU2JlgtQW16LUNyZWRlbnRpYWw9QUtJQVZDT0RZTFNBNTNQUUs0WkElMkYyMDI0MDUyMCUyRnVzLWVhc3QtMSUyRnMzJTJGYXdzNF9yZXF1ZXN0JlgtQW16LURhdGU9MjAyNDA1MjBUMTYwNjM4WiZYLUFtei1FeHBpcmVzPTMwMCZYLUFtei1TaWduYXR1cmU9OWViMDgwNGUxN2Q5Yjc0OGYxYzAxNTIzNjg4OGJjM2RmYWRmNDU3YmRjNjVmZDhhNjI3ZjYzOThmZTJkNDExZCZYLUFtei1TaWduZWRIZWFkZXJzPWhvc3QmYWN0b3JfaWQ9MCZrZXlfaWQ9MCZyZXBvX2lkPTAifQ.QoCTg1UY2hOVVdIm2soXm1cjEOeHew8HYCC0RePYmuk)

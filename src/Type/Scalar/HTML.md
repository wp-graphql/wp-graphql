# HTML Scalar

The `HTML` scalar type represents a string containing sanitized HTML code. This scalar is designed to ensure that any HTML passed through it is safe for rendering in a browser, removing potentially harmful code while preserving standard formatting tags.

## Sanitization Strategy

The sanitization process for the `HTML` scalar is a deliberate two-step process designed to provide a higher level of security and cleanliness than WordPress's default behavior.

1.  **Strip Script Tags and Content:** The raw input string is first passed through a regular expression (`preg_replace`) that specifically targets and removes `<script>` tags and all content within them. This is the crucial first step to prevent the contents of a script tag from being left behind in the output.

2.  **Standard KSES Sanitization:** The pre-cleaned string is then passed through the standard `wp_kses_post()` function. This function uses WordPress's well-established ruleset to strip any remaining disallowed tags and attributes, ensuring the final output is safe for display.

### Why this approach?

WordPress's default sanitization function, `wp_kses_post()`, removes dangerous tags like `<script>` but leaves the text content that was inside them. For example, `<script>alert('xss')</script>` would become `alert('xss')`. While this prevents a direct XSS attack, it leaves behind unwanted artifacts.

The `HTML` scalar provides a stricter guarantee. By using `preg_replace` first, we ensure that the entire script, including its content, is removed, providing a cleaner and more predictable output for API consumers. This two-step process combines a targeted fix with WordPress's robust, general-purpose sanitization engine.

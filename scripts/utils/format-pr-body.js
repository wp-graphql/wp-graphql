/**
 * Helper script to format PR body content for use in changeset generation
 */
const sanitizeHtml = require('sanitize-html');

const formatPrBody = (body) => {
  if (!body) return '';

  // First, normalize line endings to LF
  let formatted = body.replace(/\r\n?/g, '\n');

  // Use sanitize-html to safely remove HTML comments and tags
  formatted = sanitizeHtml(formatted, {
    allowedTags: [], // Remove all HTML tags
    allowedAttributes: {}, // Remove all attributes
    exclusiveFilter: function(frame) {
      // Remove HTML comments
      return frame.type === 'comment';
    },
  });

  // Remove extra whitespace and empty lines
  formatted = formatted
    .split('\n')
    .map(line => line.trim())
    .filter(line => {
      // Remove lines that are just dashes or empty
      return line.length > 0 && !/^-+$/.test(line);
    })
    .join('\n');

  // Escape special characters that could cause shell issues
  formatted = formatted
    .replace(/`/g, '\\`')     // Escape backticks
    .replace(/\$/g, '\\$')    // Escape dollar signs
    .replace(/"/g, '\\"')     // Escape double quotes
    .replace(/'/g, "\\'")     // Escape single quotes
    .replace(/\(/g, '\\(')    // Escape opening parentheses
    .replace(/\)/g, '\\)')    // Escape closing parentheses
    .replace(/\[/g, '\\[')    // Escape opening brackets
    .replace(/\]/g, '\\]')    // Escape closing brackets
    .replace(/\{/g, '\\{')    // Escape opening braces
    .replace(/\}/g, '\\}')    // Escape closing braces
    .replace(/&/g, '\\&')     // Escape ampersand
    .replace(/\|/g, '\\|')    // Escape pipe
    .replace(/;/g, '\\;')     // Escape semicolon
    .replace(/</g, '\\<')     // Escape less than
    .replace(/>/g, '\\>');    // Escape greater than

  // Debug output to stderr (won't affect the actual output)
  console.error('Formatted content:', formatted);

  return formatted;
};

// Get the PR body from command line argument
const prBody = process.argv[2];

if (!prBody) {
  console.error('No PR body provided');
  process.exit(1);
}

// Output the formatted body
process.stdout.write(formatPrBody(prBody));
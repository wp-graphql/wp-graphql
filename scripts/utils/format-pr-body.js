/**
 * Helper script to format PR body content for use in changeset generation
 */
const formatPrBody = (body) => {
  if (!body) return '';

  // First, normalize line endings to LF
  let formatted = body.replace(/\r\n?/g, '\n');

  // Remove HTML comments and their content (including multi-line)
  // This is a more aggressive approach that ensures all comment blocks are removed
  formatted = formatted
    // First pass: Remove HTML comments with their content
    .replace(/<!--[\s\S]*?-->/g, '')
    // Second pass: Remove any remaining comment markers (in case of malformed comments)
    .replace(/<!--[\s\S]*$/, '') // Remove from opening comment to end if no closing
    .replace(/^[\s\S]*?-->/, '') // Remove from start to first closing comment
    .replace(/<!--|\s*-->/g, ''); // Remove any remaining comment markers

  // Remove extra whitespace and empty lines that might be left after removing comments
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
    .replace(/'/g, "\\'");    // Escape single quotes

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
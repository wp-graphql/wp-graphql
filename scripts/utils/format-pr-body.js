/**
 * Helper script to format PR body content for use in changeset generation
 */
const formatPrBody = (body) => {
  if (!body) return '';

  // Remove any carriage returns
  let formatted = body.replace(/\r/g, '');

  // Remove HTML comments (including multi-line)
  formatted = formatted.replace(/<!--[\s\S]*?-->/g, '');

  // Remove extra whitespace and empty lines that might be left after removing comments
  formatted = formatted
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length > 0)
    .join('\n');

  // Escape special characters that could cause shell issues
  formatted = formatted
    .replace(/`/g, '\\`')     // Escape backticks
    .replace(/\$/g, '\\$')    // Escape dollar signs
    .replace(/"/g, '\\"')     // Escape double quotes
    .replace(/'/g, "\\'");    // Escape single quotes

  return formatted;
};

// Get the PR body from command line argument
const prBody = process.argv[2];

if (!prBody) {
  console.error('No PR body provided');
  process.exit(1);
}

// Output the formatted body
console.log(formatPrBody(prBody));
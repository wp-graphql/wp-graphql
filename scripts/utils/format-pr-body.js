/**
 * Helper script to format PR body content for use in changeset generation
 */
const formatPrBody = (body) => {
  if (!body) return '';

  // Remove any carriage returns
  let formatted = body.replace(/\r/g, '');

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
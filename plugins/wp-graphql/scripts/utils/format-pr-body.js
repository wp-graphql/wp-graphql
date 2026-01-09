/**
 * Helper script to format PR body content for use in changeset generation
 */

/**
 * Format PR body content by removing HTML comments and escaping special characters
 * @param {string} content The PR body content to format
 * @returns {string} The formatted content
 */
function formatPrBody(content) {
  if (!content) {
    return '';
  }

  // Remove HTML comments and their content using regex
  // This handles both single-line and multi-line comments
  let formatted = content
    .replace(/<!--[\s\S]*?-->/g, '') // Remove HTML comments
    .replace(/^[\s\r\n]+|[\s\r\n]+$/g, ''); // Trim whitespace

  return formatted;
}

// Handle both module import and command-line usage
if (require.main === module) {
  // When run from command line
  const content = process.argv[2];
  if (!content) {
    console.error('Error: No content provided');
    process.exit(1);
  }
  console.log(formatPrBody(content));
} else {
  // When imported as a module
  module.exports = formatPrBody;
}
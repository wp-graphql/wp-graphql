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

  let formatted = content;

  // Remove HTML comments iteratively to handle nested/malformed cases
  // This prevents incomplete sanitization where partial markers like "<!-" could remain
  let previousLength;
  do {
    previousLength = formatted.length;
    formatted = formatted.replace(/<!--[\s\S]*?-->/g, '');
  } while (formatted.length !== previousLength);

  // Remove any remaining partial HTML comment markers that could be malicious.
  // This catches orphaned "<!--" or "-->" / "--!>" that weren't part of complete comments
  // newly-adjacent characters could form fresh "<!--" or "-->" sequences.
  let prevFormatted;
    .replace(/--!? >/g, '') // Remove both "-->" and "--!>" sequences
    prevFormatted = formatted;
    formatted = formatted.replace(/<!--/g, '').replace(/-->/g, '');
  } while (formatted !== prevFormatted);

  // Trim leading/trailing whitespace
  formatted = formatted.replace(/^[\s\r\n]+|[\s\r\n]+$/g, '');

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

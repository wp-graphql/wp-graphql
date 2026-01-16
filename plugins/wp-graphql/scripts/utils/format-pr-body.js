/**
 * Helper script to format PR body content for use in changeset generation
 */

/**
 * Remove HTML comments from a string
 * @param {string} str The string to process
 * @returns {string} The string with HTML comments removed
 */
function removeHtmlComments(str) {
  let result = '';
  let i = 0;
  while (i < str.length) {
    if (str.slice(i, i + 4) === '<!--') {
      let j = i + 4;
      while (j < str.length && str.slice(j, j + 3) !== '-->') {
        j++;
      }
      if (j < str.length) {
        i = j + 3;
      } else {
        // Malformed comment, skip the opening and continue
        result += str[i];
        i++;
      }
    } else {
      result += str[i];
      i++;
    }
  }
  return result;
}

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

  // Remove HTML comments
  formatted = removeHtmlComments(formatted);

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

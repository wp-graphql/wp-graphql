#!/usr/bin/env node

/**
 * Test script to simulate the changeset generation workflow locally
 * This helps us test the PR body formatting and changeset generation
 * without having to push to GitHub each time.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Sample PR data from actual PRs that have caused issues
const TEST_CASES = [
  {
    number: 3363,
    title: "fix: cleanup Model fields for better source-of-truth and type-safety.",
    author: "justlevine",
    body: `## What does this implement/fix? Explain your changes.

This PR cleans up the existing WPGraphQL Models in the following ways:
- Fixes the model \`@property\` tags and sorts them.
- Sorts the model \`fields\` (cosmetic, to make the diff's easier to read now and in the future)
- Fixes individual model resolvers, either where the current return type could throw an error, or where they were "aliases" of other fields.
- Replaced internal references to "alias fields" with their source of truth.
- Fixes _all references_ to model fields (exposed now that the types were fixed and better enforced).

As a result, we're also able to begin enforcing traversable types on new code via PHPStan.

Additional benefits:
- Better types mean better usage with llms
- Cleanup now paves the way for more important changes (breaking or nonbreaking) with smaller diffs.

> [!IMPORTANT]
> This PR is based on https://github.com/wp-graphql/wp-graphql/pull/3362 which should be merged first.
>
> Relevant diffs:
> - Cleanup/fix the model fields: c5c58fcff1b7ab8387c105c326062bbaa6967710
> - Fix references to model fields: ca4b806fd461e52bcbe33058116ad225647b052e
> - Avoid using alias field references: c2200a82d21cf6b1aa47cbbb1149aedd828bd485`
  },
  // Add more test cases here as needed
];

// Create temporary directory
const tempDir = fs.mkdtempSync(path.join(require('os').tmpdir(), 'changeset-test-'));
console.log('Created temp directory:', tempDir);

// Ensure .changesets directory exists
const changesetDir = path.join(process.cwd(), '.changesets');
if (!fs.existsSync(changesetDir)) {
  fs.mkdirSync(changesetDir);
  console.log('Created .changesets directory');
}

// Test each PR case
TEST_CASES.forEach(({ number, title, author, body }) => {
  console.log(`\nTesting PR #${number}`);
  console.log('----------------------------------------');

  try {
    // Write PR body to temp file
    const bodyFile = path.join(tempDir, 'pr-body.txt');
    fs.writeFileSync(bodyFile, body);
    console.log('Wrote PR body to:', bodyFile);

    // Format PR body using our script
    const formattedBodyFile = path.join(tempDir, 'formatted-body.txt');
    const formatCommand = `node scripts/utils/format-pr-body.js "$(cat ${bodyFile})" > ${formattedBodyFile}`;
    execSync(formatCommand, { stdio: 'inherit' });
    console.log('Formatted PR body written to:', formattedBodyFile);

    // Generate changeset
    const changesetCommand = `node scripts/generate-changeset.js --pr="${number}" --title="${title}" --author="${author}" --body="$(cat ${formattedBodyFile})"`;
    execSync(changesetCommand, { stdio: 'inherit' });
    console.log('Generated changeset successfully');

    // Find and display the generated changeset
    const changesetFile = fs.readdirSync(changesetDir)
      .find(file => file.includes(`-pr-${number}.md`));

    if (changesetFile) {
      console.log('\nGenerated changeset content:');
      console.log('----------------------------------------');
      console.log(fs.readFileSync(path.join(changesetDir, changesetFile), 'utf8'));
    }

  } catch (error) {
    console.error('\nError processing PR:', error.message);
    if (error.stdout) console.log('stdout:', error.stdout.toString());
    if (error.stderr) console.log('stderr:', error.stderr.toString());
  }
});

// Cleanup
fs.rmSync(tempDir, { recursive: true, force: true });
console.log('\nCleaned up temp directory');
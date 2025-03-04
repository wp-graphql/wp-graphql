const { parseTitle, parsePRBody, createChangeset, ALLOWED_TYPES } = require('../generate-changeset');
const path = require('path');
const fs = require('fs');

jest.mock('@changesets/cli', () => ({
    createChangeset: jest.fn().mockImplementation(async (options) => ({
        summary: options.summary || '',
        releases: options.releases || [],
        major: options.major || false,
        links: options.links || [],
        breakingChanges: options.breakingChanges || '',
        upgradeInstructions: options.upgradeInstructions || ''
    }))
}));

jest.mock('../scan-since-tags', () => ({
    generateSinceTagsMetadata: jest.fn().mockResolvedValue({
        sinceFiles: ['src/test.php'],
        totalTags: 1
    })
}));

describe('Changeset Generation', () => {
    describe('parseTitle', () => {
        test('parses feat type correctly', () => {
            const result = parseTitle('feat: add new feature');
            expect(result).toEqual({ type: 'feat', isBreaking: false });
        });

        test('detects breaking change with !', () => {
            const result = parseTitle('feat!: breaking change');
            expect(result).toEqual({ type: 'feat', isBreaking: true });
        });

        test('allows scopes but ignores them for breaking detection', () => {
            const result = parseTitle('feat(graphiql): new feature');
            expect(result).toEqual({ type: 'feat', isBreaking: false });
        });

        test('detects breaking change with scope and !', () => {
            const result = parseTitle('feat(graphiql)!: breaking change');
            expect(result).toEqual({ type: 'feat', isBreaking: true });
        });

        test('handles multiple word scopes', () => {
            const result = parseTitle('fix(admin settings): bug fix');
            expect(result).toEqual({ type: 'fix', isBreaking: false });
        });

        test('throws error for invalid format', () => {
            expect(() => parseTitle('invalid title')).toThrow();
        });

        test('throws error for invalid type', () => {
            expect(() => parseTitle('invalid: some change')).toThrow();
        });

        test('handles all valid commit types', () => {
            ALLOWED_TYPES.forEach(type => {
                const result = parseTitle(`${type}: some change`);
                expect(result).toEqual({ type, isBreaking: false });
            });
        });

        test('handles scopes with special characters', () => {
            const result = parseTitle('feat(graphql-ide): new feature');
            expect(result).toEqual({ type: 'feat', isBreaking: false });
        });
    });

    describe('parsePRBody', () => {
        test('extracts all sections when present', () => {
            const body = `
What does this implement/fix? Explain your changes.
---
This is the description

### Breaking Changes
These things break

### Upgrade Instructions
Follow these steps
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'This is the description',
                breaking: 'These things break',
                upgrade: 'Follow these steps'
            });
        });

        test('handles missing sections', () => {
            const body = `
What does this implement/fix? Explain your changes.
---
Just a description
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'Just a description',
                breaking: '',
                upgrade: ''
            });
        });

        test('handles malformed PR body gracefully', () => {
            const result = parsePRBody('No proper sections');
            expect(result).toEqual({
                description: '',
                breaking: '',
                upgrade: ''
            });
        });

        test('preserves markdown in sections', () => {
            const body = `
What does this implement/fix? Explain your changes.
---
- Bullet point
- \`code\`
- **bold**

### Breaking Changes
1. First break
2. Second break

### Upgrade Instructions
\`\`\`php
// Code example
\`\`\`
            `;

            const result = parsePRBody(body);
            expect(result.description).toContain('- Bullet point');
            expect(result.breaking).toContain('1. First break');
            expect(result.upgrade).toContain('```php');
        });
    });

    describe('createChangeset', () => {
        const mockPR = {
            title: 'feat!: breaking change',
            body: `
What does this implement/fix? Explain your changes.
---
Description here

### Breaking Changes
Breaking details

### Upgrade Instructions
Update steps
            `,
            prNumber: 123
        };

        beforeEach(() => {
            jest.clearAllMocks();
            fs.writeFileSync = jest.fn();
            fs.existsSync = jest.fn().mockReturnValue(true);
        });

        test('generates correct changeset content for breaking change', async () => {
            const result = await createChangeset(mockPR);

            expect(result).toMatchObject({
                type: 'major',
                breaking: true,
                pr: 123
            });

            // Verify the written file content
            const writeCall = fs.writeFileSync.mock.calls[0][1];
            expect(writeCall).toContain('feat!: Description here');
            expect(writeCall).toContain('Breaking details');
            expect(writeCall).toContain('Update steps');
            expect(writeCall).toContain('"pr_url": "https://github.com/wp-graphql/wp-graphql/pull/123"');
        });

        test('throws error for breaking change without upgrade instructions', async () => {
            const prWithoutInstructions = {
                ...mockPR,
                body: `
What does this implement/fix? Explain your changes.
---
Description here

### Breaking Changes
Breaking details
                `
            };

            await expect(createChangeset(prWithoutInstructions)).rejects.toThrow('Breaking changes must include upgrade instructions');
        });

        test('generates correct changeset content for non-breaking feature', async () => {
            const nonBreakingPR = {
                title: 'feat: new feature',
                body: `
What does this implement/fix? Explain your changes.
---
Adding a cool feature
                `,
                prNumber: 456
            };

            const result = await createChangeset(nonBreakingPR);

            expect(result).toMatchObject({
                type: 'minor',
                breaking: false,
                pr: 456
            });

            const writeCall = fs.writeFileSync.mock.calls[0][1];
            expect(writeCall).toContain('feat: Adding a cool feature');
            expect(writeCall).toContain('"pr_url": "https://github.com/wp-graphql/wp-graphql/pull/456"');
        });
    });
});

// Add more tests...
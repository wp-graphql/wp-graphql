const { parseTitle, parsePRBody, createChangeset } = require('../generate-changeset');

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

        test('handles all valid commit types', () => {
            const types = ['feat', 'fix', 'build', 'chore', 'ci', 'docs', 'perf', 'refactor', 'revert', 'style', 'test'];
            types.forEach(type => {
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
            number: 123
        };

        beforeEach(() => {
            jest.clearAllMocks();
        });

        test('generates correct changeset content', async () => {
            const result = await createChangeset(mockPR);
            expect(result).toMatchObject({
                type: 'major',
                breaking: true,
                pr: 123
            });
        });

        test('handles missing PR sections', async () => {
            const result = await createChangeset({
                ...mockPR,
                body: 'Just a description'
            });
            expect(result).toBeDefined();
            expect(result.breaking).toBe(false);
        });
    });
});

// Add more tests...
const { parseTitle, parsePRBody, createChangeset, formatSummary, ALLOWED_TYPES } = require('../generate-changeset');
const { generateSinceTagsMetadata } = require('../scan-since-tags');
const fs = require('fs');
const path = require('path');

jest.mock('../scan-since-tags', () => ({
    generateSinceTagsMetadata: jest.fn()
}));

jest.mock('fs', () => ({
    existsSync: jest.fn(),
    writeFileSync: jest.fn(),
    mkdirSync: jest.fn()
}));

describe('Changeset Generation', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        generateSinceTagsMetadata.mockResolvedValue({
            sinceFiles: ['test.php'],
            totalTags: 1
        });
        fs.existsSync.mockReturnValue(true);
        fs.writeFileSync.mockImplementation(() => {});
        fs.mkdirSync.mockImplementation(() => {});
    });

    describe('parseTitle', () => {
        test('parses basic title format', () => {
            const result = parseTitle('feat: Add new feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: false
            });
        });

        test('parses title with scope', () => {
            const result = parseTitle('feat(core): Add new feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: false
            });
        });

        test('detects breaking change with !', () => {
            const result = parseTitle('feat!: Breaking feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: true
            });
        });

        test('detects breaking change with BREAKING CHANGE', () => {
            const result = parseTitle('feat: BREAKING CHANGE - New feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: true
            });
        });

        test('throws on invalid type', () => {
            expect(() => parseTitle('invalid: Some change')).toThrow('PR title does not follow conventional commit format');
        });

        test('validates allowed types', () => {
            ALLOWED_TYPES.forEach(type => {
                const result = parseTitle(`${type}: Some change`);
                expect(result.type).toBe(type);
            });
        });
    });

    describe('parsePRBody', () => {
        test('extracts all sections with ### headings', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                This is a description

                ### Breaking Changes
                This breaks something

                ### Upgrade Instructions
                Follow these steps
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'This is a description',
                breaking: 'This breaks something',
                upgrade: 'Follow these steps'
            });
        });

        test('extracts all sections with ## headings', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                This is a description

                ## Breaking Changes
                This breaks something

                ## Upgrade Instructions
                Follow these steps
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'This is a description',
                breaking: 'This breaks something',
                upgrade: 'Follow these steps'
            });
        });

        test('handles mixed heading levels', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                This is a description

                ## Breaking Changes
                This breaks something

                ### Upgrade Instructions
                Follow these steps
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'This is a description',
                breaking: 'This breaks something',
                upgrade: 'Follow these steps'
            });
        });

        test('handles missing sections', () => {
            const body = 'What does this implement/fix? Explain your changes.\n---\nJust a description';
            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'Just a description',
                breaking: '',
                upgrade: ''
            });
        });

        test('cleans up N/A placeholders', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                Description

                ## Breaking Changes
                N/A

                ## Upgrade Instructions
                none
            `;

            const result = parsePRBody(body);
            expect(result.breaking).toBe('');
            expect(result.upgrade).toBe('');
        });
    });

    describe('createChangeset', () => {
        const validPR = {
            title: 'feat: New feature',
            body: `
                What does this implement/fix? Explain your changes.
                ---
                Adds a new feature

                ### Breaking Changes

                ### Upgrade Instructions
            `,
            prNumber: '123'
        };

        test('creates basic changeset', async () => {
            const result = await createChangeset(validPR);
            expect(result).toEqual({
                type: 'minor',
                breaking: false,
                pr: 123,
                sinceFiles: ['test.php'],
                totalSinceTags: 1,
                changesetId: expect.stringContaining('pr-123-')
            });
            expect(fs.writeFileSync).toHaveBeenCalled();
        });

        test('handles breaking changes', async () => {
            const breakingPR = {
                title: 'feat!: Breaking feature',
                body: `
                    What does this implement/fix? Explain your changes.
                    ---
                    Breaking feature description

                    ### Breaking Changes
                    This breaks something

                    ### Upgrade Instructions
                    Follow these steps
                `,
                prNumber: '123'
            };

            const result = await createChangeset(breakingPR);
            expect(result.type).toBe('major');
            expect(result.breaking).toBe(true);

            // Verify changeset content
            const writeCall = fs.writeFileSync.mock.calls[0][1];
            expect(writeCall).toContain('#### Breaking Changes');
            expect(writeCall).toContain('#### Upgrade Instructions');
        });

        test('requires upgrade instructions for breaking changes', async () => {
            const breakingPR = {
                title: 'feat!: Breaking feature',
                body: `
                    What does this implement/fix? Explain your changes.
                    ---
                    Breaking feature

                    ### Breaking Changes
                    This breaks something
                `,
                prNumber: '123'
            };

            await expect(createChangeset(breakingPR)).rejects.toThrow('Breaking changes must include upgrade instructions');
        });

        test('includes @since tags metadata', async () => {
            const result = await createChangeset(validPR);
            expect(result.sinceFiles).toEqual(['test.php']);
            expect(result.totalSinceTags).toBe(1);

            const writeCall = fs.writeFileSync.mock.calls[0][1];
            expect(writeCall).toContain('#### Files with @since next-version');
            expect(writeCall).toContain('- test.php');
        });

        test('creates correct changeset file structure', async () => {
            await createChangeset(validPR);
            const writeCall = fs.writeFileSync.mock.calls[0][1];

            // Verify YAML front matter
            expect(writeCall).toMatch(/^---\n/);
            expect(writeCall).toMatch(/\n---\n/);

            // Verify YAML content
            const yamlContent = writeCall.match(/---\n([\s\S]*?)\n---/)[1];
            expect(yamlContent).toContain('type: minor');
            expect(yamlContent).toContain('pr: 123');
            expect(yamlContent).toContain('breaking: false');

            // Verify content structure
            expect(writeCall).toContain('### feat:');
            expect(writeCall).toContain('[PR #123]');
        });

        test('creates changeset with breaking change from title', async () => {
            const pr = {
                title: 'fix!: Breaking change in bug fix',
                body: `What does this implement/fix? Explain your changes.
---
This is a breaking bug fix

## Breaking Changes
This breaks something

## Upgrade Instructions
Follow these steps`,
                prNumber: '123'
            };

            const result = await createChangeset(pr);
            expect(result.type).toBe('major');
            expect(result.breaking).toBe(true);

            // Verify changeset content
            const writeCall = fs.writeFileSync.mock.calls[0][1];
            const yamlContent = writeCall.match(/---\n([\s\S]*?)\n---/)[1];
            expect(yamlContent).toContain('type: major');
            expect(yamlContent).toContain('breaking: true');
            expect(writeCall).toContain('#### Breaking Changes');
        });

        test('creates changeset with breaking change from PR body', async () => {
            const pr = {
                title: 'fix: Non-breaking title',
                body: `What does this implement/fix? Explain your changes.
---
This is a bug fix

## Breaking Changes
This breaks something

## Upgrade Instructions
Follow these steps`,
                prNumber: '123'
            };

            const result = await createChangeset(pr);
            expect(result.type).toBe('major');
            expect(result.breaking).toBe(false); // Title doesn't have breaking change marker

            // Verify changeset content
            const writeCall = fs.writeFileSync.mock.calls[0][1];
            const yamlContent = writeCall.match(/---\n([\s\S]*?)\n---/)[1];
            expect(yamlContent).toContain('type: major');
            expect(writeCall).toContain('#### Breaking Changes');
            expect(writeCall).toContain('This breaks something');
        });

        test('creates changeset with breaking change from both title and body', async () => {
            const pr = {
                title: 'feat!: Breaking feature change',
                body: `What does this implement/fix? Explain your changes.
---
This is a feature

## Breaking Changes
This also breaks something

## Upgrade Instructions
Follow these steps`,
                prNumber: '123'
            };

            const result = await createChangeset(pr);
            expect(result.type).toBe('major');
            expect(result.breaking).toBe(true);

            // Verify changeset content
            const writeCall = fs.writeFileSync.mock.calls[0][1];
            const yamlContent = writeCall.match(/---\n([\s\S]*?)\n---/)[1];
            expect(yamlContent).toContain('type: major');
            expect(yamlContent).toContain('breaking: true');
            expect(writeCall).toContain('#### Breaking Changes');
            expect(writeCall).toContain('This also breaks something');
            expect(writeCall).toContain('#### Upgrade Instructions');
            expect(writeCall).toContain('Follow these steps');
        });

        test('createChangeset generates correct file content', async () => {
            // Mock implementation
            fs.writeFileSync = jest.fn();

            // Call the function
            await createChangeset({
                title: 'feat: Test feature',
                body: 'Test description',
                prNumber: '123'
            });

            // Get the content that would have been written
            const fileContent = fs.writeFileSync.mock.calls[0][1];

            // Verify it has the correct format
            expect(fileContent).toContain('---\ntype: minor\npr: 123\nbreaking: false\n---');
            expect(fileContent).toContain('### feat: Test feature');
            expect(fileContent).toContain('[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)');
        });
    });

    describe('formatSummary', () => {
        test('formats regular summary', () => {
            expect(formatSummary('feat', false, 'New feature')).toBe('feat: New feature');
        });

        test('formats breaking change summary', () => {
            expect(formatSummary('feat', true, 'Breaking feature')).toBe('feat!: Breaking feature');
        });

        test('trims description', () => {
            expect(formatSummary('fix', false, '  Fix bug  ')).toBe('fix: Fix bug');
        });
    });
});
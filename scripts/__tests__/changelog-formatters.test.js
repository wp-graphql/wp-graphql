const { formatChangelogMd } = require('../changelog-formatters/changelog-md');
const { formatReadmeTxt } = require('../changelog-formatters/readme-txt');

describe('Changelog Formatters', () => {
    const mockChangeset = {
        summary: 'feat: Add new GraphQL field',
        type: 'minor',
        pr: 123,
        pr_number: '123',
        pr_url: 'https://github.com/wp-graphql/wp-graphql/pull/123',
        breaking: false,
        breaking_changes: '',
        upgrade_instructions: '',
        releases: [{ name: 'wp-graphql', type: 'minor' }]
    };

    const mockBreakingChangeset = {
        summary: 'feat!: Breaking change',
        type: 'major',
        pr: 124,
        pr_number: '124',
        pr_url: 'https://github.com/wp-graphql/wp-graphql/pull/124',
        breaking: true,
        breaking_changes: 'This breaks the existing API',
        upgrade_instructions: 'Update your queries to use the new format',
        releases: [{ name: 'wp-graphql', type: 'major' }]
    };

    describe('CHANGELOG.md Formatter', () => {
        test('formats basic feature change', () => {
            const result = formatChangelogMd('2.1.0', [mockChangeset]);

            expect(result).toContain('## [2.1.0]');
            expect(result).toContain('### Features');
            expect(result).toContain('- Add new GraphQL field ([#123](https://github.com/wp-graphql/wp-graphql/pull/123))');
        });

        test('formats breaking change', () => {
            const result = formatChangelogMd('3.0.0', [mockBreakingChangeset]);

            expect(result).toContain('## [3.0.0]');
            expect(result).toContain('### ⚠ BREAKING CHANGES');
            expect(result).toContain('This breaks the existing API');
            expect(result).toContain('#### Upgrade Instructions');
            expect(result).toContain('Update your queries to use the new format');
            expect(result).toContain('([#124](https://github.com/wp-graphql/wp-graphql/pull/124))');
        });

        test('groups multiple changes by type', () => {
            const changes = [
                mockChangeset,
                { ...mockChangeset, summary: 'fix: Fix bug', type: 'patch', pr: 125 },
                { ...mockChangeset, summary: 'docs: Update docs', type: 'patch', pr: 126 }
            ];

            const result = formatChangelogMd('2.1.0', changes);

            expect(result).toContain('### Features');
            expect(result).toContain('### Bug Fixes');
            expect(result).toContain('### Documentation');
        });
    });

    describe('readme.txt Formatter', () => {
        test('formats basic feature change', () => {
            const result = formatReadmeTxt('2.1.0', [mockChangeset]);

            expect(result).toContain('= 2.1.0 =');
            expect(result).toContain('**New Features**');
            expect(result).toContain('* Add new GraphQL field');
        });

        test('formats breaking change', () => {
            const result = formatReadmeTxt('3.0.0', [mockBreakingChangeset]);

            expect(result).toContain('= 3.0.0 =');
            expect(result).toContain('**BREAKING CHANGES**');
            expect(result).toContain('This breaks the existing API');
            expect(result).toContain('Upgrade Instructions:');
            expect(result).toContain('Update your queries to use the new format');
        });

        test('adds upgrade notice for breaking changes', () => {
            const result = formatReadmeTxt('3.0.0', [mockBreakingChangeset]);

            expect(result).toContain('== Upgrade Notice ==');
            expect(result).toContain('= 3.0.0 =');
            expect(result).toContain('**BREAKING CHANGE UPDATE**');
            expect(result).toContain('This breaks the existing API');
        });

        test('groups multiple changes by type', () => {
            const changes = [
                mockChangeset,
                { ...mockChangeset, summary: 'fix: Fix bug', type: 'patch', pr: 125 },
                { ...mockChangeset, summary: 'docs: Update docs', type: 'patch', pr: 126 }
            ];

            const result = formatReadmeTxt('2.1.0', changes);

            expect(result).toContain('**New Features**');
            expect(result).toContain('**Bug Fixes**');
            expect(result).not.toContain('**Documentation**'); // Docs changes not shown in readme.txt
        });
    });
});
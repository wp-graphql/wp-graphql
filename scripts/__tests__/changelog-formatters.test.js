const { formatChangelogMd } = require('../changelog-formatters/changelog-md');
const { formatReadmeTxt, formatUpgradeNotice } = require('../changelog-formatters/readme-txt');

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
            const mockChangeset = {
                type: 'minor',
                pr: 123,
                summary: 'feat: Add new GraphQL field',
                content: 'Adds a new GraphQL field'
            };

            const result = formatChangelogMd('2.1.0', [mockChangeset]);

            expect(result).toContain('## [2.1.0]');
            expect(result).toContain('### New Features');
            expect(result).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123): feat: Add new GraphQL field');
        });

        test('formats breaking change', () => {
            const mockBreakingChangeset = {
                type: 'major',
                pr: 124,
                breaking: true,
                summary: 'feat!: Breaking API change',
                breaking_changes: 'This breaks the existing API',
                upgrade_instructions: 'Update your queries to use the new format'
            };

            const result = formatChangelogMd('3.0.0', [mockBreakingChangeset]);

            expect(result).toContain('## [3.0.0]');
            expect(result).toContain('### ⚠ BREAKING CHANGES');
            expect(result).toContain('This breaks the existing API');
            expect(result).toContain('#### Upgrade Instructions:');
            expect(result).toContain('Update your queries to use the new format');
        });

        test('groups multiple changes by type', () => {
            const changes = [
                {
                    type: 'minor',
                    pr: 123,
                    summary: 'feat: Add new GraphQL field',
                    content: 'Adds a new GraphQL field'
                },
                {
                    type: 'patch',
                    pr: 125,
                    summary: 'fix: Fix bug',
                    content: 'Fixes a bug'
                },
                {
                    type: 'patch',
                    pr: 126,
                    summary: 'docs: Update docs',
                    content: 'Updates documentation'
                }
            ];

            const result = formatChangelogMd('2.1.0', changes);

            expect(result).toContain('### New Features');
            expect(result).toContain('### Chores / Bugfixes');
            expect(result).toContain('### Documentation');
            expect(result).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123): feat: Add new GraphQL field');
            expect(result).toContain('[#125](https://github.com/wp-graphql/wp-graphql/pull/125): fix: Fix bug');
            expect(result).toContain('[#126](https://github.com/wp-graphql/wp-graphql/pull/126): docs: Update docs');
        });
    });

    describe('readme.txt Formatter', () => {
        test('formats basic feature change', () => {
            const mockChangeset = {
                type: 'minor',
                pr: 123,
                summary: 'feat: Add new GraphQL field',
                content: 'Adds a new GraphQL field'
            };

            const result = formatReadmeTxt('2.1.0', [mockChangeset]);

            expect(result).toContain('= 2.1.0 =');
            expect(result).toContain('**New Features**');
            expect(result).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123): feat: Add new GraphQL field');
        });

        test('formats breaking change', () => {
            const mockBreakingChangeset = {
                type: 'major',
                pr: 124,
                breaking: true,
                summary: 'feat!: Breaking API change',
                breaking_changes: 'This breaks the existing API',
                upgrade_instructions: 'Update your queries to use the new format',
                content: '#### Breaking Changes\nThis breaks the existing API\n\n#### Upgrade Instructions\nUpdate your queries to use the new format'
            };

            const result = formatReadmeTxt('3.0.0', [mockBreakingChangeset]);

            expect(result).toContain('= 3.0.0 =');
            expect(result).toContain('**BREAKING CHANGES**');
            expect(result).toContain('This breaks the existing API');
            expect(result).toContain('**Upgrade Instructions:**');
            expect(result).toContain('Update your queries to use the new format');
        });

        test('adds upgrade notice for breaking changes', () => {
            const mockBreakingChangeset = {
                type: 'major',
                pr: 124,
                breaking: true,
                summary: 'feat!: Breaking API change',
                breaking_changes: 'This breaks the existing API',
                upgrade_instructions: 'Update your queries to use the new format',
                content: '#### Breaking Changes\nThis breaks the existing API\n\n#### Upgrade Instructions\nUpdate your queries to use the new format'
            };

            const result = formatUpgradeNotice('3.0.0', [mockBreakingChangeset]);

            expect(result).toContain('= 3.0.0 =');
            expect(result).toContain('**BREAKING CHANGE UPDATE**');
            expect(result).toContain('[#124](https://github.com/wp-graphql/wp-graphql/pull/124): feat!: Breaking API change');
            expect(result).toContain('This breaks the existing API');
            expect(result).toContain('Update your queries to use the new format');
        });

        test('groups multiple changes by type', () => {
            const changes = [
                {
                    type: 'minor',
                    pr: 123,
                    summary: 'feat: Add new GraphQL field',
                    content: 'Adds a new GraphQL field'
                },
                {
                    type: 'patch',
                    pr: 125,
                    summary: 'fix: Fix bug',
                    content: 'Fixes a bug'
                },
                {
                    type: 'patch',
                    pr: 126,
                    summary: 'docs: Update docs',
                    content: 'Updates documentation'
                }
            ];

            const result = formatReadmeTxt('2.1.0', changes);

            expect(result).toContain('**New Features**');
            expect(result).toContain('**Chores / Bugfixes**');
            expect(result).not.toContain('**Documentation**'); // Docs changes not shown in readme.txt
            expect(result).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123): feat: Add new GraphQL field');
            expect(result).toContain('[#125](https://github.com/wp-graphql/wp-graphql/pull/125): fix: Fix bug');
        });
    });
});
const { formatChangelogMd } = require('../changelog-formatters/changelog-md');
const { formatReadmeTxt } = require('../changelog-formatters/readme-txt');
const defaultChangelogFunctions = require('@changesets/cli/changelog');
const fs = require('fs');

jest.mock('fs');
jest.mock('@changesets/get-github-info');

jest.mock('@changesets/cli/changelog', () => ({
    getReleaseLine: async (changeset) => `${changeset.summary} (${changeset.commit})`,
    default: {
        getReleaseLine: async (changeset) => `${changeset.summary} (${changeset.commit})`
    }
}));

describe('Changelog Integration', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        // Mock both files existing
        fs.readFileSync
            .mockReturnValueOnce('Stable tag: 1.0.0') // readme.txt
            .mockReturnValueOnce('# Changelog'); // CHANGELOG.md
    });

    test('both formatters generate appropriate changelog entries', () => {
        const mockChangeset = {
            type: 'minor',
            pr: 123,
            summary: 'feat: new feature',
            content: 'Adds a new feature'
        };

        // Generate changelogs with both formatters
        const readmeTxtChangelog = formatReadmeTxt('2.0.0', [mockChangeset]);
        const defaultChangelog = formatChangelogMd('2.0.0', [mockChangeset]);

        // Verify readme.txt format
        expect(readmeTxtChangelog).toContain('= 2.0.0 =');
        expect(readmeTxtChangelog).toContain('**New Features**');
        expect(readmeTxtChangelog).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123): feat: new feature');

        // Verify CHANGELOG.md format
        expect(defaultChangelog).toContain('## [2.0.0]');
        expect(defaultChangelog).toContain('### New Features');
        expect(defaultChangelog).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123): feat: new feature');
    });

    test('handles beta releases appropriately in both formats', () => {
        const mockChangeset = {
            type: 'minor',
            pr: 123,
            summary: 'feat: beta feature',
            content: 'Adds a beta feature'
        };

        // Generate changelogs with both formatters
        const readmeTxtChangelog = formatReadmeTxt('2.0.0-beta.1', [mockChangeset]);
        const defaultChangelog = formatChangelogMd('2.0.0-beta.1', [mockChangeset]);

        // Verify readme.txt format
        expect(readmeTxtChangelog).toContain('= 2.0.0-beta.1 =');
        expect(readmeTxtChangelog).toContain('**New Features**');

        // Verify CHANGELOG.md format
        expect(defaultChangelog).toContain('## [2.0.0-beta.1]');
        expect(defaultChangelog).toContain('### New Features');
    });
});
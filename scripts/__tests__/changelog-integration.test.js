const { getReadmeTxtChangelog } = require('../changelog-formatters/readme-txt');
const defaultChangelogFunctions = require('@changesets/cli/changelog');
const fs = require('fs');

jest.mock('fs');
jest.mock('@changesets/get-github-info');

describe('Changelog Integration', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        // Mock both files existing
        fs.readFileSync
            .mockReturnValueOnce('Stable tag: 1.0.0') // readme.txt
            .mockReturnValueOnce('# Changelog'); // CHANGELOG.md
    });

    test('both formatters generate appropriate changelog entries', async () => {
        const release = {
            newVersion: '2.0.0',
            changesets: [
                {
                    summary: 'feat: new feature',
                    commit: 'abc123',
                    id: 'unique-id'
                }
            ]
        };

        const options = { repo: 'wp-graphql/wp-graphql' };

        // Get changelog entries from both formatters
        const readmeTxtChangelog = await getReadmeTxtChangelog(release, options);
        const defaultChangelog = await defaultChangelogFunctions.getReleaseLine(
            release.changesets[0],
            options
        );

        // Verify readme.txt format
        expect(readmeTxtChangelog).toContain('= 2.0.0 =');
        expect(readmeTxtChangelog).toContain('**New Features**');
        expect(readmeTxtChangelog).toContain('* feat: new feature');

        // Verify CHANGELOG.md format
        expect(defaultChangelog).toContain('new feature');
        expect(defaultChangelog).toContain('abc123');
    });

    test('handles beta releases appropriately in both formats', async () => {
        const release = {
            newVersion: '2.0.0-beta.1',
            changesets: [
                {
                    summary: 'feat: beta feature',
                    commit: 'def456',
                    id: 'beta-id'
                }
            ]
        };

        const options = { repo: 'wp-graphql/wp-graphql' };

        const readmeTxtChangelog = await getReadmeTxtChangelog(release, options);
        const defaultChangelog = await defaultChangelogFunctions.getReleaseLine(
            release.changesets[0],
            options
        );

        // Verify readme.txt format for beta
        expect(readmeTxtChangelog).toContain('= 2.0.0-beta.1 =');
        expect(fs.writeFileSync).not.toHaveBeenCalled(); // Stable tag not updated

        // Verify CHANGELOG.md format includes beta version
        expect(defaultChangelog).toContain('beta feature');
        expect(defaultChangelog).toContain('def456');
    });
});
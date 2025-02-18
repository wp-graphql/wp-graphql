const { getReadmeTxtChangelog, updateStableTag } = require('../../changelog-formatters/readme-txt');
const fs = require('fs');
const path = require('path');

jest.mock('fs');
jest.mock('@changesets/get-github-info');

describe('readme.txt formatter', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        fs.readFileSync.mockReturnValue('Stable tag: 1.0.0');
    });

    describe('updateStableTag', () => {
        test('updates stable tag for stable release', () => {
            updateStableTag('2.0.0');
            expect(fs.writeFileSync).toHaveBeenCalledWith(
                expect.any(String),
                'Stable tag: 2.0.0'
            );
        });

        test('does not update stable tag for beta release', () => {
            updateStableTag('2.0.0-beta.1');
            expect(fs.writeFileSync).not.toHaveBeenCalled();
        });
    });

    describe('getReadmeTxtChangelog', () => {
        test('generates changelog while leaving CHANGELOG.md untouched', async () => {
            const release = {
                newVersion: '2.0.0',
                changesets: [
                    { summary: 'feat: new feature', commit: 'abc' }
                ]
            };

            // Mock both files existing
            fs.readFileSync
                .mockReturnValueOnce('Stable tag: 1.0.0') // readme.txt
                .mockReturnValueOnce('# Changelog'); // CHANGELOG.md

            const changelog = await getReadmeTxtChangelog(release, { repo: 'wp-graphql/wp-graphql' });

            // Should only write to readme.txt
            expect(fs.writeFileSync).toHaveBeenCalledTimes(1);
            expect(fs.writeFileSync.mock.calls[0][0]).toContain('readme.txt');
        });

        test('formats changelog with all section types', async () => {
            const release = {
                newVersion: '2.0.0',
                changesets: [
                    { summary: 'feat: new feature', commit: 'abc' },
                    { summary: 'fix: bug fix', commit: 'def' },
                    { summary: 'chore: other change', commit: 'ghi' }
                ]
            };

            const changelog = await getReadmeTxtChangelog(release, { repo: 'wp-graphql/wp-graphql' });

            expect(changelog).toContain('= 2.0.0 =');
            expect(changelog).toContain('**New Features**');
            expect(changelog).toContain('**Chores / Bugfixes**');
            expect(changelog).toContain('**Other Changes**');
        });

        test('handles beta releases', async () => {
            const release = {
                newVersion: '2.0.0-beta.1',
                changesets: [
                    { summary: 'feat: new feature', commit: 'abc' }
                ]
            };

            const changelog = await getReadmeTxtChangelog(release, { repo: 'wp-graphql/wp-graphql' });

            expect(changelog).toContain('= 2.0.0-beta.1 =');
            expect(fs.writeFileSync).not.toHaveBeenCalled();
        });
    });

    describe('changelog grouping', () => {
        test('groups multiple changes correctly', async () => {
            const release = {
                newVersion: '2.0.0',
                changesets: [
                    { summary: 'feat: feature 1', commit: 'abc' },
                    { summary: 'feat: feature 2', commit: 'def' },
                    { summary: 'fix: bug fix', commit: 'ghi' },
                    { summary: 'docs: documentation', commit: 'jkl' }
                ]
            };

            const changelog = await getReadmeTxtChangelog(release, { repo: 'wp-graphql/wp-graphql' });

            expect(changelog).toMatch(/\*\*New Features\*\*.*feature 1.*feature 2/s);
            expect(changelog).toMatch(/\*\*Chores \/ Bugfixes\*\*.*bug fix/s);
            expect(changelog).toMatch(/\*\*Other Changes\*\*.*documentation/s);
        });

        test('handles breaking changes', async () => {
            const release = {
                newVersion: '3.0.0',
                changesets: [
                    { summary: 'feat!: breaking feature', commit: 'abc' }
                ]
            };

            const changelog = await getReadmeTxtChangelog(release, { repo: 'wp-graphql/wp-graphql' });
            expect(changelog).toContain('breaking feature');
        });
    });

    describe('error handling', () => {
        test('handles missing commit data', async () => {
            const release = {
                newVersion: '2.0.0',
                changesets: [
                    { summary: 'feat: feature' }
                ]
            };

            const changelog = await getReadmeTxtChangelog(release, { repo: 'wp-graphql/wp-graphql' });
            expect(changelog).toContain('feat: feature');
        });
    });
});
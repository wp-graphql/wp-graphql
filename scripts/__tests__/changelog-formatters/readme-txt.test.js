const { getReadmeTxtChangelog, getUpgradeNoticeEntry, updateReadmeTxt } = require('../../changelog-formatters/readme-txt');
const defaultChangelogFunctions = require('@changesets/cli/changelog');
const fs = require('fs');
const path = require('path');

jest.mock('fs');
jest.mock('@changesets/get-github-info');

describe('Changelog Integration', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        fs.readFileSync.mockReturnValue('== Changelog ==\n\nOld entries\n\n== Upgrade Notice ==\n\nOld notices');
    });

    describe('Upgrade Notice Generation', () => {
        test('generates upgrade notice for breaking changes', async () => {
            const release = {
                newVersion: '2.0.0',
                changesets: [{
                    breaking: true,
                    breakingChanges: 'This is a breaking change',
                    upgradeInstructions: 'Follow these steps',
                    pr: 123
                }]
            };

            const options = { repo: 'wp-graphql/wp-graphql' };
            const notice = await getUpgradeNoticeEntry(release, options);

            expect(notice).toContain('= 2.0.0 =');
            expect(notice).toContain('**BREAKING CHANGE UPDATE**');
            expect(notice).toContain('This is a breaking change');
            expect(notice).toContain('In <a href="wp-graphql/wp-graphql/pull/123">#123</a>');
            expect(notice).toContain('Follow these steps');
        });

        test('generates standard notice for minor versions', async () => {
            const release = {
                newVersion: '2.1.0',
                changesets: [{
                    breaking: false,
                    summary: 'New feature'
                }]
            };

            const notice = await getUpgradeNoticeEntry(release, {});
            expect(notice).toContain('= 2.1.0 =');
            expect(notice).toContain('no known breaking changes');
            expect(notice).toContain('recommend testing on staging servers');
        });

        test('skips upgrade notice for patch versions without changes', async () => {
            const release = {
                newVersion: '2.1.1',
                changesets: [{
                    breaking: false,
                    summary: 'Bug fix'
                }]
            };

            const notice = await getUpgradeNoticeEntry(release, {});
            expect(notice).toBe('');
        });
    });

    describe('Readme.txt Integration', () => {
        test('updates both changelog and upgrade notice sections', async () => {
            const release = {
                newVersion: '2.0.0',
                changesets: [{
                    breaking: true,
                    breakingChanges: 'Breaking change',
                    upgradeInstructions: 'Upgrade steps',
                    summary: 'New feature',
                    pr: 123
                }]
            };

            const options = { repo: 'wp-graphql/wp-graphql' };
            await updateReadmeTxt(release, options);

            expect(fs.writeFileSync).toHaveBeenCalledWith(
                expect.any(String),
                expect.stringContaining('== Changelog ==')
            );
            expect(fs.writeFileSync).toHaveBeenCalledWith(
                expect.any(String),
                expect.stringContaining('== Upgrade Notice ==')
            );
            expect(fs.writeFileSync).toHaveBeenCalledWith(
                expect.any(String),
                expect.stringContaining('**BREAKING CHANGE UPDATE**')
            );
        });

        test('handles missing upgrade notice section', async () => {
            fs.readFileSync.mockReturnValue('== Changelog ==\n\nOld entries');

            const release = {
                newVersion: '2.0.0',
                changesets: [{
                    breaking: true,
                    breakingChanges: 'Breaking change',
                    upgradeInstructions: 'Upgrade steps'
                }]
            };

            await updateReadmeTxt(release, {});

            expect(fs.writeFileSync).toHaveBeenCalledWith(
                expect.any(String),
                expect.stringContaining('== Upgrade Notice ==\n\n= 2.0.0 =')
            );
        });

        test('preserves stable tag for beta releases', async () => {
            const release = {
                newVersion: '2.0.0-beta.1',
                changesets: [{
                    breaking: true,
                    breakingChanges: 'Beta breaking change'
                }]
            };

            await updateReadmeTxt(release, {});

            const writeCall = fs.writeFileSync.mock.calls[0][1];
            expect(writeCall).not.toMatch(/Stable tag: 2.0.0-beta.1/);
        });
    });
});
const { getUpgradeNoticeEntry, updateReadmeTxt } = require('../../changelog-formatters/readme-txt');
const fs = require('fs');
const path = require('path');

jest.mock('fs');
jest.mock('path');

describe('Changelog Integration', () => {
    describe('Upgrade Notice Generation', () => {
        test('generates upgrade notice for breaking changes', async () => {
            const mockRelease = {
                newVersion: '2.0.0',
                changesets: [
                    {
                        breaking: true,
                        pr: 123,
                        summary: 'feat!: Breaking change',
                        breaking_changes: 'This is a breaking change',
                        upgrade_instructions: 'Follow these steps'
                    }
                ]
            };

            const notice = await getUpgradeNoticeEntry(mockRelease, { repo: 'wp-graphql/wp-graphql' });

            expect(notice).toContain('= 2.0.0 =');
            expect(notice).toContain('**BREAKING CHANGE UPDATE**');
            expect(notice).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123): feat!: Breaking change');
            expect(notice).toContain('This is a breaking change');
            expect(notice).toContain('Follow these steps');
        });

        test('generates standard notice for minor versions', async () => {
            const mockRelease = {
                newVersion: '2.1.0',
                changesets: [
                    {
                        type: 'minor',
                        breaking: false,
                        summary: 'feat: Add new feature'
                    }
                ]
            };

            const notice = await getUpgradeNoticeEntry(mockRelease, {});

            expect(notice).toContain('= 2.1.0 =');
            expect(notice).toContain('While there are no known breaking changes in this release');
        });

        test('skips notice for patch versions without breaking changes', async () => {
            const mockRelease = {
                newVersion: '2.0.1',
                changesets: [
                    {
                        type: 'patch',
                        breaking: false,
                        summary: 'fix: Fix bug'
                    }
                ]
            };

            const notice = await getUpgradeNoticeEntry(mockRelease, {});

            expect(notice).toBe('');
        });
    });

    describe('Readme.txt Integration', () => {
        beforeEach(() => {
            fs.existsSync.mockReturnValue(true);
            fs.readFileSync.mockReturnValue('== Changelog ==\n\nOld changelog\n\n== Upgrade Notice ==\n\nOld notice\n');
            // Mock the writeFileSync to capture the content
            fs.writeFileSync = jest.fn((path, content) => {
                // Store the content for assertions
                fs.writeFileSync.mockContent = content;
            });
            path.join.mockImplementation((_, file) => file);
        });

        test('updates both changelog and upgrade notice sections', async () => {
            const mockRelease = {
                newVersion: '2.0.0',
                changesets: [
                    {
                        breaking: true,
                        pr: 123,
                        summary: 'feat!: Breaking change',
                        breaking_changes: 'This breaks things'
                    }
                ]
            };

            // Mock formatReadmeTxt to return a specific string that includes the version
            jest.spyOn(require('../../changelog-formatters/readme-txt'), 'formatReadmeTxt')
                .mockReturnValue('= 2.0.0 =\n\n**BREAKING CHANGES**\n\nThis breaks things\n');

            await updateReadmeTxt(mockRelease, { repo: 'wp-graphql/wp-graphql' });

            expect(fs.writeFileSync).toHaveBeenCalled();
            const writeCall = fs.writeFileSync.mock.calls[0];
            expect(writeCall[0]).toBe('readme.txt');
            expect(fs.writeFileSync.mockContent).toContain('== Changelog ==');
            expect(fs.writeFileSync.mockContent).toContain('= 2.0.0 =');
            expect(fs.writeFileSync.mockContent).toContain('== Upgrade Notice ==');
        });

        test('handles missing upgrade notice section', async () => {
            fs.readFileSync.mockReturnValue('== Changelog ==\n\nOld changelog\n');

            const mockRelease = {
                newVersion: '2.0.0',
                changesets: [
                    {
                        breaking: true,
                        pr: 123,
                        summary: 'feat!: Breaking change',
                        breaking_changes: 'This breaks things'
                    }
                ]
            };

            // Mock formatReadmeTxt and getUpgradeNoticeEntry to return specific strings
            jest.spyOn(require('../../changelog-formatters/readme-txt'), 'formatReadmeTxt')
                .mockReturnValue('= 2.0.0 =\n\n**BREAKING CHANGES**\n\nThis breaks things\n');

            jest.spyOn(require('../../changelog-formatters/readme-txt'), 'getUpgradeNoticeEntry')
                .mockResolvedValue('= 2.0.0 =\n**BREAKING CHANGE UPDATE**\n\nThis breaks things\n');

            await updateReadmeTxt(mockRelease, { repo: 'wp-graphql/wp-graphql' });

            expect(fs.writeFileSync).toHaveBeenCalled();
            expect(fs.writeFileSync.mockContent).toContain('== Changelog ==');
            expect(fs.writeFileSync.mockContent).toContain('= 2.0.0 =');
            expect(fs.writeFileSync.mockContent).toContain('== Upgrade Notice ==');
        });

        test('preserves stable tag for beta releases', async () => {
            // Mock readme.txt with a stable tag
            fs.readFileSync.mockReturnValue('== Changelog ==\n\nOld changelog\n\nStable tag: 1.0.0\n');

            const mockRelease = {
                newVersion: '2.0.0-beta.1',
                changesets: [
                    {
                        type: 'minor',
                        summary: 'feat: New feature'
                    }
                ]
            };

            // Mock formatReadmeTxt to return a specific string
            jest.spyOn(require('../../changelog-formatters/readme-txt'), 'formatReadmeTxt')
                .mockReturnValue('= 2.0.0-beta.1 =\n\n**New Features**\n\nNew feature\n');

            await updateReadmeTxt(mockRelease, {});

            // Should not update stable tag for beta
            expect(fs.writeFileSync).toHaveBeenCalled();
            expect(fs.writeFileSync.mockContent).not.toContain('Stable tag: 2.0.0-beta.1');
            expect(fs.writeFileSync.mockContent).toContain('Stable tag: 1.0.0'); // Should preserve existing stable tag
        });
    });
});
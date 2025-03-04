const { updateVersions, getCurrentVersions } = require('../version-management');
const { updateAllSinceTags } = require('../update-since-tags');
const fs = require('fs');

jest.mock('fs');
jest.mock('../update-since-tags');

describe('Version Integration', () => {
    let mockFiles = {
        'wp-graphql.php': 'Version: 1.0.0',
        'constants.php': "define( 'WPGRAPHQL_VERSION', '1.0.0' );",
        'package.json': JSON.stringify({ version: '1.0.0' }),
        'readme.txt': 'Stable tag: 1.0.0'
    };

    beforeEach(() => {
        jest.clearAllMocks();

        // Reset mock files for each test
        mockFiles = {
            'wp-graphql.php': 'Version: 1.0.0',
            'constants.php': "define( 'WPGRAPHQL_VERSION', '1.0.0' );",
            'package.json': JSON.stringify({ version: '1.0.0' }),
            'readme.txt': 'Stable tag: 1.0.0'
        };

        // Mock file system operations
        fs.readFileSync.mockImplementation((file) => {
            const fileName = file.split('/').pop();
            if (mockFiles[fileName]) {
                return mockFiles[fileName];
            }
            throw new Error(`ENOENT: no such file or directory, open '${file}'`);
        });
        fs.existsSync.mockImplementation((file) => {
            const fileName = file.split('/').pop();
            return !!mockFiles[fileName];
        });
        fs.writeFileSync.mockImplementation((file, content) => {
            const fileName = file.split('/').pop();
            mockFiles[fileName] = content;
        });

        // Mock @since tag updates
        updateAllSinceTags.mockResolvedValue({ updated: [], errors: [] });
    });

    describe('Stable Release', () => {
        test('updates all version numbers for stable release', async () => {
            const newVersion = '2.0.0';
            await updateVersions(newVersion, false);

            // Check that all files were updated
            expect(mockFiles['wp-graphql.php']).toContain(`Version: ${newVersion}`);
            expect(mockFiles['constants.php']).toContain(`'WPGRAPHQL_VERSION', '${newVersion}'`);
            expect(mockFiles['package.json']).toContain(`"version": "${newVersion}"`);
            expect(mockFiles['readme.txt']).toContain(`Stable tag: ${newVersion}`);
        });

        test('updates @since tags with new version', async () => {
            const newVersion = '2.0.0';
            await updateVersions(newVersion, false);
            expect(updateAllSinceTags).toHaveBeenCalledWith(newVersion);
        });
    });

    describe('Beta Release', () => {
        test('does not update stable tag in readme.txt for beta release', async () => {
            const newVersion = '2.0.0-beta.1';
            await updateVersions(newVersion, true);

            // Check that readme.txt stable tag was not updated
            expect(mockFiles['readme.txt']).toContain('Stable tag: 1.0.0');

            // Check that other files were updated
            expect(mockFiles['wp-graphql.php']).toContain(`Version: ${newVersion}`);
            expect(mockFiles['constants.php']).toContain(`'WPGRAPHQL_VERSION', '${newVersion}'`);
            expect(mockFiles['package.json']).toContain(`"version": "${newVersion}"`);
        });

        test('updates @since tags with beta version', async () => {
            const newVersion = '2.0.0-beta.1';
            await updateVersions(newVersion, true);
            expect(updateAllSinceTags).toHaveBeenCalledWith(newVersion);
        });
    });

    describe('Version Validation', () => {
        test('validates versions match after update', async () => {
            const newVersion = '2.0.0';
            await updateVersions(newVersion, false);

            const versions = getCurrentVersions();
            expect(versions.php).toBe(newVersion);
            expect(versions.constants).toBe(newVersion);
            expect(versions.package).toBe(newVersion);
            expect(versions.readme).toBe(newVersion);
        });

        test('validates beta versions correctly', async () => {
            const newVersion = '2.0.0-beta.1';
            await updateVersions(newVersion, true);

            const versions = getCurrentVersions();
            expect(versions.php).toBe(newVersion);
            expect(versions.constants).toBe(newVersion);
            expect(versions.package).toBe(newVersion);
            expect(versions.readme).toBe('1.0.0'); // Should keep old stable tag
        });
    });
});
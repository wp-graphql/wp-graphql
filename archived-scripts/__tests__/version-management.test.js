const { updateVersions, getCurrentVersions, validateVersions, resetVersions } = require('../version-management');
const fs = require('fs');

jest.mock('fs');
jest.mock('../update-since-tags', () => ({
    updateAllSinceTags: jest.fn().mockResolvedValue({ updated: [], errors: [] })
}));

describe('Version Management', () => {
    let mockFiles;

    beforeEach(() => {
        jest.clearAllMocks();

        // Set up initial mock files
        mockFiles = {
            'wp-graphql.php': 'Version: 2.0.0',
            'constants.php': "define( 'WPGRAPHQL_VERSION', '2.0.0' );",
            'package.json': JSON.stringify({ version: '2.0.0' }),
            'readme.txt': 'Stable tag: 2.0.0'
        };

        // Mock file system operations
        fs.readFileSync.mockImplementation((file) => {
            const fileName = file.split('/').pop();
            if (mockFiles[fileName]) {
                return mockFiles[fileName];
            }
            throw new Error('File not found');
        });

        fs.writeFileSync.mockImplementation((file, content) => {
            const fileName = file.split('/').pop();
            mockFiles[fileName] = content;
        });

        fs.existsSync.mockImplementation((file) => {
            const fileName = file.split('/').pop();
            return !!mockFiles[fileName];
        });
    });

    describe('getCurrentVersions', () => {
        test('reads versions from all files', () => {
            const versions = getCurrentVersions();
            expect(versions).toEqual({
                php: '2.0.0',
                constants: '2.0.0',
                package: '2.0.0',
                readme: '2.0.0'
            });
        });

        test('handles missing files', () => {
            mockFiles = {}; // No files exist
            const versions = getCurrentVersions();
            expect(versions).toEqual({
                php: '2.1.0',
                constants: '2.1.0',
                package: '2.1.0',
                readme: '2.1.0'
            });
        });

        test('handles invalid versions', () => {
            mockFiles['wp-graphql.php'] = 'Version: invalid';
            const versions = getCurrentVersions();
            expect(versions.php).toBe('2.1.0');
        });
    });

    describe('validateVersions', () => {
        test('passes when versions match', () => {
            const versions = {
                php: '2.0.0',
                constants: '2.0.0',
                package: '2.0.0',
                readme: '2.0.0'
            };
            expect(validateVersions(versions)).toBe(true);
        });

        test('fails when versions mismatch', () => {
            const versions = {
                php: '2.0.0',
                constants: '2.0.1',
                package: '2.0.0',
                readme: '2.0.0'
            };
            expect(() => validateVersions(versions)).toThrow('Version mismatch');
        });

        test('handles beta versions correctly', () => {
            const versions = {
                php: '2.0.0-beta.1',
                constants: '2.0.0-beta.1',
                package: '2.0.0-beta.1',
                readme: '2.0.0' // Should keep old stable tag
            };
            expect(validateVersions(versions)).toBe(true);
        });
    });

    describe('updateVersions', () => {
        test('updates all files for stable release', async () => {
            await updateVersions('2.1.0');

            expect(mockFiles['wp-graphql.php']).toContain('Version: 2.1.0');
            expect(mockFiles['constants.php']).toContain("'WPGRAPHQL_VERSION', '2.1.0'");
            expect(mockFiles['package.json']).toContain('"version": "2.1.0"');
            expect(mockFiles['readme.txt']).toContain('Stable tag: 2.1.0');
        });

        test('handles beta releases correctly', async () => {
            await updateVersions('2.1.0-beta.1', true);

            expect(mockFiles['wp-graphql.php']).toContain('Version: 2.1.0-beta.1');
            expect(mockFiles['constants.php']).toContain("'WPGRAPHQL_VERSION', '2.1.0-beta.1'");
            expect(mockFiles['package.json']).toContain('"version": "2.1.0-beta.1"');
            expect(mockFiles['readme.txt']).toContain('Stable tag: 2.0.0'); // Should not update
        });

        test('prevents large version jumps', async () => {
            await expect(updateVersions('4.0.0')).rejects.toThrow('Version jump too large');
        });
    });

    describe('resetVersions', () => {
        test('resets all versions to specified version', () => {
            const result = resetVersions('2.5.0');
            expect(result).toEqual({
                php: '2.5.0',
                constants: '2.5.0',
                package: '2.5.0',
                readme: '2.5.0'
            });
        });

        test('handles missing files gracefully', () => {
            mockFiles = {}; // No files exist
            const result = resetVersions('2.5.0');
            expect(result).toEqual({
                php: '2.5.0',
                constants: '2.5.0',
                package: '2.5.0',
                readme: '2.5.0'
            });
        });

        test('validates version format', () => {
            expect(() => resetVersions('invalid')).toThrow('Invalid version format');
        });
    });
});
const { getCurrentVersions, validateVersions, updateVersions } = require('../version-management');
const fs = require('fs');

jest.mock('fs');

describe('Version Management', () => {
    beforeEach(() => {
        jest.clearAllMocks();

        // Mock file contents
        const mockFiles = {
            'wp-graphql.php': 'Version: 1.0.0',
            'constants.php': "define( 'WPGRAPHQL_VERSION', '1.0.0' );",
            'package.json': JSON.stringify({ version: '1.0.0' }),
            'readme.txt': 'Stable tag: 1.0.0'
        };

        fs.readFileSync.mockImplementation((file) => mockFiles[file.split('/').pop()]);
        fs.writeFileSync.mockImplementation(() => { });
    });

    describe('getCurrentVersions', () => {
        test('reads versions from all files', () => {
            const versions = getCurrentVersions();
            expect(versions).toEqual({
                php: '1.0.0',
                constants: '1.0.0',
                package: '1.0.0',
                readme: '1.0.0'
            });
        });

        test('handles missing files', () => {
            fs.readFileSync.mockImplementationOnce(() => {
                throw new Error('File not found');
            });
            expect(() => getCurrentVersions()).toThrow('Error reading version files');
        });
    });

    describe('validateVersions', () => {
        test('passes when versions match', () => {
            const versions = {
                php: '1.0.0',
                constants: '1.0.0',
                package: '1.0.0',
                readme: '1.0.0'
            };
            expect(validateVersions(versions)).toBe(true);
        });

        test('handles beta releases correctly', () => {
            const versions = {
                php: '2.0.0-beta.1',
                constants: '2.0.0-beta.1',
                package: '2.0.0-beta.1',
                readme: '1.0.0' // Should keep old stable tag
            };
            expect(validateVersions(versions)).toBe(true);
        });

        test('fails when versions mismatch', () => {
            const versions = {
                php: '1.0.0',
                constants: '1.0.1',
                package: '1.0.0',
                readme: '1.0.0'
            };
            expect(() => validateVersions(versions)).toThrow('Version mismatch');
        });
    });

    describe('updateVersions', () => {
        test('updates all files for stable release', () => {
            updateVersions('2.0.0');
            expect(fs.writeFileSync).toHaveBeenCalledTimes(4);
        });

        test('skips readme.txt for beta releases', () => {
            updateVersions('2.0.0-beta.1', true);
            expect(fs.writeFileSync).toHaveBeenCalledTimes(3);
            expect(fs.writeFileSync).not.toHaveBeenCalledWith(
                expect.stringContaining('readme.txt'),
                expect.any(String)
            );
        });
    });
});
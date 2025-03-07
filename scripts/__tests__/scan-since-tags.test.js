const { scanFileForSinceTags, findFilesWithSinceTags, generateSinceTagsMetadata } = require('../scan-since-tags');
const fs = require('fs');
const { glob } = require('glob');

jest.mock('fs');
jest.mock('glob');

describe('Since Tags Scanner', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    describe('scanFileForSinceTags', () => {
        test('counts @since todo tags correctly', () => {
            fs.readFileSync.mockReturnValue(`
                /**
                 * @since todo
                 */
                function test1() {}

                /**
                 * @since todo
                 */
                function test2() {}
            `);

            expect(scanFileForSinceTags('test.php')).toBe(2);
        });

        test('handles files without tags', () => {
            fs.readFileSync.mockReturnValue(`
                /**
                 * @since 1.0.0
                 */
                function test() {}
            `);

            expect(scanFileForSinceTags('test.php')).toBe(0);
        });
    });

    describe('findFilesWithSinceTags', () => {
        test('finds all files with tags', async () => {
            glob.mockResolvedValue(['file1.php', 'file2.php']);
            fs.readFileSync
                .mockReturnValueOnce('@since todo')
                .mockReturnValueOnce('@since todo\n@since todo');

            const results = await findFilesWithSinceTags();
            expect(results).toHaveLength(2);
            expect(results[0]).toEqual({ file: 'file1.php', count: 1 });
            expect(results[1]).toEqual({ file: 'file2.php', count: 2 });
        });
    });

    describe('generateSinceTagsMetadata', () => {
        test('generates correct metadata', async () => {
            glob.mockResolvedValue(['file1.php', 'file2.php']);
            fs.readFileSync
                .mockReturnValueOnce('@since todo')
                .mockReturnValueOnce('@since todo\n@since todo');

            const metadata = await generateSinceTagsMetadata();
            expect(metadata).toEqual({
                sinceFiles: ['file1.php', 'file2.php'],
                totalTags: 3
            });
        });
    });
});
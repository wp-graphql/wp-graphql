const { getSinceTodoTags, updateSinceTags } = require('../update-since-tags');
const fs = require('fs');
const path = require('path');

jest.mock('fs');

describe('Since Tags Management', () => {
    beforeEach(() => {
        jest.clearAllMocks();
    });

    describe('getSinceTodoTags', () => {
        test('counts @since todo tags correctly', () => {
            const content = `
                /**
                 * @since todo
                 */
                function test1() {}

                /**
                 * @since todo
                 */
                function test2() {}
            `;

            expect(getSinceTodoTags(content)).toBe(2);
        });

        test('handles no tags', () => {
            const content = `
                /**
                 * @since 1.0.0
                 */
                function test() {}
            `;

            expect(getSinceTodoTags(content)).toBe(0);
        });
    });

    describe('updateSinceTags', () => {
        const mockFilePath = 'test.php';

        test('updates @since todo tags with version', () => {
            const content = `
                /**
                 * @since todo
                 */
                function test() {}
            `;

            fs.readFileSync.mockReturnValue(content);

            updateSinceTags(mockFilePath, '2.0.0');

            expect(fs.writeFileSync).toHaveBeenCalledWith(
                mockFilePath,
                expect.stringContaining('@since 2.0.0')
            );
        });

        test('handles multiple tags in one file', () => {
            const content = `
                /**
                 * @since todo
                 */
                function test1() {}

                /**
                 * @since todo
                 */
                function test2() {}
            `;

            fs.readFileSync.mockReturnValue(content);

            updateSinceTags(mockFilePath, '2.0.0');

            const writtenContent = fs.writeFileSync.mock.calls[0][1];
            expect((writtenContent.match(/@since 2.0.0/g) || []).length).toBe(2);
        });

        test('only updates if changes needed', () => {
            const content = `
                /**
                 * @since 1.0.0
                 */
                function test() {}
            `;

            fs.readFileSync.mockReturnValue(content);

            updateSinceTags(mockFilePath, '2.0.0');

            expect(fs.writeFileSync).not.toHaveBeenCalled();
        });
    });
});
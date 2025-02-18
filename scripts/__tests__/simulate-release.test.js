const simulateRelease = require('../simulate-release');
const { getCurrentVersions, updateVersions } = require('../version-management');
const { updateAllSinceTags } = require('../update-since-tags');
const { getReadmeTxtChangelog } = require('../changelog-formatters/readme-txt');

jest.mock('../version-management');
jest.mock('../update-since-tags');
jest.mock('../changelog-formatters/readme-txt');
jest.mock('chalk', () => ({
    blue: (text) => text,
    green: (text) => text,
    yellow: (text) => text,
    red: (text) => text
}));

describe('Release Simulation', () => {
    beforeEach(() => {
        jest.clearAllMocks();

        // Mock successful responses
        getCurrentVersions.mockReturnValue({
            php: '1.0.0',
            constants: '1.0.0',
            package: '1.0.0',
            readme: '1.0.0'
        });

        updateVersions.mockResolvedValue(true);
        updateAllSinceTags.mockResolvedValue({ updated: ['file1.php'], errors: [] });
        getReadmeTxtChangelog.mockResolvedValue('Changelog content');
    });

    test('simulates standard release successfully', async () => {
        await expect(simulateRelease('2.0.0')).resolves.toBe(true);
        expect(updateVersions).toHaveBeenCalledWith('2.0.0', false);
    });

    test('simulates beta release successfully', async () => {
        await expect(simulateRelease('2.0.0-beta.1', { beta: true })).resolves.toBe(true);
        expect(updateVersions).toHaveBeenCalledWith('2.0.0-beta.1', true);
    });

    test('handles errors appropriately', async () => {
        updateVersions.mockRejectedValue(new Error('Update failed'));
        await expect(simulateRelease('2.0.0')).rejects.toThrow('Update failed');
    });
});
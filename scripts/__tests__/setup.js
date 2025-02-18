const fs = jest.createMockFromModule('fs');
const path = jest.createMockFromModule('path');
const { getInfo } = jest.createMockFromModule('@changesets/get-github-info');

// Mock filesystem operations
fs.readFileSync = jest.fn();
fs.writeFileSync = jest.fn();
fs.mkdirSync = jest.fn();

// Mock GitHub info
getInfo.mockResolvedValue({
    links: {
        commit: 'https://github.com/wp-graphql/wp-graphql/commit/abc123',
        pull: 'https://github.com/wp-graphql/wp-graphql/pull/123'
    }
});

global.console = {
    ...console,
    error: jest.fn(),
    log: jest.fn(),
};

module.exports = {
    fs,
    path,
    getInfo
};
module.exports = {
    // Use babel-jest for JS/TS files
    transform: {
        '^.+\\.[t|j]sx?$': ['babel-jest', {
            configFile: './babel.config.cjs',
            rootMode: 'upward',
            babelrc: false,
            babelrcRoots: false
        }]
    },

    // Specify test environment
    testEnvironment: 'node',

    // Specify where to find test files
    testMatch: [
        '**/scripts/__tests__/**/*.[jt]s?(x)',
    ],

    // Specify coverage collection
    collectCoverageFrom: [
        'scripts/**/*.{js,jsx}',
        '!scripts/__tests__/**',
    ],

    // Setup files
    setupFiles: [],

    // Module file extensions
    moduleFileExtensions: ['js', 'jsx', 'json', 'node'],
};
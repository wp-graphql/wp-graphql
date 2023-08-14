module.exports = {
    rootDir: "./",
    roots: [
      "../../packages"
    ],
    preset: "@wordpress/jest-preset-default",
    setupFiles: ["<rootDir>/test.setup.js", "../../packages/wpgraphiql/index.js"],
    setupFilesAfterEnv: ["<rootDir>/jest.setup.js"],
};

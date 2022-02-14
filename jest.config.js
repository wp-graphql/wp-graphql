module.exports = {
  rootDir: "./",
  preset: "@wordpress/jest-preset-default",
  setupFiles: ["<rootDir>/test.setup.js", "<rootDir>/src/index.js"],
  setupFilesAfterEnv: ["<rootDir>/jest.setup.js"],
};

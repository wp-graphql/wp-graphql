// Distribution/artifact checks, kept separate from tests/unit on purpose:
// these specs build the plugin zip and assert its contents, so they need
// webpack output to exist. They run after a real build in the Playground
// Preview workflow ("Verify plugin ZIP contents"), not in the build-free
// JS Unit Tests workflow.
module.exports = {
	testEnvironment: 'node',
	testMatch: ['**/tests/distribution/**/*.test.js'],
};

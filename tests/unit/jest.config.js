module.exports = {
	testEnvironment: 'jsdom',
	testMatch: [ '**/tests/unit/specs/**/*.js' ],
	transform: {
		'^.+\\.[t|j]sx?$': 'babel-jest',
	},
};

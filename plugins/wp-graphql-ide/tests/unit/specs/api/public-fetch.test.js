describe('fetchAsPublic', () => {
	const ENDPOINT = 'http://example.test/graphql';
	const OPTIONS = {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: '{"query":"{ __typename }"}',
	};

	let fetchAsPublic;
	let PUBLIC_REQUEST_HEADER;

	beforeEach(() => {
		// The module remembers a challenge for the page's lifetime, so
		// each test needs its own copy.
		jest.resetModules();
		({
			fetchAsPublic,
			PUBLIC_REQUEST_HEADER,
		} = require('../../../../src/api/public-fetch'));
		global.fetch = jest.fn();
	});

	afterEach(() => {
		delete global.fetch;
	});

	function mockResponse({ status = 200, headers = {} } = {}) {
		const response = { status, headers: new Headers(headers) };
		global.fetch.mockResolvedValueOnce(response);
		return response;
	}

	function mockChallenge() {
		return mockResponse({
			status: 401,
			headers: { 'WWW-Authenticate': 'Basic realm="Restricted"' },
		});
	}

	it('omits credentials when the server does not ask for HTTP auth', async () => {
		const ok = mockResponse();

		const response = await fetchAsPublic(ENDPOINT, OPTIONS);

		expect(response).toBe(ok);
		expect(global.fetch).toHaveBeenCalledTimes(1);
		expect(global.fetch).toHaveBeenCalledWith(ENDPOINT, {
			...OPTIONS,
			credentials: 'omit',
		});
	});

	it('overrides a credentials mode passed by the caller', async () => {
		mockResponse();

		await fetchAsPublic(ENDPOINT, { ...OPTIONS, credentials: 'include' });

		expect(global.fetch.mock.calls[0][1].credentials).toBe('omit');
	});

	it('resends with HTTP auth and the public flag after a challenge', async () => {
		mockChallenge();
		const ok = mockResponse();

		const response = await fetchAsPublic(ENDPOINT, OPTIONS);

		expect(response).toBe(ok);
		expect(global.fetch).toHaveBeenCalledTimes(2);
		expect(global.fetch).toHaveBeenLastCalledWith(ENDPOINT, {
			...OPTIONS,
			credentials: 'same-origin',
			headers: { ...OPTIONS.headers, [PUBLIC_REQUEST_HEADER]: '1' },
		});
	});

	it('skips the credential-less attempt once a challenge was seen', async () => {
		mockChallenge();
		mockResponse();
		await fetchAsPublic(ENDPOINT, OPTIONS);
		global.fetch.mockClear();

		mockResponse();
		await fetchAsPublic(ENDPOINT, OPTIONS);

		expect(global.fetch).toHaveBeenCalledTimes(1);
		expect(global.fetch.mock.calls[0][1].credentials).toBe('same-origin');
		expect(
			global.fetch.mock.calls[0][1].headers[PUBLIC_REQUEST_HEADER]
		).toBe('1');
	});

	it('does not resend a 401 that carries no HTTP auth challenge', async () => {
		const unauthorized = mockResponse({ status: 401 });

		const response = await fetchAsPublic(ENDPOINT, OPTIONS);

		expect(response).toBe(unauthorized);
		expect(global.fetch).toHaveBeenCalledTimes(1);
	});
});

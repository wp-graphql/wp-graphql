import {
	detectNPlusOne,
	pathPattern,
} from '../../../../../src/components/response-extensions/detect-n-plus-one';

describe('pathPattern', () => {
	it('collapses numeric segments to *', () => {
		expect(pathPattern(['posts', 'nodes', 0, 'featuredImage'])).toBe(
			'posts.nodes.*.featuredImage'
		);
		expect(pathPattern(['posts', 'nodes', 12, 'author', 'name'])).toBe(
			'posts.nodes.*.author.name'
		);
	});

	it('returns "" for non-array input', () => {
		expect(pathPattern(undefined)).toBe('');
		expect(pathPattern(null)).toBe('');
		expect(pathPattern('posts.nodes.0.title')).toBe('');
	});

	it('handles all-string paths unchanged', () => {
		expect(pathPattern(['allSettings', 'siteUrl'])).toBe(
			'allSettings.siteUrl'
		);
	});
});

describe('detectNPlusOne', () => {
	const makeResolver = (path, duration = 1000) => ({ path, duration });

	it('returns [] for non-array input', () => {
		expect(detectNPlusOne(undefined)).toEqual([]);
		expect(detectNPlusOne(null)).toEqual([]);
		expect(detectNPlusOne({})).toEqual([]);
	});

	it('returns [] when no resolvers cross the threshold', () => {
		const resolvers = [
			makeResolver(['posts', 'nodes', 0, 'featuredImage']),
			makeResolver(['posts', 'nodes', 1, 'featuredImage']),
		];
		// default threshold is 5
		expect(detectNPlusOne(resolvers)).toEqual([]);
	});

	it('flags a repeated per-element resolver as N+1', () => {
		const resolvers = Array.from({ length: 8 }, (_, i) =>
			makeResolver(['posts', 'nodes', i, 'featuredImage'], 1500)
		);
		const out = detectNPlusOne(resolvers);
		expect(out).toEqual([
			{
				pattern: 'posts.nodes.*.featuredImage',
				count: 8,
				totalDuration: 12000,
				avgDuration: 1500,
			},
		]);
	});

	it('ignores static-path resolutions (no array index)', () => {
		const resolvers = Array.from({ length: 10 }, () =>
			makeResolver(['allSettings', 'siteUrl'], 100)
		);
		// No `*` in the pattern → can't be N+1, regardless of count.
		expect(detectNPlusOne(resolvers)).toEqual([]);
	});

	it('separates patterns that share a prefix but resolve different fields', () => {
		const resolvers = [
			...Array.from({ length: 6 }, (_, i) =>
				makeResolver(['posts', 'nodes', i, 'featuredImage'], 1000)
			),
			...Array.from({ length: 6 }, (_, i) =>
				makeResolver(['posts', 'nodes', i, 'author'], 500)
			),
		];
		const out = detectNPlusOne(resolvers);
		expect(out).toHaveLength(2);
		const byPattern = Object.fromEntries(out.map((p) => [p.pattern, p]));
		expect(byPattern['posts.nodes.*.featuredImage']).toMatchObject({
			count: 6,
			totalDuration: 6000,
		});
		expect(byPattern['posts.nodes.*.author']).toMatchObject({
			count: 6,
			totalDuration: 3000,
		});
	});

	it('sorts patterns by total duration descending', () => {
		const resolvers = [
			...Array.from({ length: 5 }, (_, i) =>
				// Cheap-but-many: 5 calls × 100μs = 500μs total
				makeResolver(['posts', 'nodes', i, 'slug'], 100)
			),
			...Array.from({ length: 5 }, (_, i) =>
				// Expensive: 5 calls × 5000μs = 25000μs total
				makeResolver(['posts', 'nodes', i, 'featuredImage'], 5000)
			),
		];
		const out = detectNPlusOne(resolvers);
		expect(out.map((p) => p.pattern)).toEqual([
			'posts.nodes.*.featuredImage',
			'posts.nodes.*.slug',
		]);
	});

	it('respects a custom threshold', () => {
		const resolvers = Array.from({ length: 3 }, (_, i) =>
			makeResolver(['posts', 'nodes', i, 'featuredImage'])
		);
		expect(detectNPlusOne(resolvers, { threshold: 3 })).toHaveLength(1);
		expect(detectNPlusOne(resolvers, { threshold: 4 })).toHaveLength(0);
	});

	it('treats missing or non-numeric durations as 0', () => {
		const resolvers = Array.from({ length: 5 }, (_, i) => ({
			path: ['posts', 'nodes', i, 'title'],
			// duration omitted
		}));
		const out = detectNPlusOne(resolvers);
		expect(out).toEqual([
			{
				pattern: 'posts.nodes.*.title',
				count: 5,
				totalDuration: 0,
				avgDuration: 0,
			},
		]);
	});
});

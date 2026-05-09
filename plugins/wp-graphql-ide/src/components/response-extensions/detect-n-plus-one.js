/**
 * Detect likely N+1 query patterns in a WPGraphQL tracing payload.
 *
 * Each resolver entry from `extensions.tracing.execution.resolvers`
 * has a `path` like `['posts', 'nodes', 0, 'featuredImage']`. When
 * the same path *pattern* (with array indices collapsed) appears
 * many times in series — `posts.nodes.*.featuredImage` resolved
 * 24 times — that's the classic N+1: a parent-list resolver
 * fetched cleanly, then each child made its own roundtrip.
 *
 * The detector groups resolvers by pattern, keeps only patterns
 * that contain at least one collapsed index (the others are
 * static-path resolutions that can't be N+1 by definition), and
 * filters by a count threshold so a list with 2–3 items doesn't
 * get flagged.
 *
 * Returned patterns are sorted by aggregate impact (total
 * duration) — the slowest, most-repeated resolvers come first
 * since those are the ones a DataLoader would help most.
 */

const DEFAULT_THRESHOLD = 5;

/**
 * Collapse numeric (array-index) path segments to `*` so paths
 * like `posts.nodes.0.featuredImage` and `posts.nodes.1.featuredImage`
 * group under the same pattern.
 *
 * @param {Array<string|number>} path
 * @return {string}
 */
export function pathPattern(path) {
	if (!Array.isArray(path)) {
		return '';
	}
	return path.map((seg) => (typeof seg === 'number' ? '*' : seg)).join('.');
}

/**
 * @typedef {Object} NPlusOnePattern
 * @property {string} pattern       Path pattern with `*` for indices.
 * @property {number} count         Number of resolver calls matching the pattern.
 * @property {number} totalDuration Sum of resolver durations (μs).
 * @property {number} avgDuration   Mean per-call duration (μs).
 */

/**
 * @param {Array<{path?: Array, duration?: number}>} resolvers
 * @param {Object}                                   [opts]
 * @param {number}                                   [opts.threshold] Minimum repetitions to flag as N+1.
 * @return {NPlusOnePattern[]} Sorted by `totalDuration` descending.
 */
export function detectNPlusOne(
	resolvers,
	{ threshold = DEFAULT_THRESHOLD } = {}
) {
	if (!Array.isArray(resolvers)) {
		return [];
	}

	const groups = new Map();

	for (const r of resolvers) {
		const pattern = pathPattern(r?.path);
		if (!pattern || !pattern.includes('*')) {
			// Static-path resolutions are never N+1. Skip.
			continue;
		}
		const existing = groups.get(pattern) || {
			count: 0,
			totalDuration: 0,
		};
		existing.count++;
		existing.totalDuration += Number(r?.duration) || 0;
		groups.set(pattern, existing);
	}

	const patterns = [];
	for (const [pattern, stats] of groups.entries()) {
		if (stats.count < threshold) {
			continue;
		}
		patterns.push({
			pattern,
			count: stats.count,
			totalDuration: stats.totalDuration,
			avgDuration: stats.totalDuration / stats.count,
		});
	}

	return patterns.sort((a, b) => b.totalDuration - a.totalDuration);
}

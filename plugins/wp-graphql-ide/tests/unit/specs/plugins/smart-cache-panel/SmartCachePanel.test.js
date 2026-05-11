/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import {
	SmartCachePanelView,
	_resetSessionStatsForTests,
	_recordResultForTests,
	_setActiveCacheKeyForTests,
} from '../../../../../plugins/smart-cache-panel/src/components/SmartCachePanel';

function renderView({
	data = { graphqlObjectCache: {} },
	isAuthenticated = false,
	isMutation = false,
	cacheControl,
	docSettings,
	globalGrantMode = 'public',
	diagnostics,
} = {}) {
	const fullData = diagnostics ? { ...data, diagnostics } : data;
	return render(
		<SmartCachePanelView
			data={fullData}
			isAuthenticated={isAuthenticated}
			isMutation={isMutation}
			cacheControl={cacheControl}
			docSettings={docSettings}
			globalGrantMode={globalGrantMode}
		/>
	);
}

describe('SmartCachePanelView', () => {
	describe('cache HIT', () => {
		const hitData = {
			graphqlObjectCache: {
				cacheKey: 'abc123sha256deadbeef',
				message: 'Returned from cache',
			},
		};

		it('renders the HIT pill with the green is-hit modifier', () => {
			const { container } = renderView({ data: hitData });
			expect(screen.getByText('Cache HIT')).toBeInTheDocument();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-status')
			).toHaveClass('is-hit');
		});

		it('renders the cache key', () => {
			renderView({ data: hitData });
			expect(
				screen.getByText('abc123sha256deadbeef')
			).toBeInTheDocument();
		});

		it('copies the cache key to clipboard and flips the button label', async () => {
			const writeText = jest.fn().mockResolvedValue(undefined);
			Object.assign(navigator, { clipboard: { writeText } });
			renderView({ data: hitData });
			fireEvent.click(
				screen.getByRole('button', {
					name: /copy cache key to clipboard/i,
				})
			);
			expect(writeText).toHaveBeenCalledWith('abc123sha256deadbeef');
			await waitFor(() =>
				expect(screen.getByText('Copied')).toBeInTheDocument()
			);
		});

		it('does not render the prerequisite checklist on a HIT', () => {
			const { container } = renderView({ data: hitData });
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-checklist')
			).toBeNull();
		});
	});

	describe('cache MISS', () => {
		it('renders the MISS pill when graphqlObjectCache is empty', () => {
			const { container } = renderView();
			expect(screen.getByText('Cache MISS')).toBeInTheDocument();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-status')
			).toHaveClass('is-miss');
		});

		it('renders the MISS pill when data is undefined', () => {
			renderView({ data: undefined });
			expect(screen.getByText('Cache MISS')).toBeInTheDocument();
		});
	});

	describe('prerequisite checklist', () => {
		it('renders four rows; the settings row is always informational', () => {
			const { container } = renderView();
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items).toHaveLength(4);
			expect(items[0]).toHaveClass('is-unknown');
		});

		it('flags the auth row as blocking when authenticated', () => {
			const { container } = renderView({ isAuthenticated: true });
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items[1]).toHaveClass('is-blocking');
		});

		it('marks the auth row OK when anonymous', () => {
			const { container } = renderView({ isAuthenticated: false });
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items[1]).toHaveClass('is-ok');
		});

		it('flags the mutation row as blocking when isMutation is true', () => {
			const { container } = renderView({ isMutation: true });
			const items = container.querySelectorAll(
				'.wpgraphql-ide-smart-cache-checklist-item'
			);
			expect(items[2]).toHaveClass('is-blocking');
		});

		it('headlines the MISS with the mutation reason when applicable', () => {
			const { container } = renderView({ isMutation: true });
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-status-explainer'
				)
			).toHaveTextContent(/mutations are never cached/i);
		});

		it('headlines the MISS with the auth reason when authenticated', () => {
			const { container } = renderView({ isAuthenticated: true });
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-status-explainer'
				)
			).toHaveTextContent(/authenticated request/i);
		});
	});

	describe('document policy card', () => {
		it('shows max-age set on the document', () => {
			const { container } = renderView({
				docSettings: { maxAgeHeader: 300 },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/300s \(set on this document\)/);
		});

		it('falls back to "Global default" when max-age is unset', () => {
			const { container } = renderView({ docSettings: {} });
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/Max-age:[\s\S]*Global default/);
		});

		it('labels grant=allow as Allowed', () => {
			const { container } = renderView({
				docSettings: { grant: 'allow' },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/Access:\s*Allowed/);
		});

		it('echoes the global grant when the doc uses the default', () => {
			const { container } = renderView({
				docSettings: {},
				globalGrantMode: 'deny',
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-policy')
			).toHaveTextContent(/currently:\s*Denied/);
		});
	});

	describe('network cache card', () => {
		it('shows the Cache-Control header value when present', () => {
			const { container } = renderView({
				cacheControl: 'max-age=600, public',
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-network')
			).toHaveTextContent('max-age=600, public');
		});

		it('explains max-age in plain language', () => {
			const { container } = renderView({
				cacheControl: 'max-age=600, public',
			});
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-network-explainer'
				)
			).toHaveTextContent(/up to 600s/);
		});

		it('explains no-store in plain language', () => {
			const { container } = renderView({
				cacheControl: 'no-store',
			});
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-network-explainer'
				)
			).toHaveTextContent(/will not store/);
		});

		it('omits the card when no Cache-Control header is present', () => {
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-network')
			).toBeNull();
		});
	});

	describe('TTL card', () => {
		it('shows time remaining and age on a HIT with transient diagnostics', () => {
			const nowSec = Math.floor(Date.now() / 1000);
			const { container } = renderView({
				data: {
					graphqlObjectCache: { cacheKey: 'k' },
				},
				diagnostics: {
					expiresAt: nowSec + 480,
					cachedAt: nowSec - 120,
					globalTtl: 600,
					storage: 'transient',
				},
			});
			const ttl = container.querySelector(
				'.wpgraphql-ide-smart-cache-ttl'
			);
			expect(ttl).toBeInTheDocument();
			expect(ttl).toHaveTextContent(/8m\s+remaining/);
			expect(ttl).toHaveTextContent(/cached 2m ago/);
		});

		it('explains object_cache backend without a countdown', () => {
			const { container } = renderView({
				data: { graphqlObjectCache: { cacheKey: 'k' } },
				diagnostics: { storage: 'object_cache' },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-ttl')
			).toHaveTextContent(/Object cache backend/);
		});

		it('shows the global TTL on a MISS', () => {
			const { container } = renderView({
				diagnostics: { globalTtl: 600 },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-ttl')
			).toHaveTextContent(/Global default:\s*10m/);
		});

		it('omits the card when no diagnostics are provided', () => {
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-ttl')
			).toBeNull();
		});
	});

	describe('purge map card', () => {
		it('lists tracked nodes and list types', () => {
			const { container } = renderView({
				diagnostics: {
					purgeMap: {
						nodes: ['post:5', 'post:7'],
						lists: ['Post', 'User'],
					},
				},
			});
			const map = container.querySelector(
				'.wpgraphql-ide-smart-cache-purge-map'
			);
			expect(map).toBeInTheDocument();
			expect(map).toHaveTextContent('post:5');
			expect(map).toHaveTextContent('post:7');
			expect(map).toHaveTextContent('Post');
			expect(map).toHaveTextContent('User');
			expect(map).toHaveTextContent(/Nodes \(2\)/);
			expect(map).toHaveTextContent(/List types \(2\)/);
		});

		it('shows an explanatory empty state when no nodes or lists are tracked', () => {
			const { container } = renderView({
				diagnostics: { purgeMap: { nodes: [], lists: [] } },
			});
			expect(
				container.querySelector(
					'.wpgraphql-ide-smart-cache-purge-map-empty'
				)
			).toHaveTextContent(/won.t be auto-purged/);
		});

		it('omits the card when no purgeMap is provided', () => {
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-purge-map')
			).toBeNull();
		});

		it('falls back to legacy nodes/lists when keys is absent (back-compat for older cached entries)', () => {
			const { container } = renderView({
				diagnostics: {
					purgeMap: {
						nodes: ['cG9zdDox'],
						lists: ['list:post'],
					},
				},
			});
			const map = container.querySelector(
				'.wpgraphql-ide-smart-cache-purge-map'
			);
			expect(map).toBeInTheDocument();
			expect(map).toHaveTextContent(/List types \(1\)/);
			expect(map).toHaveTextContent(/Nodes \(1\)/);
			expect(map).toHaveTextContent('cG9zdDox');
			expect(map).toHaveTextContent('list:post');
		});

		it('categorizes every X-GraphQL-Keys entry into its purge-map group', () => {
			const sha = 'a'.repeat(64);
			const { container } = renderView({
				diagnostics: {
					purgeMap: {
						keys: [
							sha,
							'graphql:Query',
							'operation:GetPosts',
							'list:post',
							'cG9zdDo1MA==',
						],
					},
				},
			});
			const map = container.querySelector(
				'.wpgraphql-ide-smart-cache-purge-map'
			);
			expect(map).toBeInTheDocument();
			// Aside total includes every category.
			expect(map).toHaveTextContent(/5 tags/);
			// Each category renders its own group with the right chip.
			// The 64-char hex is the Query ID (X-GraphQL-Query-ID), not the
			// transient cache key — those hash different inputs.
			expect(map).toHaveTextContent(/Query ID \(1\)/);
			expect(map).toHaveTextContent(sha);
			expect(map).toHaveTextContent(/Root types \(1\)/);
			expect(map).toHaveTextContent('graphql:Query');
			expect(map).toHaveTextContent(/Operations \(1\)/);
			expect(map).toHaveTextContent('operation:GetPosts');
			expect(map).toHaveTextContent(/List types \(1\)/);
			expect(map).toHaveTextContent('list:post');
			expect(map).toHaveTextContent(/Nodes \(1\)/);
			expect(map).toHaveTextContent('cG9zdDo1MA==');
		});
	});

	describe('skipped keys card', () => {
		it('renders when the analyzer dropped entries from X-GraphQL-Keys', () => {
			const { container } = renderView({
				diagnostics: {
					skipped: {
						keys: ['post:99', 'post:100'],
						types: ['skipped:Post'],
						count: 2,
						size: 18,
					},
				},
			});
			const card = container.querySelector(
				'.wpgraphql-ide-smart-cache-skipped'
			);
			expect(card).toBeInTheDocument();
			expect(card).toHaveTextContent(/Cache integrity warning/);
			expect(card).toHaveTextContent('post:99');
			expect(card).toHaveTextContent('skipped:Post');
			expect(card).toHaveTextContent(
				/graphql_query_analyzer_header_length_limit/
			);
		});

		it('omits the card when no entries were skipped', () => {
			const { container } = renderView({
				diagnostics: { skipped: { keys: [], types: [], count: 0 } },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-skipped')
			).toBeNull();
		});

		it('omits the card when no skipped block is present', () => {
			const { container } = renderView({
				diagnostics: { purgeMap: { nodes: [], lists: [] } },
			});
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-skipped')
			).toBeNull();
		});
	});

	describe('session counter', () => {
		beforeEach(() => {
			_resetSessionStatsForTests();
		});

		it('hides the chip until at least one response is recorded', () => {
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-session')
			).toBeNull();
		});

		it('renders HIT/MISS counts and hit-rate after results are recorded', () => {
			_recordResultForTests(true);
			_recordResultForTests(true);
			_recordResultForTests(false);
			const { container } = renderView();
			const counter = container.querySelector(
				'.wpgraphql-ide-smart-cache-session'
			);
			expect(counter).toBeInTheDocument();
			expect(counter).toHaveTextContent('2 HIT');
			expect(counter).toHaveTextContent('1 MISS');
			expect(counter).toHaveTextContent(/67% hit rate/);
		});

		it('resets to zero when the query changes', () => {
			_setActiveCacheKeyForTests('{ posts { nodes { id } } }', '', false);
			_recordResultForTests(true);
			_recordResultForTests(true);
			// User edits the query in the active tab → new cache-key bucket.
			_setActiveCacheKeyForTests('{ pages { nodes { id } } }', '', false);
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-session')
			).toBeNull();
		});

		it('resets to zero when variables change (different cache key)', () => {
			const q =
				'query GetPosts($n: Int) { posts(first: $n) { nodes { id } } }';
			_setActiveCacheKeyForTests(q, '{"n": 5}', false);
			_recordResultForTests(true);
			_recordResultForTests(true);
			// Same query, different variables → different cache key.
			_setActiveCacheKeyForTests(q, '{"n": 10}', false);
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-session')
			).toBeNull();
		});

		it('resets to zero when auth state changes (different cache key)', () => {
			const q = '{ posts { nodes { id } } }';
			_setActiveCacheKeyForTests(q, '', false);
			_recordResultForTests(true);
			_recordResultForTests(true);
			// Toggling auth flips user_id in the cache-key inputs.
			_setActiveCacheKeyForTests(q, '', true);
			const { container } = renderView();
			expect(
				container.querySelector('.wpgraphql-ide-smart-cache-session')
			).toBeNull();
		});

		it('does not reset when the same signature is set again', () => {
			const q = '{ posts { nodes { id } } }';
			_setActiveCacheKeyForTests(q, '', false);
			_recordResultForTests(true);
			_recordResultForTests(false);
			_setActiveCacheKeyForTests(q, '', false); // identical re-render
			const { container } = renderView();
			const counter = container.querySelector(
				'.wpgraphql-ide-smart-cache-session'
			);
			expect(counter).toBeInTheDocument();
			expect(counter).toHaveTextContent('1 HIT');
			expect(counter).toHaveTextContent('1 MISS');
		});
	});
});

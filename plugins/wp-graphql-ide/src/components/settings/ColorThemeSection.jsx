import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Color Theme section inside the IDE Settings tab.
 *
 * A small per-user dropdown that lets developers preview the IDE in
 * each registered WP admin color scheme. Persists to `admin_color`
 * user meta (same field `user-edit.php` writes) so the choice survives
 * reload and applies admin-wide.
 *
 * Lives inside the Settings tab rather than its own topbar action so
 * the IDE doesn't ship surface area that isn't strictly useful for
 * day-to-day editing — this is a dev convenience.
 */
export function ColorThemeSection() {
	const bootstrap = window.WPGRAPHQL_IDE_DATA?.adminColor || {
		current: 'fresh',
		schemes: {},
	};
	const schemes = Object.values(bootstrap.schemes || {});
	const [active, setActive] = useState(bootstrap.current);
	const [busy, setBusy] = useState(false);
	const [error, setError] = useState(null);

	if (schemes.length === 0) {
		return null;
	}

	const apply = async (slug) => {
		if (slug === active || busy) {
			return;
		}
		const scheme = bootstrap.schemes[slug];
		const previous = active;

		// Live preview: swap the colors stylesheet + the `admin-color-*`
		// body class so WP's `body.admin-color-<slug>` scheme rules
		// (which carry `--wp-admin-theme-color`) become active and the
		// IDE's accent token re-resolves to the new scheme.
		applyColorsLink(scheme.url);
		swapColorClass(slug);
		setActive(slug);
		setBusy(true);
		setError(null);

		try {
			await apiFetch({
				path: '/wpgraphql-ide/v1/admin-color',
				method: 'POST',
				data: { scheme: slug },
			});
		} catch (err) {
			applyColorsLink(bootstrap.schemes[previous]?.url || '');
			swapColorClass(previous);
			setActive(previous);
			setError(err?.message || __('Unknown error.', 'wpgraphql-ide'));
		} finally {
			setBusy(false);
		}
	};

	return (
		<div className="wpgraphql-ide-settings-fields">
			<div className="wpgraphql-ide-settings-field">
				<div className="wpgraphql-ide-setting">
					<label
						htmlFor="wpgraphql-ide-admin-color"
						className="wpgraphql-ide-setting-label"
					>
						{__('Admin Color Scheme', 'wpgraphql-ide')}
					</label>
					<select
						id="wpgraphql-ide-admin-color"
						value={active}
						disabled={busy}
						onChange={(e) => apply(e.target.value)}
					>
						{schemes.map((scheme) => (
							<option key={scheme.slug} value={scheme.slug}>
								{scheme.name}
							</option>
						))}
					</select>
					<p className="wpgraphql-ide-setting-desc">
						{__(
							'Preview the IDE in any registered WordPress admin color scheme. Saved to your user profile.',
							'wpgraphql-ide'
						)}
						{error && (
							<>
								{' '}
								<code>{error}</code>
							</>
						)}
					</p>
				</div>
			</div>
		</div>
	);
}

/**
 * Swap the live `<link id="colors-css">` href to the new scheme's CSS.
 * Handles Fresh's missing colors.css by creating/removing the link.
 *
 * @param {string} url The new colors.css URL, or empty for Fresh.
 */
function applyColorsLink(url) {
	let linkEl = document.getElementById('colors-css');
	if (url) {
		if (!linkEl) {
			linkEl = document.createElement('link');
			linkEl.id = 'colors-css';
			linkEl.rel = 'stylesheet';
			linkEl.type = 'text/css';
			linkEl.media = 'all';
			document.head.appendChild(linkEl);
		}
		linkEl.href = url;
	} else if (linkEl && linkEl.parentNode) {
		linkEl.parentNode.removeChild(linkEl);
	}
}

/**
 * Update the body's `admin-color-<slug>` class so WP's scheme rules
 * (which carry `--wp-admin-theme-color`) take effect immediately.
 *
 * @param {string} slug The new scheme slug.
 */
function swapColorClass(slug) {
	const body = document.body;
	const existing = Array.from(body.classList).find((c) =>
		c.startsWith('admin-color-')
	);
	if (existing) {
		body.classList.remove(existing);
	}
	body.classList.add(`admin-color-${slug}`);
}

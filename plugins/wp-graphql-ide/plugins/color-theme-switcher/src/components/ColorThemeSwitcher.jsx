import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Color theme picker.
 *
 * Builds the same DOM the WordPress user-profile color picker uses
 * (`<fieldset class="color-options">` → `<div class="color-option">`
 * with a radio, label, and `<table class="color-palette">`) so the
 * standard `wp-admin/css/common.css` rules style the picker without
 * any sub-plugin styles of our own.
 *
 * Picking a scheme:
 *   1. Swaps `<link id="colors-css">` href + the `admin-color-<slug>`
 *      body class for an instant live preview.
 *   2. POSTs to `wpgraphql-ide/v1/color-scheme`, which writes the slug
 *      to `admin_color` user meta — exactly what `user-edit.php` does
 *      — so the choice survives reload and applies admin-wide.
 *   3. Rolls back on REST failure so the rendered scheme always
 *      matches what's actually saved.
 */
export function ColorThemeSwitcher() {
	const bootstrap = window.WPGraphQLIDEColorThemeSwitcher || {
		current: 'fresh',
		schemes: {},
	};

	const schemes = useMemo(
		() => Object.values(bootstrap.schemes || {}),
		[bootstrap.schemes]
	);

	const [activeSlug, setActiveSlug] = useState(bootstrap.current);
	const [savingSlug, setSavingSlug] = useState(null);
	const [error, setError] = useState(null);

	const applyScheme = async (slug) => {
		const scheme = bootstrap.schemes?.[slug];
		if (!scheme || slug === activeSlug) {
			return;
		}

		const previousSlug = activeSlug;

		// 1. Live preview — swap the colors stylesheet and the
		//    `admin-color-<slug>` body class for any rules scoped to it.
		const linkEl = document.getElementById('colors-css');
		if (linkEl && scheme.url) {
			linkEl.href = scheme.url;
		}
		const body = document.body;
		const colorClass = Array.from(body.classList).find((c) =>
			c.startsWith('admin-color-')
		);
		if (colorClass) {
			body.classList.remove(colorClass);
		}
		body.classList.add(`admin-color-${slug}`);

		setActiveSlug(slug);
		setSavingSlug(slug);
		setError(null);

		try {
			// 2. Persist to the same field user-edit.php writes.
			await apiFetch({
				path: '/wpgraphql-ide/v1/color-scheme',
				method: 'POST',
				data: { scheme: slug },
			});
		} catch (err) {
			// 3. Roll back to whatever was actually saved.
			const previous = bootstrap.schemes?.[previousSlug];
			if (linkEl && previous?.url) {
				linkEl.href = previous.url;
			}
			body.classList.remove(`admin-color-${slug}`);
			body.classList.add(`admin-color-${previousSlug}`);
			setActiveSlug(previousSlug);
			setError(err?.message || __('Unknown error.', 'wpgraphql-ide'));
			// eslint-disable-next-line no-console
			console.error('Failed to save admin color scheme', err);
		} finally {
			setSavingSlug(null);
		}
	};

	if (schemes.length === 0) {
		return (
			<div className="wpgraphql-ide-color-theme-switcher">
				<p>
					{__(
						'No admin color schemes are registered.',
						'wpgraphql-ide'
					)}
				</p>
			</div>
		);
	}

	return (
		<div
			className="wpgraphql-ide-color-theme-switcher"
			style={wrapperStyle}
		>
			<header style={headerStyle}>
				<h2 style={headingStyle}>
					{__('Color Theme', 'wpgraphql-ide')}
				</h2>
				<p style={subheadStyle}>
					{__(
						'Pick a WordPress admin color scheme. Your choice is saved to your user profile and applies across the entire admin.',
						'wpgraphql-ide'
					)}
				</p>
				{error && (
					<p style={errorStyle} role="alert">
						{__(
							'Could not save your selection. The previous scheme has been restored.',
							'wpgraphql-ide'
						)}{' '}
						<code>{error}</code>
					</p>
				)}
			</header>

			<fieldset className="color-options">
				<legend className="screen-reader-text">
					{__('Admin Color Scheme', 'wpgraphql-ide')}
				</legend>

				{schemes.map((scheme) => {
					const isActive = activeSlug === scheme.slug;
					const inputId = `wpgraphql-ide-admin-color-${scheme.slug}`;
					return (
						<div
							key={scheme.slug}
							className={`color-option${
								isActive ? ' selected' : ''
							}`}
						>
							<input
								name="admin_color"
								id={inputId}
								type="radio"
								value={scheme.slug}
								className="tog"
								checked={isActive}
								disabled={savingSlug !== null}
								onChange={() => applyScheme(scheme.slug)}
							/>
							<label htmlFor={inputId}>{scheme.name}</label>
							<table className="color-palette">
								<tbody>
									<tr>
										{(scheme.palette.length
											? scheme.palette
											: ['#1d2327']
										).map((hex, idx) => (
											<td
												// eslint-disable-next-line react/no-array-index-key
												key={idx}
												style={{
													backgroundColor: hex,
												}}
											>
												&nbsp;
											</td>
										))}
									</tr>
								</tbody>
							</table>
						</div>
					);
				})}
			</fieldset>
		</div>
	);
}

/*
 * Wrapper / header styling only — the `<fieldset class="color-options">`
 * and its children inherit their visual treatment from `wp-admin/css/
 * common.css`'s `.color-option` / `.color-palette` rules, so the picker
 * reads as identical to the user-profile one.
 */
const wrapperStyle = {
	padding: 'var(--wpgraphql-ide-space-16, 16px)',
};
const headerStyle = {
	marginBottom: 'var(--wpgraphql-ide-space-12, 12px)',
};
const headingStyle = {
	margin: '0 0 var(--wpgraphql-ide-space-4, 4px) 0',
	fontSize: 'var(--wpgraphql-ide-text-14, 14px)',
	fontWeight: 'var(--wpgraphql-ide-weight-semibold, 600)',
	color: 'var(--wpgraphql-ide-text-strong, #2c3338)',
};
const subheadStyle = {
	margin: 0,
	fontSize: 'var(--wpgraphql-ide-text-12, 12px)',
	color: 'var(--wpgraphql-ide-text-muted, #646970)',
	lineHeight: 'var(--wpgraphql-ide-leading-normal, 1.5)',
};
const errorStyle = {
	margin: 'var(--wpgraphql-ide-space-8, 8px) 0 0',
	fontSize: 'var(--wpgraphql-ide-text-12, 12px)',
	color: 'var(--wpgraphql-ide-color-error, #d63638)',
	lineHeight: 'var(--wpgraphql-ide-leading-normal, 1.5)',
};

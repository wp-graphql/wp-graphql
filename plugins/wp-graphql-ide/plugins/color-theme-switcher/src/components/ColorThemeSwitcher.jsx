import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import { check } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * Color theme picker.
 *
 * Reads the registered admin color schemes from the localized
 * `WPGraphQLIDEColorThemeSwitcher` global, renders each as a card
 * with its 4-color palette, and swaps the live `<link id="colors-css">`
 * href when one is selected so the entire admin (and the IDE) re-themes
 * without a page reload.
 *
 * The selection is also persisted to the user's `admin_color` user
 * meta via REST — same field the user-profile color picker writes —
 * so the chosen scheme survives reload and applies to every admin
 * screen, not just the IDE.
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
	const [errorSlug, setErrorSlug] = useState(null);

	const applyScheme = async (slug) => {
		const scheme = bootstrap.schemes?.[slug];
		if (!scheme) {
			return;
		}

		// Optimistic live-preview: swap stylesheet + body class first so
		// the user sees the new scheme instantly. Persistence comes next
		// and rolls back if it fails.
		const previousSlug = activeSlug;

		// WP enqueues the active scheme as `<link id="colors-css">`.
		// Swapping its href is the cleanest way to live-preview — the
		// browser re-fetches one stylesheet and every dependent CSS
		// variable cascades to its new value.
		const linkEl = document.getElementById('colors-css');
		if (linkEl && scheme.url) {
			linkEl.href = scheme.url;
		}

		// WP also writes a `admin-color-<slug>` class on `<body>` for
		// scheme-specific overrides. Keep it in sync so any rule that
		// targets `.admin-color-modern` still applies after the swap.
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
		setErrorSlug(null);

		try {
			// Same field user-edit.php writes to. From this point the
			// scheme is the user's WP-wide admin color until they pick
			// something else, here or in their profile.
			await apiFetch({
				path: '/wpgraphql-ide/v1/color-scheme',
				method: 'POST',
				data: { scheme: slug },
			});
		} catch (err) {
			// Persistence failed — restore the previous scheme so the
			// UI and the saved state stay in sync.
			const previous = bootstrap.schemes?.[previousSlug];
			if (linkEl && previous?.url) {
				linkEl.href = previous.url;
			}
			body.classList.remove(`admin-color-${slug}`);
			body.classList.add(`admin-color-${previousSlug}`);
			setActiveSlug(previousSlug);
			setErrorSlug(slug);
			// eslint-disable-next-line no-console
			console.error('Failed to save admin color scheme', err);
		} finally {
			setSavingSlug(null);
		}
	};

	if (schemes.length === 0) {
		return (
			<div className="wpgraphql-ide-color-theme-switcher-empty">
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
			style={styles.wrapper}
		>
			<header style={styles.header}>
				<h2 style={styles.heading}>
					{__('Color Theme', 'wpgraphql-ide')}
				</h2>
				<p style={styles.subheading}>
					{__(
						'Pick a WordPress admin color scheme. Your choice is saved to your user profile and applies across the entire admin.',
						'wpgraphql-ide'
					)}
				</p>
				{errorSlug && (
					<p style={styles.error} role="alert">
						{__(
							'Could not save your selection. The previous scheme has been restored.',
							'wpgraphql-ide'
						)}
					</p>
				)}
			</header>
			<ul style={styles.grid} role="list">
				{schemes.map((scheme) => {
					const isActive = activeSlug === scheme.slug;
					return (
						<li key={scheme.slug} style={styles.gridItem}>
							<Button
								onClick={() => applyScheme(scheme.slug)}
								aria-pressed={isActive}
								disabled={savingSlug !== null}
								aria-label={
									isActive
										? `${scheme.name} (${__(
												'active',
												'wpgraphql-ide'
											)})`
										: scheme.name
								}
								style={{
									...styles.card,
									...(isActive ? styles.cardActive : {}),
								}}
							>
								<span
									style={styles.swatchRow}
									aria-hidden="true"
								>
									{(scheme.palette.length
										? scheme.palette
										: ['#1d2327']
									).map((hex, idx) => (
										<span
											// eslint-disable-next-line react/no-array-index-key
											key={idx}
											style={{
												...styles.swatch,
												background: hex,
											}}
										/>
									))}
								</span>
								<span style={styles.cardLabel}>
									<span style={styles.cardName}>
										{scheme.name}
									</span>
									{isActive && (
										<Icon
											icon={check}
											size={16}
											style={styles.activeIcon}
										/>
									)}
								</span>
							</Button>
						</li>
					);
				})}
			</ul>
		</div>
	);
}

const styles = {
	wrapper: {
		padding: 'var(--wpgraphql-ide-space-16, 16px)',
		maxWidth: '900px',
	},
	header: {
		marginBottom: 'var(--wpgraphql-ide-space-16, 16px)',
	},
	heading: {
		margin: '0 0 var(--wpgraphql-ide-space-4, 4px) 0',
		fontSize: 'var(--wpgraphql-ide-text-14, 14px)',
		fontWeight: 'var(--wpgraphql-ide-weight-semibold, 600)',
		color: 'var(--wpgraphql-ide-text-strong, #2c3338)',
	},
	subheading: {
		margin: 0,
		fontSize: 'var(--wpgraphql-ide-text-12, 12px)',
		color: 'var(--wpgraphql-ide-text-muted, #646970)',
		lineHeight: 'var(--wpgraphql-ide-leading-normal, 1.5)',
	},
	error: {
		margin: 'var(--wpgraphql-ide-space-8, 8px) 0 0',
		fontSize: 'var(--wpgraphql-ide-text-12, 12px)',
		color: 'var(--wpgraphql-ide-color-error, #d63638)',
		lineHeight: 'var(--wpgraphql-ide-leading-normal, 1.5)',
	},
	grid: {
		display: 'grid',
		gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
		gap: 'var(--wpgraphql-ide-space-12, 12px)',
		listStyle: 'none',
		margin: 0,
		padding: 0,
	},
	gridItem: {
		margin: 0,
	},
	card: {
		display: 'flex',
		flexDirection: 'column',
		alignItems: 'stretch',
		gap: 'var(--wpgraphql-ide-space-8, 8px)',
		width: '100%',
		height: 'auto',
		padding: 'var(--wpgraphql-ide-space-12, 12px)',
		border: '1px solid var(--wp-components-color-gray-200, #dcdcde)',
		borderRadius: 'var(--wpgraphql-ide-radius-lg, 4px)',
		background: 'var(--wp-components-color-background, #fff)',
		cursor: 'pointer',
		textAlign: 'left',
	},
	cardActive: {
		borderColor: 'var(--wpgraphql-ide-accent)',
		boxShadow: 'inset 0 0 0 1px var(--wpgraphql-ide-accent)',
	},
	swatchRow: {
		display: 'flex',
		gap: 'var(--wpgraphql-ide-space-2, 2px)',
		height: '32px',
		borderRadius: 'var(--wpgraphql-ide-radius-md, 3px)',
		overflow: 'hidden',
	},
	swatch: {
		flex: '1 1 0',
		minWidth: 0,
	},
	cardLabel: {
		display: 'flex',
		alignItems: 'center',
		justifyContent: 'space-between',
		gap: 'var(--wpgraphql-ide-space-4, 4px)',
	},
	cardName: {
		fontSize: 'var(--wpgraphql-ide-text-12, 12px)',
		fontWeight: 'var(--wpgraphql-ide-weight-medium, 500)',
		color: 'var(--wpgraphql-ide-text-strong, #2c3338)',
		lineHeight: 'var(--wpgraphql-ide-leading-tight, 1.4)',
	},
	activeIcon: {
		color: 'var(--wpgraphql-ide-accent)',
	},
};

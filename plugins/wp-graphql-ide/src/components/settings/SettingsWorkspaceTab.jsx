import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { SettingsField } from './SettingsField';
import { ColorThemeSection } from './ColorThemeSection';
import {
	SETTINGS_TAB_ID,
	getOriginalValues,
	getPendingValues,
	setPendingValues,
	computeIsDirty,
	saveAllSettings,
	subscribeSettingsSaved,
} from './settings-tab-state';

// Virtual section for the color-theme picker. Lives next to the
// PHP-registered settings sections in the Settings tab nav but doesn't
// participate in the `graphql_ide_settings` option's save flow — its
// dropdown writes per-user `admin_color` user meta directly via REST.
const COLOR_THEME_SLUG = '__color-theme__';

export function SettingsWorkspaceTab() {
	const sections = useMemo(() => {
		const data = window.WPGRAPHQL_IDE_DATA?.settings || { sections: [] };
		return Array.isArray(data.sections) ? data.sections : [];
	}, []);

	const [activeSlug, setActiveSlug] = useState(
		() => sections[0]?.slug || null
	);

	// Hydrate from the module-level cache so unsaved edits survive a tab
	// switch and return. Falls back to the server-provided baseline.
	const [values, setValues] = useState(
		() => getPendingValues() || { ...getOriginalValues() }
	);
	const [isSaving, setIsSaving] = useState(false);

	const { updateWorkspaceTab } = useDispatch('wpgraphql-ide/document-editor');

	const isDirty = useMemo(() => computeIsDirty(values), [values]);

	// Keep the workspace virtual doc's dirty flag in sync so the tab
	// strip dot mirrors actual unsaved state.
	useEffect(() => {
		updateWorkspaceTab(SETTINGS_TAB_ID, { dirty: isDirty });
	}, [isDirty, updateWorkspaceTab]);

	// External save (e.g. close-tab "Save and close") may finish while the
	// component is mounted — sync local state to the new baseline so the
	// fields reflect any server-side sanitization.
	useEffect(() => {
		return subscribeSettingsSaved((savedValues) => {
			setValues(savedValues);
		});
	}, []);

	const onFieldChange = useCallback((sectionSlug, field, nextValue) => {
		const key = `${sectionSlug}.${field.name}`;
		setValues((prev) => {
			const next = { ...prev, [key]: nextValue };
			setPendingValues(next);
			return next;
		});
	}, []);

	const handleSave = useCallback(async () => {
		setIsSaving(true);
		try {
			// Mirror the latest local values to the module cache before
			// saving, in case onFieldChange updates haven't flushed.
			setPendingValues(values);
			await saveAllSettings();
		} finally {
			setIsSaving(false);
		}
	}, [values]);

	// Cmd+S / Ctrl+S while the Settings tab is mounted (active).
	useEffect(() => {
		const onKeyDown = (event) => {
			const isSave =
				(event.metaKey || event.ctrlKey) &&
				!event.shiftKey &&
				!event.altKey &&
				event.key.toLowerCase() === 's';
			if (!isSave) {
				return;
			}
			event.preventDefault();
			if (isDirty && !isSaving) {
				handleSave();
			}
		};
		window.addEventListener('keydown', onKeyDown);
		return () => window.removeEventListener('keydown', onKeyDown);
	}, [isDirty, isSaving, handleSave]);

	const activeSection = useMemo(
		() => sections.find((s) => s.slug === activeSlug) || sections[0],
		[sections, activeSlug]
	);
	const isColorTheme = activeSlug === COLOR_THEME_SLUG;
	const hasColorSchemes =
		Object.keys(window.WPGRAPHQL_IDE_DATA?.adminColor?.schemes || {})
			.length > 0;

	if (sections.length === 0) {
		return (
			<div className="wpgraphql-ide-settings-empty">
				<p>
					{__(
						'No WPGraphQL settings are registered, or you do not have permission to manage them.',
						'wpgraphql-ide'
					)}
				</p>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-settings-tab">
			<nav
				className="wpgraphql-ide-settings-nav"
				aria-label={__('Settings sections', 'wpgraphql-ide')}
			>
				<ul>
					{sections.map((section) => (
						<li key={section.slug}>
							<button
								type="button"
								className={`wpgraphql-ide-settings-nav-item${
									!isColorTheme &&
									section.slug === activeSection?.slug
										? ' is-active'
										: ''
								}`}
								title={section.title}
								onClick={() => setActiveSlug(section.slug)}
							>
								{section.title}
							</button>
						</li>
					))}
					{hasColorSchemes && (
						<li>
							<button
								type="button"
								className={`wpgraphql-ide-settings-nav-item${
									isColorTheme ? ' is-active' : ''
								}`}
								title={__('Color Theme', 'wpgraphql-ide')}
								onClick={() => setActiveSlug(COLOR_THEME_SLUG)}
							>
								{__('Color Theme', 'wpgraphql-ide')}
							</button>
						</li>
					)}
				</ul>
			</nav>
			<div className="wpgraphql-ide-settings-pane">
				{isColorTheme && (
					<>
						<header className="wpgraphql-ide-settings-pane-header">
							<div className="wpgraphql-ide-settings-pane-heading">
								<h2>{__('Color Theme', 'wpgraphql-ide')}</h2>
								<p className="wpgraphql-ide-settings-pane-desc">
									{__(
										'Switch the WordPress admin color scheme for your user. Useful when previewing how the IDE adapts to different themes.',
										'wpgraphql-ide'
									)}
								</p>
							</div>
						</header>
						<ColorThemeSection />
					</>
				)}
				{!isColorTheme && activeSection && (
					<>
						<header className="wpgraphql-ide-settings-pane-header">
							<div className="wpgraphql-ide-settings-pane-heading">
								<h2>{activeSection.title}</h2>
								{activeSection.desc && (
									<p
										className="wpgraphql-ide-settings-pane-desc"
										dangerouslySetInnerHTML={{
											__html: activeSection.desc,
										}}
									/>
								)}
							</div>
							<div className="wpgraphql-ide-settings-pane-actions">
								{isSaving && <Spinner />}
								<Button
									variant="primary"
									size="compact"
									onClick={handleSave}
									disabled={!isDirty || isSaving}
								>
									{__('Save changes', 'wpgraphql-ide')}
								</Button>
							</div>
						</header>
						<div className="wpgraphql-ide-settings-fields">
							{activeSection.fields.map((field) => {
								const key = `${activeSection.slug}.${field.name}`;
								return (
									<div
										key={field.name}
										className="wpgraphql-ide-settings-field"
									>
										<SettingsField
											field={field}
											value={values[key]}
											onChange={(next) =>
												onFieldChange(
													activeSection.slug,
													field,
													next
												)
											}
										/>
									</div>
								);
							})}
						</div>
					</>
				)}
			</div>
		</div>
	);
}

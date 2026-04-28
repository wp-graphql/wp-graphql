import React, { useCallback, useMemo, useRef, useState } from 'react';
import { Spinner } from '@wordpress/components';
import hooks from '../../wordpress-hooks';
import { SettingsField } from './SettingsField';

const AUTOSAVE_DEBOUNCE_MS = 500;

// Map WPGraphQL field types → OneOf variant names used in the schema. Mirrors
// the `field_type_variants()` map in PHP. Field types not in this map are
// not editable through the mutation (e.g. read-only `html`).
const FIELD_TYPE_TO_VARIANT = {
	text: 'text',
	url: 'url',
	textarea: 'textarea',
	number: 'number',
	checkbox: 'checkbox',
	select: 'select',
	radio: 'radio',
	multicheck: 'multicheck',
	color: 'color',
	user_role_select: 'userRoleSelect',
};

// Build the OneOf input for the mutation. The variant key matches the
// registered field type so the server can validate that the caller is
// targeting the correct shape.
const buildValue = (fieldConfig, value) => {
	const variant = FIELD_TYPE_TO_VARIANT[fieldConfig.type];
	if (!variant) {
		// No editable variant for this type (e.g. html). Caller shouldn't
		// reach this — surface as an error so the failure is loud.
		throw new Error(
			`Field type "${fieldConfig.type}" is not editable through the IDE.`
		);
	}

	switch (variant) {
		case 'checkbox':
			return { checkbox: Boolean(value) };
		case 'number':
			return {
				number:
					typeof value === 'number' ? value : parseFloat(value) || 0,
			};
		case 'multicheck':
			return {
				multicheck: Array.isArray(value)
					? value.map((v) => String(v))
					: [],
			};
		default:
			// All remaining variants are string-shaped.
			return {
				[variant]:
					value === null || value === undefined ? '' : String(value),
			};
	}
};

// Read the saved value out of the typed UpdateGraphqlSettingValue payload.
const readSavedValue = (fieldConfig, valuePayload) => {
	if (!valuePayload || typeof valuePayload !== 'object') {
		return undefined;
	}
	const variant = FIELD_TYPE_TO_VARIANT[fieldConfig.type];
	if (!variant) {
		return undefined;
	}
	return valuePayload[variant];
};

const callMutation = async (fieldConfig, sectionSlug, value) => {
	const { graphqlEndpoint, nonce } = window.WPGRAPHQL_IDE_DATA || {};

	const query = `
		mutation UpdateGraphqlSetting($input: UpdateGraphqlSettingInput!) {
			updateGraphqlSetting(input: $input) {
				success
				section
				field
				fieldType
				value {
					text
					url
					textarea
					number
					checkbox
					select
					radio
					multicheck
					color
					userRoleSelect
				}
				message
			}
		}
	`;

	const response = await fetch(graphqlEndpoint, {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			...(nonce ? { 'X-WP-Nonce': nonce } : {}),
		},
		credentials: 'include',
		body: JSON.stringify({
			query,
			variables: {
				input: {
					section: sectionSlug,
					field: fieldConfig.name,
					value: buildValue(fieldConfig, value),
				},
			},
		}),
	});

	const json = await response.json();

	if (json.errors && json.errors.length > 0) {
		throw new Error(json.errors[0].message || 'Failed to save setting.');
	}

	return json.data?.updateGraphqlSetting || null;
};

export function SettingsWorkspaceTab() {
	const sections = useMemo(() => {
		const data = window.WPGRAPHQL_IDE_DATA?.settings || { sections: [] };
		return Array.isArray(data.sections) ? data.sections : [];
	}, []);

	const [activeSlug, setActiveSlug] = useState(
		() => sections[0]?.slug || null
	);

	// Local copy of values keyed by `${section}.${field}` so we can update
	// optimistically without mutating the bootstrap data, and surface
	// per-field saving/saved/error state for inline feedback.
	const [values, setValues] = useState(() => {
		const next = {};
		sections.forEach((section) => {
			section.fields.forEach((field) => {
				next[`${section.slug}.${field.name}`] = field.value;
			});
		});
		return next;
	});
	const [statuses, setStatuses] = useState({});

	const debounceRef = useRef({});

	const setStatus = useCallback((key, status) => {
		setStatuses((prev) => ({ ...prev, [key]: status }));
	}, []);

	const persist = useCallback(
		async (sectionSlug, field, nextValue) => {
			const key = `${sectionSlug}.${field.name}`;
			setStatus(key, 'saving');
			try {
				const result = await callMutation(
					field,
					sectionSlug,
					nextValue
				);
				if (!result || !result.success) {
					throw new Error(
						result?.message ||
							'The mutation did not report success.'
					);
				}
				// Echo the server's saved value back into local state in case
				// sanitize_callback mutated it (e.g. number bounds, checkbox
				// boolean → 'on'/'off' coercion).
				const savedValue = readSavedValue(field, result.value);
				if (savedValue !== undefined) {
					// Local state holds checkbox values as 'on'/'off' strings to
					// match the `value` shape in the bootstrap data; the server
					// returns them as booleans, so re-encode for consistency.
					let localValue = savedValue;
					if (field.type === 'checkbox') {
						localValue = savedValue ? 'on' : 'off';
					}
					setValues((prev) => ({ ...prev, [key]: localValue }));
				}
				setStatus(key, 'saved');
				hooks.doAction(
					'wpgraphql-ide.notice',
					`Saved "${field.label || field.name}"`
				);
				// Clear the "saved" pulse after a moment so it doesn't linger.
				setTimeout(() => {
					setStatus(key, 'idle');
				}, 1500);
			} catch (error) {
				setStatus(key, 'error');
				hooks.doAction(
					'wpgraphql-ide.notice',
					`Failed to save "${field.label || field.name}": ${error.message}`,
					'error'
				);
			}
		},
		[setStatus]
	);

	const onFieldChange = useCallback(
		(sectionSlug, field, nextValue) => {
			const key = `${sectionSlug}.${field.name}`;
			setValues((prev) => ({ ...prev, [key]: nextValue }));

			// Debounce per-field so rapid keystrokes only trigger one save.
			if (debounceRef.current[key]) {
				clearTimeout(debounceRef.current[key]);
			}
			debounceRef.current[key] = setTimeout(() => {
				persist(sectionSlug, field, nextValue);
			}, AUTOSAVE_DEBOUNCE_MS);
		},
		[persist]
	);

	const activeSection = useMemo(
		() => sections.find((s) => s.slug === activeSlug) || sections[0],
		[sections, activeSlug]
	);

	if (sections.length === 0) {
		return (
			<div className="wpgraphql-ide-settings-empty">
				<p>
					No WPGraphQL settings are registered, or you do not have
					permission to manage them.
				</p>
			</div>
		);
	}

	return (
		<div className="wpgraphql-ide-settings-tab">
			<nav
				className="wpgraphql-ide-settings-nav"
				aria-label="Settings sections"
			>
				<ul>
					{sections.map((section) => (
						<li key={section.slug}>
							<button
								type="button"
								className={`wpgraphql-ide-settings-nav-item${
									section.slug === activeSection?.slug
										? ' is-active'
										: ''
								}`}
								onClick={() => setActiveSlug(section.slug)}
							>
								{section.title}
							</button>
						</li>
					))}
				</ul>
			</nav>
			<div className="wpgraphql-ide-settings-pane">
				{activeSection && (
					<>
						<header className="wpgraphql-ide-settings-pane-header">
							<h2>{activeSection.title}</h2>
							{activeSection.desc && (
								<p
									className="wpgraphql-ide-settings-pane-desc"
									dangerouslySetInnerHTML={{
										__html: activeSection.desc,
									}}
								/>
							)}
						</header>
						<div className="wpgraphql-ide-settings-fields">
							{activeSection.fields.map((field) => {
								const key = `${activeSection.slug}.${field.name}`;
								const status = statuses[key] || 'idle';
								return (
									<div
										key={field.name}
										className={`wpgraphql-ide-settings-field is-${status}`}
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
										<div className="wpgraphql-ide-settings-field-status">
											{status === 'saving' && (
												<>
													<Spinner />
													<span>Saving…</span>
												</>
											)}
											{status === 'saved' && (
												<span className="is-saved">
													Saved
												</span>
											)}
											{status === 'error' && (
												<span className="is-error">
													Save failed
												</span>
											)}
										</div>
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

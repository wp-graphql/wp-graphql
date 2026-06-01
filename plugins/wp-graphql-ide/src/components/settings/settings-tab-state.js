import { dispatch } from '@wordpress/data';
import { __, _n, sprintf } from '@wordpress/i18n';
import hooks from '../../wordpress-hooks';

// Module-level state for the Settings workspace tab. The component unmounts
// whenever the user switches to a different tab, so any pending edits would
// be lost on every tab switch unless we cache them above the component.
//
// `originalValues` is the baseline (what's currently persisted on the
// server) — captured on first mount and refreshed after a successful save.
// `pendingValues` mirrors the current editor state.

export const SETTINGS_TAB_ID = 'graphql-settings';

let originalValues = null;
let pendingValues = null;
const subscribers = new Set();

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

function buildBaselineFromBootstrap() {
	const sections = window?.WPGRAPHQL_IDE_DATA?.settings?.sections || [];
	const next = {};
	sections.forEach((section) => {
		(section.fields || []).forEach((field) => {
			next[`${section.slug}.${field.name}`] = field.value;
		});
	});
	return next;
}

export function getOriginalValues() {
	if (!originalValues) {
		originalValues = buildBaselineFromBootstrap();
	}
	return originalValues;
}

export function setOriginalValues(values) {
	originalValues = { ...values };
}

export function getPendingValues() {
	return pendingValues;
}

export function setPendingValues(values) {
	pendingValues = values;
}

export function clearPending() {
	pendingValues = null;
	dispatch('wpgraphql-ide/document-editor').updateWorkspaceTab(
		SETTINGS_TAB_ID,
		{ dirty: false }
	);
}

// Compare a field value array-aware so multicheck dirtiness works.
function valuesEqual(a, b) {
	if (Array.isArray(a) && Array.isArray(b)) {
		if (a.length !== b.length) {
			return false;
		}
		const aSorted = [...a].sort();
		const bSorted = [...b].sort();
		return aSorted.every((v, i) => v === bSorted[i]);
	}
	return a === b;
}

export function computeIsDirty(values) {
	const baseline = getOriginalValues();
	for (const key of Object.keys({ ...baseline, ...values })) {
		if (!valuesEqual(values?.[key], baseline?.[key])) {
			return true;
		}
	}
	return false;
}

// Returns the list of `{section, field, value}` entries that differ from the
// baseline — what `saveAll` needs to persist.
export function diffAgainstBaseline(values) {
	const baseline = getOriginalValues();
	const sections = window?.WPGRAPHQL_IDE_DATA?.settings?.sections || [];
	const changes = [];
	sections.forEach((section) => {
		(section.fields || []).forEach((field) => {
			const key = `${section.slug}.${field.name}`;
			if (!valuesEqual(values[key], baseline[key])) {
				changes.push({
					sectionSlug: section.slug,
					field,
					value: values[key],
				});
			}
		});
	});
	return changes;
}

function buildVariantPayload(fieldConfig, value) {
	const variant = FIELD_TYPE_TO_VARIANT[fieldConfig.type];
	if (!variant) {
		// Developer-facing error — thrown during save when an unsupported
		// field type is encountered. Translated because it surfaces as a
		// notice via the failure path below.
		throw new Error(
			sprintf(
				/* translators: %s: WPGraphQL settings field type that the IDE doesn't know how to edit (e.g. "color", "user_role_select") */
				__(
					'Field type "%s" is not editable through the IDE.',
					'wpgraphql-ide'
				),
				fieldConfig.type
			)
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
			return {
				[variant]:
					value === null || value === undefined ? '' : String(value),
			};
	}
}

function readSavedValue(fieldConfig, valuePayload) {
	if (!valuePayload || typeof valuePayload !== 'object') {
		return undefined;
	}
	const variant = FIELD_TYPE_TO_VARIANT[fieldConfig.type];
	if (!variant) {
		return undefined;
	}
	return valuePayload[variant];
}

async function callMutation(fieldConfig, sectionSlug, value) {
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
					value: buildVariantPayload(fieldConfig, value),
				},
			},
		}),
	});

	const json = await response.json();

	if (json.errors && json.errors.length > 0) {
		throw new Error(
			json.errors[0].message ||
				__('Failed to save setting.', 'wpgraphql-ide')
		);
	}

	return json.data?.updateGraphqlSetting || null;
}

// Subscribe to "settings just saved" so the in-tab component can refresh
// its local state to reflect any sanitization the server applied.
export function subscribeSettingsSaved(fn) {
	subscribers.add(fn);
	return () => subscribers.delete(fn);
}

function notifySaved(savedValues) {
	subscribers.forEach((fn) => fn(savedValues));
}

// Persist every changed field to the server. Returns `{ ok, savedValues,
// failures }`. On success, refreshes the baseline and clears the pending
// cache; the workspace tab's `dirty` flag drops to false either way.
export async function saveAllSettings() {
	const baseline = getOriginalValues();
	const current = pendingValues || baseline;
	const changes = diffAgainstBaseline(current);

	if (changes.length === 0) {
		return { ok: true, savedValues: { ...current }, failures: [] };
	}

	const savedValues = { ...current };
	const failures = [];

	for (const { sectionSlug, field, value } of changes) {
		try {
			const result = await callMutation(field, sectionSlug, value);
			if (!result || !result.success) {
				throw new Error(
					result?.message ||
						__(
							'The mutation did not report success.',
							'wpgraphql-ide'
						)
				);
			}
			const saved = readSavedValue(field, result.value);
			if (saved !== undefined) {
				let localValue = saved;
				if (field.type === 'checkbox') {
					localValue = saved ? 'on' : 'off';
				}
				savedValues[`${sectionSlug}.${field.name}`] = localValue;
			}
		} catch (error) {
			failures.push({ field, error });
		}
	}

	setOriginalValues(savedValues);
	pendingValues = null;
	dispatch('wpgraphql-ide/document-editor').updateWorkspaceTab(
		SETTINGS_TAB_ID,
		{ dirty: false }
	);
	notifySaved(savedValues);

	if (failures.length === 0) {
		hooks.doAction(
			'wpgraphql-ide.notice',
			__('Settings saved', 'wpgraphql-ide')
		);
		// Note: a couple of these fields (`graphql_ide_show_legacy_editor`,
		// `graphql_ide_link_behavior`) drive WP admin chrome that's
		// painted server-side at request time, so the visible change
		// only lands on the next pageload. We don't auto-reload — the
		// reload would drop the user onto whatever workspace tab loads
		// first, not the IDE settings tab they were saving from, which
		// is a worse UX than waiting for the next natural navigation.
	} else {
		const first = failures[0];
		hooks.doAction(
			'wpgraphql-ide.notice',
			sprintf(
				/* translators: 1: number of WPGraphQL settings that failed to save; 2: error message from the first failure */
				_n(
					'%1$d setting failed to save: %2$s',
					'%1$d settings failed to save: %2$s',
					failures.length,
					'wpgraphql-ide'
				),
				failures.length,
				first.error.message
			),
			'error'
		);
	}

	return { ok: failures.length === 0, savedValues, failures };
}

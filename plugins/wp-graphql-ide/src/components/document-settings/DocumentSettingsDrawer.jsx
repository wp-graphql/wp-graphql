import React from 'react';
import { TagListField } from './fields/TagListField';
import { RadioWithDefaultField } from './fields/RadioWithDefaultField';

/**
 * Renders all registered Document Settings fields for the active document.
 *
 * Purely presentational — edits are forwarded via `onChange` and the parent
 * (IDELayout) folds them into the document's unified save flow alongside
 * query/variables/headers. Temp/unsaved documents are fine to edit too:
 * values live in memory + localStorage and ride along when the doc is first
 * saved (or are discarded if the doc is closed without saving).
 *
 * @param {Object}   props
 * @param {Array}    props.fields          Registered field descriptors.
 * @param {Object}   props.values          Current per-field values.
 * @param {Function} props.onChange        Receives (fieldName, nextValue).
 * @param {string}   props.globalGrantMode Global Allow/Deny default.
 *
 * @return {JSX.Element}
 */
export function DocumentSettingsDrawer({
	fields,
	values,
	onChange,
	globalGrantMode = 'public',
}) {
	return (
		<div
			className="wpgraphql-ide-doc-settings-panel"
			role="region"
			aria-label="Document settings"
		>
			{(fields || []).map((field) => (
				<DocumentSettingFieldRenderer
					key={field.name}
					field={field}
					value={values?.[field.name]}
					onChange={(next) => onChange(field.name, next)}
					globalGrantMode={globalGrantMode}
				/>
			))}
		</div>
	);
}

function DocumentSettingFieldRenderer({
	field,
	value,
	onChange,
	globalGrantMode,
}) {
	const inputId = `wpgraphql-ide-doc-setting-${field.name}`;

	switch (field.type) {
		case 'tag_list':
			return (
				<TagListField field={field} value={value} onChange={onChange} />
			);
		case 'radio_with_default':
			return (
				<RadioWithDefaultField
					field={field}
					value={value}
					onChange={onChange}
					globalDefault={globalGrantMode}
				/>
			);
		case 'textarea':
			return (
				<div className="wpgraphql-ide-doc-setting">
					<label
						htmlFor={inputId}
						className="wpgraphql-ide-doc-setting-label"
					>
						{field.label}
					</label>
					<textarea
						id={inputId}
						value={value ?? ''}
						rows={4}
						onChange={(e) => onChange(e.target.value)}
					/>
					{field.desc ? (
						<p
							className="wpgraphql-ide-doc-setting-desc"
							dangerouslySetInnerHTML={{ __html: field.desc }}
						/>
					) : null}
				</div>
			);
		case 'number':
			return (
				<div className="wpgraphql-ide-doc-setting">
					<label
						htmlFor={inputId}
						className="wpgraphql-ide-doc-setting-label"
					>
						{field.label}
					</label>
					<input
						id={inputId}
						type="number"
						min={0}
						value={value ?? ''}
						onChange={(e) => onChange(e.target.value)}
					/>
					{field.desc ? (
						<p
							className="wpgraphql-ide-doc-setting-desc"
							dangerouslySetInnerHTML={{ __html: field.desc }}
						/>
					) : null}
				</div>
			);
		case 'text':
		default:
			return (
				<div className="wpgraphql-ide-doc-setting">
					<label
						htmlFor={inputId}
						className="wpgraphql-ide-doc-setting-label"
					>
						{field.label}
					</label>
					<input
						id={inputId}
						type="text"
						value={value ?? ''}
						onChange={(e) => onChange(e.target.value)}
					/>
					{field.desc ? (
						<p
							className="wpgraphql-ide-doc-setting-desc"
							dangerouslySetInnerHTML={{ __html: field.desc }}
						/>
					) : null}
				</div>
			);
	}
}

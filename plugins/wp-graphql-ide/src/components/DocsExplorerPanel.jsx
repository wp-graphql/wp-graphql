import React, { useEffect, useRef, useState } from 'react';
import { Button } from '@wordpress/components';
import { Icon, arrowLeft, search } from '@wordpress/icons';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	isObjectType,
	isInputObjectType,
	isEnumType,
	isUnionType,
	isInterfaceType,
	isListType,
	isNonNullType,
} from 'graphql';

/**
 * Docs Explorer panel icon.
 */
export const DocsExplorerIcon = () => <Icon icon={search} />;

// Decode HTML entities like `&quot;`, `&gt;`, `&#39;` without executing markup.
// Schema descriptions can carry these when authored in admin UIs that escape
// on save (e.g. `&quot;fresh&quot;`, `WP_User-&gt;display_name`).
function decodeEntities(str) {
	if (!str || typeof str !== 'string' || str.indexOf('&') === -1) {
		return str || '';
	}
	const ta = document.createElement('textarea');
	ta.innerHTML = str;
	return ta.value;
}

/**
 * Get the named type, unwrapping NonNull and List wrappers.
 *
 * @param {Object} type GraphQL type.
 * @return {Object} The named (leaf) type.
 */
function getNamedType(type) {
	if (isNonNullType(type) || isListType(type)) {
		return getNamedType(type.ofType);
	}
	return type;
}

/**
 * Format a type for display, including NonNull (!) and List ([]) wrappers.
 *
 * @param {Object} type GraphQL type.
 * @return {string} Formatted type string.
 */
function formatType(type) {
	if (isNonNullType(type)) {
		return `${formatType(type.ofType)}!`;
	}
	if (isListType(type)) {
		return `[${formatType(type.ofType)}]`;
	}
	return type.name || String(type);
}

/**
 * Return a short label for a type's kind.
 *
 * @param {Object} type GraphQL type.
 * @return {string} Kind label (e.g. "Object", "Input", "Enum").
 */
function getTypeKind(type) {
	if (isInputObjectType(type)) {
		return 'Input';
	}
	if (isObjectType(type)) {
		return 'Object';
	}
	if (isEnumType(type)) {
		return 'Enum';
	}
	if (isInterfaceType(type)) {
		return 'Interface';
	}
	if (isUnionType(type)) {
		return 'Union';
	}
	return 'Scalar';
}

/**
 * Docs Explorer panel content.
 *
 * Provides a browsable tree of the GraphQL schema — types, fields,
 * arguments, and descriptions.
 */
export function DocsExplorerPanel() {
	const schema = useSelect(
		(select) => select('wpgraphql-ide/app').schema(),
		[]
	);

	const [stack, setStack] = useState([]);

	const pushType = (type) => {
		const named = getNamedType(type);
		if (named?.name) {
			setStack((prev) => [...prev, { name: named.name }]);
		}
	};

	const goBack = () => {
		setStack((prev) => prev.slice(0, -1));
	};

	// Push a frame onto the navigation stack, optionally focusing a specific
	// field within that type. Idempotent if the top of the stack already
	// targets the same type+field — avoids spurious history entries when the
	// same target is delivered twice in a row.
	const pushFrame = (typeName, fieldName) => {
		setStack((prev) => {
			const top = prev[prev.length - 1];
			if (
				top &&
				top.name === typeName &&
				(top.focusField || null) === (fieldName || null)
			) {
				return prev;
			}
			return [...prev, { name: typeName, focusField: fieldName || null }];
		});
	};

	// One-shot navigation request from outside the panel (e.g. cmd-click in
	// the editor). The app store holds it as a `{ typeName, fieldName }` pair;
	// we read via useSelect, push onto the stack, and dispatch a clear so the
	// effect doesn't re-fire on subsequent renders. This survives mount/unmount
	// cycles and avoids the timing fragility of an event-based bridge.
	const docsNavTarget = useSelect(
		(select) => select('wpgraphql-ide/app').getDocsNavTarget(),
		[]
	);
	const { setDocsNavTarget } = useDispatch('wpgraphql-ide/app');

	useEffect(() => {
		if (!docsNavTarget || !docsNavTarget.typeName) {
			return;
		}
		pushFrame(docsNavTarget.typeName, docsNavTarget.fieldName || null);
		setDocsNavTarget(null);
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [docsNavTarget]);

	if (!schema) {
		return (
			<div className="wpgraphql-ide-docs-panel">
				<p className="wpgraphql-ide-docs-empty">
					Schema not loaded. Click the refresh button to load it.
				</p>
			</div>
		);
	}

	// Current view — either root or a specific type.
	const current = stack.length > 0 ? stack[stack.length - 1] : null;

	if (!current) {
		return (
			<RootView
				schema={schema}
				onSelectType={pushType}
				onSelectField={(typeName, fieldName) =>
					pushFrame(typeName, fieldName)
				}
			/>
		);
	}

	const type = schema.getType(current.name);
	if (!type) {
		return (
			<div className="wpgraphql-ide-docs-panel">
				<BackButton onClick={goBack} />
				<p>Type &quot;{current.name}&quot; not found.</p>
			</div>
		);
	}

	return (
		<TypeView
			type={type}
			focusField={current.focusField || null}
			onSelectType={pushType}
			onBack={goBack}
		/>
	);
}

/**
 * Back navigation button.
 *
 * @param {Object}   props
 * @param {Function} props.onClick Click handler.
 */
function BackButton({ onClick }) {
	return (
		<Button
			className="wpgraphql-ide-docs-back"
			onClick={onClick}
			size="small"
		>
			<Icon icon={arrowLeft} size={16} />
			Back
		</Button>
	);
}

/**
 * Root schema view — shows Query, Mutation, Subscription entry points
 * and a list of all types.
 *
 * @param {Object}   props
 * @param {Object}   props.schema        GraphQL schema.
 * @param {Function} props.onSelectType  Called with a type object when a type is clicked.
 * @param {Function} props.onSelectField Called with `(typeName, fieldName)` when a
 *                                       field-search result is clicked.
 */
function RootView({ schema, onSelectType, onSelectField }) {
	const [searchTerm, setSearchTerm] = useState('');

	const queryType = schema.getQueryType();
	const mutationType = schema.getMutationType();
	const subscriptionType = schema.getSubscriptionType();

	// Categorized search results — types and fields are surfaced separately
	// so users can pick the right kind of match. Both lists are capped to keep
	// large schemas (the WPGraphQL schema has ~600 types) responsive.
	const trimmed = searchTerm.trim();
	const { typeMatches, fieldMatches } = React.useMemo(() => {
		if (!trimmed) {
			return { typeMatches: [], fieldMatches: [] };
		}
		const q = trimmed.toLowerCase();
		const types = [];
		const fields = [];
		for (const type of Object.values(schema.getTypeMap())) {
			if (type.name.startsWith('__')) {
				continue;
			}
			if (type.name.toLowerCase().includes(q)) {
				types.push(type);
			}
			if (typeof type.getFields === 'function') {
				let fieldMap;
				try {
					fieldMap = type.getFields();
				} catch {
					continue;
				}
				for (const field of Object.values(fieldMap)) {
					if (field.name.toLowerCase().includes(q)) {
						fields.push({ type, field });
					}
				}
			}
		}
		types.sort((a, b) => a.name.localeCompare(b.name));
		fields.sort((a, b) => {
			const aKey = `${a.type.name}.${a.field.name}`;
			const bKey = `${b.type.name}.${b.field.name}`;
			return aKey.localeCompare(bKey);
		});
		return {
			typeMatches: types.slice(0, 25),
			fieldMatches: fields.slice(0, 50),
		};
	}, [schema, trimmed]);

	return (
		<div className="wpgraphql-ide-docs-panel">
			<div className="wpgraphql-ide-docs-section">
				<div className="wpgraphql-ide-docs-section-title">
					Root Types
				</div>
				{queryType && (
					<TypeLink
						label="query"
						type={queryType}
						onClick={() => onSelectType(queryType)}
					/>
				)}
				{mutationType && (
					<TypeLink
						label="mutation"
						type={mutationType}
						onClick={() => onSelectType(mutationType)}
					/>
				)}
				{subscriptionType && (
					<TypeLink
						label="subscription"
						type={subscriptionType}
						onClick={() => onSelectType(subscriptionType)}
					/>
				)}
			</div>
			<div className="wpgraphql-ide-docs-section">
				<div className="wpgraphql-ide-docs-section-title">Search</div>
				<input
					type="text"
					value={searchTerm}
					onChange={(e) => setSearchTerm(e.target.value)}
					placeholder="Search types and fields..."
					className="wpgraphql-ide-docs-search"
				/>

				{trimmed && typeMatches.length > 0 && (
					<div className="wpgraphql-ide-docs-search-group">
						<div className="wpgraphql-ide-docs-search-group-title">
							Types
						</div>
						{typeMatches.map((type) => (
							<TypeLink
								key={type.name}
								type={type}
								kind={getTypeKind(type)}
								onClick={() => onSelectType(type)}
							/>
						))}
					</div>
				)}

				{trimmed && fieldMatches.length > 0 && (
					<div className="wpgraphql-ide-docs-search-group">
						<div className="wpgraphql-ide-docs-search-group-title">
							Fields
						</div>
						{fieldMatches.map(({ type, field }) => (
							<button
								key={`${type.name}.${field.name}`}
								type="button"
								className="wpgraphql-ide-docs-field-link"
								onClick={() =>
									onSelectField(type.name, field.name)
								}
							>
								<span className="wpgraphql-ide-docs-field-link-path">
									<span className="wpgraphql-ide-docs-field-link-type">
										{type.name}
									</span>
									<span className="wpgraphql-ide-docs-field-link-dot">
										.
									</span>
									<span className="wpgraphql-ide-docs-field-link-name">
										{field.name}
									</span>
								</span>
								<span className="wpgraphql-ide-docs-field-link-type-ref">
									{formatType(field.type)}
								</span>
							</button>
						))}
					</div>
				)}

				{trimmed &&
					typeMatches.length === 0 &&
					fieldMatches.length === 0 && (
						<p className="wpgraphql-ide-docs-empty">No results.</p>
					)}
			</div>
		</div>
	);
}

/**
 * Detailed view for a single type — shows description, fields, enum
 * values, or union members.
 *
 * @param {Object}      props
 * @param {Object}      props.type         GraphQL type.
 * @param {string|null} props.focusField   Optional field name to scroll to / highlight.
 * @param {Function}    props.onSelectType Callback when a type is clicked.
 * @param {Function}    props.onBack       Back navigation callback.
 */
function TypeView({ type, focusField, onSelectType, onBack }) {
	return (
		<div className="wpgraphql-ide-docs-panel">
			{/* Sticky header — pins the Back button, type name, and description
				to the top of the panel's scroll container so they stay visible
				as the user scrolls through long fields lists. */}
			<header className="wpgraphql-ide-docs-type-header">
				<BackButton onClick={onBack} />
				<div className="wpgraphql-ide-docs-type-name">{type.name}</div>
				{type.description && (
					<p className="wpgraphql-ide-docs-description">
						{decodeEntities(type.description)}
					</p>
				)}
			</header>

			{(isObjectType(type) ||
				isInputObjectType(type) ||
				isInterfaceType(type)) && (
				<FieldsList
					fields={Object.values(type.getFields())}
					focusField={focusField}
					onSelectType={onSelectType}
				/>
			)}

			{isEnumType(type) && (
				<div className="wpgraphql-ide-docs-section">
					<div className="wpgraphql-ide-docs-section-title">
						Values
					</div>
					{type.getValues().map((val) => (
						<div
							key={val.name}
							className="wpgraphql-ide-docs-enum-value"
						>
							<code>{val.name}</code>
							{val.description && (
								<span className="wpgraphql-ide-docs-field-desc">
									{decodeEntities(val.description)}
								</span>
							)}
						</div>
					))}
				</div>
			)}

			{isUnionType(type) && (
				<div className="wpgraphql-ide-docs-section">
					<div className="wpgraphql-ide-docs-section-title">
						Possible Types
					</div>
					{type.getTypes().map((t) => (
						<TypeLink
							key={t.name}
							type={t}
							onClick={() => onSelectType(t)}
						/>
					))}
				</div>
			)}
		</div>
	);
}

/**
 * Renders a list of fields with their types and arguments.
 *
 * @param {Object}      props
 * @param {Array}       props.fields       Array of GraphQL field objects.
 * @param {string|null} props.focusField   Optional field name to scroll to / highlight.
 * @param {Function}    props.onSelectType Callback when a type is clicked.
 */
function FieldsList({ fields, focusField, onSelectType }) {
	return (
		<div className="wpgraphql-ide-docs-section">
			<div className="wpgraphql-ide-docs-section-title">Fields</div>
			{fields.map((field) => (
				<FieldItem
					key={field.name}
					field={field}
					isFocused={focusField === field.name}
					onSelectType={onSelectType}
				/>
			))}
		</div>
	);
}

/**
 * Single field row with collapsible arguments.
 *
 * @param {Object}   props
 * @param {Object}   props.field        GraphQL field object.
 * @param {boolean}  props.isFocused    Whether this field is the cmd-click target.
 * @param {Function} props.onSelectType Callback when a type is clicked.
 */
function FieldItem({ field, isFocused, onSelectType }) {
	const [argsOpen, setArgsOpen] = useState(false);
	const hasArgs = field.args && field.args.length > 0;
	const rowRef = useRef(null);

	// When this field is the cmd-click target, scroll it into view and pulse
	// a brief highlight so it stands out among its siblings.
	useEffect(() => {
		if (!isFocused || !rowRef.current) {
			return undefined;
		}
		rowRef.current.scrollIntoView({
			behavior: 'smooth',
			block: 'center',
		});
		// Auto-expand args for the focused field so the user lands on a
		// fully-revealed row.
		if (field.args && field.args.length > 0) {
			setArgsOpen(true);
		}
		return undefined;
	}, [isFocused, field.args]);

	return (
		<div
			ref={rowRef}
			className={`wpgraphql-ide-docs-field${isFocused ? ' is-focused' : ''}`}
		>
			<div className="wpgraphql-ide-docs-field-header">
				<span className="wpgraphql-ide-docs-field-name">
					{field.name}
				</span>
				<button
					type="button"
					className="wpgraphql-ide-docs-type-badge"
					onClick={() => onSelectType(getNamedType(field.type))}
				>
					{formatType(field.type)}
				</button>
			</div>
			{field.description && (
				<div className="wpgraphql-ide-docs-field-desc">
					{decodeEntities(field.description)}
				</div>
			)}
			{hasArgs && (
				<button
					type="button"
					className="wpgraphql-ide-docs-args-toggle"
					onClick={() => setArgsOpen(!argsOpen)}
					aria-expanded={argsOpen}
				>
					<span
						className={`wpgraphql-ide-docs-args-chevron${argsOpen ? ' is-open' : ''}`}
					>
						&#9656;
					</span>
					{field.args.length} argument
					{field.args.length !== 1 ? 's' : ''}
				</button>
			)}
			{hasArgs && argsOpen && (
				<div className="wpgraphql-ide-docs-args">
					{field.args.map((arg) => (
						<div key={arg.name} className="wpgraphql-ide-docs-arg">
							<span className="wpgraphql-ide-docs-arg-name">
								{arg.name}
							</span>
							<button
								type="button"
								className="wpgraphql-ide-docs-type-badge is-small"
								onClick={() =>
									onSelectType(getNamedType(arg.type))
								}
							>
								{formatType(arg.type)}
							</button>
						</div>
					))}
				</div>
			)}
		</div>
	);
}

/**
 * Clickable type name link.
 *
 * @param {Object}   props
 * @param {string}   [props.label] Optional label prefix (e.g. "query").
 * @param {string}   [props.kind]  Type kind label (e.g. "Object", "Input").
 * @param {Object}   props.type    GraphQL type.
 * @param {Function} props.onClick Click handler.
 */
function TypeLink({ label, kind, type, onClick }) {
	return (
		<div className="wpgraphql-ide-docs-type-entry">
			{label && (
				<span className="wpgraphql-ide-docs-type-label">{label}:</span>
			)}
			<button
				type="button"
				className="wpgraphql-ide-docs-type-link"
				onClick={onClick}
			>
				{type.name}
			</button>
			{kind && (
				<span className="wpgraphql-ide-docs-type-kind">{kind}</span>
			)}
		</div>
	);
}

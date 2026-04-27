import React, { useState } from 'react';
import { Button } from '@wordpress/components';
import { Icon, arrowLeft, search } from '@wordpress/icons';
import { useSelect } from '@wordpress/data';
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
		return <RootView schema={schema} onSelectType={pushType} />;
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

	return <TypeView type={type} onSelectType={pushType} onBack={goBack} />;
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
 * @param {Object}   props.schema       GraphQL schema.
 * @param {Function} props.onSelectType Callback when a type is clicked.
 */
function RootView({ schema, onSelectType }) {
	const [searchTerm, setSearchTerm] = useState('');

	const queryType = schema.getQueryType();
	const mutationType = schema.getMutationType();
	const subscriptionType = schema.getSubscriptionType();

	// Only compute filtered types when searching.
	const filteredTypes = searchTerm.trim()
		? Object.values(schema.getTypeMap())
				.filter((t) => {
					if (t.name.startsWith('__')) {
						return false;
					}
					const q = searchTerm.toLowerCase();
					if (t.name.toLowerCase().includes(q)) {
						return true;
					}
					// Also search field names.
					if (typeof t.getFields === 'function') {
						try {
							const fields = t.getFields();
							return Object.keys(fields).some((f) =>
								f.toLowerCase().includes(q)
							);
						} catch {
							return false;
						}
					}
					return false;
				})
				.sort((a, b) => a.name.localeCompare(b.name))
				.slice(0, 25)
		: [];

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
				<div className="wpgraphql-ide-docs-section-title">
					Search Types
				</div>
				<input
					type="text"
					value={searchTerm}
					onChange={(e) => setSearchTerm(e.target.value)}
					placeholder="Search types and fields..."
					className="wpgraphql-ide-docs-search"
				/>
				{filteredTypes.map((type) => (
					<TypeLink
						key={type.name}
						type={type}
						kind={getTypeKind(type)}
						onClick={() => onSelectType(type)}
					/>
				))}
				{searchTerm.trim() && filteredTypes.length === 0 && (
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
 * @param {Object}   props
 * @param {Object}   props.type         GraphQL type.
 * @param {Function} props.onSelectType Callback when a type is clicked.
 * @param {Function} props.onBack       Back navigation callback.
 */
function TypeView({ type, onSelectType, onBack }) {
	return (
		<div className="wpgraphql-ide-docs-panel">
			<BackButton onClick={onBack} />
			<div className="wpgraphql-ide-docs-type-name">{type.name}</div>
			{type.description && (
				<p className="wpgraphql-ide-docs-description">
					{type.description}
				</p>
			)}

			{(isObjectType(type) ||
				isInputObjectType(type) ||
				isInterfaceType(type)) && (
				<FieldsList
					fields={Object.values(type.getFields())}
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
									{val.description}
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
 * @param {Object}   props
 * @param {Array}    props.fields       Array of GraphQL field objects.
 * @param {Function} props.onSelectType Callback when a type is clicked.
 */
function FieldsList({ fields, onSelectType }) {
	return (
		<div className="wpgraphql-ide-docs-section">
			<div className="wpgraphql-ide-docs-section-title">Fields</div>
			{fields.map((field) => (
				<FieldItem
					key={field.name}
					field={field}
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
 * @param {Function} props.onSelectType Callback when a type is clicked.
 */
function FieldItem({ field, onSelectType }) {
	const [argsOpen, setArgsOpen] = useState(false);
	const hasArgs = field.args && field.args.length > 0;

	return (
		<div className="wpgraphql-ide-docs-field">
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
					{field.description}
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

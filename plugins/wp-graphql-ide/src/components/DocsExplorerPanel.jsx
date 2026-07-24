import React, { useEffect, useMemo, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import {
	Icon,
	arrowLeft,
	chevronDown,
	chevronUp,
	search,
} from '@wordpress/icons';
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
	// Search lives at the panel level (not inside `RootView`) so it
	// stays visible — and the input keeps focus + content — as the
	// user navigates into specific types and back. Any non-empty
	// search term takes over the body of the panel with results;
	// clicking a result navigates AND clears the input so the user
	// drops cleanly into the type they picked.
	const [searchTerm, setSearchTerm] = useState('');

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

	// Navigation helpers that also clear the active search — so a search
	// → click → type-view transition doesn't leave a stale term in the
	// input. The user can refine the search by typing again at any time.
	const selectType = (type) => {
		pushType(type);
		setSearchTerm('');
	};
	const selectField = (typeName, fieldName) => {
		pushFrame(typeName, fieldName);
		setSearchTerm('');
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

	// Categorized search results — types and fields are surfaced
	// separately so users can pick the right kind of match. Both lists
	// are capped to keep large schemas (the WPGraphQL schema has ~600
	// types) responsive.
	const trimmed = searchTerm.trim();
	const { typeMatches, fieldMatches } = useMemo(() => {
		if (!schema || !trimmed) {
			return { typeMatches: [], fieldMatches: [] };
		}
		const q = trimmed.toLowerCase();
		const types = [];
		const fields = [];
		for (const t of Object.values(schema.getTypeMap())) {
			if (t.name.startsWith('__')) {
				continue;
			}
			if (t.name.toLowerCase().includes(q)) {
				types.push(t);
			}
			if (typeof t.getFields === 'function') {
				let fieldMap;
				try {
					fieldMap = t.getFields();
				} catch {
					continue;
				}
				for (const f of Object.values(fieldMap)) {
					if (f.name.toLowerCase().includes(q)) {
						fields.push({ type: t, field: f });
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

	if (!schema) {
		return (
			<div className="wpgraphql-ide-docs-panel">
				<p className="wpgraphql-ide-docs-empty">
					{__(
						'Schema not loaded. Click the refresh button to load it.',
						'wpgraphql-ide'
					)}
				</p>
			</div>
		);
	}

	const current = stack.length > 0 ? stack[stack.length - 1] : null;
	const type = current ? schema.getType(current.name) : null;

	return (
		<div className="wpgraphql-ide-docs-panel">
			<div className="wpgraphql-ide-docs-search-header">
				<input
					type="text"
					value={searchTerm}
					onChange={(e) => setSearchTerm(e.target.value)}
					placeholder={__(
						'Search types and fields…',
						'wpgraphql-ide'
					)}
					aria-label={__(
						'Search GraphQL schema types and fields',
						'wpgraphql-ide'
					)}
					className="wpgraphql-ide-docs-search"
				/>
			</div>
			<div className="wpgraphql-ide-docs-body">
				{renderBody({
					trimmed,
					typeMatches,
					fieldMatches,
					schema,
					current,
					type,
					selectType,
					selectField,
					goBack,
				})}
			</div>
		</div>
	);
}

/**
 * Pick the right body view for the docs panel. Lifted out so the
 * panel render avoids nested ternaries.
 *
 * @param {Object}      ctx              Body-render context.
 * @param {string}      ctx.trimmed      Active search term (already trimmed).
 * @param {Array}       ctx.typeMatches  Capped type matches for the current search.
 * @param {Array}       ctx.fieldMatches Capped `{type, field}` matches for the current search.
 * @param {Object}      ctx.schema       Active GraphQL schema.
 * @param {Object|null} ctx.current      Top frame on the navigation stack, if any.
 * @param {Object|null} ctx.type         Resolved GraphQL type for `current`, if any.
 * @param {Function}    ctx.selectType   Push a type onto the nav stack.
 * @param {Function}    ctx.selectField  Push a type+field onto the nav stack.
 * @param {Function}    ctx.goBack       Pop the top of the nav stack.
 */
function renderBody({
	trimmed,
	typeMatches,
	fieldMatches,
	schema,
	current,
	type,
	selectType,
	selectField,
	goBack,
}) {
	if (trimmed) {
		return (
			<SearchResults
				typeMatches={typeMatches}
				fieldMatches={fieldMatches}
				onSelectType={selectType}
				onSelectField={selectField}
			/>
		);
	}
	if (!current) {
		return <RootView schema={schema} onSelectType={selectType} />;
	}
	if (!type) {
		return (
			<>
				<BackButton onClick={goBack} />
				<p>
					{sprintf(
						/* translators: %s: name of the missing GraphQL type */
						__('Type "%s" not found.', 'wpgraphql-ide'),
						current.name
					)}
				</p>
			</>
		);
	}
	return (
		<TypeView
			// Keyed by type name so per-section collapse state resets to the
			// expanded default whenever the user navigates to another type.
			key={type.name}
			type={type}
			schema={schema}
			focusField={current.focusField || null}
			onSelectType={selectType}
			onBack={goBack}
		/>
	);
}

/**
 * Search-result body — surfaces type hits and field hits in two
 * groups. Rendered when the search input at the panel top has a
 * non-empty term.
 *
 * @param {Object}   props
 * @param {Array}    props.typeMatches   Capped list of matching types.
 * @param {Array}    props.fieldMatches  Capped list of matching `{type, field}` pairs.
 * @param {Function} props.onSelectType  Called with the type object when a type result is clicked.
 * @param {Function} props.onSelectField Called with `(typeName, fieldName)` when a field result is clicked.
 */
function SearchResults({
	typeMatches,
	fieldMatches,
	onSelectType,
	onSelectField,
}) {
	if (typeMatches.length === 0 && fieldMatches.length === 0) {
		return (
			<div className="wpgraphql-ide-docs-section">
				<p className="wpgraphql-ide-docs-empty">
					{__('No results.', 'wpgraphql-ide')}
				</p>
			</div>
		);
	}
	return (
		<div className="wpgraphql-ide-docs-section">
			{typeMatches.length > 0 && (
				<div className="wpgraphql-ide-docs-search-group">
					<div className="wpgraphql-ide-docs-search-group-title">
						{__('Types', 'wpgraphql-ide')}
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
			{fieldMatches.length > 0 && (
				<div className="wpgraphql-ide-docs-search-group">
					<div className="wpgraphql-ide-docs-search-group-title">
						{__('Fields', 'wpgraphql-ide')}
					</div>
					{fieldMatches.map(({ type, field }) => (
						<button
							key={`${type.name}.${field.name}`}
							type="button"
							className="wpgraphql-ide-docs-field-link"
							onClick={() => onSelectField(type.name, field.name)}
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
		</div>
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
			{__('Back', 'wpgraphql-ide')}
		</Button>
	);
}

/**
 * Root schema view — shows the Query / Mutation / Subscription entry
 * points. The schema-wide search input now lives at the panel level
 * (see `DocsExplorerPanel`), so this view is just the entry points.
 *
 * @param {Object}   props
 * @param {Object}   props.schema       GraphQL schema.
 * @param {Function} props.onSelectType Called with a type object when an entry point is clicked.
 */
function RootView({ schema, onSelectType }) {
	const queryType = schema.getQueryType();
	const mutationType = schema.getMutationType();
	const subscriptionType = schema.getSubscriptionType();

	return (
		<div className="wpgraphql-ide-docs-section">
			{/* A plain heading, not a `DocsSection`: the root view has no
				sticky type header to offset against, and its single group
				of entry points has nothing worth collapsing. */}
			<div className="wpgraphql-ide-docs-root-title">
				{__('Root Types', 'wpgraphql-ide')}
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
	);
}

/**
 * Collapsible section with a sticky heading.
 *
 * The heading is a toggle button that stays pinned just below the sticky
 * type header while its section scrolls, so the user always knows which
 * section (Fields, Implements, …) the rows under the cursor belong to.
 * Collapse state is per-mount — `TypeView` is keyed by type name, so every
 * type opens with all sections expanded.
 *
 * @param {Object}          props
 * @param {string}          props.title       Section heading label.
 * @param {number}          props.count       Number of entries in the section.
 * @param {*}               [props.forceOpen] Expands the section whenever this changes to something truthy, revealing a focus target the user had collapsed out of view. Pass the identity of the thing being focused (a field name), not a boolean — a boolean stays `true` between two focus targets and would only fire once.
 * @param {React.ReactNode} props.children    Section content.
 */
function DocsSection({ title, count, forceOpen, children }) {
	const [open, setOpen] = useState(true);

	useEffect(() => {
		if (forceOpen) {
			setOpen(true);
		}
	}, [forceOpen]);

	return (
		<div className="wpgraphql-ide-docs-section">
			<button
				type="button"
				className="wpgraphql-ide-docs-section-title"
				onClick={() => setOpen((prev) => !prev)}
				aria-expanded={open}
			>
				<span className="wpgraphql-ide-docs-section-label">
					{title}
				</span>
				<span className="wpgraphql-ide-docs-section-count">
					{count}
				</span>
				{/* Trailing up/down chevron — the Gutenberg PanelBody idiom,
					also used by DocumentNotices. */}
				<span
					className="wpgraphql-ide-docs-section-chevron"
					aria-hidden="true"
				>
					<Icon icon={open ? chevronUp : chevronDown} size={18} />
				</span>
			</button>
			{open && children}
		</div>
	);
}

/**
 * Detailed view for a single type — shows description, fields, enum
 * values, or union members.
 *
 * @param {Object}      props
 * @param {Object}      props.type         GraphQL type.
 * @param {Object}      props.schema       Active GraphQL schema (used to look up interface implementations).
 * @param {string|null} props.focusField   Optional field name to scroll to / highlight.
 * @param {Function}    props.onSelectType Callback when a type is clicked.
 * @param {Function}    props.onBack       Back navigation callback.
 */
function TypeView({ type, schema, focusField, onSelectType, onBack }) {
	// Object and interface types can implement interfaces; interfaces can
	// also be implemented by other types. Both directions are shown so the
	// user can hop between an interface and its implementations.
	const implementedInterfaces =
		isObjectType(type) || isInterfaceType(type) ? type.getInterfaces() : [];
	// `getImplementations()` (rather than `getPossibleTypes()`) so the list
	// includes interfaces that implement this interface, not just objects —
	// e.g. `ContentNode` shows up as an implementation of `Node`.
	let implementations = [];
	if (isInterfaceType(type)) {
		const { objects, interfaces } = schema.getImplementations(type);
		implementations = [...objects, ...interfaces].sort((a, b) =>
			a.name.localeCompare(b.name)
		);
	}

	// Section headings stick just below the type header, so they need its
	// rendered height as a CSS offset. Measured (rather than hardcoded)
	// because the type name can wrap.
	const rootRef = useRef(null);
	const headerRef = useRef(null);
	useEffect(() => {
		const root = rootRef.current;
		const header = headerRef.current;
		if (!root || !header) {
			return undefined;
		}
		const update = () => {
			root.style.setProperty(
				'--wpgraphql-ide-docs-header-h',
				`${header.offsetHeight}px`
			);
		};
		update();
		if (typeof window.ResizeObserver === 'undefined') {
			return undefined;
		}
		const observer = new window.ResizeObserver(update);
		observer.observe(header);
		return () => observer.disconnect();
	}, []);

	return (
		<div ref={rootRef} className="wpgraphql-ide-docs-panel">
			{/* Sticky header — pins the Back button and type name to the top
				of the panel's scroll container so the user can always see what
				type they're looking at. The description intentionally lives
				outside the header: it scrolls away so long descriptions don't
				eat vertical space while browsing fields. */}
			<header ref={headerRef} className="wpgraphql-ide-docs-type-header">
				<BackButton onClick={onBack} />
				<div className="wpgraphql-ide-docs-type-name">{type.name}</div>
			</header>

			{type.description && (
				<p className="wpgraphql-ide-docs-description wpgraphql-ide-docs-type-description">
					{decodeEntities(type.description)}
				</p>
			)}

			{implementedInterfaces.length > 0 && (
				<DocsSection
					title={__('Implements', 'wpgraphql-ide')}
					count={implementedInterfaces.length}
				>
					{implementedInterfaces.map((iface) => (
						<TypeLink
							key={iface.name}
							type={iface}
							onClick={() => onSelectType(iface)}
						/>
					))}
				</DocsSection>
			)}

			{(isObjectType(type) ||
				isInputObjectType(type) ||
				isInterfaceType(type)) && (
				<FieldsList
					fields={Object.values(type.getFields())}
					focusField={focusField}
					onSelectType={onSelectType}
				/>
			)}

			{implementations.length > 0 && (
				<DocsSection
					title={__('Implementations', 'wpgraphql-ide')}
					count={implementations.length}
				>
					{implementations.map((t) => (
						<TypeLink
							key={t.name}
							type={t}
							kind={getTypeKind(t)}
							onClick={() => onSelectType(t)}
						/>
					))}
				</DocsSection>
			)}

			{isEnumType(type) && (
				<DocsSection
					title={__('Values', 'wpgraphql-ide')}
					count={type.getValues().length}
				>
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
				</DocsSection>
			)}

			{isUnionType(type) && (
				<DocsSection
					title={__('Possible Types', 'wpgraphql-ide')}
					count={type.getTypes().length}
				>
					{type.getTypes().map((t) => (
						<TypeLink
							key={t.name}
							type={t}
							onClick={() => onSelectType(t)}
						/>
					))}
				</DocsSection>
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
		<DocsSection
			title={__('Fields', 'wpgraphql-ide')}
			count={fields.length}
			// The field name itself, not a boolean: `TypeView` is keyed by
			// type, so moving between two fields of the same type doesn't
			// remount it, and a boolean would stay `true` across the move
			// and never re-trigger the re-open.
			forceOpen={focusField}
		>
			{fields.map((field) => (
				<FieldItem
					key={field.name}
					field={field}
					isFocused={focusField === field.name}
					onSelectType={onSelectType}
				/>
			))}
		</DocsSection>
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
					{sprintf(
						/* translators: %d: number of arguments for a GraphQL field */
						_n(
							'%d argument',
							'%d arguments',
							field.args.length,
							'wpgraphql-ide'
						),
						field.args.length
					)}
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
 * Clickable row that navigates to a type's detail view.
 *
 * The whole row is the button, not just the type name: the row is what
 * lights up on hover, so the row is what has to be clickable.
 *
 * The accessible name is composed rather than left to the row's text so it
 * carries the same information the row shows — in an Implementations list
 * the kind is what separates an object from an interface — and so it stays
 * put regardless of the `text-transform` the kind is styled with.
 *
 * @param {Object}   props
 * @param {string}   [props.label] Optional label prefix (e.g. "query").
 * @param {string}   [props.kind]  Type kind label (e.g. "Object", "Input").
 * @param {Object}   props.type    GraphQL type.
 * @param {Function} props.onClick Click handler.
 */
function TypeLink({ label, kind, type, onClick }) {
	const accessibleName = [
		label ? `${label}: ` : '',
		type.name,
		kind ? `, ${kind}` : '',
	].join('');

	return (
		<button
			type="button"
			className="wpgraphql-ide-docs-type-entry"
			aria-label={accessibleName}
			onClick={onClick}
		>
			{label && (
				<span className="wpgraphql-ide-docs-type-label">{label}:</span>
			)}
			<span className="wpgraphql-ide-docs-type-link">{type.name}</span>
			{kind && (
				<span className="wpgraphql-ide-docs-type-kind">{kind}</span>
			)}
		</button>
	);
}

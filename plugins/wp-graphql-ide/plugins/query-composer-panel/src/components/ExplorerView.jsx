import React from 'react';
import {
	capitalize,
	DEFAULT_DOCUMENT,
	defaultColors,
	defaultGetDefaultFieldNames,
	defaultGetDefaultScalarArgValue,
	defaultStyles,
	findCursorPath,
	memoizeParseQuery,
	operationKey,
} from '../utils';
import { GraphQLObjectType, print } from 'graphql';
import RootView from './RootView';
import { defaultCheckboxChecked, defaultCheckboxUnchecked } from './Checkbox';
import ArrowOpen from './ArrowOpen';
import ArrowClosed from './ArrowClosed';

// Pipe-delimited path key for matching FieldView's `data-field-path`
// attribute. Pipes can't appear in GraphQL identifiers so collisions are
// impossible. Returns null when there's no field-level target (cursor is
// outside every operation, or inside an operation but not on a field).
const CURSOR_TARGET_CLASS = 'graphiql-explorer-row--cursor-target';
const CURSOR_HIGHLIGHT_MS = 1500;
function fieldPathKey(cursor) {
	if (!cursor || !cursor.fieldPath || cursor.fieldPath.length === 0) {
		return null;
	}
	return `${cursor.opKey}|${cursor.fieldPath.join('|')}`;
}

class ExplorerView extends React.PureComponent {
	state = {
		newOperationType: 'query',
		operationToScrollTo: null,
		// Map of `${kind}:${name}` -> boolean. Absent key means "use default
		// (cursor-matched op expanded, rest collapsed when multiple exist)".
		collapsedByKey: {},
		// Tracks which op the cursor was last in, so we can wipe stale user
		// toggles when the cursor moves to a different operation.
		lastCursorOpKey: null,
		// Tracks the last cursor field-path key we revealed, so typing within
		// the same field doesn't re-trigger scroll/highlight on every keystroke.
		lastFieldPathKey: null,
	};

	static getDerivedStateFromProps(props, state) {
		const ops = (() => {
			try {
				return memoizeParseQuery(props.query).definitions.filter(
					(d) =>
						d.kind === 'OperationDefinition' ||
						d.kind === 'FragmentDefinition'
				);
			} catch {
				return [];
			}
		})();
		const cursor = findCursorPath(ops, props.cursorOffset);
		const cursorOpKey = cursor ? cursor.opKey : null;
		const nextFieldPathKey = fieldPathKey(cursor);
		const patch = {};
		// When the cursor enters a new operation, drop user toggles so the
		// composer refocuses on the active op.
		if (cursorOpKey && cursorOpKey !== state.lastCursorOpKey) {
			patch.lastCursorOpKey = cursorOpKey;
			patch.collapsedByKey = {};
		}
		if (nextFieldPathKey !== state.lastFieldPathKey) {
			patch.lastFieldPathKey = nextFieldPathKey;
		}
		return Object.keys(patch).length ? patch : null;
	}

	_setCollapsed = (key, collapsed) => {
		this.setState((prev) => ({
			collapsedByKey: { ...prev.collapsedByKey, [key]: collapsed },
		}));
	};

	_ref;
	_cursorHighlightEl = null;
	_cursorHighlightTimer = null;
	_resetScroll = () => {
		const container = this._ref;
		if (container) {
			container.scrollLeft = 0;
		}
	};
	componentDidMount() {
		this._resetScroll();
		// Reveal the row for the cursor's initial position (e.g. when the
		// panel opens with the editor already focused on a field).
		this._revealCursorTarget(this.state.lastFieldPathKey);
	}

	componentDidUpdate(_prevProps, prevState) {
		if (this.state.lastFieldPathKey !== prevState.lastFieldPathKey) {
			this._revealCursorTarget(this.state.lastFieldPathKey);
		}
	}

	componentWillUnmount() {
		if (this._cursorHighlightTimer) {
			clearTimeout(this._cursorHighlightTimer);
			this._cursorHighlightTimer = null;
		}
	}

	// Scroll the row matching `pathKey` into view and apply a brief highlight
	// class. No-op when there's no container, no key, or no matching row.
	_revealCursorTarget = (pathKey) => {
		const container = this._ref;
		if (this._cursorHighlightTimer) {
			clearTimeout(this._cursorHighlightTimer);
			this._cursorHighlightTimer = null;
		}
		if (this._cursorHighlightEl) {
			this._cursorHighlightEl.classList.remove(CURSOR_TARGET_CLASS);
			this._cursorHighlightEl = null;
		}
		if (!container || !pathKey) {
			return;
		}
		// findCursorPath walks AST Field nodes — it will surface names the
		// user typed that aren't in the schema (and so have no DOM row).
		// Walk back up the path until a row that exists is found so the
		// reveal still lands on the nearest visible ancestor.
		//
		// querySelector with a data-attribute selector — pathKey is built
		// from AST field names (validated identifiers), so it's safe to embed.
		let candidate = pathKey;
		let el = container.querySelector(`[data-field-path="${candidate}"]`);
		while (!el && candidate.includes('|')) {
			candidate = candidate.substring(0, candidate.lastIndexOf('|'));
			el = container.querySelector(`[data-field-path="${candidate}"]`);
		}
		if (!el) {
			return;
		}
		// `center` keeps the row away from the panel's sticky operation
		// title-bar at the top and the bottom edge of the scroll area —
		// both clip a row aligned to the nearest edge.
		el.scrollIntoView({ block: 'center', behavior: 'smooth' });
		el.classList.add(CURSOR_TARGET_CLASS);
		this._cursorHighlightEl = el;
		this._cursorHighlightTimer = setTimeout(() => {
			if (this._cursorHighlightEl) {
				this._cursorHighlightEl.classList.remove(CURSOR_TARGET_CLASS);
				this._cursorHighlightEl = null;
			}
			this._cursorHighlightTimer = null;
		}, CURSOR_HIGHLIGHT_MS);
	};

	_onEdit = (query) => this.props.onEdit(query);

	_setAddOperationType = (value) => {
		this.setState({ newOperationType: value });
	};

	_handleRootViewMount = (rootViewElId) => {
		if (
			!!this.state.operationToScrollTo &&
			this.state.operationToScrollTo === rootViewElId
		) {
			const selector = `.graphiql-explorer-root #${rootViewElId}`;

			const el = document.querySelector(selector);
			if (el) {
				el.scrollIntoView();
			}
		}
	};

	render() {
		const { schema, query, makeDefaultArg } = this.props;

		if (!schema) {
			return (
				<div
					style={{ fontFamily: 'sans-serif' }}
					className="error-container"
				>
					No Schema Available
				</div>
			);
		}
		const styleConfig = {
			colors: this.props.colors || defaultColors,
			checkboxChecked:
				this.props.checkboxChecked || defaultCheckboxChecked,
			checkboxUnchecked:
				this.props.checkboxUnchecked || defaultCheckboxUnchecked,
			arrowClosed: this.props.arrowClosed || ArrowClosed,
			arrowOpen: this.props.arrowOpen || ArrowOpen,
			styles: this.props.styles
				? {
						...defaultStyles,
						...this.props.styles,
					}
				: defaultStyles,
		};
		const queryType = schema.getQueryType();
		const mutationType = schema.getMutationType();
		const subscriptionType = schema.getSubscriptionType();
		if (!queryType && !mutationType && !subscriptionType) {
			return <div>Missing query type</div>;
		}
		const queryFields = queryType && queryType.getFields();
		const mutationFields = mutationType && mutationType.getFields();
		const subscriptionFields =
			subscriptionType && subscriptionType.getFields();

		const parsedQuery = memoizeParseQuery(query);
		const getDefaultFieldNames =
			this.props.getDefaultFieldNames || defaultGetDefaultFieldNames;
		const getDefaultScalarArgValue =
			this.props.getDefaultScalarArgValue ||
			defaultGetDefaultScalarArgValue;

		const definitions = parsedQuery.definitions;

		const _relevantOperations = definitions
			.map((definition) => {
				if (definition.kind === 'FragmentDefinition') {
					return definition;
				} else if (definition.kind === 'OperationDefinition') {
					return definition;
				}
				return null;
			})
			.filter(Boolean);

		const relevantOperations =
			_relevantOperations.length === 0
				? DEFAULT_DOCUMENT.definitions
				: _relevantOperations;

		const cursor = findCursorPath(
			_relevantOperations,
			this.props.cursorOffset
		);
		const cursorOpKey = cursor ? cursor.opKey : null;

		const renameOperation = (targetOperation, name) => {
			const newName =
				name === null || name === undefined || name === ''
					? null
					: { kind: 'Name', value: name, loc: undefined };
			const newOperation = { ...targetOperation, name: newName };

			const existingDefs = parsedQuery.definitions;

			const newDefinitions = existingDefs.map((existingOperation) => {
				if (targetOperation === existingOperation) {
					return newOperation;
				}
				return existingOperation;
			});

			return {
				...parsedQuery,
				definitions: newDefinitions,
			};
		};

		const cloneOperation = (targetOperation) => {
			let kind;
			if (targetOperation.kind === 'FragmentDefinition') {
				kind = 'fragment';
			} else {
				kind = targetOperation.operation;
			}

			const newOperationName =
				((targetOperation.name && targetOperation.name.value) || '') +
				'Copy';

			const newName = {
				kind: 'Name',
				value: newOperationName,
				loc: undefined,
			};

			const newOperation = { ...targetOperation, name: newName };

			const existingDefs = parsedQuery.definitions;

			const newDefinitions = [...existingDefs, newOperation];

			this.setState({
				operationToScrollTo: `${kind}-${newOperationName}`,
			});

			return {
				...parsedQuery,
				definitions: newDefinitions,
			};
		};

		const destroyOperation = (targetOperation) => {
			const existingDefs = parsedQuery.definitions;

			const newDefinitions = existingDefs.filter((existingOperation) => {
				if (targetOperation === existingOperation) {
					return false;
				}
				return true;
			});

			return {
				...parsedQuery,
				definitions: newDefinitions,
			};
		};

		const addOperation = (kind) => {
			const existingDefs = parsedQuery.definitions;

			const viewingDefaultOperation =
				parsedQuery.definitions.length === 1 &&
				parsedQuery.definitions[0] === DEFAULT_DOCUMENT.definitions[0];

			const MySiblingDefs = viewingDefaultOperation
				? []
				: existingDefs.filter((def) => {
						if (def.kind === 'OperationDefinition') {
							return def.operation === kind;
						}
						return false;
					});

			const newOperationName = `My${capitalize(kind)}${
				MySiblingDefs.length === 0 ? '' : MySiblingDefs.length + 1
			}`;

			const firstFieldName = '__typename # Placeholder value';

			const selectionSet = {
				kind: 'SelectionSet',
				selections: [
					{
						kind: 'Field',
						name: {
							kind: 'Name',
							value: firstFieldName,
							loc: null,
						},
						arguments: [],
						directives: [],
						selectionSet: null,
						loc: null,
					},
				],
				loc: null,
			};

			const newDefinition = {
				kind: 'OperationDefinition',
				operation: kind,
				name: { kind: 'Name', value: newOperationName },
				variableDefinitions: [],
				directives: [],
				selectionSet,
				loc: null,
			};

			const newDefinitions = viewingDefaultOperation
				? [newDefinition]
				: [...parsedQuery.definitions, newDefinition];

			const newOperationDef = {
				...parsedQuery,
				definitions: newDefinitions,
			};

			this.setState({
				operationToScrollTo: `${kind}-${newOperationName}`,
			});

			this.props.onEdit(print(newOperationDef));
		};

		const actionsOptions = [
			!!queryFields ? (
				<option
					key="query"
					className={'toolbar-button'}
					style={styleConfig.styles.buttonStyle}
					type="link"
					value="query"
				>
					Query
				</option>
			) : null,
			!!mutationFields ? (
				<option
					key="mutation"
					className={'toolbar-button'}
					style={styleConfig.styles.buttonStyle}
					type="link"
					value="mutation"
				>
					Mutation
				</option>
			) : null,
			!!subscriptionFields ? (
				<option
					key="subscription"
					className={'toolbar-button'}
					style={styleConfig.styles.buttonStyle}
					type="link"
					value="subscription"
				>
					Subscription
				</option>
			) : null,
		].filter(Boolean);

		const actionsEl =
			actionsOptions.length === 0 || this.props.hideActions ? null : (
				<form
					className="graphiql-explorer-actions"
					onSubmit={(event) => event.preventDefault()}
				>
					<span className="graphiql-explorer-actions-label">
						Add new
					</span>
					<select
						onChange={(event) =>
							this._setAddOperationType(event.target.value)
						}
						value={this.state.newOperationType}
					>
						{actionsOptions}
					</select>
					<button
						type="submit"
						className="graphiql-explorer-actions-add"
						aria-label="Add operation"
						onClick={() =>
							this.state.newOperationType
								? addOperation(this.state.newOperationType)
								: null
						}
					>
						+
					</button>
				</form>
			);

		const externalFragments =
			this.props.externalFragments &&
			this.props.externalFragments.reduce((acc, fragment) => {
				if (fragment.kind === 'FragmentDefinition') {
					const fragmentTypeName = fragment.typeCondition.name.value;
					const existingFragmentsForType =
						acc[fragmentTypeName] || [];
					const newFragmentsForType = [
						...existingFragmentsForType,
						fragment,
					].sort((a, b) => a.name.value.localeCompare(b.name.value));
					return {
						...acc,
						[fragmentTypeName]: newFragmentsForType,
					};
				}

				return acc;
			}, {});

		const documentFragments = relevantOperations.reduce(
			(acc, operation) => {
				if (operation.kind === 'FragmentDefinition') {
					const fragmentTypeName = operation.typeCondition.name.value;
					const existingFragmentsForType =
						acc[fragmentTypeName] || [];
					const newFragmentsForType = [
						...existingFragmentsForType,
						operation,
					].sort((a, b) => a.name.value.localeCompare(b.name.value));
					return {
						...acc,
						[fragmentTypeName]: newFragmentsForType,
					};
				}

				return acc;
			},
			{}
		);

		const availableFragments = {
			...documentFragments,
			...externalFragments,
		};

		return (
			<div
				ref={(ref) => {
					this._ref = ref;
				}}
				className="graphiql-explorer-root"
			>
				<div className="graphiql-explorer-operations">
					{relevantOperations.map((operation, index) => {
						const operationName =
							operation && operation.name && operation.name.value;

						const operationType =
							operation.kind === 'FragmentDefinition'
								? 'fragment'
								: (operation && operation.operation) || 'query';

						const onOperationRename = (newName) => {
							const newOperationDef = renameOperation(
								operation,
								newName
							);
							this.props.onEdit(print(newOperationDef));
						};

						const onOperationClone = () => {
							const newOperationDef = cloneOperation(operation);
							this.props.onEdit(print(newOperationDef));
						};

						const onOperationDestroy = () => {
							const newOperationDef = destroyOperation(operation);
							this.props.onEdit(print(newOperationDef));
						};

						const fragmentType =
							operation.kind === 'FragmentDefinition' &&
							operation.typeCondition.kind === 'NamedType' &&
							schema.getType(operation.typeCondition.name.value);

						const fragmentFields =
							fragmentType instanceof GraphQLObjectType
								? fragmentType.getFields()
								: null;

						let fields = null;
						if (operationType === 'query') {
							fields = queryFields;
						} else if (operationType === 'mutation') {
							fields = mutationFields;
						} else if (operationType === 'subscription') {
							fields = subscriptionFields;
						} else if (operation.kind === 'FragmentDefinition') {
							fields = fragmentFields;
						}

						const fragmentTypeName =
							operation.kind === 'FragmentDefinition'
								? operation.typeCondition.name.value
								: null;

						const onCommit = (parsedDocument) => {
							const textualNewDocument = print(parsedDocument);

							this.props.onEdit(textualNewDocument);
						};

						// With multiple operations: when the editor cursor
						// is inside an operation, that one is the default-
						// expanded; otherwise the first is. User toggles
						// override the default until the cursor moves to a
						// new operation (which wipes the toggle map).
						const opKey = operationKey(operation, index);
						const userCollapsed = this.state.collapsedByKey[opKey];
						let defaultCollapsed;
						if (relevantOperations.length <= 1) {
							defaultCollapsed = false;
						} else if (cursorOpKey) {
							defaultCollapsed = opKey !== cursorOpKey;
						} else {
							defaultCollapsed = index !== 0;
						}
						const isCollapsed =
							typeof userCollapsed === 'boolean'
								? userCollapsed
								: defaultCollapsed;
						const onToggleCollapsed = () =>
							this._setCollapsed(opKey, !isCollapsed);

						let rootTypeName = null;
						if (operationType === 'query' && queryType) {
							rootTypeName = queryType.name;
						} else if (
							operationType === 'mutation' &&
							mutationType
						) {
							rootTypeName = mutationType.name;
						} else if (
							operationType === 'subscription' &&
							subscriptionType
						) {
							rootTypeName = subscriptionType.name;
						} else if (
							operation.kind === 'FragmentDefinition' &&
							fragmentTypeName
						) {
							rootTypeName = fragmentTypeName;
						}

						return (
							<RootView
								key={index}
								isLast={index === relevantOperations.length - 1}
								fields={fields}
								rootTypeName={rootTypeName}
								operationType={operationType}
								name={operationName}
								definition={operation}
								isCollapsed={isCollapsed}
								onToggleCollapsed={onToggleCollapsed}
								canCollapse={true}
								onOperationRename={onOperationRename}
								onOperationDestroy={onOperationDestroy}
								onOperationClone={onOperationClone}
								onTypeName={fragmentTypeName}
								onMount={this._handleRootViewMount}
								onCommit={onCommit}
								onEdit={(newDefinition, options) => {
									let commit;
									if (
										typeof options === 'object' &&
										typeof options.commit !== 'undefined'
									) {
										commit = options.commit;
									} else {
										commit = true;
									}

									if (!!newDefinition) {
										const newQuery = {
											...parsedQuery,
											definitions:
												parsedQuery.definitions.map(
													(existingDefinition) =>
														existingDefinition ===
														operation
															? newDefinition
															: existingDefinition
												),
										};

										if (commit) {
											onCommit(newQuery);
											return newQuery;
										}
										return newQuery;
									}
									return parsedQuery;
								}}
								schema={schema}
								getDefaultFieldNames={getDefaultFieldNames}
								getDefaultScalarArgValue={
									getDefaultScalarArgValue
								}
								makeDefaultArg={makeDefaultArg}
								onRunOperation={() => {
									if (!!this.props.onRunOperation) {
										this.props.onRunOperation(
											operationName
										);
									}
								}}
								styleConfig={styleConfig}
								availableFragments={availableFragments}
								opKey={opKey}
							/>
						);
					})}
				</div>

				{actionsEl}
			</div>
		);
	}
}

export default ExplorerView;

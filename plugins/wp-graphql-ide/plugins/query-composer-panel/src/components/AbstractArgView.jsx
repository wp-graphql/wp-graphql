import React from 'react';
import {
	coerceArgValue,
	defaultInputObjectFields,
	getListItemType,
	isRequiredArgument,
	makeDefaultValueNode,
	unwrapInputType,
} from '../utils';
import {
	isEnumType,
	isInputObjectType,
	isLeafType,
	isScalarType,
	parseType,
	visit,
} from 'graphql';
import ScalarInput from './ScalarInput';
import InputArgView from './InputArgView';
import Checkbox from './Checkbox';
import ArgHoverTooltip from './ArgHoverTooltip';

class AbstractArgView extends React.PureComponent {
	// Replace the whole list value with a new one and commit. Used by
	// add/remove/edit-item handlers below — the underlying setArgValue
	// already accepts a fully-formed value node and updates the right
	// argument in the document.
	_replaceListValue = (newValues) => {
		this.props.setArgValue({ kind: 'ListValue', values: newValues }, true);
	};

	_addListItem = (listItemType) => {
		const { argValue } = this.props;
		const currentValues =
			argValue && argValue.kind === 'ListValue' ? argValue.values : [];
		const item = makeDefaultValueNode(
			listItemType,
			this.props.getDefaultScalarArgValue,
			this.props.makeDefaultArg,
			this.props.parentField,
			this.props.arg
		);
		if (!item) {
			return;
		}
		this._replaceListValue([...currentValues, item]);
	};

	_removeListItem = (index) => {
		const { argValue } = this.props;
		if (!argValue || argValue.kind !== 'ListValue') {
			return;
		}
		this._replaceListValue(argValue.values.filter((_, i) => i !== index));
	};

	_setListItemValue = (index, newItem) => {
		const { argValue } = this.props;
		if (!argValue || argValue.kind !== 'ListValue') {
			return;
		}
		this._replaceListValue(
			argValue.values.map((v, i) => (i === index ? newItem : v))
		);
	};

	// Render a single list element. The inner type (`itemType`) keeps
	// its own non-null wrapper so we can validate later, but for the
	// UI we only care about the named inner type — that determines
	// whether to show a text input, a select, etc.
	_renderListItem = (item, index, itemType) => {
		const { styleConfig } = this.props;
		const namedItemType = unwrapInputType(itemType);
		const onScalarChange = (event) => {
			const next = coerceArgValue(namedItemType, event.target.value);
			this._setListItemValue(index, next);
		};

		let content;
		if (isEnumType(namedItemType)) {
			content = (
				<select
					className="graphiql-explorer-list-item-input"
					style={{ color: styleConfig.colors.string2 }}
					value={item.kind === 'EnumValue' ? item.value : ''}
					onChange={onScalarChange}
				>
					{namedItemType.getValues().map((v) => (
						<option key={v.name} value={v.name}>
							{v.name}
						</option>
					))}
				</select>
			);
		} else if (
			isScalarType(namedItemType) &&
			namedItemType.name === 'Boolean'
		) {
			content = (
				<select
					className="graphiql-explorer-list-item-input"
					style={{ color: styleConfig.colors.builtin }}
					value={item.kind === 'BooleanValue' ? item.value : ''}
					onChange={onScalarChange}
				>
					<option value="true">true</option>
					<option value="false">false</option>
				</select>
			);
		} else if (isLeafType(namedItemType)) {
			const stringValue =
				item && typeof item.value === 'string' ? item.value : '';
			const color =
				item && item.kind === 'StringValue'
					? styleConfig.colors.string
					: styleConfig.colors.number;
			content = (
				<input
					className="graphiql-explorer-list-item-input"
					type="text"
					value={stringValue}
					onChange={onScalarChange}
					style={{
						// Grow with content, but never below 3ch so an
						// empty chip is still wide enough to click into.
						width: `${Math.max(3, Math.min(20, stringValue.length))}ch`,
						color,
					}}
				/>
			);
		} else {
			// Nested input-object list items — render the placeholder
			// `<…>` for now. Editing happens by extracting the whole
			// list as a variable and editing in the Variables JSON.
			content = (
				<span
					style={{
						color: styleConfig.colors.atom,
						fontStyle: 'italic',
					}}
				>
					{'<'}
					{namedItemType.name}
					{'>'}
				</span>
			);
		}

		return (
			<div
				key={index}
				className="graphiql-explorer-list-item"
				data-list-index={index}
			>
				{content}
				<button
					type="button"
					className="graphiql-explorer-list-item-remove"
					title="Remove item"
					onClick={(e) => {
						e.preventDefault();
						e.stopPropagation();
						this._removeListItem(index);
					}}
				>
					&times;
				</button>
			</div>
		);
	};

	// Replace the fields of a single object-list item, leaving the rest of
	// the list untouched. Used as the `modifyFields` callback handed to
	// each per-item `InputArgView` so its existing add/remove/setValue
	// plumbing keeps working unchanged.
	_setListObjectItemFields = (index) => (newFields, commitOrOptions) => {
		const { argValue } = this.props;
		if (!argValue || argValue.kind !== 'ListValue') {
			return undefined;
		}
		const newValues = argValue.values.map((v, i) =>
			i === index ? { kind: 'ObjectValue', fields: newFields } : v
		);
		return this.props.setArgValue(
			{ kind: 'ListValue', values: newValues },
			commitOrOptions
		);
	};

	// Block-layout list editor for `[InputObject]` types. Each item gets
	// its own vertical block with the input type's fields rendered as
	// nested `InputArgView`s — the same components that drive ordinary
	// input-object args, so users get the full add/remove/extract
	// machinery on every nested field of every list element.
	_renderObjectListEditor(items, namedItemType, listItemType) {
		const itemFields = namedItemType.getFields();
		return (
			<div
				className="graphiql-explorer-list-objects"
				style={{ marginLeft: 16 }}
			>
				{items.map((item, i) => (
					<div key={i} className="graphiql-explorer-list-object-item">
						<div className="graphiql-explorer-list-object-head">
							<span className="graphiql-explorer-list-object-index">
								{namedItemType.name}[{i}]
							</span>
							<button
								type="button"
								className="graphiql-explorer-list-item-remove"
								title="Remove item"
								onClick={(e) => {
									e.preventDefault();
									e.stopPropagation();
									this._removeListItem(i);
								}}
							>
								&times;
							</button>
						</div>
						{item.kind === 'ObjectValue' && (
							<div className="graphiql-explorer-list-object-body">
								{Object.keys(itemFields)
									.sort()
									.map((fieldName) => (
										<InputArgView
											key={fieldName}
											arg={itemFields[fieldName]}
											parentField={this.props.parentField}
											parentTypeName={namedItemType.name}
											selection={item}
											modifyFields={this._setListObjectItemFields(
												i
											)}
											getDefaultScalarArgValue={
												this.props
													.getDefaultScalarArgValue
											}
											makeDefaultArg={
												this.props.makeDefaultArg
											}
											onRunOperation={
												this.props.onRunOperation
											}
											styleConfig={this.props.styleConfig}
											onCommit={this.props.onCommit}
											definition={this.props.definition}
										/>
									))}
							</div>
						)}
					</div>
				))}
				<button
					type="button"
					className="graphiql-explorer-list-add graphiql-explorer-list-add-block"
					title={`Add a new ${namedItemType.name} to the list`}
					onClick={(e) => {
						e.preventDefault();
						e.stopPropagation();
						this._addListItem(listItemType);
					}}
				>
					+ {namedItemType.name}
				</button>
			</div>
		);
	}

	_renderListEditor(listItemType) {
		const { argValue } = this.props;
		const items = argValue.values || [];
		const namedItemType = unwrapInputType(listItemType);

		// Lists of input objects need a vertical block layout so each
		// item's nested fields have somewhere to render. Lists of leaf
		// types (scalars / enums / booleans) keep the inline chip flow.
		if (isInputObjectType(namedItemType)) {
			return this._renderObjectListEditor(
				items,
				namedItemType,
				listItemType
			);
		}

		return (
			<div className="graphiql-explorer-list" style={{ marginLeft: 16 }}>
				{items.map((item, i) =>
					this._renderListItem(item, i, listItemType)
				)}
				<button
					type="button"
					className="graphiql-explorer-list-add"
					title={`Add a new ${namedItemType.name} to the list`}
					onClick={(e) => {
						e.preventDefault();
						e.stopPropagation();
						this._addListItem(listItemType);
					}}
				>
					+
				</button>
			</div>
		);
	}

	render() {
		const { argValue, arg, styleConfig } = this.props;
		const argType = unwrapInputType(arg.type);
		// `arg.type` may be wrapped: `[ID]`, `[ID!]!`, etc. The composer
		// treated everything as the inner scalar before, so a `[ID]` arg
		// rendered as a single text input. `listItemType` is non-null
		// when the arg expects a list — the inner type retains its own
		// non-null wrapper so list-of-non-null is still detectable.
		const listItemType = getListItemType(arg.type);

		// `inlineInput` sits next to the label inside the row header
		// (scalar inputs, enum/boolean selects, $variable text). `blockInput`
		// is the structured-children block (input-object fields, OneOf
		// variant, list items) and stacks below the row so the head's
		// flex layout (label + Extract/Inline pill) doesn't pull it
		// sideways.
		let inlineInput = null;
		let blockInput = null;
		if (argValue) {
			if (argValue.kind === 'Variable') {
				inlineInput = (
					<span style={{ color: styleConfig.colors.variable }}>
						${argValue.name.value}
					</span>
				);
			} else if (listItemType && argValue.kind === 'ListValue') {
				blockInput = this._renderListEditor(listItemType);
			} else if (isScalarType(argType)) {
				if (argType.name === 'Boolean') {
					inlineInput = (
						<select
							style={{
								color: styleConfig.colors.builtin,
							}}
							onChange={this.props.setArgValue}
							value={
								argValue.kind === 'BooleanValue'
									? argValue.value
									: undefined
							}
						>
							<option key="true" value="true">
								true
							</option>
							<option key="false" value="false">
								false
							</option>
						</select>
					);
				} else {
					inlineInput = (
						<ScalarInput
							setArgValue={this.props.setArgValue}
							arg={arg}
							argValue={argValue}
							onRunOperation={this.props.onRunOperation}
							styleConfig={this.props.styleConfig}
						/>
					);
				}
			} else if (isEnumType(argType)) {
				if (argValue.kind === 'EnumValue') {
					inlineInput = (
						<select
							style={{ color: styleConfig.colors.string2 }}
							onChange={this.props.setArgValue}
							value={argValue.value}
						>
							{argType.getValues().map((value) => (
								<option key={value.name} value={value.name}>
									{value.name}
								</option>
							))}
						</select>
					);
				} else {
					// eslint-disable-next-line no-console
					console.error(
						'arg mismatch between arg and selection',
						argType,
						argValue
					);
				}
			} else if (isInputObjectType(argType)) {
				if (argValue.kind === 'ObjectValue') {
					const fields = argType.getFields();
					if (argType.isOneOf) {
						const variantNames = Object.keys(fields).sort();
						const currentVariant =
							argValue.fields && argValue.fields[0]
								? argValue.fields[0].name.value
								: '';
						const handleVariantChange = (event) => {
							const variantName = event.target.value;
							if (!variantName) {
								this.props.setArgFields([], true);
								return;
							}
							const variantField = fields[variantName];
							const variantType = unwrapInputType(
								variantField.type
							);
							let value;
							if (isInputObjectType(variantType)) {
								const subFields = variantType.getFields();
								value = {
									kind: 'ObjectValue',
									fields: defaultInputObjectFields(
										this.props.getDefaultScalarArgValue,
										this.props.makeDefaultArg,
										this.props.parentField,
										Object.keys(subFields).map(
											(k) => subFields[k]
										)
									),
								};
							} else {
								value = this.props.getDefaultScalarArgValue(
									this.props.parentField,
									variantField,
									variantType
								);
							}
							this.props.setArgFields(
								[
									{
										kind: 'ObjectField',
										name: {
											kind: 'Name',
											value: variantName,
										},
										value,
									},
								],
								true
							);
						};
						blockInput = (
							<div
								className="graphiql-explorer-oneof"
								style={{ marginLeft: 16 }}
							>
								<div className="graphiql-explorer-oneof-row">
									<span className="graphiql-explorer-oneof-label">
										variant:
									</span>
									<select
										className="graphiql-explorer-oneof-variant"
										value={currentVariant}
										onChange={handleVariantChange}
									>
										<option value="">(choose)</option>
										{variantNames.map((name) => (
											<option key={name} value={name}>
												{name}
											</option>
										))}
									</select>
								</div>
								{currentVariant && fields[currentVariant] && (
									<InputArgView
										key={currentVariant}
										arg={fields[currentVariant]}
										parentField={this.props.parentField}
										parentTypeName={argType.name}
										selection={argValue}
										modifyFields={this.props.setArgFields}
										getDefaultScalarArgValue={
											this.props.getDefaultScalarArgValue
										}
										makeDefaultArg={
											this.props.makeDefaultArg
										}
										onRunOperation={
											this.props.onRunOperation
										}
										styleConfig={this.props.styleConfig}
										onCommit={this.props.onCommit}
										definition={this.props.definition}
									/>
								)}
							</div>
						);
					} else {
						blockInput = (
							<div style={{ marginLeft: 16 }}>
								{Object.keys(fields)
									.sort()
									.map((fieldName) => (
										<InputArgView
											key={fieldName}
											arg={fields[fieldName]}
											parentField={this.props.parentField}
											// Children of this input object
											// belong to *this* type — used by
											// the hover tooltip to render
											// `<InputObjectType>.<field>` instead
											// of inheriting the outermost
											// field's name.
											parentTypeName={argType.name}
											selection={argValue}
											modifyFields={
												this.props.setArgFields
											}
											getDefaultScalarArgValue={
												this.props
													.getDefaultScalarArgValue
											}
											makeDefaultArg={
												this.props.makeDefaultArg
											}
											onRunOperation={
												this.props.onRunOperation
											}
											styleConfig={this.props.styleConfig}
											onCommit={this.props.onCommit}
											definition={this.props.definition}
										/>
									))}
							</div>
						);
					}
				} else {
					// eslint-disable-next-line no-console
					console.error(
						'arg mismatch between arg and selection',
						argType,
						argValue
					);
				}
			}
		}

		const variablize = () => {
			const baseVariableName = arg.name;
			const conflictingNameCount = (
				this.props.definition.variableDefinitions || []
			).filter((varDef) =>
				varDef.variable.name.value.startsWith(baseVariableName)
			).length;

			let variableName;
			if (conflictingNameCount > 0) {
				variableName = `${baseVariableName}${conflictingNameCount}`;
			} else {
				variableName = baseVariableName;
			}
			const argPrintedType = arg.type.toString();
			const parsedArgType = parseType(argPrintedType);

			const base = {
				kind: 'VariableDefinition',
				variable: {
					kind: 'Variable',
					name: {
						kind: 'Name',
						value: variableName,
					},
				},
				type: parsedArgType,
				directives: [],
			};

			const variableDefinitionByName = (name) =>
				(this.props.definition.variableDefinitions || []).find(
					(varDef) => varDef.variable.name.value === name
				);

			let variable;

			const subVariableUsageCountByName = {};

			if (typeof argValue !== 'undefined' && argValue !== null) {
				const cleanedDefaultValue = visit(argValue, {
					Variable(node) {
						const varName = node.name.value;
						const varDef = variableDefinitionByName(varName);

						subVariableUsageCountByName[varName] =
							subVariableUsageCountByName[varName] + 1 || 1;

						if (!varDef) {
							return;
						}

						return varDef.defaultValue;
					},
				});

				const isNonNullable = base.type.kind === 'NonNullType';

				const unwrappedBase = isNonNullable
					? { ...base, type: base.type.type }
					: base;

				variable = {
					...unwrappedBase,
					defaultValue: cleanedDefaultValue,
				};
			} else {
				variable = base;
			}

			const newlyUnusedVariables = Object.entries(
				subVariableUsageCountByName
			)
				.filter(([, usageCount]) => usageCount < 2)
				.map(([varName]) => varName);

			if (variable) {
				const newDoc = this.props.setArgValue(variable, false);

				if (newDoc) {
					const targetOperation = newDoc.definitions.find(
						(definition) => {
							if (
								!!definition.operation &&
								!!definition.name &&
								!!definition.name.value &&
								!!this.props.definition.name &&
								!!this.props.definition.name.value
							) {
								return (
									definition.name.value ===
									this.props.definition.name.value
								);
							}
							return false;
						}
					);

					const newVariableDefinitions = [
						...(targetOperation.variableDefinitions || []),
						variable,
					].filter(
						(varDef) =>
							newlyUnusedVariables.indexOf(
								varDef.variable.name.value
							) === -1
					);

					const newOperation = {
						...targetOperation,
						variableDefinitions: newVariableDefinitions,
					};

					const existingDefs = newDoc.definitions;

					const newDefinitions = existingDefs.map(
						(existingOperation) => {
							if (targetOperation === existingOperation) {
								return newOperation;
							}
							return existingOperation;
						}
					);

					const finalDoc = {
						...newDoc,
						definitions: newDefinitions,
					};

					this.props.onCommit(finalDoc);
				}
			}
		};

		const devariablize = () => {
			if (!argValue || !argValue.name || !argValue.name.value) {
				return;
			}

			const variableName = argValue.name.value;
			const variableDefinition = (
				this.props.definition.variableDefinitions || []
			).find((varDef) => varDef.variable.name.value === variableName);

			if (!variableDefinition) {
				return;
			}

			// `defaultValue` is what the variable was extracted from. It's
			// missing when the variable was authored manually in the
			// editor, or when the arg was extracted with no value yet.
			// Synthesize a default that matches the arg's type so Inline
			// always yields an editable inline structure — for input
			// objects that's the same default-fields shape the row uses
			// when the user first checks the arg.
			let defaultValue = variableDefinition.defaultValue;
			if (defaultValue === undefined || defaultValue === null) {
				if (isInputObjectType(argType)) {
					const fields = argType.getFields();
					defaultValue = {
						kind: 'ObjectValue',
						fields: defaultInputObjectFields(
							this.props.getDefaultScalarArgValue,
							this.props.makeDefaultArg,
							this.props.parentField,
							Object.keys(fields).map((k) => fields[k])
						),
					};
				} else {
					defaultValue = this.props.getDefaultScalarArgValue(
						this.props.parentField,
						arg,
						argType
					);
				}
			}

			const newDoc = this.props.setArgValue(defaultValue, {
				commit: false,
			});

			if (newDoc) {
				const targetOperation = newDoc.definitions.find(
					(definition) =>
						definition.name.value ===
						this.props.definition.name.value
				);

				if (!targetOperation) {
					return;
				}

				let variableUseCount = 0;

				visit(targetOperation, {
					Variable(node) {
						if (node.name.value === variableName) {
							variableUseCount = variableUseCount + 1;
						}
					},
				});

				let newVariableDefinitions =
					targetOperation.variableDefinitions || [];

				if (variableUseCount < 2) {
					newVariableDefinitions = newVariableDefinitions.filter(
						(varDef) => varDef.variable.name.value !== variableName
					);
				}

				const newOperation = {
					...targetOperation,
					variableDefinitions: newVariableDefinitions,
				};

				const existingDefs = newDoc.definitions;

				const newDefinitions = existingDefs.map((existingOperation) => {
					if (targetOperation === existingOperation) {
						return newOperation;
					}
					return existingOperation;
				});

				const finalDoc = {
					...newDoc,
					definitions: newDefinitions,
				};

				this.props.onCommit(finalDoc);
			}
		};

		const isArgValueVariable = argValue && argValue.kind === 'Variable';

		// "Extract" / "Inline" — the same vocabulary IDE refactor menus
		// use. Clearer than a bare `$` glyph and explicit about
		// direction: Extract turns a literal value into a `$variable`
		// (added to the operation's variable list); Inline replaces a
		// `$variable` reference with its current literal value.
		//
		// The button is always rendered when the arg has a value (so
		// the row's layout never shifts on hover) and right-aligned so
		// changing the value's representation between literal and
		// variable doesn't move the button either.
		let variablizeTooltip;
		if (isArgValueVariable) {
			variablizeTooltip = isInputObjectType(argType)
				? 'Inline: restore the inline value so you can drill into and edit (or extract) individual nested fields'
				: 'Inline: replace this $variable with its current literal value';
		} else {
			variablizeTooltip =
				'Extract: convert this value into a $variable on the operation';
		}
		const variablizeActionButton = argValue ? (
			<button
				type="button"
				className={`graphiql-explorer-variablize${
					isArgValueVariable ? ' is-active' : ''
				}`}
				title={variablizeTooltip}
				onClick={(event) => {
					event.preventDefault();
					event.stopPropagation();

					if (isArgValueVariable) {
						devariablize();
					} else {
						variablize();
					}
				}}
			>
				{isArgValueVariable ? 'Inline' : 'Extract'}
			</button>
		) : null;

		return (
			<div
				style={{
					WebkitUserSelect: 'none',
					userSelect: 'none',
				}}
				data-arg-name={arg.name}
				data-arg-type={argType.name}
				className={`graphiql-explorer-arg-row graphiql-explorer-${arg.name}`}
			>
				<div className="graphiql-explorer-arg-row-head">
					<span
						role="button"
						tabIndex="0"
						style={{
							cursor: 'pointer',
							display: 'inline-flex',
							alignItems: 'center',
							gap: 4,
							flexShrink: 0,
						}}
						onClick={() => {
							if (!argValue) {
								this.props.addArg(true);
							} else {
								this.props.removeArg(true);
							}
						}}
						onKeyDown={(e) => {
							if (e.key === 'Enter' || e.key === ' ') {
								e.preventDefault();
								if (!argValue) {
									this.props.addArg(true);
								} else {
									this.props.removeArg(true);
								}
							}
						}}
					>
						{isInputObjectType(argType) ? (
							<span>
								{/*
								 * Open arrow only when there's an inline
								 * literal to expand. A variabilized input
								 * (`$where`) replaces the whole literal
								 * with a variable reference — there are
								 * no nested fields visible until the user
								 * clicks Inline to bring the structure
								 * back, so the closed arrow is honest
								 * about that.
								 */}
								{argValue && argValue.kind === 'ObjectValue'
									? this.props.styleConfig.arrowOpen
									: this.props.styleConfig.arrowClosed}
							</span>
						) : (
							<Checkbox
								checked={!!argValue}
								styleConfig={this.props.styleConfig}
							/>
						)}
						<ArgHoverTooltip
							argName={arg.name}
							argType={arg.type.toString()}
							parentName={
								// Nested input fields: the immediate
								// container is an `InputObjectType`, so
								// show its name (e.g.
								// `RootQueryToPostConnectionWhereArgs`).
								// Top-level args fall back to the field
								// they're declared on (e.g. `posts`).
								this.props.parentTypeName ||
								(this.props.parentField &&
									this.props.parentField.name)
							}
							description={arg.description}
						>
							<span
								style={{
									color: styleConfig.colors.attribute,
								}}
							>
								{arg.name}
								{isRequiredArgument(arg) ? '*' : ''}:
							</span>
						</ArgHoverTooltip>
					</span>
					{inlineInput}
					{variablizeActionButton}
				</div>
				{blockInput}
			</div>
		);
	}
}

export default AbstractArgView;

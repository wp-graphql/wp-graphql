import React from 'react';
import {
	defaultInputObjectFields,
	isRequiredArgument,
	unwrapInputType,
} from '../utils';
import {
	isEnumType,
	isInputObjectType,
	isScalarType,
	parseType,
	visit,
} from 'graphql';
import ScalarInput from './ScalarInput';
import InputArgView from './InputArgView';
import Checkbox from './Checkbox';

class AbstractArgView extends React.PureComponent {
	state = { displayArgActions: false };
	render() {
		const { argValue, arg, styleConfig } = this.props;
		const argType = unwrapInputType(arg.type);

		let input = null;
		if (argValue) {
			if (argValue.kind === 'Variable') {
				input = (
					<span style={{ color: styleConfig.colors.variable }}>
						${argValue.name.value}
					</span>
				);
			} else if (isScalarType(argType)) {
				if (argType.name === 'Boolean') {
					input = (
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
					input = (
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
					input = (
						<select
							style={{
								backgroundColor: 'white',
								color: styleConfig.colors.string2,
							}}
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
						input = (
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
						input = (
							<div style={{ marginLeft: 16 }}>
								{Object.keys(fields)
									.sort()
									.map((fieldName) => (
										<InputArgView
											key={fieldName}
											arg={fields[fieldName]}
											parentField={this.props.parentField}
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

			const defaultValue = variableDefinition.defaultValue;

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

		const variablizeActionButton = !this.state.displayArgActions ? null : (
			<button
				type="submit"
				className="toolbar-button"
				title={
					isArgValueVariable
						? 'Remove the variable'
						: 'Extract the current value into a GraphQL variable'
				}
				onClick={(event) => {
					event.preventDefault();
					event.stopPropagation();

					if (isArgValueVariable) {
						devariablize();
					} else {
						variablize();
					}
				}}
				style={styleConfig.styles.actionButtonStyle}
			>
				<span style={{ color: styleConfig.colors.variable }}>
					{'$'}
				</span>
			</button>
		);

		return (
			<div
				style={{
					cursor: 'pointer',
					minHeight: '16px',
					WebkitUserSelect: 'none',
					userSelect: 'none',
				}}
				data-arg-name={arg.name}
				data-arg-type={argType.name}
				className={`graphiql-explorer-${arg.name}`}
			>
				<span
					role="button"
					tabIndex="0"
					style={{ cursor: 'pointer' }}
					onClick={() => {
						const shouldAdd = !argValue;
						if (shouldAdd) {
							this.props.addArg(true);
						} else {
							this.props.removeArg(true);
						}
						this.setState({ displayArgActions: shouldAdd });
					}}
					onKeyDown={(e) => {
						if (e.key === 'Enter' || e.key === ' ') {
							e.preventDefault();
							const shouldAdd = !argValue;
							if (shouldAdd) {
								this.props.addArg(true);
							} else {
								this.props.removeArg(true);
							}
							this.setState({ displayArgActions: shouldAdd });
						}
					}}
				>
					{isInputObjectType(argType) ? (
						<span>
							{!!argValue
								? this.props.styleConfig.arrowOpen
								: this.props.styleConfig.arrowClosed}
						</span>
					) : (
						<Checkbox
							checked={!!argValue}
							styleConfig={this.props.styleConfig}
						/>
					)}
					<span
						style={{ color: styleConfig.colors.attribute }}
						title={arg.description}
						onMouseEnter={() => {
							if (
								argValue !== null &&
								typeof argValue !== 'undefined'
							) {
								this.setState({ displayArgActions: true });
							}
						}}
						onMouseLeave={() =>
							this.setState({ displayArgActions: false })
						}
					>
						{arg.name}
						{isRequiredArgument(arg) ? '*' : ''}:{' '}
						{variablizeActionButton}{' '}
					</span>{' '}
				</span>
				{input || <span />}{' '}
			</div>
		);
	}
}

export default AbstractArgView;

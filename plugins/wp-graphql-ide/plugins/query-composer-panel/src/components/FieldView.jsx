import React from 'react';
import { defaultArgs, unwrapOutputType } from '../utils';
import {
	getNamedType,
	isInterfaceType,
	isObjectType,
	isUnionType,
} from 'graphql';
import Checkbox from './Checkbox';
import ArgView from './ArgView';
import FragmentView from './FragmentView';
import AbstractView from './AbstractView';

class FieldView extends React.PureComponent {
	state = { displayFieldActions: false };

	_previousSelection;
	_addAllFieldsToSelections = (rawSubfields) => {
		const subFields = !!rawSubfields
			? Object.keys(rawSubfields).map((fieldName) => {
					return {
						kind: 'Field',
						name: { kind: 'Name', value: fieldName },
						arguments: [],
					};
				})
			: [];

		const subSelectionSet = {
			kind: 'SelectionSet',
			selections: subFields,
		};

		const nextSelections = [
			...this.props.selections.filter((selection) => {
				if (selection.kind === 'InlineFragment') {
					return true;
				}
				return selection.name.value !== this.props.field.name;
			}),
			{
				kind: 'Field',
				name: { kind: 'Name', value: this.props.field.name },
				arguments: defaultArgs(
					this.props.getDefaultScalarArgValue,
					this.props.makeDefaultArg,
					this.props.field
				),
				selectionSet: subSelectionSet,
			},
		];

		this.props.modifySelections(nextSelections);
	};

	_addFieldToSelections = () => {
		const nextSelections = [
			...this.props.selections,
			this._previousSelection || {
				kind: 'Field',
				name: { kind: 'Name', value: this.props.field.name },
				arguments: defaultArgs(
					this.props.getDefaultScalarArgValue,
					this.props.makeDefaultArg,
					this.props.field
				),
			},
		];

		this.props.modifySelections(nextSelections);
	};

	_handleUpdateSelections = (event) => {
		const selection = this._getSelection();
		if (selection && !event.altKey) {
			this._removeFieldFromSelections();
		} else {
			const fieldType = getNamedType(this.props.field.type);
			const rawSubfields =
				isObjectType(fieldType) && fieldType.getFields();

			const shouldSelectAllSubfields = !!rawSubfields && event.altKey;

			if (shouldSelectAllSubfields) {
				this._addAllFieldsToSelections(rawSubfields);
			} else {
				this._addFieldToSelections();
			}
		}
	};

	_removeFieldFromSelections = () => {
		const previousSelection = this._getSelection();
		this._previousSelection = previousSelection;
		this.props.modifySelections(
			this.props.selections.filter(
				(selection) => selection !== previousSelection
			)
		);
	};
	_getSelection = () => {
		const foundSelection = this.props.selections.find(
			(selection) =>
				selection.kind === 'Field' &&
				this.props.field.name === selection.name.value
		);
		if (!foundSelection) {
			return null;
		}
		if (foundSelection.kind === 'Field') {
			return foundSelection;
		}
		return null;
	};

	_setArguments = (argumentNodes, options) => {
		const selection = this._getSelection();
		if (!selection) {
			// eslint-disable-next-line no-console
			console.error(
				'Missing selection when setting arguments',
				argumentNodes
			);
			return;
		}
		return this.props.modifySelections(
			this.props.selections.map((s) =>
				s === selection
					? {
							alias: selection.alias,
							arguments: argumentNodes,
							directives: selection.directives,
							kind: 'Field',
							name: selection.name,
							selectionSet: selection.selectionSet,
						}
					: s
			),
			options
		);
	};

	_modifyChildSelections = (selections, options) => {
		return this.props.modifySelections(
			this.props.selections.map((selection) => {
				if (
					selection.kind === 'Field' &&
					this.props.field.name === selection.name.value
				) {
					if (selection.kind !== 'Field') {
						throw new Error('invalid selection');
					}
					return {
						alias: selection.alias,
						arguments: selection.arguments,
						directives: selection.directives,
						kind: 'Field',
						name: selection.name,
						selectionSet: {
							kind: 'SelectionSet',
							selections,
						},
					};
				}
				return selection;
			}),
			options
		);
	};

	render() {
		const { field, schema, getDefaultFieldNames, styleConfig } = this.props;
		const selection = this._getSelection();
		const type = unwrapOutputType(field.type);
		const args = field.args.sort((a, b) => a.name.localeCompare(b.name));
		let className = `graphiql-explorer-node graphiql-explorer-${field.name}`;

		if (field.isDeprecated) {
			className += ' graphiql-explorer-deprecated';
		}

		const applicableFragments =
			isObjectType(type) || isInterfaceType(type) || isUnionType(type)
				? this.props.availableFragments &&
					this.props.availableFragments[type.name]
				: null;

		const node = (
			<div className={className}>
				<span
					role="button"
					tabIndex="0"
					title={field.description}
					style={{
						cursor: 'pointer',
						display: 'inline-flex',
						alignItems: 'center',
						minHeight: '16px',
						WebkitUserSelect: 'none',
						userSelect: 'none',
					}}
					data-field-name={field.name}
					data-field-type={type.name}
					onClick={this._handleUpdateSelections}
					onKeyDown={(e) => {
						if (e.key === 'Enter' || e.key === ' ') {
							e.preventDefault();
							this._handleUpdateSelections();
						}
					}}
					onMouseEnter={() => {
						const containsMeaningfulSubselection =
							isObjectType(type) &&
							selection &&
							selection.selectionSet &&
							selection.selectionSet.selections.filter(
								(subSelection) =>
									subSelection.kind !== 'FragmentSpread'
							).length > 0;

						if (containsMeaningfulSubselection) {
							this.setState({ displayFieldActions: true });
						}
					}}
					onMouseLeave={() =>
						this.setState({ displayFieldActions: false })
					}
				>
					{isObjectType(type) ? (
						<span>
							{!!selection
								? this.props.styleConfig.arrowOpen
								: this.props.styleConfig.arrowClosed}
						</span>
					) : null}
					{isObjectType(type) ? null : (
						<Checkbox
							checked={!!selection}
							styleConfig={this.props.styleConfig}
						/>
					)}
					<span
						style={{ color: styleConfig.colors.property }}
						className="graphiql-explorer-field-view"
					>
						{field.name}
					</span>
					{!this.state.displayFieldActions ? null : (
						<button
							type="submit"
							className="toolbar-button"
							title="Extract selections into a new reusable fragment"
							onClick={(event) => {
								event.preventDefault();
								event.stopPropagation();
								const typeName = type.name;
								let newFragmentName = `${typeName}Fragment`;

								const conflictingNameCount = (
									applicableFragments || []
								).filter((fragment) => {
									return fragment.name.value.startsWith(
										newFragmentName
									);
								}).length;

								if (conflictingNameCount > 0) {
									newFragmentName = `${newFragmentName}${conflictingNameCount}`;
								}

								let childSelections = [];
								if (selection && selection.selectionSet) {
									childSelections =
										selection.selectionSet.selections;
								}

								const nextSelections = [
									{
										kind: 'FragmentSpread',
										name: {
											kind: 'Name',
											value: newFragmentName,
										},
										directives: [],
									},
								];

								const newFragmentDefinition = {
									kind: 'FragmentDefinition',
									name: {
										kind: 'Name',
										value: newFragmentName,
									},
									typeCondition: {
										kind: 'NamedType',
										name: {
											kind: 'Name',
											value: type.name,
										},
									},
									directives: [],
									selectionSet: {
										kind: 'SelectionSet',
										selections: childSelections,
									},
								};

								const newDoc = this._modifyChildSelections(
									nextSelections,
									false
								);

								if (newDoc) {
									const newDocWithFragment = {
										...newDoc,
										definitions: [
											...newDoc.definitions,
											newFragmentDefinition,
										],
									};

									this.props.onCommit(newDocWithFragment);
								} else {
									// eslint-disable-next-line no-console
									console.warn(
										'Unable to complete extractFragment operation'
									);
								}
							}}
							style={{
								...styleConfig.styles.actionButtonStyle,
							}}
						>
							<span>{'…'}</span>
						</button>
					)}
				</span>
				{selection && args.length ? (
					<div
						style={{ marginLeft: 16 }}
						className="graphiql-explorer-graphql-arguments"
					>
						{args.map((arg) => (
							<ArgView
								key={arg.name}
								parentField={field}
								arg={arg}
								selection={selection}
								modifyArguments={this._setArguments}
								getDefaultScalarArgValue={
									this.props.getDefaultScalarArgValue
								}
								makeDefaultArg={this.props.makeDefaultArg}
								onRunOperation={this.props.onRunOperation}
								styleConfig={this.props.styleConfig}
								onCommit={this.props.onCommit}
								definition={this.props.definition}
							/>
						))}
					</div>
				) : null}
			</div>
		);

		if (
			selection &&
			(isObjectType(type) || isInterfaceType(type) || isUnionType(type))
		) {
			const fields = isUnionType(type) ? {} : type.getFields();
			let childSelections = [];
			if (selection && selection.selectionSet) {
				childSelections = selection.selectionSet.selections;
			}
			return (
				<div className={`graphiql-explorer-${field.name}`}>
					{node}
					<div style={{ marginLeft: 16 }}>
						{!!applicableFragments
							? applicableFragments.map((fragment) => {
									const fragmentType = schema.getType(
										fragment.typeCondition.name.value
									);
									const fragmentName = fragment.name.value;
									return !fragmentType ? null : (
										<FragmentView
											key={fragmentName}
											fragment={fragment}
											selections={childSelections}
											modifySelections={
												this._modifyChildSelections
											}
											schema={schema}
											styleConfig={this.props.styleConfig}
											onCommit={this.props.onCommit}
										/>
									);
								})
							: null}
						{Object.keys(fields)
							.sort()
							.map((fieldName) => (
								<FieldView
									key={fieldName}
									field={fields[fieldName]}
									selections={childSelections}
									modifySelections={
										this._modifyChildSelections
									}
									schema={schema}
									getDefaultFieldNames={getDefaultFieldNames}
									getDefaultScalarArgValue={
										this.props.getDefaultScalarArgValue
									}
									makeDefaultArg={this.props.makeDefaultArg}
									onRunOperation={this.props.onRunOperation}
									styleConfig={this.props.styleConfig}
									onCommit={this.props.onCommit}
									definition={this.props.definition}
									availableFragments={
										this.props.availableFragments
									}
								/>
							))}
						{isInterfaceType(type) || isUnionType(type)
							? schema
									.getPossibleTypes(type)
									.map((possibleType) => (
										<AbstractView
											key={possibleType.name}
											implementingType={possibleType}
											selections={childSelections}
											modifySelections={
												this._modifyChildSelections
											}
											schema={schema}
											getDefaultFieldNames={
												getDefaultFieldNames
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
									))
							: null}
					</div>
				</div>
			);
		}
		return node;
	}
}

export default FieldView;

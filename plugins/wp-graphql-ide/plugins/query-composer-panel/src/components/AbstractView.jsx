import React from 'react';
import Checkbox from './Checkbox';
import FieldView from './FieldView';

class AbstractView extends React.PureComponent {
	_previousSelection;
	_addFragment = () => {
		this.props.modifySelections([
			...this.props.selections,
			this._previousSelection || {
				kind: 'InlineFragment',
				typeCondition: {
					kind: 'NamedType',
					name: {
						kind: 'Name',
						value: this.props.implementingType.name,
					},
				},
				selectionSet: {
					kind: 'SelectionSet',
					selections: this.props
						.getDefaultFieldNames(this.props.implementingType)
						.map((fieldName) => ({
							kind: 'Field',
							name: { kind: 'Name', value: fieldName },
						})),
				},
			},
		]);
	};
	_removeFragment = () => {
		const thisSelection = this._getSelection();
		this._previousSelection = thisSelection;
		this.props.modifySelections(
			this.props.selections.filter((s) => s !== thisSelection)
		);
	};
	_getSelection = () => {
		const foundSelection = this.props.selections.find(
			(selection) =>
				selection.kind === 'InlineFragment' &&
				selection.typeCondition &&
				this.props.implementingType.name ===
					selection.typeCondition.name.value
		);
		if (!foundSelection) {
			return null;
		}
		if (foundSelection.kind === 'InlineFragment') {
			return foundSelection;
		}
		return null;
	};

	_modifyChildSelections = (selections, options) => {
		const thisSelection = this._getSelection();
		return this.props.modifySelections(
			this.props.selections.map((selection) => {
				if (selection === thisSelection) {
					return {
						directives: selection.directives,
						kind: 'InlineFragment',
						typeCondition: {
							kind: 'NamedType',
							name: {
								kind: 'Name',
								value: this.props.implementingType.name,
							},
						},
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
		const { implementingType, schema, getDefaultFieldNames, styleConfig } =
			this.props;
		const selection = this._getSelection();
		const fields = implementingType.getFields();
		let childSelections = [];
		if (selection && selection.selectionSet) {
			childSelections = selection.selectionSet.selections;
		}

		return (
			<div className={`graphiql-explorer-${implementingType.name}`}>
				<span
					role="button"
					tabIndex="0"
					style={{ cursor: 'pointer' }}
					onClick={
						selection ? this._removeFragment : this._addFragment
					}
					onKeyDown={(e) => {
						if (e.key === 'Enter' || e.key === ' ') {
							e.preventDefault();
							if (selection) {
								this._removeFragment();
							} else {
								this._addFragment();
							}
						}
					}}
				>
					<Checkbox
						checked={!!selection}
						styleConfig={this.props.styleConfig}
					/>
					<span style={{ color: styleConfig.colors.atom }}>
						{this.props.implementingType.name}
					</span>
				</span>
				{selection ? (
					<div style={{ marginLeft: 16 }}>
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
									onCommit={this.props.onCommit}
									styleConfig={this.props.styleConfig}
									definition={this.props.definition}
									availableFragments={
										this.props.availableFragments
									}
								/>
							))}
					</div>
				) : null}
			</div>
		);
	}
}

export default AbstractView;

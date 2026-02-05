import React from 'react';
import { coerceArgValue, unwrapInputType } from '../utils';
import { isInputObjectType, isLeafType } from 'graphql';
import AbstractArgView from './AbstractArgView';

class ArgView extends React.PureComponent {
	_previousArgSelection;
	_getArgSelection = () => {
		const { selection } = this.props;

		return (selection.arguments || []).find(
			(arg) => arg.name.value === this.props.arg.name
		);
	};
	_removeArg = (commit) => {
		const { selection } = this.props;
		const argSelection = this._getArgSelection();
		this._previousArgSelection = argSelection;
		return this.props.modifyArguments(
			(selection.arguments || []).filter((arg) => arg !== argSelection),
			commit
		);
	};
	_addArg = (commit) => {
		const {
			selection,
			getDefaultScalarArgValue,
			makeDefaultArg,
			parentField,
			arg,
		} = this.props;
		const argType = unwrapInputType(arg.type);

		let argSelection = null;
		if (this._previousArgSelection) {
			argSelection = this._previousArgSelection;
		} else if (isInputObjectType(argType)) {
			const fields = argType.getFields();
			argSelection = {
				kind: 'Argument',
				name: { kind: 'Name', value: arg.name },
				value: {
					kind: 'ObjectValue',
					fields: defaultInputObjectFields(
						getDefaultScalarArgValue,
						makeDefaultArg,
						parentField,
						Object.keys(fields).map((k) => fields[k])
					),
				},
			};
		} else if (isLeafType(argType)) {
			argSelection = {
				kind: 'Argument',
				name: { kind: 'Name', value: arg.name },
				value: getDefaultScalarArgValue(parentField, arg, argType),
			};
		}

		if (!argSelection) {
			console.error('Unable to add arg for argType', argType);
			return null;
		}
		return this.props.modifyArguments(
			[...(selection.arguments || []), argSelection],
			commit
		);
	};
	_setArgValue = (event, options) => {
		let settingToNull = false;
		let settingToVariable = false;
		let settingToLiteralValue = false;
		try {
			if (event.kind === 'VariableDefinition') {
				settingToVariable = true;
			} else if (event === null || typeof event === 'undefined') {
				settingToNull = true;
			} else if (typeof event.kind === 'string') {
				settingToLiteralValue = true;
			}
		} catch (e) {}
		const { selection } = this.props;
		const argSelection = this._getArgSelection();
		if (!argSelection && !settingToVariable) {
			console.error('missing arg selection when setting arg value');
			return;
		}
		const argType = unwrapInputType(this.props.arg.type);

		const handleable =
			isLeafType(argType) ||
			settingToVariable ||
			settingToNull ||
			settingToLiteralValue;

		if (!handleable) {
			console.warn(
				'Unable to handle non leaf types in ArgView._setArgValue'
			);
			return;
		}

		let targetValue;
		let value;

		if (event === null || typeof event === 'undefined') {
			value = null;
		} else if (event.target && typeof event.target.value === 'string') {
			targetValue = event.target.value;
			value = coerceArgValue(argType, targetValue);
		} else if (!event.target && event.kind === 'VariableDefinition') {
			targetValue = event;
			value = targetValue.variable;
		} else if (typeof event.kind === 'string') {
			value = event;
		}

		return this.props.modifyArguments(
			(selection.arguments || []).map((a) =>
				a === argSelection
					? {
							...a,
							value,
						}
					: a
			),
			options
		);
	};

	_setArgFields = (fields, commit) => {
		const { selection } = this.props;
		const argSelection = this._getArgSelection();
		if (!argSelection) {
			console.error('missing arg selection when setting arg value');
			return;
		}

		return this.props.modifyArguments(
			(selection.arguments || []).map((a) =>
				a === argSelection
					? {
							...a,
							value: {
								kind: 'ObjectValue',
								fields,
							},
						}
					: a
			),
			commit
		);
	};

	render() {
		const { arg, parentField } = this.props;
		const argSelection = this._getArgSelection();

		return (
			<AbstractArgView
				argValue={argSelection ? argSelection.value : null}
				arg={arg}
				parentField={parentField}
				addArg={this._addArg}
				removeArg={this._removeArg}
				setArgFields={this._setArgFields}
				setArgValue={this._setArgValue}
				getDefaultScalarArgValue={this.props.getDefaultScalarArgValue}
				makeDefaultArg={this.props.makeDefaultArg}
				onRunOperation={this.props.onRunOperation}
				styleConfig={this.props.styleConfig}
				onCommit={this.props.onCommit}
				definition={this.props.definition}
			/>
		);
	}
}

export default ArgView;

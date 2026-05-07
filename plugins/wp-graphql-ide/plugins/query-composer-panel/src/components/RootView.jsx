import React from 'react';
import {
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Tooltip,
} from '@wordpress/components';
import {
	Icon,
	chevronDown,
	chevronRight,
	moreVertical,
} from '@wordpress/icons';
import { canRunOperation, capitalize, isRunShortcut } from '../utils';
import FieldView from './FieldView';

class RootView extends React.PureComponent {
	_previousOperationDef;

	_modifySelections = (selections, options) => {
		let operationDef = this.props.definition;

		if (
			operationDef.selectionSet.selections.length === 0 &&
			this._previousOperationDef
		) {
			operationDef = this._previousOperationDef;
		}

		let newOperationDef;

		if (operationDef.kind === 'FragmentDefinition') {
			newOperationDef = {
				...operationDef,
				selectionSet: {
					...operationDef.selectionSet,
					selections,
				},
			};
		} else if (operationDef.kind === 'OperationDefinition') {
			let cleanedSelections = selections.filter((selection) => {
				return !(
					selection.kind === 'Field' &&
					selection.name.value === '__typename'
				);
			});

			if (cleanedSelections.length === 0) {
				cleanedSelections = [
					{
						kind: 'Field',
						name: {
							kind: 'Name',
							value: '__typename ## Placeholder value',
						},
					},
				];
			}

			newOperationDef = {
				...operationDef,
				selectionSet: {
					...operationDef.selectionSet,
					selections: cleanedSelections,
				},
			};
		}

		return this.props.onEdit(newOperationDef, options);
	};

	_onOperationRename = (event) =>
		this.props.onOperationRename(event.target.value);

	_handlePotentialRun = (event) => {
		if (
			isRunShortcut(event) &&
			canRunOperation(this.props.definition.kind)
		) {
			this.props.onRunOperation(this.props.name);
		}
	};

	_rootViewElId = () => {
		const { operationType, name } = this.props;
		const rootViewElId = `${operationType}-${name || 'unknown'}`;
		return rootViewElId;
	};

	componentDidMount() {
		const rootViewElId = this._rootViewElId();

		this.props.onMount(rootViewElId);
	}

	render() {
		const { operationType, definition, schema, getDefaultFieldNames } =
			this.props;
		const rootViewElId = this._rootViewElId();

		const fields = this.props.fields || {};
		const operationDef = definition;
		const selections = operationDef.selectionSet.selections;

		const { canCollapse, isCollapsed, onToggleCollapsed } = this.props;

		return (
			// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions
			<div
				id={rootViewElId}
				role="group"
				tabIndex="0"
				onKeyDown={this._handlePotentialRun}
				className={`graphiql-explorer-operation${this.props.isLast ? ' is-last' : ''}${isCollapsed ? ' is-collapsed' : ''}`}
			>
				<div className="graphiql-operation-title-bar">
					{canCollapse ? (
						<Tooltip
							text={
								isCollapsed
									? 'Expand operation'
									: 'Collapse operation'
							}
						>
							<button
								type="button"
								className="graphiql-operation-toggle"
								aria-label={
									isCollapsed
										? 'Expand operation'
										: 'Collapse operation'
								}
								aria-expanded={!isCollapsed}
								onClick={onToggleCollapsed}
							>
								<Icon
									icon={
										isCollapsed ? chevronRight : chevronDown
									}
									size={16}
								/>
							</button>
						</Tooltip>
					) : null}
					<span className="graphiql-operation-keyword">
						{operationType}
					</span>
					<input
						className="graphiql-operation-name"
						autoComplete="false"
						placeholder={`${capitalize(operationType)} Name`}
						/* Coerce to '' so the input stays controlled when
						   the active document has an anonymous query
						   (`{ posts { id } }`). Otherwise React treats
						   `undefined` as uncontrolled and the input keeps
						   the previous tab's operation name on screen. */
						value={this.props.name || ''}
						onKeyDown={this._handlePotentialRun}
						onChange={this._onOperationRename}
					/>
					{!!this.props.onTypeName ? (
						<span className="graphiql-operation-typename">
							{`on ${this.props.onTypeName}`}
						</span>
					) : null}
					<DropdownMenu
						icon={moreVertical}
						label="Operation actions"
						toggleProps={{
							className: 'graphiql-operation-action',
							size: 'small',
							showTooltip: true,
						}}
						popoverProps={{ placement: 'bottom-end' }}
					>
						{({ onClose: closeMenu }) => (
							<MenuGroup>
								<MenuItem
									onClick={() => {
										closeMenu();
										this.props.onOperationClone();
									}}
								>
									Duplicate
								</MenuItem>
								<MenuItem
									isDestructive
									onClick={() => {
										closeMenu();
										this.props.onOperationDestroy();
									}}
								>
									Remove
								</MenuItem>
							</MenuGroup>
						)}
					</DropdownMenu>
				</div>

				{isCollapsed ? null : (
					<div className="graphiql-operation-body">
						{Object.keys(fields)
							.sort()
							.map((fieldName) => (
								<FieldView
									key={fieldName}
									field={fields[fieldName]}
									parentTypeName={this.props.rootTypeName}
									selections={selections}
									modifySelections={this._modifySelections}
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
					</div>
				)}
			</div>
		);
	}
}

export default RootView;

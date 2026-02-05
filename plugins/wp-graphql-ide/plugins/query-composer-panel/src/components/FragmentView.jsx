import React from 'react';
import Checkbox from './Checkbox';

class FragmentView extends React.PureComponent {
	_previousSelection;
	_addFragment = () => {
		this.props.modifySelections([
			...this.props.selections,
			this._previousSelection || {
				kind: 'FragmentSpread',
				name: this.props.fragment.name,
			},
		]);
	};
	_removeFragment = () => {
		const thisSelection = this._getSelection();
		this._previousSelection = thisSelection;
		this.props.modifySelections(
			this.props.selections.filter((s) => {
				const isTargetSelection =
					s.kind === 'FragmentSpread' &&
					s.name.value === this.props.fragment.name.value;

				return !isTargetSelection;
			})
		);
	};
	_getSelection = () => {
		const foundSelection = this.props.selections.find((selection) => {
			return (
				selection.kind === 'FragmentSpread' &&
				selection.name.value === this.props.fragment.name.value
			);
		});

		return foundSelection;
	};

	render() {
		const { styleConfig } = this.props;
		const selection = this._getSelection();
		return (
			<div
				className={`graphiql-explorer-${this.props.fragment.name.value}`}
			>
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
					<span
						style={{ color: styleConfig.colors.def }}
						className={`graphiql-explorer-${this.props.fragment.name.value}`}
					>
						{this.props.fragment.name.value}
					</span>
				</span>
			</div>
		);
	}
}

export default FragmentView;

import React from 'react';
import ErrorBoundary from './ErrorBoundary';
import ExplorerView from './ExplorerView';

class ExplorerWrapper extends React.PureComponent {
	render() {
		return (
			<div
				className="docExplorerWrap"
				aria-label="Query Composer"
				style={{
					height: '100%',
					width: this.props.width,
					minWidth: this.props.width,
					zIndex: 7,
					display: this.props.explorerIsOpen ? 'flex' : 'none',
					flexDirection: 'column',
					overflow: 'hidden',
				}}
			>
				<div className="doc-explorer-title-bar">
					<div className="doc-explorer-title">{this.props.title}</div>
					<div className="doc-explorer-rhs">
						<div
							className="docExplorerHide"
							onClick={this.props.onToggleExplorer}
						>
							{'\u2715'}
						</div>
					</div>
				</div>
				<div
					className="doc-explorer-contents"
					style={{
						padding: '0px',
						overflowY: 'unset',
					}}
				>
					<ErrorBoundary>
						<ExplorerView {...this.props} />
					</ErrorBoundary>
				</div>
			</div>
		);
	}
}

export default ExplorerWrapper;

import React from 'react';
import ErrorBoundary from './ErrorBoundary';
import ExplorerView from './ExplorerView';

class ExplorerWrapper extends React.PureComponent {
	render() {
		return (
			<div className="docExplorerWrap" aria-label="Query Composer">
				<div className="doc-explorer-contents">
					<ErrorBoundary>
						<ExplorerView {...this.props} />
					</ErrorBoundary>
				</div>
			</div>
		);
	}
}

export default ExplorerWrapper;

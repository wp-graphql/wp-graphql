import React from 'react';

class ErrorBoundary extends React.Component {
	state = { hasError: false, error: null, errorInfo: null };

	componentDidCatch(error, errorInfo) {
		this.setState({ hasError: true, error, errorInfo });
		console.error('Error in component', error, errorInfo);
	}

	render() {
		if (this.state.hasError) {
			return (
				<div style={{ padding: 18, fontFamily: 'sans-serif' }}>
					<div>Something went wrong</div>
					<details style={{ whiteSpace: 'pre-wrap' }}>
						{this.state.error ? this.state.error.toString() : null}
						<br />
						{this.state.errorInfo
							? this.state.errorInfo.componentStack
							: null}
					</details>
				</div>
			);
		}
		return this.props.children;
	}
}

export default ErrorBoundary;

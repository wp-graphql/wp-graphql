import ExplorerWrapper from './ExplorerWrapper';
import QueryBuilder from './QueryBuilder';
import ErrorBoundary from './ErrorBoundary';
import { memoizeParseQuery } from '../utils/utils';
import { Spin, Alert } from 'antd';
const { useAppContext } = wpGraphiQL;
const { useState, useEffect } = wp.element;

/**
 * Error message component for when schema fails to load.
 *
 * Provides helpful context, especially for URL mismatch issues that commonly
 * occur with local development tools like LocalWP.
 */
const SchemaErrorMessage = ({ error }) => {
	const hasUrlMismatch = error?.urlMismatch;
	const isAuthError = error?.type === 'auth';

	return (
		<div
			className="graphiql-container"
			style={{ padding: '20px', overflow: 'auto' }}
		>
			<Alert
				type="error"
				message="Schema Unavailable"
				description={
					<div>
						<p style={{ margin: '0 0 12px 0' }}>{error?.message}</p>

						{hasUrlMismatch && (
							<div
								style={{
									background: '#fff7e6',
									border: '1px solid #ffd591',
									borderRadius: '4px',
									padding: '12px',
									marginBottom: '12px',
								}}
							>
								<strong style={{ color: '#d46b08' }}>
									URL Mismatch Detected
								</strong>
								<p
									style={{
										margin: '8px 0 0 0',
										fontSize: '12px',
									}}
								>
									You are accessing the admin at{' '}
									<code>
										{error.urlMismatch.currentOrigin}
									</code>{' '}
									but the GraphQL endpoint is configured for{' '}
									<code>
										{error.urlMismatch.endpointOrigin}
									</code>
									.
								</p>
								<p
									style={{
										margin: '8px 0 0 0',
										fontSize: '12px',
									}}
								>
									This commonly occurs with local development
									tools (like LocalWP) when WordPress is
									configured for a custom domain but accessed
									via localhost.
								</p>
							</div>
						)}

						{isAuthError && (
							<div style={{ fontSize: '12px', marginTop: '8px' }}>
								<p style={{ margin: '0 0 8px 0' }}>
									<strong>Possible causes:</strong>
								</p>
								<ul
									style={{ margin: '0', paddingLeft: '20px' }}
								>
									<li>
										Session expired - try refreshing the
										page
									</li>
									<li>
										Cookie authentication failed - ensure
										you're logged in
									</li>
									{hasUrlMismatch && (
										<li>
											URL mismatch preventing cookie/nonce
											validation (see above)
										</li>
									)}
								</ul>
							</div>
						)}
					</div>
				}
				showIcon
			/>
		</div>
	);
};

/**
 * Establish some markup to wrap the Explorer with. Sets up some dimension and styling constraints.
 *
 * @param schema
 * @param schemaError
 * @param schemaLoading
 * @param children
 * @returns {JSX.Element}
 * @constructor
 */
const Wrapper = ({ schema, schemaError, schemaLoading, children }) => {
	if (schemaLoading) {
		return (
			<div
				className="graphiql-container"
				style={{
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center',
					height: '100%',
				}}
			>
				<Spin tip="Loading schema..." />
			</div>
		);
	}

	if (schemaError) {
		return <SchemaErrorMessage error={schemaError} />;
	}

	if (!schema) {
		return (
			// Wrap in graphiql-container div so error message matches that in the Documentation Explorer
			<div className="graphiql-container">
				<div className="error-container">No Schema Available</div>
			</div>
		);
	}

	return (
		<div
			style={{
				fontSize: 12,
				textOverflow: 'ellipsis',
				whiteSpace: 'nowrap',
				margin: 0,
				padding: 0,
				fontFamily:
					'Consolas, Inconsolata, "Droid Sans Mono", Monaco, monospace',
				display: 'flex',
				flexDirection: 'column',
				height: '100%',
			}}
			className="graphiql-explorer-root"
		>
			{children}
		</div>
	);
};

/**
 * This is the main Explorer component that adds the "Query Builder" UI to GraphiQL
 *
 * @returns {JSX.Element}
 * @constructor
 */
const Explorer = (props) => {
	const { query, setQuery } = props;
	const { schema, schemaError, schemaLoading } = useAppContext();

	const [document, setDocument] = useState(null);

	useEffect(() => {
		// When the component mounts, parse the query and keep it in memory
		const parsedQuery = memoizeParseQuery(query);

		// Update the document, if needed
		if (document !== parsedQuery) {
			setDocument(parsedQuery);
		}
	}, [query]);

	return (
		<>
			<ExplorerWrapper>
				<ErrorBoundary>
					<Wrapper
						schema={schema}
						schemaError={schemaError}
						schemaLoading={schemaLoading}
					>
						<QueryBuilder
							schema={schema}
							query={query}
							onEdit={(query) => {
								// When the Query Builder makes changes
								// to the query, this callback from AppContext
								// is executed
								setQuery(query);
							}}
						/>
					</Wrapper>
				</ErrorBoundary>
			</ExplorerWrapper>
		</>
	);
};

export default Explorer;

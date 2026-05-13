import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

// `DEBUG` is the conventional log-level label kept untranslated to match
// the upstream debug.type values (`GRAPHQL_DEBUG`, etc) that this strips.
const typeLabel = (type) => {
	if (!type) {
		return 'DEBUG';
	}
	return String(type).replace(/^GRAPHQL_/, '');
};

const DebugMessage = ({ entry }) => {
	const [showStack, setShowStack] = useState(false);
	const stack = Array.isArray(entry.stack) ? entry.stack : [];
	return (
		<li className="wpgraphql-ide-debug-entry">
			<div className="wpgraphql-ide-debug-entry-header">
				<span className="wpgraphql-ide-debug-type">
					{typeLabel(entry.type)}
				</span>
				<span className="wpgraphql-ide-debug-message">
					{entry.message || __('(no message)', 'wpgraphql-ide')}
				</span>
			</div>
			{stack.length > 0 && (
				<>
					<button
						type="button"
						className="wpgraphql-ide-debug-stack-toggle"
						aria-expanded={showStack}
						onClick={() => setShowStack((s) => !s)}
					>
						<span
							className={`wpgraphql-ide-debug-stack-chevron${showStack ? ' is-open' : ''}`}
						>
							›
						</span>
						{showStack
							? sprintf(
									/* translators: %d: number of stack frames being hidden */
									__('Hide stack (%d)', 'wpgraphql-ide'),
									stack.length
								)
							: sprintf(
									/* translators: %d: number of stack frames available */
									__('Show stack (%d)', 'wpgraphql-ide'),
									stack.length
								)}
					</button>
					{showStack && (
						<ol className="wpgraphql-ide-debug-stack">
							{stack.map((frame, i) => (
								<li key={i}>
									<code>{frame}</code>
								</li>
							))}
						</ol>
					)}
				</>
			)}
		</li>
	);
};

export const DebugExtensionTab = ({ data }) => {
	if (!Array.isArray(data) || data.length === 0) {
		return (
			<p className="wpgraphql-ide-extensions-empty">
				{__('No debug messages in the last response.', 'wpgraphql-ide')}
			</p>
		);
	}

	return (
		<ul className="wpgraphql-ide-debug-list">
			{data.map((entry, i) => (
				<DebugMessage
					key={`${entry.message || 'msg'}-${i}`}
					entry={entry}
				/>
			))}
		</ul>
	);
};

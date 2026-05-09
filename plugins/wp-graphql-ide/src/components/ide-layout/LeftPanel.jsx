import React from 'react';
import { Button, ResizableBox } from '@wordpress/components';
import { Icon, close } from '@wordpress/icons';
import { useResizeReporter } from '../ResizeOverlay';

/**
 * Resizable left-side panel that hosts either the Query Composer or
 * the Document Settings drawer alongside the GraphQL editor. Handles
 * the resize chrome and the panel-header bar (title + close button)
 * uniformly so the two consumers don't repeat themselves.
 *
 * @param {Object}          props
 * @param {string}          props.title      - Header title shown to the user.
 * @param {string}          props.className  - Outer ResizableBox className (e.g. `wpgraphql-ide-query-composer-inline`).
 * @param {number}          props.width      - Current width in px.
 * @param {Function}        props.onResize   - Called with the new px width on resize stop.
 * @param {number}          [props.minWidth] - Resize lower bound.
 * @param {number}          [props.maxWidth] - Resize upper bound.
 * @param {Function}        props.onClose    - Click handler for the header close button.
 * @param {string}          props.closeLabel - aria-label for the close button.
 * @param {React.ReactNode} props.children   - Panel body content.
 */
export function LeftPanel({
	title,
	className,
	width,
	onResize,
	minWidth = 200,
	maxWidth = 600,
	onClose,
	closeLabel,
	children,
}) {
	const reporter = useResizeReporter(title);
	return (
		<ResizableBox
			size={{ width, height: '100%' }}
			minWidth={minWidth}
			maxWidth={maxWidth}
			enable={{ top: false, right: true, bottom: false, left: false }}
			onResizeStart={reporter.reportStart}
			onResize={reporter.reportResize}
			onResizeStop={(e, d, elt) => {
				reporter.reportStop();
				onResize(elt.offsetWidth);
			}}
			className={className}
		>
			{reporter.indicator}
			<div className="wpgraphql-ide-panel-header">
				<span className="wpgraphql-ide-panel-title">{title}</span>
				<div className="wpgraphql-ide-panel-header-spacer" />
				<Button
					className="wpgraphql-ide-panel-close"
					onClick={onClose}
					aria-label={closeLabel}
					size="small"
				>
					<Icon icon={close} size={20} />
				</Button>
			</div>
			{children}
		</ResizableBox>
	);
}

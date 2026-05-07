import React, { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

// Render a tooltip in document.body so it escapes the composer panel's
// overflow + stacking context — the GraphQL editor does the same trick
// via `tooltips({ parent: document.body })`. Reusing those CSS classes
// keeps the visual identical: 460px max-width, monospace signature,
// hairline divider, prose description.
//
// Showing/hiding is debounced lightly so a fast cross between sibling
// rows doesn't flicker the tooltip in and out.

// 700ms matches the IDE-tooltip cadence in VS Code / IntelliJ — long
// enough that flying past a row doesn't pop the tooltip and block the
// chip the user was about to click, short enough that an intentional
// hover still feels responsive. Hide latency stays small so leaving
// the row clears the surface immediately.
const SHOW_DELAY_MS = 700;
const HIDE_DELAY_MS = 80;

function positionFor(rect) {
	return {
		top: rect.bottom + window.scrollY + 4,
		left: rect.left + window.scrollX,
	};
}

/**
 * @param {Object}          props
 * @param {React.ReactNode} props.children      - Element the tooltip anchors on.
 * @param {string}          [props.argName]     - Argument or field name (rendered colored).
 * @param {string}          [props.argType]     - Printed type (e.g. `[ID]`, `Int!`).
 * @param {string}          [props.parentName]  - Optional containing type/field name (`Type.argName: Type`).
 * @param {string}          [props.description] - Description prose below the divider.
 */
export default function ArgHoverTooltip({
	children,
	argName,
	argType,
	parentName,
	description,
}) {
	const [visible, setVisible] = useState(false);
	const [coords, setCoords] = useState({ top: 0, left: 0 });
	const wrapperRef = useRef(null);
	const showTimer = useRef(null);
	const hideTimer = useRef(null);

	const clearTimers = useCallback(() => {
		if (showTimer.current) {
			window.clearTimeout(showTimer.current);
			showTimer.current = null;
		}
		if (hideTimer.current) {
			window.clearTimeout(hideTimer.current);
			hideTimer.current = null;
		}
	}, []);

	const handleEnter = useCallback(() => {
		clearTimers();
		showTimer.current = window.setTimeout(() => {
			if (wrapperRef.current) {
				setCoords(
					positionFor(wrapperRef.current.getBoundingClientRect())
				);
				setVisible(true);
			}
		}, SHOW_DELAY_MS);
	}, [clearTimers]);

	const handleLeave = useCallback(() => {
		clearTimers();
		hideTimer.current = window.setTimeout(() => {
			setVisible(false);
		}, HIDE_DELAY_MS);
	}, [clearTimers]);

	useEffect(() => {
		return () => clearTimers();
	}, [clearTimers]);

	const hasSignature = !!(argName && argType);
	const hasContent = hasSignature || !!description;

	return (
		<span
			ref={wrapperRef}
			onMouseEnter={handleEnter}
			onMouseLeave={handleLeave}
			style={{ display: 'inline-flex', alignItems: 'center' }}
		>
			{children}
			{hasContent &&
				visible &&
				createPortal(
					<div
						className="cm-tooltip wpgraphql-ide-hover"
						style={{
							position: 'absolute',
							top: coords.top,
							left: coords.left,
							zIndex: 100000,
							pointerEvents: 'none',
						}}
					>
						<div className="cm-tooltip-section">
							{hasSignature && (
								<div className="wpgraphql-ide-hover-signature">
									{parentName && (
										<>
											<span className="wpgraphql-ide-hov-type">
												{parentName}
											</span>
											<span className="wpgraphql-ide-hov-punct">
												.
											</span>
										</>
									)}
									<span className="wpgraphql-ide-hov-arg">
										{argName}
									</span>
									<span className="wpgraphql-ide-hov-punct">
										:{' '}
									</span>
									<span className="wpgraphql-ide-hov-type">
										{argType}
									</span>
								</div>
							)}
							{description && (
								<div className="wpgraphql-ide-hover-description">
									{description}
								</div>
							)}
						</div>
					</div>,
					document.body
				)}
		</span>
	);
}

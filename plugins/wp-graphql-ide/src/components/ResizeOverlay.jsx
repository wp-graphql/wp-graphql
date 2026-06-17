import React, {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from 'react';

const ResizeContext = createContext({ active: false });

/**
 * Provider that flips on a global "any pane is being dragged" flag.
 * Each pane renders its own indicator inside its DOM, so positioning is
 * naturally relative to that pane (no rect math, no overlap).
 *
 * @param {Object}          props
 * @param {React.ReactNode} props.children
 */
export function ResizeOverlayProvider({ children }) {
	const [active, setActive] = useState(false);
	const dragCount = useRef(0);

	const start = useCallback(() => {
		dragCount.current += 1;
		setActive(true);
	}, []);
	const stop = useCallback(() => {
		dragCount.current = Math.max(0, dragCount.current - 1);
		if (dragCount.current === 0) {
			setActive(false);
		}
	}, []);

	const ctx = useMemo(() => ({ active, start, stop }), [active, start, stop]);
	return (
		<ResizeContext.Provider value={ctx}>{children}</ResizeContext.Provider>
	);
}

/**
 * Indicator pill rendered inside a ResizableBox. Reads the parent
 * element's live dimensions via ResizeObserver, so a sibling drag that
 * shrinks this pane updates this pane's pill in real time.
 *
 * @param {Object} props
 * @param {string} props.label - Display name for this pane.
 */
function ResizeIndicator({ label }) {
	const { active } = useContext(ResizeContext);
	const ref = useRef(null);
	const [dim, setDim] = useState({ w: 0, h: 0 });

	useEffect(() => {
		if (!active) {
			return undefined;
		}
		const node = ref.current?.parentElement;
		if (!node) {
			return undefined;
		}
		const update = () => {
			setDim({ w: node.offsetWidth, h: node.offsetHeight });
		};
		update();
		// eslint-disable-next-line no-undef
		const ro = new ResizeObserver(update);
		ro.observe(node);
		return () => ro.disconnect();
	}, [active]);

	return (
		<div
			ref={ref}
			className={`wpgraphql-ide-resize-overlay${active ? ' is-active' : ''}`}
			role="status"
			aria-live="polite"
			aria-hidden={!active}
			aria-label={label}
		>
			{Math.round(dim.w)}px × {Math.round(dim.h)}px
		</div>
	);
}

/**
 * Returns ResizableBox-compatible reporters plus an `indicator` JSX
 * element that the consumer renders inside its ResizableBox so it sits
 * at the pane's top-right corner.
 *
 * @param {string} label - Display name for this pane.
 */
export function useResizeReporter(label) {
	const ctx = useContext(ResizeContext);
	const reportStart = useCallback(() => {
		if (ctx.start) {
			ctx.start();
		}
	}, [ctx]);
	const reportResize = useCallback(() => {
		// No-op; ResizeObserver inside the indicator handles live updates.
	}, []);
	const reportStop = useCallback(() => {
		if (ctx.stop) {
			ctx.stop();
		}
	}, [ctx]);

	const indicator = useMemo(() => <ResizeIndicator label={label} />, [label]);

	return { reportStart, reportResize, reportStop, indicator };
}

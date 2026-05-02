import React, {
	createContext,
	useCallback,
	useContext,
	useMemo,
	useState,
} from 'react';
import { Button, Modal, TextControl } from '@wordpress/components';

/**
 * IDE-wide dialog system.
 *
 * Replaces `window.confirm`/`window.alert`/`window.prompt` so the IDE
 * has a single look-and-feel for confirmations across the app and so
 * 3rd-party extensions can call `useDialog().confirm(...)` instead of
 * shipping their own modals.
 */

// The IDE ships as two separate webpack entry bundles (`wpgraphql-ide`
// and `wpgraphql-ide-render`). If each bundle defined its own
// `createContext(null)` we'd end up with two distinct Context objects:
// the Provider in the render bundle would never match consumers
// (e.g. SavedQueriesPanel) that the main bundle's registry imports.
// Stash the Context on `window` so every bundle reuses one instance.
const SHARED_KEY = '__wpgraphqlIdeDialogContext';
const DialogContext =
	window[SHARED_KEY] || (window[SHARED_KEY] = createContext(null));

// Confirm options:
//   { title, message, confirmLabel?, cancelLabel?, isDestructive? }
// Prompt options:
//   { title, message?, defaultValue?, placeholder?, inputLabel?,
//     confirmLabel?, cancelLabel? }
// Both helpers return Promises that resolve when the user closes the
// dialog (boolean for confirm, string|null for prompt).

export function DialogProvider({ children }) {
	const [dialog, setDialog] = useState(null);

	const close = useCallback(
		(result) => {
			if (dialog?.resolve) {
				dialog.resolve(result);
			}
			setDialog(null);
		},
		[dialog]
	);

	const confirm = useCallback(
		(opts) =>
			new Promise((resolve) => {
				setDialog({ kind: 'confirm', opts, resolve });
			}),
		[]
	);

	const prompt = useCallback(
		(opts) =>
			new Promise((resolve) => {
				setDialog({ kind: 'prompt', opts, resolve });
			}),
		[]
	);

	const value = useMemo(() => ({ confirm, prompt }), [confirm, prompt]);

	return (
		<DialogContext.Provider value={value}>
			{children}
			{dialog && (
				<DialogHost
					kind={dialog.kind}
					opts={dialog.opts}
					onClose={close}
				/>
			)}
		</DialogContext.Provider>
	);
}

/**
 * Hook for triggering confirm/prompt dialogs from anywhere in the
 * IDE tree. Throws if called outside `DialogProvider`.
 */
export function useDialog() {
	const ctx = useContext(DialogContext);
	if (!ctx) {
		throw new Error('useDialog must be used within a DialogProvider');
	}
	return ctx;
}

function DialogHost({ kind, opts, onClose }) {
	if (kind === 'confirm') {
		return <ConfirmDialog opts={opts} onClose={onClose} />;
	}
	if (kind === 'prompt') {
		return <PromptDialog opts={opts} onClose={onClose} />;
	}
	return null;
}

function ConfirmDialog({ opts, onClose }) {
	const {
		title,
		message,
		confirmLabel = 'Confirm',
		cancelLabel = 'Cancel',
		isDestructive = false,
	} = opts;

	return (
		<Modal
			title={title}
			onRequestClose={() => onClose(false)}
			className="wpgraphql-ide-dialog"
		>
			{message && (
				<p className="wpgraphql-ide-dialog-message">{message}</p>
			)}
			<div className="wpgraphql-ide-dialog-actions">
				<Button variant="tertiary" onClick={() => onClose(false)}>
					{cancelLabel}
				</Button>
				<Button
					variant="primary"
					isDestructive={isDestructive}
					onClick={() => onClose(true)}
				>
					{confirmLabel}
				</Button>
			</div>
		</Modal>
	);
}

function PromptDialog({ opts, onClose }) {
	const {
		title,
		message,
		defaultValue = '',
		placeholder = '',
		confirmLabel = 'Save',
		cancelLabel = 'Cancel',
		inputLabel = '',
	} = opts;

	const [value, setValue] = useState(defaultValue);

	const submit = () => {
		const trimmed = value.trim();
		onClose(trimmed === '' ? null : trimmed);
	};

	return (
		<Modal
			title={title}
			onRequestClose={() => onClose(null)}
			className="wpgraphql-ide-dialog"
		>
			{message && (
				<p className="wpgraphql-ide-dialog-message">{message}</p>
			)}
			<TextControl
				label={inputLabel}
				value={value}
				onChange={setValue}
				placeholder={placeholder}
				// eslint-disable-next-line jsx-a11y/no-autofocus
				autoFocus
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				onKeyDown={(e) => {
					if (e.key === 'Enter') {
						e.preventDefault();
						submit();
					}
				}}
			/>
			<div className="wpgraphql-ide-dialog-actions">
				<Button variant="tertiary" onClick={() => onClose(null)}>
					{cancelLabel}
				</Button>
				<Button
					variant="primary"
					onClick={submit}
					disabled={!value.trim()}
				>
					{confirmLabel}
				</Button>
			</div>
		</Modal>
	);
}

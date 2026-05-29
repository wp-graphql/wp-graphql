import React, { useCallback, useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	NavigableMenu,
	Tooltip,
} from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { Icon, chevronDown, check } from '@wordpress/icons';
import authStyles from '../../../styles/ToggleAuthenticationButton.module.css';
import hooks from '../../wordpress-hooks';
import { tipify } from '../../utils/tipify';
import { RUN_QUERY_LABEL, SAVE_LABEL } from '../../utils/shortcut-labels';
import { SignInPromptDialog } from '../dialogs/SignInPromptDialog';

// Play-button easter eggs: count rapid presses (< 1.5s apart) and fire a
// milestone snackbar via `wpgraphql-ide.notice`. One stable id so the
// snackbar replaces in place rather than stacking.
const PLAY_RAPID_WINDOW_MS = 1500;
const PLAY_NOTICE_ID = 'wpgraphql-ide-play-mash';
// Built lazily so __() runs after wp.i18n is loaded.
const getPlayMilestones = () => ({
	5: sprintf(
		/* translators: %s is a keyboard shortcut, e.g. "Cmd+Enter" or "Ctrl+Enter". */
		__(
			'Whoa there, speedy. Tip: %s runs the query, no clicking required.',
			'wpgraphql-ide'
		),
		RUN_QUERY_LABEL
	),
	10: __(
		'GraphQL fan club, party of one. Tip: variables live in the Variables tab below the editor.',
		'wpgraphql-ide'
	),
	15: __(
		'Achievement unlocked: Excessive Curiosity. Tip: name your operations (`query GetPosts { … }`) so the picker shows them.',
		'wpgraphql-ide'
	),
	20: __(
		"OK now you're just showing off. Tip: switch HTTP method to GET on read-only queries so CDNs can cache them.",
		'wpgraphql-ide'
	),
	30: sprintf(
		/* translators: 1: run-query shortcut (e.g. "Cmd+Enter"), 2: save shortcut (e.g. "Cmd+S"). */
		__(
			'Have you tried %1$s? Just saying. Tip: drafts auto-save as you type — no %2$s needed.',
			'wpgraphql-ide'
		),
		RUN_QUERY_LABEL,
		SAVE_LABEL
	),
});

// Empty-query execute: replaces the server's "Unexpected EOF" with a
// quip + insertable snippet. Cycles so repeat-testing sees variety.
const EMPTY_QUERY_NOTICE_ID = 'wpgraphql-ide-empty-query-quip';
const getEmptyQueryQuips = () => [
	{
		content: __(
			'An empty query. Try the smallest valid one:',
			'wpgraphql-ide'
		),
		insert: '{\n  __typename\n}\n',
	},
	{
		content: __(
			'Nothing? List every type the server exposes:',
			'wpgraphql-ide'
		),
		insert: '{\n  __schema {\n    types {\n      name\n    }\n  }\n}\n',
	},
	{
		content: __(
			'WPGraphQL has more to offer — try a posts query:',
			'wpgraphql-ide'
		),
		insert: '{\n  posts {\n    nodes {\n      id\n      title\n    }\n  }\n}\n',
	},
	{
		content: __('Inspect the schema\u2019s queryType:', 'wpgraphql-ide'),
		insert: '{\n  __schema {\n    queryType {\n      name\n      fields {\n        name\n      }\n    }\n  }\n}\n',
	},
];
let emptyQueryQuipIndex = 0;

function insertSnippetIntoEditor(snippet) {
	dispatch('wpgraphql-ide/app').setQuery(snippet);
}

/**
 * Single-choice menu. Auto-focuses the selected option on mount so the
 * focus-visible ring lands on the same row as the check (matching the
 * WP-core dropdown pattern); otherwise NavigableMenu focuses index 0.
 *
 * @param {Object}        props
 * @param {string}        props.label         - Group label shown above the options.
 * @param {Array<Object>} props.options       - `{ value, label }` entries.
 * @param {*}             props.selectedValue - Currently active value.
 * @param {Function}      props.onChange      - Called with the new value when an option is picked.
 * @param {Function}      props.onClose       - Closes the dropdown.
 */
function SelectMenuContent({
	label,
	options,
	selectedValue,
	onChange,
	onClose,
}) {
	const selectedRef = useRef(null);
	// Dropdown's popover focuses its first focusable element on mount
	// (`focusOnMount: 'firstElement'`), which lands on position 0 even
	// when a later option is selected. Defer to the next frame so our
	// focus on the selected item runs after the popover's, overriding it.
	useEffect(() => {
		const id = window.requestAnimationFrame(() => {
			selectedRef.current?.focus();
		});
		return () => window.cancelAnimationFrame(id);
	}, []);
	return (
		<NavigableMenu>
			<MenuGroup label={label}>
				{options.map((opt) => {
					const isSelected = opt.value === selectedValue;
					return (
						<MenuItem
							key={opt.value}
							ref={isSelected ? selectedRef : null}
							isSelected={isSelected}
							icon={isSelected ? check : null}
							iconPosition="left"
							onClick={() => {
								onChange(opt.value);
								onClose();
							}}
						>
							{opt.label}
						</MenuItem>
					);
				})}
			</MenuGroup>
		</NavigableMenu>
	);
}

const PlayIcon = (
	<svg
		viewBox="0 0 24 24"
		width="20"
		height="20"
		fill="currentColor"
		aria-hidden="true"
	>
		<path d="M8 5v14l11-7z" />
	</svg>
);

const StopIcon = (
	<svg
		viewBox="0 0 24 24"
		width="20"
		height="20"
		fill="currentColor"
		aria-hidden="true"
	>
		<rect x="6" y="6" width="12" height="12" rx="1" />
	</svg>
);

const HTTP_METHODS = ['GET', 'POST'];

/* eslint-disable jsdoc/require-param, jsdoc/require-param-type, jsdoc/require-param-description */
/**
 * Editor's bottom-right execute pill: method picker, auth toggle,
 * execute button (promoted to an operation dropdown when the doc
 * declares more than one named operation).
 */
export function ExecutionControls({
	query,
	httpMethod,
	onSetHttpMethod,
	isAuthenticated,
	onToggleAuth,
	avatarUrl,
	operationNames,
	isFetching,
	isSchemaLoading,
	onExecute,
	signInUrl,
	showAuthControl = true,
}) {
	// `signInUrl` set means the visitor is anonymous on the public-endpoint
	// render: WordPress has no current user, so there is no auth to toggle.
	// Clicking the avatar opens a confirm modal that explains the handoff
	// before navigating to wp_login — gentler than yanking them out of the
	// IDE on the first click.
	const isSignInMode = !!signInUrl;
	const isPublicMode = isSignInMode || !isAuthenticated;
	const [signInPromptOpen, setSignInPromptOpen] = useState(false);

	let authTooltip;
	let authAriaLabel;
	if (isSignInMode) {
		authTooltip = __(
			'Sending as public visitor — sign in for authenticated requests',
			'wpgraphql-ide'
		);
		authAriaLabel = __(
			'Sending as public visitor (sign in to send authenticated requests)',
			'wpgraphql-ide'
		);
	} else if (isAuthenticated) {
		authTooltip = __(
			'Sending as authenticated user — click to send anonymously',
			'wpgraphql-ide'
		);
		authAriaLabel = __(
			'Send as authenticated user (click to switch to public)',
			'wpgraphql-ide'
		);
	} else {
		authTooltip = __(
			'Sending as public visitor — click to authenticate',
			'wpgraphql-ide'
		);
		authAriaLabel = __(
			'Send as public visitor (click to switch to authenticated)',
			'wpgraphql-ide'
		);
	}

	// Multi-op documents promote the play button to an operation
	// picker — GraphQL spec §6.1 requires the client to specify
	// `operationName` when the document has more than one operation,
	// so we force an explicit pick instead of silently defaulting to
	// the first definition. Cmd/Ctrl+Enter takes a different route:
	// it resolves the op under the cursor and runs that one without
	// opening the menu.
	const showOpPicker = !isFetching && operationNames.length > 1;

	const playCountRef = useRef(0);
	const lastPlayAtRef = useRef(0);
	const handleExecute = useCallback(
		(opName) => {
			// Empty-query easter egg: short-circuit before sending so
			// the user gets an "Insert" snackbar instead of the server's
			// "Unexpected EOF" syntax error.
			if (!query || !query.trim()) {
				const quips = getEmptyQueryQuips();
				const quip = quips[emptyQueryQuipIndex % quips.length];
				emptyQueryQuipIndex += 1;
				hooks.doAction(
					'wpgraphql-ide.notice',
					{
						id: EMPTY_QUERY_NOTICE_ID,
						content: quip.content,
						// Snackbar auto-dismisses after ~10s; the user needs
						// time to read the quip, parse the offered snippet,
						// and click Insert. Sticky until ✕.
						explicitDismiss: true,
						actions: [
							{
								label: __('Insert', 'wpgraphql-ide'),
								onClick: () => {
									insertSnippetIntoEditor(quip.insert);
									hooks.doAction(
										'wpgraphql-ide.notice.dismiss',
										EMPTY_QUERY_NOTICE_ID
									);
								},
							},
						],
					},
					'default'
				);
				return;
			}

			const now = Date.now();
			if (now - lastPlayAtRef.current < PLAY_RAPID_WINDOW_MS) {
				playCountRef.current += 1;
			} else {
				playCountRef.current = 1;
			}
			lastPlayAtRef.current = now;

			const message = getPlayMilestones()[playCountRef.current];
			if (message) {
				hooks.doAction(
					'wpgraphql-ide.notice',
					{ id: PLAY_NOTICE_ID, content: tipify(message) },
					'default'
				);
			}

			onExecute(opName);
		},
		[query, onExecute]
	);

	return (
		<div className="wpgraphql-ide-execution-pill">
			<Dropdown
				popoverProps={{ placement: 'top-end' }}
				renderToggle={({ isOpen, onToggle }) => (
					<Tooltip text={__('HTTP method', 'wpgraphql-ide')}>
						<Button
							onClick={onToggle}
							aria-expanded={isOpen}
							aria-haspopup="menu"
							className="wpgraphql-ide-execution-method-btn"
							size="compact"
						>
							{httpMethod}
							<Icon icon={chevronDown} size={14} />
						</Button>
					</Tooltip>
				)}
				renderContent={({ onClose: closeMenu }) => (
					<SelectMenuContent
						label={__('HTTP method', 'wpgraphql-ide')}
						options={HTTP_METHODS.map((m) => ({
							value: m,
							label: m,
						}))}
						selectedValue={httpMethod}
						onChange={onSetHttpMethod}
						onClose={closeMenu}
					/>
				)}
			/>
			{showAuthControl && (
				<Tooltip text={authTooltip}>
					{/* No `aria-pressed`: @wordpress/components/Button paints
					    a pressed-state black fill from it, which fights the
					    badge/grayscale we use to convey state. */}
					<Button
						className={`wpgraphql-ide-auth-avatar ${isPublicMode ? authStyles.authAvatarPublic : ''}`}
						aria-label={authAriaLabel}
						onClick={
							isSignInMode
								? () => setSignInPromptOpen(true)
								: onToggleAuth
						}
					>
						<span
							className={authStyles.authAvatar}
							style={{
								backgroundImage: `url(${avatarUrl || ''})`,
							}}
						>
							{!isSignInMode && (
								<span className={authStyles.authBadge} />
							)}
						</span>
					</Button>
				</Tooltip>
			)}
			{showAuthControl && signInPromptOpen && (
				<SignInPromptDialog
					signInUrl={signInUrl}
					onClose={() => setSignInPromptOpen(false)}
				/>
			)}
			<span
				className="wpgraphql-ide-execution-pill-divider"
				aria-hidden="true"
			/>
			{showOpPicker ? (
				<Dropdown
					popoverProps={{ placement: 'top-end' }}
					renderToggle={({ isOpen, onToggle }) => (
						<Tooltip
							text={__(
								'Execute (pick operation)',
								'wpgraphql-ide'
							)}
						>
							<Button
								variant="primary"
								onClick={onToggle}
								aria-expanded={isOpen}
								disabled={isSchemaLoading}
								className="wpgraphql-ide-send-button"
								size="compact"
								aria-label={__(
									'Execute query',
									'wpgraphql-ide'
								)}
							>
								{PlayIcon}
							</Button>
						</Tooltip>
					)}
					renderContent={({ onClose: closeMenu }) => (
						<NavigableMenu>
							<MenuGroup
								label={__('Run operation', 'wpgraphql-ide')}
							>
								{operationNames.map((name) => (
									<MenuItem
										key={name}
										onClick={() => {
											closeMenu();
											handleExecute(name);
										}}
									>
										{name}
									</MenuItem>
								))}
							</MenuGroup>
						</NavigableMenu>
					)}
				/>
			) : (
				<Tooltip
					text={
						isFetching
							? sprintf(
									/* translators: %s is a keyboard shortcut, e.g. "Cmd+Enter" or "Ctrl+Enter". */
									__('Stop (%s)', 'wpgraphql-ide'),
									RUN_QUERY_LABEL
								)
							: sprintf(
									/* translators: %s is a keyboard shortcut, e.g. "Cmd+Enter" or "Ctrl+Enter". */
									__('Execute (%s)', 'wpgraphql-ide'),
									RUN_QUERY_LABEL
								)
					}
				>
					<Button
						variant="primary"
						onClick={() => handleExecute()}
						disabled={isSchemaLoading}
						className="wpgraphql-ide-send-button"
						size="compact"
						aria-label={
							isFetching
								? __('Stop execution', 'wpgraphql-ide')
								: __('Execute query', 'wpgraphql-ide')
						}
					>
						{isFetching ? StopIcon : PlayIcon}
					</Button>
				</Tooltip>
			)}
		</div>
	);
}

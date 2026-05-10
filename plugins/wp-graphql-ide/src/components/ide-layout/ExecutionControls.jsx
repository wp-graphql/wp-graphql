import React, { useCallback, useEffect, useRef } from 'react';
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

// Play-button easter eggs: count rapid presses (< 1.5s apart) and fire a
// milestone snackbar via `wpgraphql-ide.notice`. One stable id so the
// snackbar replaces in place rather than stacking.
const PLAY_RAPID_WINDOW_MS = 1500;
const PLAY_NOTICE_ID = 'wpgraphql-ide-play-mash';
const PLAY_MILESTONES = {
	5: 'Whoa there, speedy. Tip: Cmd+Enter runs the query, no clicking required.',
	10: 'GraphQL fan club, party of one. Tip: variables live in the Variables tab below the editor.',
	15: 'Achievement unlocked: Excessive Curiosity. Tip: name your operations (`query GetPosts { ... }`) so the picker shows them.',
	20: "OK now you're just showing off. Tip: switch HTTP method to GET on read-only queries so CDNs can cache them.",
	30: 'Have you tried Cmd+Enter? Just saying. (Also: drafts auto-save as you type — no Cmd+S needed.)',
};

// Empty-query execute: replaces the server's "Unexpected EOF" with a
// quip + insertable snippet. Cycles so repeat-testing sees variety.
const EMPTY_QUERY_NOTICE_ID = 'wpgraphql-ide-empty-query-quip';
const EMPTY_QUERY_QUIPS = [
	{
		content: 'An empty query. Try the smallest valid one:',
		insert: '{\n  __typename\n}\n',
	},
	{
		content: 'Nothing? List every type the server exposes:',
		insert: '{\n  __schema {\n    types {\n      name\n    }\n  }\n}\n',
	},
	{
		content: 'WPGraphQL has more to offer — try a posts query:',
		insert: '{\n  posts {\n    nodes {\n      id\n      title\n    }\n  }\n}\n',
	},
	{
		content: 'Inspect the schema\u2019s queryType:',
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
	canSwitchAuth = true,
}) {
	const showOpPicker = !isFetching && operationNames.length > 1;

	const playCountRef = useRef(0);
	const lastPlayAtRef = useRef(0);
	const handleExecute = useCallback(
		(opName) => {
			// Empty-query easter egg: short-circuit before sending so
			// the user gets an "Insert" snackbar instead of the server's
			// "Unexpected EOF" syntax error.
			if (!query || !query.trim()) {
				const quip =
					EMPTY_QUERY_QUIPS[
						emptyQueryQuipIndex % EMPTY_QUERY_QUIPS.length
					];
				emptyQueryQuipIndex += 1;
				hooks.doAction(
					'wpgraphql-ide.notice',
					{
						id: EMPTY_QUERY_NOTICE_ID,
						content: quip.content,
						actions: [
							{
								label: 'Insert',
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

			const message = PLAY_MILESTONES[playCountRef.current];
			if (message) {
				hooks.doAction(
					'wpgraphql-ide.notice',
					{ id: PLAY_NOTICE_ID, content: message },
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
					<Tooltip text="HTTP method">
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
						label="HTTP method"
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
			{canSwitchAuth && (
				<Tooltip
					text={
						isAuthenticated
							? 'Sending as authenticated user — click to send anonymously'
							: 'Sending as public visitor — click to authenticate'
					}
				>
					{/* No `aria-pressed`: @wordpress/components/Button paints
					    a pressed-state black fill from it, which fights the
					    badge/grayscale we use to convey state. */}
					<Button
						onClick={onToggleAuth}
						className={`wpgraphql-ide-auth-avatar ${!isAuthenticated ? authStyles.authAvatarPublic : ''}`}
						aria-label={
							isAuthenticated
								? 'Send as authenticated user (click to switch to public)'
								: 'Send as public visitor (click to switch to authenticated)'
						}
					>
						<span
							className={authStyles.authAvatar}
							style={{
								backgroundImage: `url(${avatarUrl || ''})`,
							}}
						>
							<span className={authStyles.authBadge} />
						</span>
					</Button>
				</Tooltip>
			)}
			<span
				className="wpgraphql-ide-execution-pill-divider"
				aria-hidden="true"
			/>
			{showOpPicker ? (
				<Dropdown
					popoverProps={{ placement: 'top-end' }}
					renderToggle={({ isOpen, onToggle }) => (
						<Tooltip text="Execute (pick operation)">
							<Button
								variant="primary"
								onClick={onToggle}
								aria-expanded={isOpen}
								disabled={isSchemaLoading}
								className="wpgraphql-ide-send-button"
								size="compact"
								aria-label="Execute query"
							>
								{PlayIcon}
							</Button>
						</Tooltip>
					)}
					renderContent={({ onClose: closeMenu }) => (
						<NavigableMenu>
							<MenuGroup label="Run operation">
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
						isFetching ? 'Stop (Cmd+Enter)' : 'Execute (Cmd+Enter)'
					}
				>
					<Button
						variant="primary"
						onClick={() => handleExecute()}
						disabled={isSchemaLoading}
						className="wpgraphql-ide-send-button"
						size="compact"
						aria-label={
							isFetching ? 'Stop execution' : 'Execute query'
						}
					>
						{isFetching ? StopIcon : PlayIcon}
					</Button>
				</Tooltip>
			)}
		</div>
	);
}

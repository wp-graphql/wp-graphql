import React, { useCallback, useEffect, useRef } from 'react';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	NavigableMenu,
	Tooltip,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { Icon, chevronDown, check } from '@wordpress/icons';
import authStyles from '../../../styles/ToggleAuthenticationButton.module.css';

// --- Play-button easter egg ---------------------------------------------
// If the user mash-clicks the play button (gap < 1.5s between clicks),
// we count consecutive presses and surface a playful snackbar at preset
// milestones. Sharing a stable notice id means each new milestone
// replaces the previous one — no stack of giggling snackbars.
const PLAY_RAPID_WINDOW_MS = 1500;
const PLAY_NOTICE_ID = 'wpgraphql-ide-play-easter-egg';
const PLAY_MILESTONES = {
	5: 'Whoa there, speedy.',
	10: 'GraphQL fan club, party of one.',
	15: 'Achievement unlocked: Excessive Curiosity.',
	20: "OK now you're just showing off.",
	30: 'Have you tried Cmd+Enter? Just saying.',
};

/**
 * Renders a select-style menu (single-choice). Auto-focuses the
 * selected option on mount so the focus-visible ring lands on the
 * same item as the leading check, matching the WP-core dropdown
 * pattern (block style switcher, settings menus). Without this,
 * NavigableMenu focuses position 0 and the focus ring can outweigh
 * the check on the actually-selected option.
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

/**
 * Controls anchored to the GraphQL editor's bottom-right corner:
 * HTTP method dropdown, authentication-mode dropdown, and the execute
 * button — promoted to a dropdown of operation names when the document
 * declares more than one named operation.
 *
 * @param {Object}        props
 * @param {'GET'|'POST'}  props.httpMethod      - Active HTTP method.
 * @param {Function}      props.onSetHttpMethod - Setter for `httpMethod`.
 * @param {boolean}       props.isAuthenticated - Whether requests carry the user's nonce.
 * @param {Function}      props.onToggleAuth    - Toggles auth on/off.
 * @param {string}        [props.avatarUrl]     - User avatar image URL (when authenticated).
 * @param {Array<string>} props.operationNames  - Named operation list parsed from the doc.
 * @param {boolean}       props.isFetching      - Whether a request is in flight (Stop icon).
 * @param {boolean}       props.isSchemaLoading - Disables the Execute button while the schema fetches.
 * @param {Function}      props.onExecute       - Called with an optional operation name; no-arg defaults to the first.
 * @param {boolean}       [props.canSwitchAuth] - Whether the auth dropdown should render. False on the public endpoint for anonymous visitors (nothing to switch).
 */
export function ExecutionControls({
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

	const noticesDispatch = useDispatch('core/notices');
	const playCountRef = useRef(0);
	const lastPlayAtRef = useRef(0);
	const handleExecute = useCallback(
		(opName) => {
			const now = Date.now();
			if (now - lastPlayAtRef.current < PLAY_RAPID_WINDOW_MS) {
				playCountRef.current += 1;
			} else {
				playCountRef.current = 1;
			}
			lastPlayAtRef.current = now;

			const message = PLAY_MILESTONES[playCountRef.current];
			if (message && noticesDispatch?.createNotice) {
				noticesDispatch.createNotice('info', message, {
					id: PLAY_NOTICE_ID,
					type: 'snackbar',
					isDismissible: true,
				});
			}

			onExecute(opName);
		},
		[noticesDispatch, onExecute]
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
				<Dropdown
					popoverProps={{ placement: 'top-end' }}
					renderToggle={({ isOpen, onToggle }) => (
						<Tooltip
							text={
								isAuthenticated
									? 'Sending as authenticated user'
									: 'Sending as public visitor'
							}
						>
							<Button
								onClick={onToggle}
								aria-expanded={isOpen}
								aria-haspopup="menu"
								className={`wpgraphql-ide-auth-avatar ${!isAuthenticated ? authStyles.authAvatarPublic : ''}`}
								aria-label="Authentication mode"
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
					renderContent={({ onClose: closeMenu }) => (
						<SelectMenuContent
							label="Send as"
							options={[
								{
									value: 'authenticated',
									label: 'Authenticated user',
								},
								{ value: 'public', label: 'Public visitor' },
							]}
							selectedValue={
								isAuthenticated ? 'authenticated' : 'public'
							}
							onChange={(next) => {
								const wantsAuth = next === 'authenticated';
								if (wantsAuth !== isAuthenticated) {
									onToggleAuth();
								}
							}}
							onClose={closeMenu}
						/>
					)}
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

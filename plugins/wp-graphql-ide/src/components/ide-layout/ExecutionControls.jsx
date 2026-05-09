import React from 'react';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	NavigableMenu,
	Tooltip,
} from '@wordpress/components';
import authStyles from '../../../styles/ToggleAuthenticationButton.module.css';

const PlayIcon = (
	<svg
		viewBox="0 0 24 24"
		width="16"
		height="16"
		fill="currentColor"
		aria-hidden="true"
	>
		<path d="M8 5v14l11-7z" />
	</svg>
);

const StopIcon = (
	<svg
		viewBox="0 0 24 24"
		width="16"
		height="16"
		fill="currentColor"
		aria-hidden="true"
	>
		<rect x="6" y="6" width="12" height="12" rx="1" />
	</svg>
);

/**
 * Controls anchored to the GraphQL editor's bottom-right corner:
 * HTTP method toggle (GET/POST), authentication avatar, and the
 * execute button — promoted to a dropdown when the document declares
 * more than one named operation so the user can pick which one to run.
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
 * @param {boolean}       [props.canSwitchAuth] - Whether the auth toggle should render. False on the public endpoint for anonymous visitors (nothing to switch).
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

	return (
		<div className="wpgraphql-ide-execution-pill">
			<div
				className="wpgraphql-ide-response-mode-toggle"
				role="group"
				aria-label="HTTP method"
			>
				{['GET', 'POST'].map((m) => (
					<button
						key={m}
						type="button"
						aria-pressed={httpMethod === m}
						className={`wpgraphql-ide-response-mode-btn${httpMethod === m ? ' is-active' : ''}`}
						onClick={() => onSetHttpMethod(m)}
					>
						{m}
					</button>
				))}
			</div>
			{canSwitchAuth && (
				<Tooltip
					text={
						isAuthenticated
							? 'Authenticated (click to switch)'
							: 'Public (click to switch)'
					}
				>
					<button
						type="button"
						onClick={onToggleAuth}
						className={`wpgraphql-ide-auth-avatar ${!isAuthenticated ? authStyles.authAvatarPublic : ''}`}
						aria-label={
							isAuthenticated
								? 'Switch to public'
								: 'Switch to authenticated'
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
					</button>
				</Tooltip>
			)}
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
											onExecute(name);
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
						onClick={() => onExecute()}
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

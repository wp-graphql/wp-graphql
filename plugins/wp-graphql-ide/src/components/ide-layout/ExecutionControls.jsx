import React from 'react';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	NavigableMenu,
	Tooltip,
} from '@wordpress/components';
import { Icon, chevronDown, check } from '@wordpress/icons';
import authStyles from '../../../styles/ToggleAuthenticationButton.module.css';

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
					<NavigableMenu>
						<MenuGroup label="HTTP method">
							{HTTP_METHODS.map((m) => (
								<MenuItem
									key={m}
									isSelected={httpMethod === m}
									icon={httpMethod === m ? check : null}
									iconPosition="left"
									onClick={() => {
										onSetHttpMethod(m);
										closeMenu();
									}}
								>
									{m}
								</MenuItem>
							))}
						</MenuGroup>
					</NavigableMenu>
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
								<Icon icon={chevronDown} size={14} />
							</Button>
						</Tooltip>
					)}
					renderContent={({ onClose: closeMenu }) => (
						<NavigableMenu>
							<MenuGroup label="Send as">
								<MenuItem
									isSelected={isAuthenticated}
									icon={isAuthenticated ? check : null}
									iconPosition="left"
									onClick={() => {
										if (!isAuthenticated) {
											onToggleAuth();
										}
										closeMenu();
									}}
								>
									Authenticated user
								</MenuItem>
								<MenuItem
									isSelected={!isAuthenticated}
									icon={!isAuthenticated ? check : null}
									iconPosition="left"
									onClick={() => {
										if (isAuthenticated) {
											onToggleAuth();
										}
										closeMenu();
									}}
								>
									Public visitor
								</MenuItem>
							</MenuGroup>
						</NavigableMenu>
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

import React, { useState } from 'react';
import {
	Button,
	Dropdown,
	MenuGroup,
	MenuItem,
	NavigableMenu,
	Tooltip,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Icon, chevronDown, check } from '@wordpress/icons';
import { SignInPromptDialog } from '../dialogs/SignInPromptDialog';

const PlayIcon = (
	<svg
		viewBox="0 0 24 24"
		width="18"
		height="18"
		fill="currentColor"
		aria-hidden="true"
	>
		<path d="M8 5v14l11-7z" />
	</svg>
);

const StopIcon = (
	<svg
		viewBox="0 0 24 24"
		width="18"
		height="18"
		fill="currentColor"
		aria-hidden="true"
	>
		<rect x="6" y="6" width="12" height="12" rx="1" />
	</svg>
);

const HTTP_METHODS = ['POST', 'GET'];

/**
 * Per-operation execution pill rendered inside a CodeMirror line. Reads
 * httpMethod / isAuthenticated / isFetching from the `wpgraphql-ide/app`
 * store directly because the CM6 widget host doesn't propagate prop
 * updates the way a React parent would.
 *
 * @param {Object}   props
 * @param {string}   props.operationName     - Operation this pill runs.
 * @param {Function} props.onRun             - Called with `operationName` on play click.
 * @param {string}   [props.avatarUrl]       - Current user's avatar URL.
 * @param {string}   [props.signInUrl]       - When set, the avatar opens a sign-in prompt instead of toggling auth.
 * @param {boolean}  [props.showAuthControl] - Whether to render the auth avatar.
 * @param {boolean}  [props.isSchemaLoading] - Disables play while the schema loads.
 */
export function InlineExecutionPill({
	operationName,
	onRun,
	avatarUrl,
	signInUrl,
	showAuthControl = true,
	isSchemaLoading = false,
}) {
	const { httpMethod, isAuthenticated, isFetching } = useSelect((select) => {
		const app = select('wpgraphql-ide/app');
		return {
			httpMethod: app.getHttpMethod(),
			isAuthenticated: app.isAuthenticated(),
			isFetching: app.isFetching(),
		};
	}, []);
	const { setHttpMethod, toggleAuthentication } =
		useDispatch('wpgraphql-ide/app');
	const [signInPromptOpen, setSignInPromptOpen] = useState(false);

	const isSignInMode = !!signInUrl;
	const isPublicMode = isSignInMode || !isAuthenticated;

	let authTooltip;
	let authAriaLabel;
	if (isSignInMode) {
		authTooltip =
			'Sending as public visitor — sign in for authenticated requests';
		authAriaLabel =
			'Sending as public visitor (sign in to send authenticated requests)';
	} else if (isAuthenticated) {
		authTooltip =
			'Sending as authenticated user — click to send anonymously';
		authAriaLabel =
			'Send as authenticated user (click to switch to public)';
	} else {
		authTooltip = 'Sending as public visitor — click to authenticate';
		authAriaLabel =
			'Send as public visitor (click to switch to authenticated)';
	}

	return (
		// `contentEditable={false}` + mousedown stop keep CM6 from
		// treating clicks inside the pill as caret placement.
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions
		<span
			className="wpgraphql-ide-inline-pill"
			contentEditable={false}
			suppressContentEditableWarning
			onMouseDown={(e) => e.stopPropagation()}
		>
			<Dropdown
				popoverProps={{ placement: 'bottom-end' }}
				renderToggle={({ isOpen, onToggle }) => (
					<Tooltip text="HTTP method">
						<Button
							onClick={onToggle}
							aria-expanded={isOpen}
							aria-haspopup="menu"
							className="wpgraphql-ide-inline-pill-method"
							size="compact"
						>
							{httpMethod}
							<Icon icon={chevronDown} size={12} />
						</Button>
					</Tooltip>
				)}
				renderContent={({ onClose }) => (
					<NavigableMenu>
						<MenuGroup label="HTTP method">
							{HTTP_METHODS.map((m) => {
								const isSelected = m === httpMethod;
								return (
									<MenuItem
										key={m}
										isSelected={isSelected}
										icon={isSelected ? check : null}
										iconPosition="left"
										onClick={() => {
											setHttpMethod(m);
											onClose();
										}}
									>
										{m}
									</MenuItem>
								);
							})}
						</MenuGroup>
					</NavigableMenu>
				)}
			/>
			{showAuthControl && (
				<Tooltip text={authTooltip}>
					<Button
						className={`wpgraphql-ide-inline-pill-avatar ${isPublicMode ? 'is-public' : ''}`}
						aria-label={authAriaLabel}
						onClick={
							isSignInMode
								? () => setSignInPromptOpen(true)
								: toggleAuthentication
						}
					>
						<span
							className="wpgraphql-ide-inline-pill-avatar-inner"
							style={{
								backgroundImage: avatarUrl
									? `url(${avatarUrl})`
									: undefined,
							}}
						>
							{!isSignInMode && (
								<span className="wpgraphql-ide-inline-pill-avatar-badge" />
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
				className="wpgraphql-ide-inline-pill-divider"
				aria-hidden="true"
			/>
			<Tooltip
				text={isFetching ? 'Stop execution' : `Run ${operationName}`}
			>
				<Button
					variant="primary"
					onClick={() => onRun(operationName)}
					disabled={isSchemaLoading}
					className="wpgraphql-ide-inline-pill-send"
					size="compact"
					aria-label={
						isFetching ? 'Stop execution' : `Run ${operationName}`
					}
				>
					{isFetching ? StopIcon : PlayIcon}
				</Button>
			</Tooltip>
		</span>
	);
}

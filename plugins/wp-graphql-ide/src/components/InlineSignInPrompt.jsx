import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { Icon, lockOutline } from '@wordpress/icons';
import { loginUrl } from '../bootstrap';
import { SignInPromptDialog } from './dialogs/SignInPromptDialog';

/**
 * Inline empty-state shown inside a global-sidebar panel when the
 * visitor is anonymous and the panel's feature requires authentication
 * (currently Saved Queries and History). Mirrors the auth-avatar flow
 * in the execution pill: clicking "Sign in" opens the same confirm
 * dialog rather than yanking the user out of the IDE on first click.
 *
 * @param {Object} props
 * @param {string} props.title       - Heading text, e.g. "Sign in to see your queries".
 * @param {string} props.description - Sub-line explaining why the feature is gated.
 *
 * @return {JSX.Element}
 */
export function InlineSignInPrompt({ title, description }) {
	const [open, setOpen] = useState(false);
	return (
		<div className="wpgraphql-ide-inline-signin">
			<div
				className="wpgraphql-ide-inline-signin-icon"
				aria-hidden="true"
			>
				<Icon icon={lockOutline} size={28} />
			</div>
			<h3 className="wpgraphql-ide-inline-signin-title">{title}</h3>
			<p className="wpgraphql-ide-inline-signin-description">
				{description}
			</p>
			<Button variant="primary" onClick={() => setOpen(true)}>
				{__('Sign in', 'wpgraphql-ide')}
			</Button>
			{open && (
				<SignInPromptDialog
					signInUrl={loginUrl}
					onClose={() => setOpen(false)}
				/>
			)}
		</div>
	);
}

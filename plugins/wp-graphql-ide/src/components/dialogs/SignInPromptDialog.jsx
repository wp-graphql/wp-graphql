import React from 'react';
import { Button, Modal } from '@wordpress/components';

/**
 * Confirm dialog presented when an anonymous visitor clicks an
 * auth-gated affordance (execution-pill avatar, Documents / History
 * panel prompts). Explains the round-trip and gives them a way out
 * before the page navigates — a softer landing than yanking them out
 * of the IDE on the first stray click.
 *
 * @param {Object}   props
 * @param {string}   props.signInUrl - Sign-in URL with redirect back to the current page.
 * @param {Function} props.onClose   - Close the modal (cancel or dismiss).
 *
 * @return {JSX.Element}
 */
export function SignInPromptDialog({ signInUrl, onClose }) {
	return (
		<Modal
			title="Sign in?"
			onRequestClose={onClose}
			className="wpgraphql-ide-dialog wpgraphql-ide-sign-in-prompt"
		>
			<p className="wpgraphql-ide-dialog-message">
				You&rsquo;ll be sent to the sign-in page and returned to the IDE
				signed in — able to send queries as your user and resolve fields
				that require authentication.
			</p>
			<div className="wpgraphql-ide-dialog-actions">
				<Button variant="tertiary" onClick={onClose}>
					Not now
				</Button>
				<Button variant="primary" href={signInUrl}>
					Sign in
				</Button>
			</div>
		</Modal>
	);
}

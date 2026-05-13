import React from 'react';
import { __ } from '@wordpress/i18n';
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
			title={__('Sign in?', 'wpgraphql-ide')}
			onRequestClose={onClose}
			className="wpgraphql-ide-dialog wpgraphql-ide-sign-in-prompt"
		>
			<p className="wpgraphql-ide-dialog-message">
				{__(
					'You’ll be sent to the sign-in page and returned to the IDE signed in — able to send queries as your user and resolve fields that require authentication.',
					'wpgraphql-ide'
				)}
			</p>
			<div className="wpgraphql-ide-dialog-actions">
				<Button variant="tertiary" onClick={onClose}>
					{__('Not now', 'wpgraphql-ide')}
				</Button>
				<Button variant="primary" href={signInUrl}>
					{__('Sign in', 'wpgraphql-ide')}
				</Button>
			</div>
		</Modal>
	);
}

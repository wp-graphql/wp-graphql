/* eslint-env browser, jest */
import '@testing-library/jest-dom';
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

// @wordpress/components probes matchMedia at render time; jsdom doesn't
// ship it. Stub before the component imports.
if (typeof window.matchMedia !== 'function') {
	window.matchMedia = () => ({
		matches: false,
		media: '',
		onchange: null,
		addListener: () => {},
		removeListener: () => {},
		addEventListener: () => {},
		removeEventListener: () => {},
		dispatchEvent: () => false,
	});
}

// The project's CSS-module loader isn't wired into jest; stub the one
// module ExecutionControls pulls in so the import doesn't try to parse
// raw CSS as JS.
jest.mock(
	'../../../../styles/ToggleAuthenticationButton.module.css',
	() => ({
		authAvatar: 'authAvatar',
		authAvatarPublic: 'authAvatarPublic',
		authBadge: 'authBadge',
	}),
	{ virtual: true }
);

// eslint-disable-next-line import/first
import { ExecutionControls } from '../../../../src/components/ide-layout/ExecutionControls';

const baseProps = {
	query: '',
	httpMethod: 'POST',
	onSetHttpMethod: () => {},
	isAuthenticated: false,
	onToggleAuth: () => {},
	avatarUrl: '',
	operationNames: [],
	isFetching: false,
	isSchemaLoading: false,
	onExecute: () => {},
};

describe('ExecutionControls auth slot', () => {
	it('clicking the avatar toggles auth when there is no signInUrl', () => {
		const onToggleAuth = jest.fn();
		render(
			<ExecutionControls
				{...baseProps}
				avatarUrl="http://example.test/avatar.png"
				isAuthenticated
				onToggleAuth={onToggleAuth}
			/>
		);
		const toggle = screen.getByLabelText(
			/Send as authenticated user \(click to switch to public\)/i
		);
		fireEvent.click(toggle);
		expect(onToggleAuth).toHaveBeenCalledTimes(1);
		// No sign-in modal is opened in toggle mode.
		expect(screen.queryByRole('dialog')).toBeNull();
	});

	it('clicking the avatar opens a confirm modal when signInUrl is set', () => {
		const url =
			'http://example.test/wp-login.php?redirect_to=%2F%3Fgraphql';
		render(<ExecutionControls {...baseProps} signInUrl={url} />);
		const avatar = screen.getByLabelText(
			/Sending as public visitor \(sign in to send authenticated requests\)/i
		);
		// Avatar is a plain button, not an anchor — the navigation is
		// gated behind the modal confirm.
		expect(avatar).not.toHaveAttribute('href');

		fireEvent.click(avatar);
		const dialog = screen.getByRole('dialog');
		expect(dialog).toBeInTheDocument();
		// Confirm button is the anchor that goes to wp_login.
		const confirm = screen.getByRole('link', { name: /^Sign in$/i });
		expect(confirm).toHaveAttribute('href', url);
		// Cancel keeps the user where they are.
		expect(
			screen.getByRole('button', { name: /Not now/i })
		).toBeInTheDocument();
	});
});

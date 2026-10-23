import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { describeExtensionError, isFolderExistsError } from './utils';

/**
 * @typedef {Object} ExtensionError
 * @property {'install'|'activate'} action  The step that failed.
 * @property {string}               message A short, readable description of the failure.
 * @property {string}               details The original error, as plain text, for debugging.
 */

const useInstallPlugin = (pluginUrl, pluginPath, pluginFile) => {
	const [installing, setInstalling] = useState(false);
	const [activating, setActivating] = useState(false);
	const [status, setStatus] = useState('');
	/** @type {[ExtensionError|null, Function]} */
	const [error, setError] = useState(null);

	// Helper function to update the status and error states
	const updateStatus = (newStatus, newError = null) => {
		setStatus(newStatus);
		setError(newError);
	};

	/**
	 * Records a failed install or activation and returns the error to throw.
	 *
	 * @param {'install'|'activate'} action The step that failed.
	 * @param {*}                    err    The error thrown by apiFetch, or the unexpected response.
	 * @return {Error} The error to re-throw to the caller, with the failed step in `action`.
	 */
	const fail = (action, err) => {
		const { message, details } = describeExtensionError(err, action);

		updateStatus(
			'install' === action
				? __('Installation failed', 'wp-graphql')
				: __('Activation failed', 'wp-graphql'),
			{ action, message, details }
		);

		const failure = new Error(message);
		failure.action = action;
		failure.cause = err;

		return failure;
	};

	// Helper function to update the plugin's activation state in wpgraphqlExtensions
	const updateExtensionStatus = (isActive) => {
		window.wpgraphqlExtensions.extensions =
			window.wpgraphqlExtensions.extensions.map((extension) =>
				extension.plugin_url === pluginUrl
					? { ...extension, installed: true, active: isActive }
					: extension
			);
	};

	const activatePlugin = async (path = pluginPath) => {
		setActivating(true);
		updateStatus(__('Activating…', 'wp-graphql'));

		if (!path) {
			const slug = new URL(pluginUrl).pathname
				.split('/')
				.filter(Boolean)
				.pop();
			// Prefer the declared main file: the directory slug and the file name differ for some plugins.
			path = `${slug}/${pluginFile || `${slug}.php`}`;
		}

		try {
			const activateResult = await apiFetch({
				path: `/wp/v2/plugins/${path}`,
				method: 'PUT',
				data: { status: 'active' },
				headers: {
					'X-WP-Nonce': wpgraphqlExtensions.nonce,
				},
			});

			if (activateResult?.status !== 'active') {
				// A successful response that does not confirm activation: report its message, if it has one.
				const unexpected = new Error(
					'string' === typeof activateResult?.message
						? activateResult.message
						: ''
				);
				unexpected.status = 'error';
				throw unexpected;
			}

			updateStatus(__('Active', 'wp-graphql'));
			updateExtensionStatus(true);
			return true; // Indicate success
		} catch (err) {
			throw fail('activate', err); // Re-throw error to handle in PluginCard
		} finally {
			setInstalling(false);
			setActivating(false);
		}
	};

	const installPlugin = async () => {
		setInstalling(true);
		updateStatus(__('Installing…', 'wp-graphql'));

		const slug = new URL(pluginUrl).pathname
			.split('/')
			.filter(Boolean)
			.pop();

		let installResult;

		try {
			installResult = await apiFetch({
				path: '/wp/v2/plugins',
				method: 'POST',
				data: {
					slug,
					status: 'inactive',
				},
				headers: {
					'X-WP-Nonce': wpgraphqlExtensions.nonce,
				},
			});
		} catch (err) {
			// The plugin is already on disk (e.g. an earlier attempt installed it), so activate it instead.
			if (isFolderExistsError(err)) {
				return activatePlugin(pluginPath);
			}

			setInstalling(false);
			throw fail('install', err); // Re-throw error to handle in PluginCard
		}

		if (installResult?.status !== 'inactive') {
			setInstalling(false);
			throw fail('install', installResult);
		}

		// The REST response names the installed plugin ("dir/file" without the .php suffix),
		// which is more reliable than guessing the path from the slug.
		const installedPath = installResult.plugin
			? `${installResult.plugin}.php`
			: pluginPath;

		// Activation errors are reported (and re-thrown) by activatePlugin as activation failures.
		return activatePlugin(installedPath);
	};

	return {
		installing,
		activating,
		status,
		error,
		installPlugin,
		activatePlugin,
	};
};

export default useInstallPlugin;

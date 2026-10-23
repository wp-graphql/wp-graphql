import { __, sprintf } from '@wordpress/i18n';

/**
 * Returns the details for the button based on the plugin status and host.
 *
 * @param {string}   host           - The host name where the plugin is being used.
 * @param {string}   plugin_url     - The URL of the plugin.
 * @param {boolean}  isInstalled    - Whether the plugin is installed.
 * @param {boolean}  isActive       - Whether the plugin is active.
 * @param {boolean}  installing     - Whether the plugin is currently being installed.
 * @param {boolean}  activating     - Whether the plugin is currently being activated.
 * @param {Function} activatePlugin - Function to activate the plugin.
 * @return {{buttonText: string, buttonDisabled: boolean, buttonOnClick: Function|null}} The button details.
 */
export const getButtonDetails = (
	host,
	plugin_url,
	isInstalled,
	isActive,
	installing,
	activating,
	activatePlugin
) => {
	let buttonText;
	let buttonDisabled = false;
	let buttonOnClick = null;

	/**
	 * Opens a new browser window with the specified URL.
	 *
	 * @param {string} url - The URL to open.
	 * @return {Function} A function that opens the URL in a new window.
	 */
	const openLink = (url) => () => window.open(url, '_blank');

	if (installing) {
		buttonText = __('Installing…', 'wp-graphql');
		buttonDisabled = true;
	} else if (activating) {
		buttonText = __('Activating…', 'wp-graphql');
		buttonDisabled = true;
	} else if (isActive) {
		buttonText = __('Active', 'wp-graphql');
		buttonDisabled = true;
	} else if (isInstalled) {
		buttonText = __('Activate', 'wp-graphql');
		buttonOnClick = activatePlugin;
	} else {
		const domain = new URL(plugin_url).hostname.toLowerCase();

		switch (true) {
			case /github\.com$/.test(domain):
				buttonText = __('View on GitHub', 'wp-graphql');
				buttonOnClick = openLink(plugin_url);
				break;
			case /bitbucket\.org$/.test(domain):
				buttonText = __('View on Bitbucket', 'wp-graphql');
				buttonOnClick = openLink(plugin_url);
				break;
			case /gitlab\.com$/.test(domain):
				buttonText = __('View on GitLab', 'wp-graphql');
				buttonOnClick = openLink(plugin_url);
				break;
			case /wordpress\.org$/.test(domain):
				buttonText = __('Install & Activate', 'wp-graphql');
				buttonOnClick = activatePlugin;
				break;
			default:
				buttonText = __('View Plugin', 'wp-graphql');
				buttonOnClick = openLink(plugin_url);
		}
	}

	return { buttonText, buttonDisabled, buttonOnClick };
};

/**
 * Error codes whose message, as returned by WordPress, already explains the failure in plain language.
 *
 * For these, the message is shown in the card's headline instead of a generic one.
 *
 * @type {string[]}
 */
const READABLE_ERROR_CODES = [
	'plugins_api_failed', // e.g. "Plugin not found." from WordPress.org.
	'rest_cannot_install_plugin',
	'rest_cannot_activate_plugin',
	'rest_forbidden',
	'fs_unavailable',
	'unable_to_connect_to_filesystem',
];

/**
 * Matches the wording PHP and WordPress use when a PHP error stops a request.
 *
 * @type {RegExp}
 */
const PHP_ERROR_PATTERN =
	/\b(?:uncaught [a-z]*(?:error|exception)|fatal error|parse error|critical error)\b/i;

/**
 * Converts a message from an error response to plain text.
 *
 * Error messages can contain HTML (WordPress's fatal error handler wraps its message in
 * paragraphs and a link). They are only ever rendered as text, so the markup is stripped
 * to keep them readable.
 *
 * @param {*} value The value to convert.
 * @return {string} The plain text, or an empty string when the value is not a string.
 */
const toPlainText = (value) =>
	'string' === typeof value
		? value
				.replace(/<[^>]*>/g, ' ')
				.replace(/\s+/g, ' ')
				.trim()
		: '';

/**
 * Whether an install error means the plugin's folder already exists on disk.
 *
 * The plugin is already installed (for example, by an earlier attempt whose activation
 * failed), so the caller can go straight to activating it.
 *
 * @param {*} error The error thrown by apiFetch.
 * @return {boolean} Whether the destination folder already exists.
 */
export const isFolderExistsError = (error) =>
	'folder_exists' === error?.code ||
	/destination folder already exists/i.test(toPlainText(error?.message));

/**
 * Builds the technical details of an install or activation error, for debugging.
 *
 * Includes the error message, the PHP error that WordPress's fatal error handler attaches
 * to its response (message, file and line) and the error code.
 *
 * @param {*} error The error thrown by apiFetch, or the unexpected response.
 * @return {string} The details as plain text, one item per line. Empty when there are none.
 */
export const getErrorDetails = (error) => {
	if ('string' === typeof error) {
		return toPlainText(error);
	}

	if (!error || 'object' !== typeof error) {
		return '';
	}

	const lines = [];
	const message = toPlainText(error.message);

	if (message) {
		lines.push(message);
	}

	const phpError = error.data?.error;
	const phpMessage = toPlainText(phpError?.message);

	if (phpMessage) {
		lines.push(
			phpError.file
				? sprintf(
						/* translators: 1: PHP error message, 2: file path, 3: line number. */
						__('%1$s in %2$s on line %3$s', 'wp-graphql'),
						phpMessage,
						toPlainText(String(phpError.file)),
						String(phpError.line ?? '?')
					)
				: phpMessage
		);
	}

	if ('string' === typeof error.code && error.code) {
		lines.push(
			sprintf(
				/* translators: %s: error code, e.g. "invalid_json". */
				__('Error code: %s', 'wp-graphql'),
				error.code
			)
		);
	}

	return lines.join('\n');
};

/**
 * Describes a failed install or activation in a short, readable message.
 *
 * The original error is kept in `details` for debugging but is never the headline: when a
 * site prints PHP output during the request, or a PHP error stops it, the raw text is a
 * PHP error or a JSON parse error that does not tell the user what happened.
 *
 * @param {*}                    error  The error thrown by apiFetch, or the unexpected response.
 * @param {'install'|'activate'} action The step that failed.
 * @return {{message: string, details: string}} The readable message and the technical details.
 */
export const describeExtensionError = (error, action) => {
	const isInstall = 'install' === action;
	const code = 'string' === typeof error?.code ? error.code : '';

	// apiFetch could not parse the response: something printed output (usually a PHP error or notice) into it.
	if ('invalid_json' === code) {
		return {
			message: isInstall
				? __(
						'The installation could not be confirmed because the site did not return a valid JSON response. This usually means a PHP error or notice was printed during the request.',
						'wp-graphql'
					)
				: __(
						'The activation could not be confirmed because the site did not return a valid JSON response. This usually means a PHP error or notice was printed during the request.',
						'wp-graphql'
					),
			details: getErrorDetails(error),
		};
	}

	const rawMessage = toPlainText(
		'string' === typeof error ? error : error?.message
	);

	// WordPress's fatal error handler responds with `internal_server_error` and the PHP error in `data.error`.
	if (
		'internal_server_error' === code ||
		error?.data?.error?.message ||
		PHP_ERROR_PATTERN.test(rawMessage)
	) {
		return {
			message: isInstall
				? __(
						'A PHP error on this site stopped the installation.',
						'wp-graphql'
					)
				: __(
						'A PHP error on this site stopped the activation.',
						'wp-graphql'
					),
			details: getErrorDetails(error),
		};
	}

	// apiFetch could not reach the site at all.
	if ('fetch_error' === code) {
		return {
			message: __(
				'The request could not reach your site. Check your connection and try again.',
				'wp-graphql'
			),
			details: getErrorDetails(error),
		};
	}

	/*
	 * WordPress messages that already explain the failure. The activation route responds
	 * with `status: "error"` and the message of the error returned by activate_plugin().
	 */
	const isReadable =
		READABLE_ERROR_CODES.includes(code) ||
		(!isInstall && 'error' === error?.status);

	if (isReadable && rawMessage) {
		return {
			message: isInstall
				? sprintf(
						/* translators: %s: error message from WordPress, e.g. "Plugin not found." */
						__('Installation failed: %s', 'wp-graphql'),
						rawMessage
					)
				: sprintf(
						/* translators: %s: error message from WordPress, e.g. "Plugin file does not exist." */
						__('Activation failed: %s', 'wp-graphql'),
						rawMessage
					),
			// The message is already in the headline.
			details: '',
		};
	}

	return {
		message: isInstall
			? __('Installation failed.', 'wp-graphql')
			: __('Activation failed.', 'wp-graphql'),
		details: getErrorDetails(error),
	};
};

/**
 * Returns the Plugins screen where the user can install or activate a plugin themselves.
 *
 * Offered when installing or activating from the Extensions page fails. Uses the URLs
 * localized by PHP, falling back to paths relative to the current admin page.
 *
 * @param {'install'|'activate'} action    The step that failed.
 * @param {string}               pluginUrl The plugin's URL on WordPress.org.
 * @return {string} The URL of the Plugins screen.
 */
export const getPluginsScreenUrl = (action, pluginUrl) => {
	const localized = window.wpgraphqlExtensions || {};

	if ('install' !== action) {
		return new URL(
			localized.pluginsUrl || 'plugins.php',
			window.location.href
		).toString();
	}

	const url = new URL(
		localized.pluginInstallUrl || 'plugin-install.php',
		window.location.href
	);
	const slug = new URL(pluginUrl).pathname.split('/').filter(Boolean).pop();

	url.searchParams.set('s', slug || '');
	url.searchParams.set('tab', 'search');
	url.searchParams.set('type', 'term');

	return url.toString();
};

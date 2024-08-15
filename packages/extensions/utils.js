import { __ } from '@wordpress/i18n';

/**
 * Returns the details for the button based on the plugin status and host.
 *
 * @param {string} host - The host name where the plugin is being used.
 * @param {string} plugin_url - The URL of the plugin.
 * @param {boolean} isInstalled - Whether the plugin is installed.
 * @param {boolean} isActive - Whether the plugin is active.
 * @param {boolean} installing - Whether the plugin is currently being installed.
 * @param {boolean} activating - Whether the plugin is currently being activated.
 * @param {Function} activatePlugin - Function to activate the plugin.
 * @returns {{buttonText: string, buttonDisabled: boolean, buttonOnClick: Function|null}} The button details.
 */
export const getButtonDetails = (host, plugin_url, isInstalled, isActive, installing, activating, activatePlugin) => {
    let buttonText;
    let buttonDisabled = false;
    let buttonOnClick = null;

    /**
     * Opens a new browser window with the specified URL.
     *
     * @param {string} url - The URL to open.
     * @returns {Function} A function that opens the URL in a new window.
     */
    const openLink = (url) => () => window.open(url, '_blank');

    if (installing) {
        buttonText = __('Installing...', 'wp-graphql');
        buttonDisabled = true;
    } else if (activating) {
        buttonText = __('Activating...', 'wp-graphql');
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

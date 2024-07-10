import { __ } from '@wordpress/i18n';

export const getButtonDetails = (host, plugin_url, isInstalled, isActive, installing, activating, activatePlugin) => {
    let buttonText;
    let buttonDisabled = false;
    let buttonOnClick;

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
        // Using `true` for readability to handle multiple `case` conditions
        switch (true) {
            case host.includes('github.com'):
                buttonText = __('View on GitHub', 'wp-graphql');
                buttonOnClick = openLink(plugin_url);
                break;
            case host.includes('bitbucket.org'):
                buttonText = __('View on Bitbucket', 'wp-graphql');
                buttonOnClick = openLink(plugin_url);
                break;
            case host.includes('gitlab.com'):
                buttonText = __('View on GitLab', 'wp-graphql');
                buttonOnClick = openLink(plugin_url);
                break;
            case host.includes('wordpress.org'):
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

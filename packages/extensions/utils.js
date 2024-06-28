import { __ } from '@wordpress/i18n';

export const getButtonDetails = (host, plugin_url, isInstalled, isActive, installing, activating, activatePlugin) => {
	let buttonText;
	let buttonDisabled = false;
	let buttonOnClick;

	if (host.includes('github.com')) {
		buttonText = __( 'View on GitHub', 'wp-graphql' );
		buttonOnClick = () => window.open(plugin_url, '_blank');
	} else if (host.includes('bitbucket.org')) {
		buttonText = __( 'View on Bitbucket', 'wp-graphql' );
		buttonOnClick = () => window.open(plugin_url, '_blank');
	} else if (host.includes('gitlab.com')) {
		buttonText = __( 'View on GitLab', 'wp-graphql' );
		buttonOnClick = () => window.open(plugin_url, '_blank');
	} else if (installing) {
		buttonText = __( 'Installing...', 'wp-graphql' );
		buttonDisabled = true;
	} else if (activating) {
		buttonText = __( 'Activating...', 'wp-graphql' );
		buttonDisabled = true;
	} else if (isActive) {
		buttonText = __( 'Active', 'wp-graphql' );
		buttonDisabled = true;
	} else if (isInstalled) {
		buttonText = __( 'Activate', 'wp-graphql' );
		buttonOnClick = activatePlugin;
	} else if (host.includes('wordpress.org')) {
		buttonText = __( 'Install & Activate', 'wp-graphql' );
		buttonOnClick = activatePlugin;
	} else {
		buttonText = __( 'View Plugin', 'wp-graphql' );
		buttonOnClick = () => window.open(plugin_url, '_blank');
	}

	return { buttonText, buttonDisabled, buttonOnClick };
};

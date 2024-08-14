import { createRoot, createElement } from '@wordpress/element';
import { createHooks, addAction } from "@wordpress/hooks";
import { createNotice } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import { getPluginByURL } from './utils';
import Extensions from './Extensions';
import './index.scss';

/**
 * Initialize the hooks.
 */
export const hooks = createHooks();

/**
 * Mount the Extensions component.
 */
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('wpgraphql-extensions');
    if (container) {
        const root = createRoot(container);
        root.render(createElement(Extensions));
    }
});


const displayAdminNotice = (pluginUrl) => {
    const pluginData = getPluginByURL(pluginUrl);
    const pluginName = pluginData.name || __('Plugin', 'wp-graphql');

    createNotice(
        'success',
        pluginName + ' ' + __('activated.', 'wp-graphql'),
        {
            isDismissible: true,
        }
    );
};

// Register the action
addAction('pluginActivated', 'my-plugin/displayAdminNotice', displayAdminNotice);
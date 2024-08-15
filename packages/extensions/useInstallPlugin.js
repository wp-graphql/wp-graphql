import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const useInstallPlugin = (pluginUrl, pluginPath) => {
    const [installing, setInstalling] = useState(false);
    const [activating, setActivating] = useState(false);
    const [status, setStatus] = useState('');
    const [error, setError] = useState('');

    // Helper function to update the status and error states
    const updateStatus = (newStatus, newError = '') => {
        setStatus(newStatus);
        setError(newError);
    };

    // Helper function to update the plugin's activation state in wpgraphqlExtensions
    const updateExtensionStatus = (isActive) => {
        window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map((extension) =>
            extension.plugin_url === pluginUrl ? { ...extension, installed: true, active: isActive } : extension
        );
    };

    const activatePlugin = async (path = pluginPath) => {
        setActivating(true);
        updateStatus(__('Activating...', 'wp-graphql'));
    
        if (!path) {
            let slug = new URL(pluginUrl).pathname.split('/').filter(Boolean).pop();
            path = `${slug}/${slug}.php`;
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
    
            const jsonResponse = activateResult;
    
            if (jsonResponse.status === 'active') {
                updateStatus(__('Active', 'wp-graphql'));
                updateExtensionStatus(true);
                return true;  // Indicate success
            } else if (jsonResponse.message.includes('Plugin file does not exist')) {
                throw new Error(__('Plugin file does not exist', 'wp-graphql'));
            } else {
                throw new Error(__('Activation failed', 'wp-graphql'));
            }
        } catch (err) {
            updateStatus(__('Activation failed', 'wp-graphql'), err.message || __('Activation failed', 'wp-graphql'));
            throw err;  // Re-throw error to handle in PluginCard
        } finally {
            setInstalling(false);
            setActivating(false);
        }
    };
    
    const installPlugin = async () => {
        setInstalling(true);
        updateStatus(__('Installing...', 'wp-graphql'));
    
        let slug = new URL(pluginUrl).pathname.split('/').filter(Boolean).pop();
    
        try {
            const installResult = await apiFetch({
                path: '/wp/v2/plugins',
                method: 'POST',
                data: {
                    slug: slug,
                    status: 'inactive',
                },
                headers: {
                    'X-WP-Nonce': wpgraphqlExtensions.nonce,
                },
            });
    
            if (installResult.status !== 'inactive') {
                throw new Error(__('Installation failed', 'wp-graphql'));
            }
    
            await activatePlugin(pluginPath);
        } catch (err) {
            if (err.message.includes('destination folder already exists')) {
                await activatePlugin(pluginPath);
            } else {
                updateStatus(__('Installation failed', 'wp-graphql'), err.message || __('Installation failed', 'wp-graphql'));
                setInstalling(false);
                throw err;  // Re-throw error to handle in PluginCard
            }
        }
    };    

    return { installing, activating, status, error, installPlugin, activatePlugin };
};

export default useInstallPlugin;

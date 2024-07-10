import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const useInstallPlugin = (pluginUrl, pluginPath) => {
    const [installing, setInstalling] = useState(false);
    const [activating, setActivating] = useState(false);
    const [status, setStatus] = useState('');
    const [error, setError] = useState('');

    const installPlugin = async () => {
        setInstalling(true);
        setStatus(__('Installing...', 'wp-graphql'));
        setError('');

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
                setStatus(__('Installation failed', 'wp-graphql'));
                setError(err.message || __('Installation failed', 'wp-graphql'));
                setInstalling(false);
            }
        }
    };

    const activatePlugin = async (path = null) => {
        setActivating(true);
        setStatus(__('Activating...', 'wp-graphql'));
        setError('');

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
                setStatus(__('Active', 'wp-graphql'));
                window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map((extension) =>
                    extension.plugin_url === pluginUrl ? { ...extension, installed: true, active: true } : extension
                );
            } else if (jsonResponse.message.includes('Plugin file does not exist')) {
                // The plugin is already activated
                setStatus(__('Active', 'wp-graphql'));
                setError('');
                window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map((extension) =>
                    extension.plugin_url === pluginUrl ? { ...extension, installed: true, active: true } : extension
                );
            } else {
                throw new Error(__('Activation failed', 'wp-graphql'));
            }
        } catch (err) {
            setStatus(__('Activation failed', 'wp-graphql'));
            setError(err.message || __('Activation failed', 'wp-graphql'));
        } finally {
            setInstalling(false);
            setActivating(false);
        }
    };

    return { installing, activating, status, error, installPlugin, activatePlugin };
};

export default useInstallPlugin;

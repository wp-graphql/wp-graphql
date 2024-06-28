import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const useInstallPlugin = (pluginUrl, logNetworkActivity) => {
    const [installing, setInstalling] = useState(false);
    const [activating, setActivating] = useState(false);
    const [status, setStatus] = useState('');
    const [error, setError] = useState('');

    const installPlugin = async () => {
        setInstalling(true);
        setStatus(__('Installing...', 'wp-graphql'));
        setError('');

        let slug = new URL(pluginUrl).pathname.split('/').filter(Boolean).pop();
        if (slug === 'wpgraphql-smart-cache') {
            slug = 'wp-graphql-smart-cache';
        }

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

            logNetworkActivity({ type: 'install', slug, result: installResult });

            if (installResult.status !== 'inactive') {
                throw new Error(__('Installation failed', 'wp-graphql'));
            }

            await activatePlugin();
        } catch (err) {
            logNetworkActivity({ type: 'install', slug, error: err.message });

            if (err.message.includes('destination folder already exists')) {
                await activatePlugin();
            } else {
                setStatus(__('Installation failed', 'wp-graphql'));
                setError(err.message || __('Installation failed', 'wp-graphql'));
                setInstalling(false);
            }
        }
    };

    const activatePlugin = async () => {
        setActivating(true);
        setStatus(__('Activating...', 'wp-graphql'));
        setError('');

        let slug = new URL(pluginUrl).pathname.split('/').filter(Boolean).pop();
        if (slug === 'wpgraphql-smart-cache') {
            slug = 'wp-graphql-smart-cache';
        }

        try {
            const activateResult = await apiFetch({
                path: `/wp/v2/plugins/${slug}`,
                method: 'PUT',
                data: { status: 'active' },
                headers: {
                    'X-WP-Nonce': wpgraphqlExtensions.nonce,
                },
            });

            const responseText = await activateResult.text();

            logNetworkActivity({ type: 'activate', slug, result: activateResult, responseText });

            try {
                const jsonResponse = JSON.parse(responseText);
                if (jsonResponse.status === 'active') {
                    setStatus(__('Active', 'wp-graphql'));
                    window.wpgraphqlExtensions.extensions = window.wpgraphqlExtensions.extensions.map((extension) =>
                        extension.plugin_url === pluginUrl ? { ...extension, installed: true, active: true } : extension
                    );
                } else {
                    throw new Error(__('Activation failed', 'wp-graphql'));
                }
            } catch (parseError) {
                throw new Error(__('The response is not a valid JSON response.', 'wp-graphql'));
            }
        } catch (err) {
            logNetworkActivity({ type: 'activate', slug, error: err.message, responseText: err.responseText });

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

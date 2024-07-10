import { __ } from '@wordpress/i18n';
import { Button, Spinner, Notice } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import useInstallPlugin from './useInstallPlugin';
import { getButtonDetails } from './utils';

const PluginCard = ({ plugin }) => {
    const { installing, activating, status, error, installPlugin, activatePlugin } = useInstallPlugin(plugin.plugin_url, plugin.plugin_path);
    const [isInstalled, setIsInstalled] = useState(plugin.installed);
    const [isActive, setIsActive] = useState(plugin.active);
    const [isErrorVisible, setIsErrorVisible] = useState(true);

    useEffect(() => {
        setIsInstalled(plugin.installed);
        setIsActive(plugin.active);
    }, [plugin]);

    const handleButtonClick = async () => {
        if (!isInstalled) {
            await installPlugin();
        } else {
            await activatePlugin();
        }

        setIsInstalled(true);
        setIsActive(true);
    };

    const host = new URL(plugin.plugin_url).host;
    const { buttonText, buttonDisabled } = getButtonDetails(host, plugin.plugin_url, isInstalled, isActive, installing, activating);

    return (
        <div className="plugin-card">
            <div className="plugin-card-top">
                <div className="name column-name">
                    <h2>{plugin.name}</h2>
                    {plugin.experiment && <em className="plugin-experimental">(experimental)</em>}
                </div>
                <div className="action-links">
                    <ul className="plugin-action-buttons">
                        {host.includes('wordpress.org') && (
                            <li>
                                <button
                                    type="button"
                                    className={`button ${isActive ? 'button-disabled' : 'button-primary'}`}
                                    disabled={buttonDisabled}
                                    onClick={handleButtonClick}
                                >
                                    {buttonText}
                                    {(installing || activating) && <Spinner />}
                                </button>
                            </li>
                        )}
                        {host.includes('github.com') && (
                            <li>
                                <a
                                    href={plugin.plugin_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="button button-secondary"
                                >
                                    {__('View on GitHub', 'wp-graphql')}
                                </a>
                            </li>
                        )}
                        {plugin.support_link && (
                            <li>
                                <a
                                    href={plugin.support_link}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="thickbox open-plugin-details-modal"
                                >
                                    {__('Get Support', 'wp-graphql')}
                                </a>
                            </li>
                        )}
                        {plugin.settings_link && (
                            <li>
                                <a href={plugin.settings_link}>{__('Settings', 'wp-graphql')}</a>
                            </li>
                        )}
                    </ul>
                </div>
                <div className="desc column-description">
                    <p>{plugin.description}</p>
                </div>
            </div>
            {error && isErrorVisible && (
                <Notice
                    status="error"
                    isDismissible
                    onRemove={() => setIsErrorVisible(false)}
                >
                    {error}
                </Notice>
            )}
        </div>
    );
};

export default PluginCard;

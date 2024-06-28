import { __ } from '@wordpress/i18n';
import useInstallPlugin from './useInstallPlugin';
import { getButtonDetails } from './utils';

const PluginCard = ({ plugin, logNetworkActivity }) => {
    const { installing, activating, status, error, installPlugin, activatePlugin } = useInstallPlugin(plugin.plugin_url, logNetworkActivity);
    const isInstalled = plugin.installed;
    const isActive = plugin.active;

    const host = new URL(plugin.plugin_url).host;
    const { buttonText, buttonDisabled, buttonOnClick } = getButtonDetails(host, plugin.plugin_url, isInstalled, isActive, installing, activating, activatePlugin);

    return (
        <div className="plugin-card">
            <div className="plugin-card-top">
                <div className="name column-name">
                    <h2>{ plugin.name }</h2>
                    { plugin.experiment && <em className="plugin-experimental">(experimental)</em> }
                </div>
                <div className="action-links">
                    <ul className="plugin-action-buttons">
                        <li>
                            <button
                                type="button"
                                className={`button ${isActive ? 'button-disabled' : 'button-primary'}`}
                                disabled={buttonDisabled}
                                onClick={buttonOnClick}
                            >
                                {buttonText}
                            </button>
                        </li>
                        {plugin.support_link && (
                            <li>
                                <a
                                    href={plugin.support_link}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="thickbox open-plugin-details-modal"
                                >
                                    {__( 'Get Support', 'wp-graphql' )}
                                </a>
                            </li>
                        )}
                        {plugin.settings_link && (
                            <li>
                                <a href={plugin.settings_link}>{__( 'Settings', 'wp-graphql' )}</a>
                            </li>
                        )}
                    </ul>
                </div>
                <div className="desc column-description">
                    <p>{plugin.description}</p>
                </div>
            </div>
            {status && <div className="notice notice-error is-dismissible"><p>{status}</p></div>}
            {error && <div className="notice notice-error is-dismissible"><p>{error}</p></div>}
        </div>
    );
};

export default PluginCard;

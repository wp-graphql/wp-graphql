import { __ } from '@wordpress/i18n';
import { useEffect, useState } from 'react';
import PluginCard from './PluginCard';
import DebugPanel from './DebugPanel';

/**
 * Extensions component to display the list of WPGraphQL extensions
 *
 * @return {JSX.Element} The Extensions component
 */
const Extensions = () => {
    const [extensions, setExtensions] = useState([]);
    const [networkLogs, setNetworkLogs] = useState([]);

    useEffect(() => {
        if (window.wpgraphqlExtensions && window.wpgraphqlExtensions.extensions) {
            setExtensions(window.wpgraphqlExtensions.extensions);
        }
    }, []);

    const logNetworkActivity = (log) => {
        setNetworkLogs((prevLogs) => [...prevLogs, log]);
    };

    return (
        <div className="wp-clearfix">
            <DebugPanel networkLogs={networkLogs} />
            {extensions.map((extension) => (
                <PluginCard key={extension.plugin_url} plugin={extension} logNetworkActivity={logNetworkActivity} />
            ))}
        </div>
    );
};

export default Extensions;

import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import PluginCard from './PluginCard';

/**
 * Extensions component to display the list of WPGraphQL extensions.
 *
 * @return {JSX.Element} The Extensions component.
 */
const Extensions = () => {
    const [extensions, setExtensions] = useState([]);

    useEffect(() => {
        if (window.wpgraphqlExtensions && window.wpgraphqlExtensions.extensions) {
            setExtensions(window.wpgraphqlExtensions.extensions);
        }
    }, []);

    return (
        <div className="wp-clearfix">
            <div className="plugin-cards">
                {extensions.map((extension) => (
                    <PluginCard key={extension.plugin_url} plugin={extension} />
                ))}
            </div>
        </div>
    );
};

export default Extensions;

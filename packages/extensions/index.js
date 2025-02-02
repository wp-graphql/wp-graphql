import { createRoot, createElement } from '@wordpress/element';
import Extensions from './Extensions';
import './index.scss';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('wpgraphql-extensions');
    if (container) {
        const root = createRoot(container);
        root.render(createElement(Extensions));
    }
});

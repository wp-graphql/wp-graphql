import React from 'react';

const DebugPanel = ({ networkLogs }) => {
    return (
        <details className="debug-panel">
            <summary>Debug Information</summary>
            <h2>Network Logs</h2>
            <pre>{JSON.stringify(networkLogs, null, 2)}</pre>
        </details>
    );
};

export default DebugPanel;

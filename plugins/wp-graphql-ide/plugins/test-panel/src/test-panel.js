/**
 * Test Panel — internal plugin that dogfoods the workspace tab API.
 *
 * Registers an activity bar panel and a custom workspace tab type.
 * Clicking "Open Test Tab" in the sidebar opens a full-workspace tab
 * rendered by this plugin instead of the query editor.
 */
import { Button } from '@wordpress/components';

const TestTabContent = () => (
	<div
		style={{
			display: 'flex',
			flexDirection: 'column',
			alignItems: 'center',
			justifyContent: 'center',
			height: '100%',
			gap: '16px',
			color: '#1e1e1e',
			fontSize: '14px',
		}}
	>
		<div
			style={{
				width: 48,
				height: 48,
				borderRadius: '50%',
				background: '#007cba',
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
				color: '#fff',
				fontSize: 24,
				fontWeight: 'bold',
			}}
		>
			T
		</div>
		<h2 style={{ margin: 0, fontSize: 18 }}>Test Workspace Tab</h2>
		<p
			style={{
				margin: 0,
				color: '#757575',
				maxWidth: 400,
				textAlign: 'center',
			}}
		>
			This tab was opened by the <strong>test-panel</strong> plugin using
			the <code>openWorkspaceTab</code> API. It replaces the query editor
			workspace with custom content.
		</p>
		<p style={{ margin: 0, color: '#a7aaad', fontSize: 12 }}>
			Tab type: <code>test-panel</code>
		</p>
	</div>
);

const TestPanelSidebar = () => {
	const { openWorkspaceTab } = window.WPGraphQLIDE;

	return (
		<div style={{ padding: '12px 8px' }}>
			<p style={{ margin: '0 0 12px', color: '#757575', fontSize: 13 }}>
				Internal test plugin for the workspace tab API.
			</p>
			<Button
				variant="secondary"
				onClick={() =>
					openWorkspaceTab('test-panel', {
						id: 'test-panel-singleton',
						title: 'Test Tab',
					})
				}
				style={{ width: '100%', justifyContent: 'center' }}
			>
				Open Test Tab
			</Button>
		</div>
	);
};

const TestPanelIcon = () => (
	<svg
		viewBox="0 0 24 24"
		width="24"
		height="24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
	>
		<path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18" />
	</svg>
);

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const { registerActivityBarPanel, registerWorkspaceTabType } =
		window.WPGraphQLIDE;

	// Register the custom workspace tab type.
	if (typeof registerWorkspaceTabType === 'function') {
		registerWorkspaceTabType('test-panel', {
			title: 'Test Tab',
			content: TestTabContent,
		});
	}

	// Register the activity bar panel (sidebar).
	if (typeof registerActivityBarPanel === 'function') {
		registerActivityBarPanel(
			'test-panel',
			{
				title: 'Test Panel',
				icon: TestPanelIcon,
				content: TestPanelSidebar,
			},
			99
		);
	}
});

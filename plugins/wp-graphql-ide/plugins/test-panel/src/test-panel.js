/**
 * Test Tabbed Workspacebed Workspace — internal plugin that dogfoods the workspace tab API.
 *
 * Registers a workspace tab type and an activity bar action button.
 * Clicking the button directly opens a workspace tab.
 */
import { Icon, plugins as pluginsIcon } from '@wordpress/icons';

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
		<h2 style={{ margin: 0, fontSize: 18 }}>Test Tabbed Workspace</h2>
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

const TestPanelIcon = () => <Icon icon={pluginsIcon} />;

window.addEventListener('WPGraphQLIDE_Window_Ready', () => {
	if (!window.WPGraphQLIDE) {
		return;
	}

	const {
		registerActivityBarPanel,
		registerWorkspaceTabType,
		openWorkspaceTab,
	} = window.WPGraphQLIDE;

	// Register the custom workspace tab type.
	if (typeof registerWorkspaceTabType === 'function') {
		registerWorkspaceTabType('test-panel', {
			title: 'Test Tabbed Workspace',
			content: TestTabContent,
		});
	}

	// Register an activity bar action that opens the tab directly.
	if (typeof registerActivityBarPanel === 'function') {
		registerActivityBarPanel(
			'test-panel',
			{
				title: 'Test Tabbed Workspacebed Workspace',
				icon: TestPanelIcon,
				action: () =>
					openWorkspaceTab('test-panel', {
						id: 'test-panel-singleton',
						title: 'Test Tabbed Workspace',
					}),
			},
			99
		);
	}
});

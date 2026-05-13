import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	ResizableBox,
	Tooltip,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { Icon, cog, listView, moreVertical } from '@wordpress/icons';
import { GraphQLEditor } from '../editors/GraphQLEditor';
import { DocumentNotices } from '../DocumentNotices';
import { DocumentSettingsDrawer } from '../document-settings/DocumentSettingsDrawer';
import { EditorToolbar } from '../EditorToolbar';
import { ExecutionControls } from './ExecutionControls';
import { LeftPanel } from './LeftPanel';
import { OverflowTabs } from '../OverflowTabs';
import { useResizeReporter } from '../ResizeOverlay';

/**
 * Minimum px height we'll allow the editor to be persisted at. Recovers
 * from a previous tiny-drag (or stale flex-mode height saved while the
 * bottom strip was hidden) leaving the editor unreadable on next visit.
 */
const MIN_EDITOR_HEIGHT_PX = 220;

/* eslint-disable jsdoc/require-param, jsdoc/require-param-type, jsdoc/require-param-description */
/**
 * Left half of the editor split: editor toolbar (composer/settings
 * toggles, kebab menu, save/publish), the GraphQL editor with optional
 * left panels (Query Composer or Document Settings), the floating
 * execution pill, and the bottom Variables/Headers tabs.
 *
 * Pure presentational orchestration — every interaction is forwarded
 * up via props. The pane manages its own resizable widths/heights via
 * `usePersistedSize` setters passed in from IDELayout. The prop list
 * mirrors the data flow IDELayout already had inline; see the destructure
 * below for the full set.
 */
export function EditorPane({
	// Endpoint mode (public-endpoint render): hides Save / Publish, the
	// share / rename / duplicate kebab items, and the Document Settings
	// toggle. Editor + variables/headers + execute pill stay visible.
	endpointMode = false,
	// Sizing
	queryPaneWidth,
	onSetQueryPaneWidth,
	editorHeight,
	onSetEditorHeight,
	// Document & query state
	activeDocument,
	activeDocDirty,
	isPublished,
	isSavedDraft,
	query,
	onQueryChange,
	parsedQuery,
	onSave,
	onPublish,
	onDuplicateAsDraft,
	// Toolbar dialogs / menu
	onOpenShareDialog,
	onOpenRenameDialog,
	addNotice,
	isTempId,
	// Schema + cm6 wiring
	schema,
	editorKeyBindings,
	onShowInDocs,
	onCursorChange,
	jumpRequest,
	onJumpApplied,
	// Left panel: Composer / Settings
	ComposerContent,
	showQueryComposer,
	toggleQueryComposer,
	onCloseLeftPanel,
	composerWidth,
	onSetComposerWidth,
	docSettingsFields,
	docSettingsValues,
	docSettingsGlobalGrant,
	onDocSettingChange,
	showDocSettingsPanel,
	toggleDocSettingsPanel,
	docSettingsPanelWidth,
	onSetDocSettingsPanelWidth,
	// Bottom Variables / Headers
	editorBottomTabs,
	variables,
	onVariablesChange,
	variableToType,
	headers,
	onHeadersChange,
	// Execution pill
	httpMethod,
	onSetHttpMethod,
	isAuthenticated,
	onToggleAuth,
	avatarUrl,
	operationNames,
	isFetching,
	isSchemaLoading,
	onExecute,
	signInUrl,
	showAuthControl,
	// Bottom strip collapse + active-tab state (controlled by IDELayout
	// so they persist alongside other UI-chrome device prefs).
	bottomCollapsed = false,
	onSetBottomCollapsed,
	bottomActiveTab,
	onSetBottomActiveTab,
}) {
	const hasComposer = !!ComposerContent && !isPublished;
	const hasLeftPanel =
		(hasComposer && showQueryComposer) || showDocSettingsPanel;

	const queryPaneReporter = useResizeReporter(
		__('Query pane', 'wpgraphql-ide')
	);
	const editorAreaReporter = useResizeReporter(__('Editor', 'wpgraphql-ide'));
	const bottomToolsReporter = useResizeReporter(
		__('Variables / Headers', 'wpgraphql-ide')
	);

	const editorActions = useSelect(
		(s) => s('wpgraphql-ide/editor-actions').editorActions(),
		[]
	);

	return (
		<ResizableBox
			size={{ width: queryPaneWidth, height: 'auto' }}
			minWidth={hasComposer && showQueryComposer ? 480 : 280}
			enable={{ right: true }}
			onResizeStart={queryPaneReporter.reportStart}
			onResize={queryPaneReporter.reportResize}
			onResizeStop={(e, d, elt) => {
				queryPaneReporter.reportStop();
				onSetQueryPaneWidth(elt.offsetWidth);
			}}
			className="wpgraphql-ide-query-pane"
		>
			<div className="wpgraphql-ide-editor-toolbar">
				{hasComposer && (
					<Tooltip
						text={
							showQueryComposer
								? __('Hide Query Composer', 'wpgraphql-ide')
								: __('Show Query Composer', 'wpgraphql-ide')
						}
					>
						<Button
							onClick={toggleQueryComposer}
							aria-label={
								showQueryComposer
									? __('Hide Query Composer', 'wpgraphql-ide')
									: __('Show Query Composer', 'wpgraphql-ide')
							}
							aria-pressed={showQueryComposer}
							size="compact"
							className={`wpgraphql-ide-toolbar-composer-btn${showQueryComposer ? ' is-active' : ''}`}
						>
							<Icon icon={listView} />
						</Button>
					</Tooltip>
				)}
				{!endpointMode && docSettingsFields.length > 0 && (
					<Tooltip
						text={
							showDocSettingsPanel
								? __('Hide Document Settings', 'wpgraphql-ide')
								: __('Show Document Settings', 'wpgraphql-ide')
						}
					>
						<Button
							onClick={toggleDocSettingsPanel}
							aria-label={
								showDocSettingsPanel
									? __(
											'Hide Document Settings',
											'wpgraphql-ide'
										)
									: __(
											'Show Document Settings',
											'wpgraphql-ide'
										)
							}
							aria-pressed={showDocSettingsPanel}
							size="compact"
							className={`wpgraphql-ide-toolbar-doc-settings-btn${showDocSettingsPanel ? ' is-active' : ''}`}
						>
							<Icon icon={cog} />
						</Button>
					</Tooltip>
				)}
				<span className="wpgraphql-ide-editor-label">
					{__('Query', 'wpgraphql-ide')}
				</span>
				<DropdownMenu
					icon={moreVertical}
					label={__('Editor actions', 'wpgraphql-ide')}
					toggleProps={{
						size: 'small',
						className: 'wpgraphql-ide-panel-kebab',
					}}
				>
					{({ onClose: closeMenu }) => {
						const ctx = {
							query,
							activeDocument,
							isPublished,
							isTempId,
							endpointMode,
							openShareDialog: onOpenShareDialog,
							openRenameDialog: onOpenRenameDialog,
							duplicateAsDraft: onDuplicateAsDraft,
							addNotice,
							closeMenu,
						};
						const visible = editorActions.filter((a) =>
							a.predicate ? a.predicate(ctx) : true
						);
						const groups = [];
						const groupIndex = new Map();
						for (const a of visible) {
							const key = a.group || '';
							if (!groupIndex.has(key)) {
								groupIndex.set(key, groups.length);
								groups.push({ label: key, items: [] });
							}
							groups[groupIndex.get(key)].items.push(a);
						}
						return (
							<>
								<MenuGroup>
									<EditorToolbar
										onClose={closeMenu}
										onNotice={addNotice}
										hideMutating={isPublished}
									/>
								</MenuGroup>
								{groups.map((g, i) => (
									<MenuGroup
										key={g.label || `group-${i}`}
										label={g.label || undefined}
									>
										{g.items.map((item) => {
											const handleClick = () => {
												item.onClick(ctx);
											};
											const labelText =
												typeof item.label === 'function'
													? item.label(ctx)
													: item.label;
											return (
												<MenuItem
													key={item.name}
													onClick={handleClick}
													isSelected={
														item.isSelected
															? item.isSelected(
																	ctx
																)
															: undefined
													}
													isDestructive={
														!!item.isDestructive
													}
													disabled={
														item.isDisabled
															? !!item.isDisabled(
																	ctx
																)
															: false
													}
												>
													{labelText}
												</MenuItem>
											);
										})}
									</MenuGroup>
								))}
							</>
						);
					}}
				</DropdownMenu>
				<div className="wpgraphql-ide-editor-toolbar-spacer" />
				{!endpointMode && !isPublished && (
					<>
						{(() => {
							// Temp drafts have never been saved to the
							// server — Save must be reachable regardless of
							// the dirty bullet (which is suppressed for
							// autopersisted temps).
							const isTemp =
								!!activeDocument?.id &&
								isTempId(activeDocument.id);
							const canSave = activeDocDirty || isTemp;
							return (
								<Button
									onClick={onSave}
									disabled={!canSave}
									size="compact"
									className={`wpgraphql-ide-save-button${canSave ? ' is-dirty' : ''}`}
								>
									{__('Save draft', 'wpgraphql-ide')}
								</Button>
							);
						})()}
						{isSavedDraft && query?.trim() && (
							<Tooltip
								text={
									!parsedQuery.parseable
										? __(
												'Fix the syntax error to publish',
												'wpgraphql-ide'
											)
										: ''
								}
							>
								<Button
									onClick={onPublish}
									disabled={!parsedQuery.parseable}
									size="compact"
									variant="primary"
									className="wpgraphql-ide-publish-button"
								>
									{__('Publish', 'wpgraphql-ide')}
								</Button>
							</Tooltip>
						)}
					</>
				)}
			</div>
			{/* The side panel sits outside the editor's height-resizable so
			    it spans both the editor and the Variables/Headers strip. */}
			<div
				className={`wpgraphql-ide-editor-with-side${hasLeftPanel ? ' has-left-panel' : ''}`}
			>
				{hasComposer && showQueryComposer && (
					<LeftPanel
						title={__('Query Composer', 'wpgraphql-ide')}
						className="wpgraphql-ide-query-composer-inline"
						width={composerWidth}
						onResize={onSetComposerWidth}
						minWidth={200}
						onClose={onCloseLeftPanel}
						closeLabel={__(
							'Close Query Composer panel',
							'wpgraphql-ide'
						)}
					>
						<ComposerContent />
					</LeftPanel>
				)}
				{showDocSettingsPanel && docSettingsFields.length > 0 && (
					<LeftPanel
						title={__('Document Settings', 'wpgraphql-ide')}
						className="wpgraphql-ide-doc-settings-inline"
						width={docSettingsPanelWidth}
						onResize={onSetDocSettingsPanelWidth}
						minWidth={240}
						onClose={onCloseLeftPanel}
						closeLabel={__(
							'Close Document Settings panel',
							'wpgraphql-ide'
						)}
					>
						<DocumentSettingsDrawer
							fields={docSettingsFields}
							values={docSettingsValues}
							onChange={onDocSettingChange}
							globalGrantMode={docSettingsGlobalGrant}
						/>
					</LeftPanel>
				)}
				<div className="wpgraphql-ide-editor-stack">
					{(() => {
						const editorBody = (
							<>
								{editorAreaReporter.indicator}
								{/* Notice rides inside the editor's height
								    so the bottom strip stays anchored
								    whether or not it's showing. */}
								<DocumentNotices
									isPublished={isPublished}
									onDuplicate={onDuplicateAsDraft}
								/>
								<div className="wpgraphql-ide-editor-resizable-body">
									<GraphQLEditor
										key={activeDocument?.id || 'empty'}
										className={
											isPublished ? 'is-readonly' : ''
										}
										value={query}
										onChange={onQueryChange}
										schema={schema}
										readOnly={isPublished}
										extraKeys={editorKeyBindings.current}
										onShowInDocs={onShowInDocs}
										onCursorChange={onCursorChange}
										jumpRequest={jumpRequest}
										onJumpApplied={onJumpApplied}
									/>
									<ExecutionControls
										query={query}
										httpMethod={httpMethod}
										onSetHttpMethod={onSetHttpMethod}
										isAuthenticated={isAuthenticated}
										onToggleAuth={onToggleAuth}
										avatarUrl={avatarUrl}
										operationNames={operationNames}
										isFetching={isFetching}
										isSchemaLoading={isSchemaLoading}
										onExecute={onExecute}
										// Anonymous visitors on the public
										// endpoint have no auth session to
										// toggle — the avatar becomes a
										// sign-in link to wp_login instead.
										signInUrl={signInUrl}
										showAuthControl={showAuthControl}
									/>
								</div>
							</>
						);
						// With the bottom strip collapsed there's nothing to
						// resize against, so drop the ResizableBox and let
						// the editor fill the remaining flex column.
						return bottomCollapsed ? (
							<div className="wpgraphql-ide-editor-resizable wpgraphql-ide-editor-resizable--filling">
								{editorBody}
							</div>
						) : (
							<ResizableBox
								size={{
									width: '100%',
									height: editorHeight,
								}}
								minHeight={MIN_EDITOR_HEIGHT_PX}
								enable={{ bottom: true }}
								onResizeStart={editorAreaReporter.reportStart}
								onResize={editorAreaReporter.reportResize}
								onResizeStop={(e, d, elt) => {
									editorAreaReporter.reportStop();
									onSetEditorHeight(elt.offsetHeight);
								}}
								className="wpgraphql-ide-editor-resizable wpgraphql-ide-resizable-split"
							>
								{editorBody}
							</ResizableBox>
						);
					})()}
					<div
						className={`wpgraphql-ide-editor-bottom${bottomCollapsed ? ' is-collapsed' : ''}`}
					>
						{bottomToolsReporter.indicator}
						<OverflowTabs
							className="wpgraphql-ide-editor-tools"
							tabs={editorBottomTabs.map((t) => ({
								name: t.name,
								title:
									typeof t.title === 'function'
										? t.title({
												query,
												variables,
												headers,
												activeDocument,
												variableToType,
											})
										: t.title || t.name,
							}))}
							initialTabName={
								bottomActiveTab || editorBottomTabs[0]?.name
							}
							activeTabName={bottomActiveTab || undefined}
							onActiveTabChange={onSetBottomActiveTab}
							collapsed={bottomCollapsed}
							onCollapse={() => onSetBottomCollapsed(true)}
							onExpand={(name) => {
								onSetBottomCollapsed(false);
								if (
									name &&
									(!bottomActiveTab ||
										bottomActiveTab !== name)
								) {
									onSetBottomActiveTab(name);
								}
							}}
						>
							{(tab) => {
								const ext = editorBottomTabs.find(
									(t) => t.name === tab.name
								);
								const ExtContent = ext?.content;
								if (!ExtContent) {
									return null;
								}
								return (
									<ExtContent
										key={tab.name}
										query={query}
										variables={variables}
										onVariablesChange={onVariablesChange}
										variableToType={variableToType}
										headers={headers}
										onHeadersChange={onHeadersChange}
										activeDocument={activeDocument}
										editorKeyBindings={editorKeyBindings}
									/>
								);
							}}
						</OverflowTabs>
					</div>
				</div>
			</div>
		</ResizableBox>
	);
}

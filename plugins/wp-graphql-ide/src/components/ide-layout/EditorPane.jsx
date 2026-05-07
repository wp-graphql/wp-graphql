import React from 'react';
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	ResizableBox,
	TabPanel,
	Tooltip,
} from '@wordpress/components';
import { Icon, cog, listView, moreVertical } from '@wordpress/icons';
import { GraphQLEditor } from '../editors/GraphQLEditor';
import { JSONEditor } from '../editors/JSONEditor';
import { DocumentNotices } from '../DocumentNotices';
import { DocumentSettingsDrawer } from '../document-settings/DocumentSettingsDrawer';
import { EditorToolbar } from '../EditorToolbar';
import { ExecutionPill } from './ExecutionPill';
import { LeftPanel } from './LeftPanel';

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
}) {
	const hasComposer = !!ComposerContent && !isPublished;
	const hasLeftPanel =
		(hasComposer && showQueryComposer) || showDocSettingsPanel;

	return (
		<ResizableBox
			size={{ width: queryPaneWidth, height: 'auto' }}
			minWidth={hasComposer && showQueryComposer ? 480 : 280}
			enable={{ right: true }}
			onResizeStop={(e, d, elt) => onSetQueryPaneWidth(elt.offsetWidth)}
			className="wpgraphql-ide-query-pane"
		>
			<div className="wpgraphql-ide-editor-toolbar">
				{hasComposer && (
					<Tooltip
						text={
							showQueryComposer
								? 'Hide Query Composer'
								: 'Show Query Composer'
						}
					>
						<Button
							onClick={toggleQueryComposer}
							aria-label={
								showQueryComposer
									? 'Hide Query Composer'
									: 'Show Query Composer'
							}
							aria-pressed={showQueryComposer}
							size="compact"
							className={`wpgraphql-ide-toolbar-composer-btn${showQueryComposer ? ' is-active' : ''}`}
						>
							<Icon icon={listView} />
						</Button>
					</Tooltip>
				)}
				{docSettingsFields.length > 0 && (
					<Tooltip
						text={
							showDocSettingsPanel
								? 'Hide Document Settings'
								: 'Show Document Settings'
						}
					>
						<Button
							onClick={toggleDocSettingsPanel}
							aria-label={
								showDocSettingsPanel
									? 'Hide Document Settings'
									: 'Show Document Settings'
							}
							aria-pressed={showDocSettingsPanel}
							size="compact"
							className={`wpgraphql-ide-toolbar-doc-settings-btn${showDocSettingsPanel ? ' is-active' : ''}`}
						>
							<Icon icon={cog} />
						</Button>
					</Tooltip>
				)}
				<span className="wpgraphql-ide-editor-label">Query</span>
				<DropdownMenu icon={moreVertical} label="Editor actions">
					{({ onClose: closeMenu }) => (
						<>
							<MenuGroup>
								<EditorToolbar
									onClose={closeMenu}
									onNotice={addNotice}
									hideMutating={isPublished}
								/>
							</MenuGroup>
							<MenuGroup>
								<MenuItem
									onClick={() => {
										closeMenu();
										onOpenShareDialog();
									}}
									disabled={!query?.trim()}
								>
									Share link…
								</MenuItem>
							</MenuGroup>
							{!!activeDocument?.id &&
								!isTempId(activeDocument.id) && (
									<MenuGroup>
										<MenuItem
											onClick={() => {
												closeMenu();
												onOpenRenameDialog();
											}}
										>
											Rename document
										</MenuItem>
									</MenuGroup>
								)}
							{isPublished && (
								<MenuGroup>
									<MenuItem
										onClick={() => {
											closeMenu();
											onDuplicateAsDraft();
										}}
									>
										Duplicate as draft
									</MenuItem>
								</MenuGroup>
							)}
						</>
					)}
				</DropdownMenu>
				<div className="wpgraphql-ide-editor-toolbar-spacer" />
				{!isPublished && (
					<>
						<Button
							onClick={onSave}
							disabled={!activeDocDirty}
							size="compact"
							className={`wpgraphql-ide-save-button${activeDocDirty ? ' is-dirty' : ''}`}
						>
							Save draft
						</Button>
						{isSavedDraft && query?.trim() && (
							<Tooltip
								text={
									!parsedQuery.parseable
										? 'Fix the syntax error to publish'
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
									Publish
								</Button>
							</Tooltip>
						)}
					</>
				)}
			</div>
			<ResizableBox
				size={{ width: '100%', height: editorHeight }}
				minHeight={MIN_EDITOR_HEIGHT_PX}
				enable={{ bottom: true }}
				onResizeStop={(e, d, elt) =>
					onSetEditorHeight(elt.offsetHeight)
				}
				className={`wpgraphql-ide-editor-resizable wpgraphql-ide-resizable-split${hasLeftPanel ? ' has-left-panel' : ''}`}
			>
				{/* Mounted inside the ResizableBox so its height is borrowed
				    from the editor area, not added above it — keeps the bottom
				    Variables/Headers panel anchored to the same position
				    whether or not the notice is showing. */}
				<DocumentNotices
					isPublished={isPublished}
					onDuplicate={onDuplicateAsDraft}
				/>
				<div className="wpgraphql-ide-editor-resizable-body">
					{hasComposer && showQueryComposer && (
						<LeftPanel
							title="Query Composer"
							className="wpgraphql-ide-query-composer-inline"
							width={composerWidth}
							onResize={onSetComposerWidth}
							minWidth={200}
							onClose={onCloseLeftPanel}
							closeLabel="Close Query Composer panel"
						>
							<ComposerContent />
						</LeftPanel>
					)}
					{showDocSettingsPanel && docSettingsFields.length > 0 && (
						<LeftPanel
							title="Document Settings"
							className="wpgraphql-ide-doc-settings-inline"
							width={docSettingsPanelWidth}
							onResize={onSetDocSettingsPanelWidth}
							minWidth={240}
							onClose={onCloseLeftPanel}
							closeLabel="Close Document Settings panel"
						>
							<DocumentSettingsDrawer
								fields={docSettingsFields}
								values={docSettingsValues}
								onChange={onDocSettingChange}
								globalGrantMode={docSettingsGlobalGrant}
							/>
						</LeftPanel>
					)}
					<GraphQLEditor
						key={activeDocument?.id || 'empty'}
						className={isPublished ? 'is-readonly' : ''}
						value={query}
						onChange={onQueryChange}
						schema={schema}
						readOnly={isPublished}
						extraKeys={editorKeyBindings.current}
						onShowInDocs={onShowInDocs}
						onCursorChange={onCursorChange}
					/>
					<ExecutionPill
						httpMethod={httpMethod}
						onSetHttpMethod={onSetHttpMethod}
						isAuthenticated={isAuthenticated}
						onToggleAuth={onToggleAuth}
						avatarUrl={avatarUrl}
						operationNames={operationNames}
						isFetching={isFetching}
						isSchemaLoading={isSchemaLoading}
						onExecute={onExecute}
					/>
				</div>
			</ResizableBox>
			<TabPanel
				className="wpgraphql-ide-editor-tools"
				tabs={editorBottomTabs}
			>
				{(tab) =>
					tab.name === 'variables' ? (
						<JSONEditor
							key="variables"
							value={variables}
							onChange={onVariablesChange}
							placeholder="Variables (JSON)"
							variableToType={variableToType}
						/>
					) : (
						<JSONEditor
							key="headers"
							value={headers}
							onChange={onHeadersChange}
							placeholder="Headers (JSON)"
						/>
					)
				}
			</TabPanel>
		</ResizableBox>
	);
}

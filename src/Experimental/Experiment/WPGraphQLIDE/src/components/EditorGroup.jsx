import {
	ChevronDownIcon,
	ChevronUpIcon,
	ExecuteButton,
	HeaderEditor,
	QueryEditor,
	ResponseEditor,
	Spinner,
	Tab,
	Tabs,
	Tooltip,
	UnStyledButton,
	VariableEditor,
} from '@graphiql/react';
import { EditorToolbar } from './EditorToolbar';
import React from 'react';

export const EditorGroup = ( props ) => {
	const {
		secondRef, // pluginResize.secondRef
		disableTabs,
		editorContext,
		handleReorder,
		executionContext,
		onClickReference,
		editorTheme,
		keyMap,
		editorToolsResize,
		addTab,
		logo,
		onCopyQuery,
		onEditQuery,
		readOnly,
		activeSecondaryEditor,
		handleToolsTabClick,
		isHeadersEditorEnabled,
		toggleEditorTools,
		onEditVariables,
		onEditHeaders,
		editorResize,
		responseTooltip,
	} = props;

	return (
		<div ref={ secondRef } className="graphiql-sessions">
			<div className="graphiql-session-header">
				{ disableTabs ? null : (
					<Tabs
						values={ editorContext.tabs }
						onReorder={ handleReorder }
						aria-label="Select active operation"
					>
						{ editorContext.tabs.length > 1 && (
							<>
								{ editorContext.tabs.map( ( tab, index ) => (
									<Tab
										key={ tab.id }
										value={ tab }
										isActive={
											index ===
											editorContext.activeTabIndex
										}
									>
										<Tab.Button
											aria-controls="graphiql-session"
											id={ `graphiql-session-tab-${ index }` }
											onClick={ () => {
												executionContext.stop();
												editorContext.changeTab(
													index
												);
											} }
										>
											{ tab.title }
										</Tab.Button>
										<Tab.Close
											onClick={ () => {
												if (
													editorContext.activeTabIndex ===
													index
												) {
													executionContext.stop();
												}
												editorContext.closeTab( index );
											} }
										/>
									</Tab>
								) ) }
								{ addTab }
							</>
						) }
					</Tabs>
				) }
				<div className="graphiql-session-header-right">
					{ editorContext.tabs.length === 1 && addTab }
					{ logo }
				</div>
			</div>
			<div
				role="tabpanel"
				id="graphiql-session"
				className="graphiql-session"
				aria-labelledby={ `graphiql-session-tab-${ editorContext.activeTabIndex }` }
			>
				<div ref={ editorResize.firstRef }>
					<div
						className={ `graphiql-editors${
							editorContext.tabs.length === 1
								? ' full-height'
								: ''
						}` }
					>
						<div ref={ editorToolsResize.firstRef }>
							<section
								className="graphiql-query-editor"
								aria-label="Query Editor"
							>
								<QueryEditor
									editorTheme={ editorTheme }
									keyMap={ keyMap }
									onClickReference={ onClickReference }
									onCopyQuery={ onCopyQuery }
									onEdit={ onEditQuery }
									readOnly={ readOnly }
								/>
								<div
									className="graphiql-toolbar"
									role="toolbar"
									aria-label="Editor Commands"
								>
									<ExecuteButton />
									<EditorToolbar />
								</div>
							</section>
						</div>

						<div ref={ editorToolsResize.dragBarRef }>
							<div className="graphiql-editor-tools">
								<UnStyledButton
									type="button"
									className={
										activeSecondaryEditor === 'variables' &&
										editorToolsResize.hiddenElement !==
											'second'
											? 'active'
											: ''
									}
									onClick={ handleToolsTabClick }
									data-name="variables"
								>
									Variables
								</UnStyledButton>
								{ isHeadersEditorEnabled && (
									<UnStyledButton
										type="button"
										className={
											activeSecondaryEditor ===
												'headers' &&
											editorToolsResize.hiddenElement !==
												'second'
												? 'active'
												: ''
										}
										onClick={ handleToolsTabClick }
										data-name="headers"
									>
										Headers
									</UnStyledButton>
								) }

								<Tooltip
									label={
										editorToolsResize.hiddenElement ===
										'second'
											? 'Show editor tools'
											: 'Hide editor tools'
									}
								>
									<UnStyledButton
										type="button"
										onClick={ toggleEditorTools }
										aria-label={
											editorToolsResize.hiddenElement ===
											'second'
												? 'Show editor tools'
												: 'Hide editor tools'
										}
										className="graphiql-toggle-editor-tools"
									>
										{ editorToolsResize.hiddenElement ===
										'second' ? (
											<ChevronUpIcon
												className="graphiql-chevron-icon"
												aria-hidden="true"
											/>
										) : (
											<ChevronDownIcon
												className="graphiql-chevron-icon"
												aria-hidden="true"
											/>
										) }
									</UnStyledButton>
								</Tooltip>
							</div>
						</div>

						<div ref={ editorToolsResize.secondRef }>
							<section
								className="graphiql-editor-tool"
								aria-label={
									activeSecondaryEditor === 'variables'
										? 'Variables'
										: 'Headers'
								}
							>
								<VariableEditor
									editorTheme={ editorTheme }
									isHidden={
										activeSecondaryEditor !== 'variables'
									}
									keyMap={ keyMap }
									onEdit={ onEditVariables }
									onClickReference={ onClickReference }
									readOnly={ readOnly }
								/>
								{ isHeadersEditorEnabled && (
									<HeaderEditor
										editorTheme={ editorTheme }
										isHidden={
											activeSecondaryEditor !== 'headers'
										}
										keyMap={ keyMap }
										onEdit={ onEditHeaders }
										readOnly={ readOnly }
									/>
								) }
							</section>
						</div>
					</div>
				</div>

				<div
					className="graphiql-horizontal-drag-bar"
					ref={ editorResize.dragBarRef }
				/>

				<div ref={ editorResize.secondRef }>
					<div className="graphiql-response">
						{ executionContext.isFetching ? <Spinner /> : null }
						<ResponseEditor
							editorTheme={ editorTheme }
							responseTooltip={ responseTooltip }
							keyMap={ keyMap }
						/>
					</div>
				</div>
			</div>
		</div>
	);
};

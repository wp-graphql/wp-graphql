/**
 *  Copyright (c) 2020 GraphQL Contributors.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 */

import React, { useCallback, useState } from 'react';

import {
	GraphiQLProvider,
	PlusIcon,
	Tooltip,
	UnStyledButton,
	useDragResize,
	useEditorContext,
	useExecutionContext,
	usePluginContext,
	useSchemaContext,
	useStorageContext,
	useTheme,
} from '@graphiql/react';
import { ActivityBar } from './ActivityBar';
import { ShortKeysDialog } from './ShortKeysDialog';
import { SettingsDialog } from './SettingsDialog';
import ActivityPanel from './ActivityPanel';
import { EditorGroup } from './EditorGroup';

/**
 * The top-level React component for GraphiQL, intended to encompass the entire
 * browser viewport.
 *
 * @see https://github.com/graphql/graphiql#usage
 */

export function GraphiQL( {
	dangerouslyAssumeSchemaIsValid,
	defaultQuery,
	defaultTabs,
	externalFragments,
	fetcher,
	getDefaultFieldNames,
	headers,
	inputValueDeprecation,
	introspectionQueryName,
	maxHistoryLength,
	onEditOperationName,
	onSchemaChange,
	onTabChange,
	onTogglePluginVisibility,
	operationName,
	plugins,
	query,
	response,
	schema,
	schemaDescription,
	shouldPersistHeaders,
	storage,
	validationRules,
	variables,
	visiblePlugin,
	defaultHeaders,
	...props
} ) {
	// Ensure props are correct
	if ( typeof fetcher !== 'function' ) {
		throw new TypeError(
			'The `GraphiQL` component requires a `fetcher` function to be passed as prop.'
		);
	}

	return (
		<GraphiQLProvider
			getDefaultFieldNames={ getDefaultFieldNames }
			dangerouslyAssumeSchemaIsValid={ dangerouslyAssumeSchemaIsValid }
			defaultQuery={ defaultQuery }
			defaultHeaders={ defaultHeaders }
			defaultTabs={ defaultTabs }
			externalFragments={ externalFragments }
			fetcher={ fetcher }
			headers={ headers }
			inputValueDeprecation={ inputValueDeprecation }
			introspectionQueryName={ introspectionQueryName }
			maxHistoryLength={ maxHistoryLength }
			onEditOperationName={ onEditOperationName }
			onSchemaChange={ onSchemaChange }
			onTabChange={ onTabChange }
			onTogglePluginVisibility={ onTogglePluginVisibility }
			plugins={ plugins }
			visiblePlugin={ visiblePlugin }
			operationName={ operationName }
			query={ query }
			response={ response }
			schema={ schema }
			schemaDescription={ schemaDescription }
			shouldPersistHeaders={ shouldPersistHeaders }
			storage={ storage }
			validationRules={ validationRules }
			variables={ variables }
		>
			<GraphiQLInterface
				showPersistHeadersSettings={ shouldPersistHeaders !== false }
				disableTabs={ props.disableTabs ?? false }
				{ ...props }
			/>
		</GraphiQLProvider>
	);
}

// Export main windows/panes to be used separately if desired.
GraphiQL.Logo = GraphiQLLogo;

export function GraphiQLInterface( props ) {
	const isHeadersEditorEnabled = props.isHeadersEditorEnabled ?? true;
	const editorContext = useEditorContext( { nonNull: true } );
	const executionContext = useExecutionContext( { nonNull: true } );
	const schemaContext = useSchemaContext( { nonNull: true } );
	const storageContext = useStorageContext();
	const pluginContext = usePluginContext();

	const { theme, setTheme } = useTheme();

	const PluginContent = pluginContext?.visiblePlugin?.content;

	const pluginResize = useDragResize( {
		defaultSizeRelation: 1 / 3,
		direction: 'horizontal',
		initiallyHidden: pluginContext?.visiblePlugin ? undefined : 'first',
		onHiddenElementChange( resizableElement ) {
			if ( resizableElement === 'first' ) {
				pluginContext?.setVisiblePlugin( null );
			}
		},
		sizeThresholdSecond: 200,
		storageKey: 'docExplorerFlex',
	} );
	const editorResize = useDragResize( {
		direction: 'horizontal',
		storageKey: 'editorFlex',
	} );
	const editorToolsResize = useDragResize( {
		defaultSizeRelation: 3,
		direction: 'vertical',
		initiallyHidden: ( () => {
			if (
				props.defaultEditorToolsVisibility === 'variables' ||
				props.defaultEditorToolsVisibility === 'headers'
			) {
				return;
			}

			if ( typeof props.defaultEditorToolsVisibility === 'boolean' ) {
				return props.defaultEditorToolsVisibility
					? undefined
					: 'second';
			}

			return editorContext.initialVariables ||
				editorContext.initialHeaders
				? undefined
				: 'second';
		} )(),
		sizeThresholdSecond: 60,
		storageKey: 'secondaryEditorFlex',
	} );

	const [ activeSecondaryEditor, setActiveSecondaryEditor ] = useState(
		() => {
			if (
				props.defaultEditorToolsVisibility === 'variables' ||
				props.defaultEditorToolsVisibility === 'headers'
			) {
				return props.defaultEditorToolsVisibility;
			}
			return ! editorContext.initialVariables &&
				editorContext.initialHeaders &&
				isHeadersEditorEnabled
				? 'headers'
				: 'variables';
		}
	);
	const [ showDialog, setShowDialog ] = useState( null );
	const [ clearStorageStatus, setClearStorageStatus ] = useState( null );

	const children = React.Children.toArray( props.children );

	const logo = children.find( ( child ) =>
		isChildComponentType( child, GraphiQL.Logo )
	) || <GraphiQL.Logo />;

	const onClickReference = useCallback( () => {
		if ( pluginResize.hiddenElement === 'first' ) {
			pluginResize.setHiddenElement( null );
		}
	}, [ pluginResize ] );

	const handleClearData = useCallback( () => {
		try {
			storageContext?.clear();
			setClearStorageStatus( 'success' );
		} catch {
			setClearStorageStatus( 'error' );
		}
	}, [ storageContext ] );

	const handlePersistHeaders = useCallback(
		( event ) => {
			editorContext.setShouldPersistHeaders(
				event.currentTarget.dataset.value === 'true'
			);
		},
		[ editorContext ]
	);

	const handleChangeTheme = useCallback(
		( event ) => {
			const selectedTheme =
				event.currentTarget.dataset.theme || undefined;
			setTheme( selectedTheme || null );
		},
		[ setTheme ]
	);

	const handleAddTab = editorContext.addTab;
	const handleRefetchSchema = schemaContext.introspect;
	const handleReorder = editorContext.moveTab;

	const handleShowDialog = useCallback( ( event ) => {
		setShowDialog( event.currentTarget.dataset.value );
	}, [] );

	const handlePluginClick = useCallback(
		( e ) => {
			const context = pluginContext;
			const pluginIndex = Number( e.currentTarget.dataset.index );
			const plugin = context.plugins.find(
				( _, index ) => pluginIndex === index
			);
			const isVisible = plugin === context.visiblePlugin;
			if ( isVisible ) {
				context.setVisiblePlugin( null );
				pluginResize.setHiddenElement( 'first' );
			} else {
				context.setVisiblePlugin( plugin );
				pluginResize.setHiddenElement( null );
			}
		},
		[ pluginContext, pluginResize ]
	);

	const handleToolsTabClick = useCallback(
		( event ) => {
			if ( editorToolsResize.hiddenElement === 'second' ) {
				editorToolsResize.setHiddenElement( null );
			}
			setActiveSecondaryEditor( event.currentTarget.dataset.name );
		},
		[ editorToolsResize ]
	);

	const toggleEditorTools = useCallback( () => {
		editorToolsResize.setHiddenElement(
			editorToolsResize.hiddenElement === 'second' ? null : 'second'
		);
	}, [ editorToolsResize ] );

	const handleOpenShortKeysDialog = useCallback( ( isOpen ) => {
		if ( ! isOpen ) {
			setShowDialog( null );
		}
	}, [] );

	const handleOpenSettingsDialog = useCallback( ( isOpen ) => {
		if ( ! isOpen ) {
			setShowDialog( null );
			setClearStorageStatus( null );
		}
	}, [] );

	const addTab = (
		<Tooltip label="Add tab">
			<UnStyledButton
				type="button"
				className="graphiql-tab-add"
				onClick={ handleAddTab }
				aria-label="Add tab"
			>
				<PlusIcon aria-hidden="true" />
			</UnStyledButton>
		</Tooltip>
	);

	return (
		<Tooltip.Provider>
			<div
				data-testid="graphiql-container"
				className="graphiql-container"
			>
				<ActivityBar
					handlePluginClick={ handlePluginClick }
					handleRefetchSchema={ handleRefetchSchema }
					handleShowDialog={ handleShowDialog }
					pluginContext={ pluginContext }
					schemaContext={ schemaContext }
				/>
				<div className="graphiql-main">
					<ActivityPanel
						pluginContext={ pluginContext }
						schemaContext={ schemaContext }
						PluginContent={ PluginContent }
						firstRef={ pluginResize.firstRef }
						dragBarRef={ pluginResize.dragBarRef }
					/>
					<EditorGroup
						secondRef={ pluginResize.secondRef }
						disableTabs={ props.disableTabs }
						editorContext={ editorContext }
						handleReorder={ handleReorder }
						executionContext={ executionContext }
						onClickReference={ onClickReference }
						editorTheme={ props.editorTheme }
						keyMap={ props.keyMap }
						editorToolsResize={ editorToolsResize }
						addTab={ addTab }
						logo={ logo }
						onCopyQuery={ props.onCopyQuery }
						onEditQuery={ props.onEditQuery }
						readOnly={ props.readOnly }
						activeSecondaryEditor={ activeSecondaryEditor }
						handleToolsTabClick={ handleToolsTabClick }
						toggleEditorTools={ toggleEditorTools }
						onEditVariables={ props.onEditVariables }
						onEditHeaders={ props.onEditHeaders }
						editorResize={ editorResize }
						responseTooltip={ props.responseTooltip }
					/>
				</div>
				<ShortKeysDialog
					keyMap={ props.keyMap }
					handleOpenShortKeysDialog={ handleOpenSettingsDialog }
					showDialog={ showDialog }
				/>
				<SettingsDialog
					showDialog={ showDialog }
					handleOpenSettingsDialog={ handleOpenSettingsDialog }
					showPersistHeadersSettings={
						props.showPersistHeadersSettings || false
					}
					editorContext={ editorContext }
					handlePersistHeaders={ handlePersistHeaders }
					theme={ theme }
					handleChangeTheme={ handleChangeTheme }
					storageContext={ storageContext }
					clearStorageStatus={ clearStorageStatus }
					handleClearData={ handleClearData }
				/>
			</div>
		</Tooltip.Provider>
	);
}

// Configure the UI by providing this Component as a child of GraphiQL.
function GraphiQLLogo( props ) {
	return (
		<div className="graphiql-logo">
			{ props.children || (
				<a
					className="graphiql-logo-link"
					href="https://github.com/graphql/graphiql"
					target="_blank"
					rel="noreferrer"
				>
					Graph
					<em>i</em>
					QL
				</a>
			) }
		</div>
	);
}

GraphiQLLogo.displayName = 'GraphiQLLogo';

// Determines if the React child is of the same type of the provided React component
function isChildComponentType( child, component ) {
	if (
		child?.type?.displayName &&
		child.type.displayName === component.displayName
	) {
		return true;
	}

	return child.type === component;
}

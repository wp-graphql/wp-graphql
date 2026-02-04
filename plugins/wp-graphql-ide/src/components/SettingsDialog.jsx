import { Button, ButtonGroup, Dialog } from '@graphiql/react';
import React from 'react';

export const SettingsDialog = ( props ) => {
	const {
		showDialog,
		handleOpenSettingsDialog,
		showPersistHeadersSettings,
		editorContext,
		handlePersistHeaders,
		theme,
		handleChangeTheme,
		storageContext,
		clearStorageStatus,
		handleClearData,
	} = props;
	return (
		<Dialog
			open={ showDialog === 'settings' }
			onOpenChange={ handleOpenSettingsDialog }
		>
			<div className="graphiql-dialog-header graphiql-settings-dialog-header">
				<Dialog.Title className="graphiql-dialog-title">
					Settings
				</Dialog.Title>
				<Dialog.Close />
			</div>
			{ showPersistHeadersSettings ? (
				<div className="graphiql-dialog-section">
					<div>
						<div className="graphiql-dialog-section-title">
							Persist headers
						</div>
						<div className="graphiql-dialog-section-caption">
							Save headers upon reloading.{ ' ' }
							<span className="graphiql-warning-text">
								Only enable if you trust this device.
							</span>
						</div>
					</div>
					<ButtonGroup>
						<Button
							type="button"
							id="enable-persist-headers"
							className={
								editorContext.shouldPersistHeaders
									? 'active'
									: ''
							}
							data-value="true"
							onClick={ handlePersistHeaders }
						>
							On
						</Button>
						<Button
							type="button"
							id="disable-persist-headers"
							className={
								editorContext.shouldPersistHeaders
									? ''
									: 'active'
							}
							onClick={ handlePersistHeaders }
						>
							Off
						</Button>
					</ButtonGroup>
				</div>
			) : null }
			<div className="graphiql-dialog-section">
				<div>
					<div className="graphiql-dialog-section-title">Theme</div>
					<div className="graphiql-dialog-section-caption">
						Adjust how the interface looks like.
					</div>
				</div>
				<ButtonGroup>
					<Button
						type="button"
						className={ theme === null ? 'active' : '' }
						onClick={ handleChangeTheme }
					>
						System
					</Button>
					<Button
						type="button"
						className={ theme === 'light' ? 'active' : '' }
						data-theme="light"
						onClick={ handleChangeTheme }
					>
						Light
					</Button>
					<Button
						type="button"
						className={ theme === 'dark' ? 'active' : '' }
						data-theme="dark"
						onClick={ handleChangeTheme }
					>
						Dark
					</Button>
				</ButtonGroup>
			</div>
			{ storageContext ? (
				<div className="graphiql-dialog-section">
					<div>
						<div className="graphiql-dialog-section-title">
							Clear storage
						</div>
						<div className="graphiql-dialog-section-caption">
							Remove all locally stored data and start fresh.
						</div>
					</div>
					<Button
						type="button"
						state={ clearStorageStatus || undefined }
						disabled={ clearStorageStatus === 'success' }
						onClick={ handleClearData }
					>
						{ {
							success: 'Cleared data',
							error: 'Failed',
						}[ clearStorageStatus ] || 'Clear data' }
					</Button>
				</div>
			) : null }
		</Dialog>
	);
};

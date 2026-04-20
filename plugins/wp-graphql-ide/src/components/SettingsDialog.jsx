import React from 'react';
import {
	Modal,
	Button,
	ButtonGroup,
	ToggleControl,
} from '@wordpress/components';

// Settings modal for the IDE. Uses @wordpress/components Modal for native
// WordPress admin look and feel.
export const SettingsDialog = ({
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
}) => {
	if (showDialog !== 'settings') {
		return null;
	}

	const onToggleHeaders = (checked) => {
		handlePersistHeaders({
			currentTarget: { dataset: { value: String(checked) } },
		});
	};

	const onThemeClick = (nextTheme) => {
		handleChangeTheme({
			currentTarget: { dataset: { theme: nextTheme || '' } },
		});
	};

	const getClearButtonLabel = () => {
		if (clearStorageStatus === 'success') {
			return 'Cleared data';
		}
		if (clearStorageStatus === 'error') {
			return 'Failed';
		}
		return 'Clear data';
	};

	return (
		<Modal
			title="Settings"
			onRequestClose={() => handleOpenSettingsDialog(false)}
			className="wpgraphql-ide-settings-modal"
		>
			{showPersistHeadersSettings ? (
				<div className="wpgraphql-ide-settings-section">
					<ToggleControl
						label="Persist headers"
						help={
							<>
								Save headers upon reloading.{' '}
								<strong>
									Only enable if you trust this device.
								</strong>
							</>
						}
						checked={!!editorContext?.shouldPersistHeaders}
						onChange={onToggleHeaders}
					/>
				</div>
			) : null}

			<div className="wpgraphql-ide-settings-section">
				<div className="wpgraphql-ide-settings-section-title">
					Theme
				</div>
				<div className="wpgraphql-ide-settings-section-caption">
					Adjust how the interface looks.
				</div>
				<ButtonGroup>
					<Button
						variant={theme === null ? 'primary' : 'secondary'}
						onClick={() => onThemeClick(null)}
					>
						System
					</Button>
					<Button
						variant={theme === 'light' ? 'primary' : 'secondary'}
						onClick={() => onThemeClick('light')}
					>
						Light
					</Button>
					<Button
						variant={theme === 'dark' ? 'primary' : 'secondary'}
						onClick={() => onThemeClick('dark')}
					>
						Dark
					</Button>
				</ButtonGroup>
			</div>

			{storageContext ? (
				<div className="wpgraphql-ide-settings-section">
					<div className="wpgraphql-ide-settings-section-title">
						Clear storage
					</div>
					<div className="wpgraphql-ide-settings-section-caption">
						Remove all locally stored data and start fresh.
					</div>
					<Button
						variant="secondary"
						isDestructive={clearStorageStatus !== 'success'}
						disabled={clearStorageStatus === 'success'}
						onClick={handleClearData}
					>
						{getClearButtonLabel()}
					</Button>
				</div>
			) : null}
		</Modal>
	);
};

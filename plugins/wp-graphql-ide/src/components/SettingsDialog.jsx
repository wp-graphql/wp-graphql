import React, { useState, useEffect } from 'react';
import {
	Modal,
	Button,
	ButtonGroup,
	ToggleControl,
} from '@wordpress/components';
import { getPreferences, savePreference } from '../api/preferences';

/**
 * Settings modal for the IDE.
 *
 * Reads and writes user preferences via the WordPress REST API.
 *
 * @param {Object}   props
 * @param {string}   props.showDialog               - Current dialog name or null.
 * @param {Function} props.handleOpenSettingsDialog - Close handler.
 */
export const SettingsDialog = ({ showDialog, handleOpenSettingsDialog }) => {
	const [theme, setTheme] = useState('');
	const [persistHeaders, setPersistHeaders] = useState(false);

	useEffect(() => {
		if (showDialog !== 'settings') {
			return;
		}
		getPreferences().then((prefs) => {
			setTheme(prefs.theme || '');
			setPersistHeaders(!!prefs.persist_headers);
		});
	}, [showDialog]);

	if (showDialog !== 'settings') {
		return null;
	}

	const onThemeClick = (nextTheme) => {
		setTheme(nextTheme);
		savePreference('theme', nextTheme);
	};

	const onToggleHeaders = (checked) => {
		setPersistHeaders(checked);
		savePreference('persist_headers', checked);
	};

	return (
		<Modal
			title="Settings"
			onRequestClose={() => handleOpenSettingsDialog(false)}
			className="wpgraphql-ide-settings-modal"
		>
			<div className="wpgraphql-ide-settings-section">
				<ToggleControl
					label="Persist headers"
					help="Save headers upon reloading. Only enable if you trust this device."
					checked={persistHeaders}
					onChange={onToggleHeaders}
				/>
			</div>

			<div className="wpgraphql-ide-settings-section">
				<div className="wpgraphql-ide-settings-section-title">
					Theme
				</div>
				<div className="wpgraphql-ide-settings-section-caption">
					Adjust how the interface looks.
				</div>
				<ButtonGroup>
					<Button
						variant={theme === '' ? 'primary' : 'secondary'}
						onClick={() => onThemeClick('')}
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
		</Modal>
	);
};

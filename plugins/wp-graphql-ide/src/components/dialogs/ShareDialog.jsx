import React, { useState } from 'react';
import {
	Button,
	CheckboxControl,
	Modal,
	ToggleControl,
} from '@wordpress/components';
import { select } from '@wordpress/data';
import LZString from 'lz-string';
import copy from 'copy-to-clipboard';

const PREFS_KEY = 'wpgraphql-ide:share-prefs:v1';

const DEFAULT_PREFS = {
	includeVariables: true,
	// Headers off by default — they often carry auth tokens that
	// shouldn't end up in a shared link.
	includeHeaders: false,
};

function readPrefs() {
	try {
		const raw = window.localStorage.getItem(PREFS_KEY);
		if (!raw) {
			return null;
		}
		const parsed = JSON.parse(raw);
		return {
			includeVariables:
				typeof parsed.includeVariables === 'boolean'
					? parsed.includeVariables
					: DEFAULT_PREFS.includeVariables,
			includeHeaders:
				typeof parsed.includeHeaders === 'boolean'
					? parsed.includeHeaders
					: DEFAULT_PREFS.includeHeaders,
		};
	} catch (e) {
		return null;
	}
}

function writePrefs(prefs) {
	try {
		window.localStorage.setItem(PREFS_KEY, JSON.stringify(prefs));
	} catch (e) {
		// ignore quota / privacy mode
	}
}

// Build a shareable URL for the current editor state. Pulls query
// (and optionally variables/headers) from the app store and packs
// them into the IDE deep-link query param.
export function buildShareUrl({ includeVariables, includeHeaders }) {
	const { dedicatedIdeBaseUrl } = window.WPGRAPHQL_IDE_DATA;
	const app = select('wpgraphql-ide/app');
	const payload = { query: app.getQuery() };
	if (includeVariables) {
		payload.variables = app.getVariables();
	}
	if (includeHeaders) {
		payload.headers = app.getHeaders();
	}
	const encoded = LZString.compressToEncodedURIComponent(
		JSON.stringify(payload)
	);
	return `${dedicatedIdeBaseUrl}&wpgraphql_ide=${encoded}`;
}

// Apollo Studio-style share settings modal. Lets the user pick which
// pieces of the editor state to bake into the link, optionally
// remembering the choice for next time. Props: { onClose, onCopy? }.
export function ShareDialog({ onClose, onCopy }) {
	const stored = readPrefs();
	const [prefs, setPrefs] = useState(stored || DEFAULT_PREFS);
	const [remember, setRemember] = useState(stored !== null);

	const handleCopy = () => {
		if (remember) {
			writePrefs(prefs);
		}
		const url = buildShareUrl(prefs);
		copy(url);
		if (onCopy) {
			onCopy(url);
		}
		onClose();
	};

	return (
		<Modal
			title="Shareable link settings"
			onRequestClose={onClose}
			className="wpgraphql-ide-dialog wpgraphql-ide-share-dialog"
		>
			<p className="wpgraphql-ide-dialog-message">
				Pick what to include in the link generated for this document.
			</p>
			<div className="wpgraphql-ide-share-toggles">
				<ToggleControl
					label="Include variables"
					checked={prefs.includeVariables}
					onChange={(includeVariables) =>
						setPrefs((p) => ({ ...p, includeVariables }))
					}
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label="Include headers"
					help="Headers may contain auth tokens — only include them when sharing with someone you trust."
					checked={prefs.includeHeaders}
					onChange={(includeHeaders) =>
						setPrefs((p) => ({ ...p, includeHeaders }))
					}
					__nextHasNoMarginBottom
				/>
			</div>
			<div className="wpgraphql-ide-share-footer">
				<CheckboxControl
					label="Remember this preference"
					checked={remember}
					onChange={setRemember}
					__nextHasNoMarginBottom
				/>
				<div className="wpgraphql-ide-dialog-actions">
					<Button variant="tertiary" onClick={onClose}>
						Cancel
					</Button>
					<Button variant="primary" onClick={handleCopy}>
						Copy link
					</Button>
				</div>
			</div>
		</Modal>
	);
}

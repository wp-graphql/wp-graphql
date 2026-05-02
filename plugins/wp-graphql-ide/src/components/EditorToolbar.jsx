import { useSelect } from '@wordpress/data';
import { MenuItem } from '@wordpress/components';

const BUTTON_NOTICES = {
	prettify: 'Query prettified',
	share: 'Shareable link copied to clipboard',
	'merge-fragments': 'Fragments merged',
	'copy-query': 'Query copied to clipboard',
};

export const EditorToolbar = ({ onClose, onNotice }) => {
	const buttons = useSelect((select) =>
		select('wpgraphql-ide/document-editor').buttons()
	);

	return (
		<>
			{buttons.map((button, index) => {
				const props = button.config();
				const buttonName = button.name ?? String(index);

				if (!isValidButton(props, buttonName)) {
					return null;
				}

				return (
					<MenuItem
						key={button.name ?? index}
						onClick={() => {
							if (onClose) {
								onClose();
							}
							props.onClick();
							if (onNotice && BUTTON_NOTICES[buttonName]) {
								onNotice(BUTTON_NOTICES[buttonName]);
							}
						}}
						aria-label={props.label}
					>
						{props.children || props.label}
					</MenuItem>
				);
			})}
		</>
	);
};

const isValidButton = (config, name) => {
	let hasError = false;
	if (undefined === config.label) {
		console.warn(`Button "${name}" needs a "label" defined`, {
			config,
		});
		hasError = true;
	}
	if (undefined === config.children) {
		console.warn(`Button "${name}" needs "children" defined`, {
			config,
		});
		hasError = true;
	}
	if (undefined === config.onClick) {
		console.warn(`Button "${name}" needs "onClick" defined`, {
			config,
		});
		hasError = true;
	}

	if (hasError) {
		return false;
	}

	return true;
};

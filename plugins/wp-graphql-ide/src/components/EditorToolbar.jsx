import { useSelect } from '@wordpress/data';
import { MenuItem } from '@wordpress/components';

export const EditorToolbar = ({ onClose }) => {
	const buttons = useSelect((select) =>
		select('wpgraphql-ide/document-editor').buttons()
	);

	return (
		<>
			{Object.entries(buttons).map(([key, button]) => {
				const props = button.config();
				const buttonName = buttons[key].name ?? key;

				if (!isValidButton(props, buttonName)) {
					return null;
				}

				return (
					<MenuItem
						key={key}
						onClick={() => {
							if (onClose) {
								onClose();
							}
							props.onClick();
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

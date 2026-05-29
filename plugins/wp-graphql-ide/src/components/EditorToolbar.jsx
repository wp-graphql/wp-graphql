import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { MenuItem } from '@wordpress/components';

// Built lazily so __() runs after wp.i18n is loaded. Notice strings
// are keyed by built-in button name; extension buttons can supply
// their own notice via the onClick handler.
const getButtonNotices = () => ({
	prettify: __('Query prettified', 'wpgraphql-ide'),
	share: __('Shareable link copied to clipboard', 'wpgraphql-ide'),
	'merge-fragments': __('Fragments merged', 'wpgraphql-ide'),
	'copy-query': __('Query copied to clipboard', 'wpgraphql-ide'),
});

export const EditorToolbar = ({ onClose, onNotice, hideMutating = false }) => {
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

				// Buttons that mutate the query (Prettify, Merge) are hidden
				// on read-only documents. Authors opt in by declaring
				// `mutates: true` in their config.
				if (hideMutating && props.mutates) {
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
							const notices = getButtonNotices();
							if (onNotice && notices[buttonName]) {
								onNotice(notices[buttonName]);
							}
						}}
						aria-label={props.label}
						shortcut={props.shortcut}
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

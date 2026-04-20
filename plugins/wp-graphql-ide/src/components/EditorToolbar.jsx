import clsx from 'clsx';
import { useSelect } from '@wordpress/data';
import { Button, Tooltip } from '@wordpress/components';

export const EditorToolbar = () => {
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

				const baseClassName = `wpgraphql-ide-${buttonName}-button`;
				const mergedClassName = clsx(baseClassName, props?.className);
				const Component = props.component || Button;

				return (
					<Tooltip key={key} text={props.label}>
						<Component
							{...props}
							className={mergedClassName}
							aria-label={props.label}
						/>
					</Tooltip>
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

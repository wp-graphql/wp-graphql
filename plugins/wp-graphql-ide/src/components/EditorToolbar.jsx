import React, { useMemo } from 'react';
import clsx from 'clsx';
import { useSelect } from '@wordpress/data';
import { ToolbarButton } from '@graphiql/react';

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

				const baseClassName = `graphiql-${buttonName}-button`;

				// Merge the base className with any classNames provided in props.
				const mergedClassName = clsx(baseClassName, props?.className);

				// If a component is provided, use it, otherwise use the default ToolbarButton
				const Component = props.component || ToolbarButton;
				return (
					<Component
						{...props}
						className={mergedClassName} // mergedClassName must be below { ...props } in order to render with the correct classNames
						key={key}
					/>
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

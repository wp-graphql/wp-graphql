import { help, Icon } from '@wordpress/icons';

export function HelpIcon() {
	return (
		<Icon
			icon={help}
			style={{
				fill: 'hsla(var(--color-neutral), var(--alpha-tertiary))',
			}}
		/>
	);
}

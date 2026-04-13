import React from 'react';

export const checkboxUnchecked = (
	<svg
		viewBox="0 0 15 15"
		style={{
			color: 'hsla(var(--color-neutral), var(--alpha-tertiary, 0.4))',
			marginRight: 'var(--px-4)',
			height: 'var(--px-16)',
			width: 'var(--px-16)',
		}}
	>
		<circle cx="7.5" cy="7.5" r="6" stroke="currentColor" fill="none" />
	</svg>
);

export const checkboxChecked = (
	<svg
		viewBox="0 0 15 15"
		style={{
			color: 'hsl(var(--color-info))',
			marginRight: 'var(--px-4)',
			height: 'var(--px-16)',
			width: 'var(--px-16)',
		}}
	>
		<circle cx="7.5" cy="7.5" r="7.5" fill="currentColor" />
		<path
			d="M4.64641 7.00106L6.8801 9.23256L10.5017 5.61325"
			fill="none"
			stroke="white"
			strokeWidth="1.5"
		/>
	</svg>
);

export const defaultCheckboxChecked = (
	<svg
		style={{ marginRight: '3px', marginLeft: '-3px' }}
		width="12"
		height="12"
		viewBox="0 0 18 18"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<path
			d="M16 0H2C0.9 0 0 0.9 0 2V16C0 17.1 0.9 18 2 18H16C17.1 18 18 17.1 18 16V2C18 0.9 17.1 0 16 0ZM16 16H2V2H16V16ZM14.99 6L13.58 4.58L6.99 11.17L4.41 8.6L2.99 10.01L6.99 14L14.99 6Z"
			fill="#666"
		/>
	</svg>
);

export const defaultCheckboxUnchecked = (
	<svg
		style={{ marginRight: '3px', marginLeft: '-3px' }}
		width="12"
		height="12"
		viewBox="0 0 18 18"
		fill="none"
		xmlns="http://www.w3.org/2000/svg"
	>
		<path
			d="M16 2V16H2V2H16ZM16 0H2C0.9 0 0 0.9 0 2V16C0 17.1 0.9 18 2 18H16C17.1 18 18 17.1 18 16V2C18 0.9 17.1 0 16 0Z"
			fill="#CCC"
		/>
	</svg>
);

export default function Checkbox(props) {
	return props.checked
		? props.styleConfig.checkboxChecked
		: props.styleConfig.checkboxUnchecked;
}

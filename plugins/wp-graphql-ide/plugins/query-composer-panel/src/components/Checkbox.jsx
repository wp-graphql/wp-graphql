import React from 'react';

export const checkboxUnchecked = (
	<span className="graphiql-explorer-checkbox" aria-hidden="true">
		<svg
			viewBox="0 0 16 16"
			width="14"
			height="14"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect
				x="1.5"
				y="1.5"
				width="13"
				height="13"
				rx="1.5"
				stroke="currentColor"
			/>
		</svg>
	</span>
);

export const checkboxChecked = (
	<span className="graphiql-explorer-checkbox is-checked" aria-hidden="true">
		<svg
			viewBox="0 0 16 16"
			width="14"
			height="14"
			fill="none"
			xmlns="http://www.w3.org/2000/svg"
		>
			<rect
				x="1"
				y="1"
				width="14"
				height="14"
				rx="2"
				fill="currentColor"
			/>
			<path
				d="M4.5 8.2 L7 10.7 L11.5 5.7"
				stroke="#fff"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
				fill="none"
			/>
		</svg>
	</span>
);

export const defaultCheckboxChecked = checkboxChecked;
export const defaultCheckboxUnchecked = checkboxUnchecked;

export default function Checkbox(props) {
	return props.checked
		? props.styleConfig.checkboxChecked
		: props.styleConfig.checkboxUnchecked;
}

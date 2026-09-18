import { useDispatch, useSelect } from '@wordpress/data';
import {
	RadioControl,
	SelectControl,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

import { store } from '../store';

/**
 * Renders the control for a single setting, based on its registered field type.
 *
 * @param {Object} props
 * @param {string} props.settingKey The setting key, "{section}.{name}".
 *
 * @return {JSX.Element|null} The control.
 */
export function SettingControl({ settingKey }) {
	const { field, value, fieldError } = useSelect(
		(select) => {
			const review = select(store);
			return {
				field: review.getField(settingKey),
				value: review.getValue(settingKey),
				fieldError: review.getFieldError(settingKey),
			};
		},
		[settingKey]
	);
	const { setValue } = useDispatch(store);

	if (!field) {
		return null;
	}

	const help = fieldError || field.description;
	const commonProps = {
		label: field.label,
		help,
		disabled: field.locked,
	};
	const onChange = (next) => setValue(settingKey, next);

	switch (field.type) {
		case 'checkbox':
			return (
				<ToggleControl
					__nextHasNoMarginBottom
					{...commonProps}
					checked={'on' === value}
					onChange={(checked) => onChange(checked ? 'on' : 'off')}
				/>
			);
		case 'number':
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					{...commonProps}
					type="number"
					min={field.min ?? undefined}
					max={field.max ?? undefined}
					step={field.inputStep ?? undefined}
					value={value ?? ''}
					onChange={(next) =>
						onChange('' === next ? '' : Number(next))
					}
				/>
			);
		case 'select':
		case 'user_role_select':
			return (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					{...commonProps}
					value={value}
					options={field.options}
					onChange={onChange}
				/>
			);
		case 'radio':
			return (
				<RadioControl
					{...commonProps}
					selected={value}
					options={field.options}
					onChange={onChange}
				/>
			);
		case 'textarea':
			return (
				<TextareaControl
					__nextHasNoMarginBottom
					{...commonProps}
					value={value ?? ''}
					onChange={onChange}
				/>
			);
		default:
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					{...commonProps}
					type={'url' === field.type ? 'url' : 'text'}
					value={value ?? ''}
					onChange={onChange}
				/>
			);
	}
}

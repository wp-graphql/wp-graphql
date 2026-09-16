import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { Notice } from '@wordpress/components';

import { store } from '../../store';
import { formatValue, getChangedKeys } from '../format-value';

/**
 * Returns the warnings shown when saving turns on a setting that can make clients start getting errors.
 *
 * @return {Object<string,string>} Warnings keyed by setting key.
 */
function getWarningsWhenTurnedOn() {
	return {
		'graphql_general_settings.restrict_endpoint_to_logged_in_users': __(
			'Requests from visitors who are not logged in will be rejected. Make sure your apps send credentials before saving.',
			'wp-graphql'
		),
		'graphql_general_settings.query_depth_enabled': __(
			'Queries nested deeper than the maximum depth will be rejected. Make sure the queries your apps send are within the limit before saving.',
			'wp-graphql'
		),
	};
}

/**
 * Renders the last step: every setting in the wizard with its saved and chosen value.
 *
 * @return {JSX.Element} The step.
 */
export function SummaryStep() {
	const { fields, values, savedValues, fieldErrors } = useSelect((select) => {
		const wizard = select(store);
		return {
			fields: wizard.getFields(),
			values: wizard.getValues(),
			savedValues: wizard.getSavedValues(),
			fieldErrors: wizard.getFieldErrors(),
		};
	}, []);
	const changedKeys = useMemo(
		() => getChangedKeys(fields, values, savedValues),
		[fields, values, savedValues]
	);
	const warningsWhenTurnedOn = getWarningsWhenTurnedOn();
	const warnings = Object.keys(warningsWhenTurnedOn).filter(
		(key) => changedKeys.includes(key) && 'on' === values[key]
	);

	return (
		<div className="wpgraphql-setup-wizard__step">
			<h2>{__('Review and save', 'wp-graphql')}</h2>
			<p>
				{0 === changedKeys.length
					? __(
							'You kept every setting as it is. Saving records that you reviewed them.',
							'wp-graphql'
						)
					: __(
							'Changed settings are highlighted. They take effect as soon as you save.',
							'wp-graphql'
						)}
			</p>

			{warnings.map((key) => (
				<Notice key={key} status="warning" isDismissible={false}>
					{warningsWhenTurnedOn[key]}
				</Notice>
			))}

			<table className="widefat striped wpgraphql-setup-wizard__summary">
				<thead>
					<tr>
						<th scope="col">{__('Setting', 'wp-graphql')}</th>
						<th scope="col">
							{__('Currently saved', 'wp-graphql')}
						</th>
						<th scope="col">{__('After saving', 'wp-graphql')}</th>
					</tr>
				</thead>
				<tbody>
					{fields.map((field) => {
						const isChanged = changedKeys.includes(field.key);
						return (
							<tr
								key={field.key}
								className={isChanged ? 'is-changed' : undefined}
							>
								<th scope="row">{field.label}</th>
								<td>
									{formatValue(field, savedValues[field.key])}
								</td>
								<td>
									{formatValue(field, values[field.key])}
									{isChanged && (
										<span className="wpgraphql-setup-wizard__badge">
											{__('Changed', 'wp-graphql')}
										</span>
									)}
									{fieldErrors[field.key] && (
										<p className="wpgraphql-setup-wizard__field-error">
											{fieldErrors[field.key]}
										</p>
									)}
								</td>
							</tr>
						);
					})}
				</tbody>
			</table>
		</div>
	);
}

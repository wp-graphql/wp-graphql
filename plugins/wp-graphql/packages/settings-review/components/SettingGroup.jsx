import { __, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { Card, CardBody, CardHeader } from '@wordpress/components';

import { store } from '../store';
import { SettingControl } from './SettingControl';
import { formatValue } from './format-value';

/**
 * Renders a setting with the settings that depend on it and the tradeoffs of turning it on.
 *
 * Settings that depend on a checkbox (for example the maximum depth for query depth limiting)
 * are only shown while that checkbox is on.
 *
 * @param {Object} props
 * @param {string} props.settingKey The setting key, "{section}.{name}".
 *
 * @return {JSX.Element|null} The setting group.
 */
export function SettingGroup({ settingKey }) {
	const { field, fields, value, savedValue, isNew } = useSelect(
		(select) => {
			const review = select(store);
			return {
				field: review.getField(settingKey),
				fields: review.getFields(),
				value: review.getValue(settingKey),
				savedValue: review.getSavedValue(settingKey),
				isNew:
					!!review.getReviewState()?.status &&
					review.getUnreviewedKeys().includes(settingKey),
			};
		},
		[settingKey]
	);

	const dependents = useMemo(
		() => fields.filter((candidate) => candidate.dependsOn === settingKey),
		[fields, settingKey]
	);

	if (!field) {
		return null;
	}

	const showDependents = 'checkbox' !== field.type || 'on' === value;
	const hasTradeoffs = field.benefits.length > 0 || field.costs.length > 0;

	return (
		<Card className="wpgraphql-settings-review__setting" size="medium">
			<CardHeader className="wpgraphql-settings-review__setting-header">
				<SettingControl settingKey={settingKey} />
				{isNew && (
					<span className="wpgraphql-settings-review__badge">
						{__('New', 'wp-graphql')}
					</span>
				)}
			</CardHeader>
			<CardBody className="wpgraphql-settings-review__setting-body">
				<p className="wpgraphql-settings-review__meta">
					{sprintf(
						/* translators: %s: the setting's saved value, e.g. "On" */
						__('Currently saved: %s', 'wp-graphql'),
						formatValue(field, savedValue)
					)}
				</p>

				{showDependents && dependents.length > 0 && (
					<div className="wpgraphql-settings-review__dependents">
						{dependents.map((dependent) => (
							<SettingControl
								key={dependent.key}
								settingKey={dependent.key}
							/>
						))}
					</div>
				)}

				{hasTradeoffs && (
					<div className="wpgraphql-settings-review__tradeoffs">
						{field.benefits.length > 0 && (
							<div>
								<h4>{__('What you gain', 'wp-graphql')}</h4>
								<ul>
									{field.benefits.map((item) => (
										<li key={item}>{item}</li>
									))}
								</ul>
							</div>
						)}
						{field.costs.length > 0 && (
							<div>
								<h4>{__('What it costs', 'wp-graphql')}</h4>
								<ul>
									{field.costs.map((item) => (
										<li key={item}>{item}</li>
									))}
								</ul>
							</div>
						)}
					</div>
				)}
			</CardBody>
		</Card>
	);
}

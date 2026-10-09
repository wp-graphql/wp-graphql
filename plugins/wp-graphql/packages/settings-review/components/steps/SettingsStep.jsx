import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

import { store } from '../../store';
import { SettingGroup } from '../SettingGroup';

/**
 * Renders a registered step and its settings.
 *
 * Settings that depend on another setting in the same step are shown inside that setting's group.
 *
 * @param {Object} props
 * @param {string} props.slug The step slug.
 *
 * @return {JSX.Element|null} The step.
 */
export function SettingsStep({ slug }) {
	const { step, fields } = useSelect(
		(select) => {
			const review = select(store);
			return {
				step: review.getRegisteredStep(slug),
				fields: review.getFields(),
			};
		},
		[slug]
	);

	const topLevelKeys = useMemo(() => {
		if (!step) {
			return [];
		}

		return step.fields.filter((key) => {
			const field = fields.find((candidate) => candidate.key === key);
			return !field?.dependsOn || !step.fields.includes(field.dependsOn);
		});
	}, [step, fields]);

	if (!step) {
		return null;
	}

	return (
		<div className="wpgraphql-settings-review__step">
			<h2>{step.title}</h2>
			{step.description && <p>{step.description}</p>}
			<div className="wpgraphql-settings-review__settings">
				{topLevelKeys.map((key) => (
					<SettingGroup key={key} settingKey={key} />
				))}
			</div>
		</div>
	);
}

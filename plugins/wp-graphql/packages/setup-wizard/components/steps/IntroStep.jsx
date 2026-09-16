import { __, _n, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { ExternalLink, Notice } from '@wordpress/components';

import { store } from '../../store';
import { getBootstrapData } from '../../api/setup-wizard';

/**
 * Renders the first step of the wizard.
 *
 * @return {JSX.Element} The step.
 */
export function IntroStep() {
	const { wizardState, unreviewedCount } = useSelect((select) => {
		const wizard = select(store);
		return {
			wizardState: wizard.getWizardState(),
			unreviewedCount: wizard.getUnreviewedKeys().length,
		};
	}, []);
	const { docsUrl } = getBootstrapData();

	let previousRunNotice = null;
	if (wizardState?.status && unreviewedCount > 0) {
		previousRunNotice = sprintf(
			/* translators: %d: number of settings added to the setup wizard since it was last run */
			_n(
				'%d setting was added since you last ran this wizard. It is marked as new.',
				'%d settings were added since you last ran this wizard. They are marked as new.',
				unreviewedCount,
				'wp-graphql'
			),
			unreviewedCount
		);
	} else if ('completed' === wizardState?.status) {
		previousRunNotice = __(
			'You completed this wizard before. Run it again to review your choices.',
			'wp-graphql'
		);
	} else if ('skipped' === wizardState?.status) {
		previousRunNotice = __(
			'You skipped this wizard before. You can review your settings now.',
			'wp-graphql'
		);
	}

	return (
		<div className="wpgraphql-setup-wizard__step">
			<h2>{__('Review how your GraphQL API is set up', 'wp-graphql')}</h2>
			<p>
				{__(
					'This wizard walks through settings that decide who can use your GraphQL API, how much work a single request can ask for, and what debugging information responses include. For each one it explains what you gain and what it costs.',
					'wp-graphql'
				)}
			</p>
			<p>
				{__(
					'Every setting starts at the value your site uses today. Nothing changes until you save on the last step, and you can come back to this wizard at any time from the GraphQL menu.',
					'wp-graphql'
				)}
			</p>
			{previousRunNotice && (
				<Notice status="info" isDismissible={false}>
					{previousRunNotice}
				</Notice>
			)}
			{docsUrl && (
				<p>
					<ExternalLink href={docsUrl}>
						{__('Read more about WPGraphQL security', 'wp-graphql')}
					</ExternalLink>
				</p>
			)}
		</div>
	);
}

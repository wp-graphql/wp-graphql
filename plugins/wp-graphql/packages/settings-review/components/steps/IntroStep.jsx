import { __, _n, sprintf } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { ExternalLink, Notice } from '@wordpress/components';

import { store } from '../../store';
import { getBootstrapData } from '../../api/settings-review';

/**
 * Renders the first step of the review.
 *
 * @return {JSX.Element} The step.
 */
export function IntroStep() {
	const { reviewState, unreviewedCount } = useSelect((select) => {
		const review = select(store);
		return {
			reviewState: review.getReviewState(),
			unreviewedCount: review.getUnreviewedKeys().length,
		};
	}, []);
	const { docsUrl } = getBootstrapData();

	let previousRunNotice = null;
	if (reviewState?.status && unreviewedCount > 0) {
		previousRunNotice = sprintf(
			/* translators: %d: number of settings added to the settings review since it was last run */
			_n(
				'%d setting was added since your last review. It is marked as new.',
				'%d settings were added since your last review. They are marked as new.',
				unreviewedCount,
				'wp-graphql'
			),
			unreviewedCount
		);
	} else if ('completed' === reviewState?.status) {
		previousRunNotice = __(
			'You completed this review before. Go through it again to revisit your choices.',
			'wp-graphql'
		);
	} else if ('skipped' === reviewState?.status) {
		previousRunNotice = __(
			'You skipped this review before. You can go through it now.',
			'wp-graphql'
		);
	}

	return (
		<div className="wpgraphql-settings-review__step">
			<h2>{__('Review how your GraphQL API is set up', 'wp-graphql')}</h2>
			<p>
				{__(
					'This review walks through settings that decide who can use your GraphQL API, how much work a single request can ask for, and what debugging information responses include. For each one it explains what you gain and what it costs.',
					'wp-graphql'
				)}
			</p>
			<p>
				{__(
					'Every setting starts at the value your site uses today. Nothing changes until you save on the last step, and you can come back to this review at any time from the Settings page.',
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

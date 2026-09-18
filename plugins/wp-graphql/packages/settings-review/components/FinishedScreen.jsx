import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	Notice,
} from '@wordpress/components';

import { store } from '../store';
import { getBootstrapData } from '../api/settings-review';

/**
 * Renders the screen shown after the review is completed or skipped.
 *
 * @return {JSX.Element} The screen.
 */
export function FinishedScreen() {
	const finishedStatus = useSelect(
		(select) => select(store).getFinishedStatus(),
		[]
	);
	const { restart } = useDispatch(store);
	const { settingsUrl } = getBootstrapData();

	return (
		<Card className="wpgraphql-settings-review__card">
			<CardBody>
				<Notice status="success" isDismissible={false}>
					{'completed' === finishedStatus
						? __('Your settings are saved.', 'wp-graphql')
						: __(
								'The review was skipped. Your settings were not changed.',
								'wp-graphql'
							)}
				</Notice>
				<p>
					{__(
						'You can review your settings again at any time from the Settings page, where you can also change individual settings.',
						'wp-graphql'
					)}
				</p>
			</CardBody>
			<CardFooter>
				<Button variant="secondary" onClick={restart}>
					{__('Review again', 'wp-graphql')}
				</Button>
				{settingsUrl && (
					<Button variant="primary" href={settingsUrl}>
						{__('Go to Settings', 'wp-graphql')}
					</Button>
				)}
			</CardFooter>
		</Card>
	);
}

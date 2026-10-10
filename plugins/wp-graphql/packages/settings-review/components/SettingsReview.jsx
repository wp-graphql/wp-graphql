import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	Notice,
} from '@wordpress/components';

import { store } from '../store';
import {
	INTRO_STEP,
	SUMMARY_STEP,
} from '../store/settings-review-store-reducer';
import { IntroStep } from './steps/IntroStep';
import { SettingsStep } from './steps/SettingsStep';
import { SummaryStep } from './steps/SummaryStep';
import { FinishedScreen } from './FinishedScreen';

/**
 * Returns the title shown in the step list for a step.
 *
 * @param {string}   slug            The step slug.
 * @param {Object[]} registeredSteps The registered steps.
 *
 * @return {string} The step title.
 */
function getStepTitle(slug, registeredSteps) {
	if (INTRO_STEP === slug) {
		return __('Welcome', 'wp-graphql');
	}

	if (SUMMARY_STEP === slug) {
		return __('Review and save', 'wp-graphql');
	}

	return registeredSteps.find((step) => step.slug === slug)?.title || slug;
}

/**
 * Renders the list of steps with the current one marked.
 *
 * @param {Object}   props
 * @param {string[]} props.stepSlugs       The step slugs, in order.
 * @param {Object[]} props.registeredSteps The registered steps.
 * @param {number}   props.currentIndex    The index of the current step.
 *
 * @return {JSX.Element} The step list.
 */
function StepList({ stepSlugs, registeredSteps, currentIndex }) {
	return (
		<ol className="wpgraphql-settings-review__steps">
			{stepSlugs.map((slug, index) => {
				let className = 'wpgraphql-settings-review__steps-item';
				if (index === currentIndex) {
					className += ' is-current';
				} else if (index < currentIndex) {
					className += ' is-done';
				}
				return (
					<li
						key={slug}
						className={className}
						aria-current={
							index === currentIndex ? 'step' : undefined
						}
					>
						{getStepTitle(slug, registeredSteps)}
					</li>
				);
			})}
		</ol>
	);
}

/**
 * Renders the current step's content.
 *
 * @param {Object} props
 * @param {string} props.slug The step slug.
 *
 * @return {JSX.Element} The step content.
 */
function StepContent({ slug }) {
	switch (slug) {
		case INTRO_STEP:
			return <IntroStep />;
		case SUMMARY_STEP:
			return <SummaryStep />;
		default:
			return <SettingsStep slug={slug} />;
	}
}

/**
 * The settings review app.
 *
 * @return {JSX.Element} The review.
 */
export function SettingsReview() {
	const {
		stepSlugs,
		registeredSteps,
		stepIndex,
		currentSlug,
		isSaving,
		error,
		finishedStatus,
	} = useSelect((select) => {
		const review = select(store);
		return {
			stepSlugs: review.getStepSlugs(),
			registeredSteps: review.getRegisteredSteps(),
			stepIndex: review.getStepIndex(),
			currentSlug: review.getCurrentStepSlug(),
			isSaving: review.isSaving(),
			error: review.getError(),
			finishedStatus: review.getFinishedStatus(),
		};
	}, []);
	const { nextStep, previousStep, complete, skip } = useDispatch(store);
	const headingRef = useRef(null);

	// Move focus to the step heading when the step changes, so keyboard and screen reader
	// users start at the top of the new step.
	useEffect(() => {
		if (stepIndex > 0 && headingRef.current) {
			headingRef.current.focus();
		}
	}, [stepIndex]);

	if (finishedStatus) {
		return <FinishedScreen />;
	}

	const isFirstStep = 0 === stepIndex;
	const isLastStep = stepSlugs.length - 1 === stepIndex;

	return (
		<Card className="wpgraphql-settings-review__card">
			<CardHeader>
				<StepList
					stepSlugs={stepSlugs}
					registeredSteps={registeredSteps}
					currentIndex={stepIndex}
				/>
			</CardHeader>
			<CardBody>
				<p
					className="wpgraphql-settings-review__progress"
					ref={headingRef}
					tabIndex={-1}
				>
					{sprintf(
						/* translators: 1: current step number, 2: total number of steps */
						__('Step %1$d of %2$d', 'wp-graphql'),
						stepIndex + 1,
						stepSlugs.length
					)}
				</p>
				{error && (
					<Notice status="error" isDismissible={false}>
						{error}
					</Notice>
				)}
				<StepContent slug={currentSlug} />
			</CardBody>
			<CardFooter className="wpgraphql-settings-review__footer">
				<div>
					{isFirstStep ? (
						<Button
							variant="tertiary"
							onClick={skip}
							isBusy={isSaving}
							disabled={isSaving}
						>
							{__('Skip for now', 'wp-graphql')}
						</Button>
					) : (
						<Button
							variant="secondary"
							onClick={previousStep}
							disabled={isSaving}
						>
							{__('Back', 'wp-graphql')}
						</Button>
					)}
				</div>
				<div>
					{isLastStep ? (
						<Button
							variant="primary"
							onClick={complete}
							isBusy={isSaving}
							disabled={isSaving}
						>
							{__('Save settings', 'wp-graphql')}
						</Button>
					) : (
						<Button variant="primary" onClick={nextStep}>
							{isFirstStep
								? __('Start', 'wp-graphql')
								: __('Next', 'wp-graphql')}
						</Button>
					)}
				</div>
			</CardFooter>
		</Card>
	);
}

<?php

namespace WPGraphQL\Admin\SettingsReview;

/**
 * Class ReviewState
 *
 * Reads and writes the option that records whether the settings review was completed or skipped,
 * and which settings were in it at the time.
 *
 * Keeping "which settings have been reviewed" separate from "which settings are in the review" is
 * what lets a later release add a setting and invite administrators back: the stored list simply
 * stops covering every current key.
 *
 * @package WPGraphQL\Admin\SettingsReview
 *
 * @phpstan-import-type SettingsReviewState from \WPGraphQL\Admin\SettingsReview\SettingsReview
 */
final class ReviewState {

	/**
	 * Returns the saved review state.
	 *
	 * @return SettingsReviewState
	 */
	public static function get(): array {
		$state = get_option( SettingsReview::STATE_OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * Records that the given settings have been reviewed.
	 *
	 * @param string   $status     'completed' or 'skipped'.
	 * @param string[] $field_keys The keys of every setting currently in the review.
	 *
	 * @return SettingsReviewState The saved state.
	 */
	public static function record( string $status, array $field_keys ): array {
		$state = [
			'status'     => $status,
			'reviewed'   => $field_keys,
			'updated_at' => time(),
		];

		// Not autoloaded: it is read on the review's own screens, not on every request.
		update_option( SettingsReview::STATE_OPTION, $state, false );

		return $state;
	}

	/**
	 * Returns the keys that haven't been reviewed yet.
	 *
	 * @param string[] $field_keys The keys of every setting currently in the review.
	 *
	 * @return string[]
	 */
	public static function unreviewed( array $field_keys ): array {
		$state    = self::get();
		$reviewed = isset( $state['reviewed'] ) && is_array( $state['reviewed'] ) ? $state['reviewed'] : [];

		return array_values( array_diff( $field_keys, $reviewed ) );
	}
}

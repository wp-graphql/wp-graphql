<?php

namespace WPGraphQL\Admin\SettingsReview;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class RestController
 *
 * The REST route the settings review saves through.
 *
 * Writes are built from the review's own field list rather than from the submitted payload, so a
 * request can only ever change settings the review actually shows. Anything else is rejected, and
 * locked settings are left alone. Values are validated against the field they belong to before the
 * section's registered sanitize callback runs on save.
 *
 * @package WPGraphQL\Admin\SettingsReview
 *
 * @phpstan-import-type SettingsReviewField from \WPGraphQL\Admin\SettingsReview\SettingsReview
 */
final class RestController {

	/**
	 * The review this route saves.
	 */
	private SettingsReview $review;

	/**
	 * RestController constructor.
	 *
	 * @param \WPGraphQL\Admin\SettingsReview\SettingsReview $review The settings review.
	 */
	public function __construct( SettingsReview $review ) {
		$this->review = $review;
	}

	/**
	 * Registers the REST API route the review saves through.
	 */
	public function register_routes(): void {
		register_rest_route(
			SettingsReview::REST_NAMESPACE,
			SettingsReview::REST_ROUTE,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'save' ],
				'permission_callback' => [ $this, 'can_save' ],
				'args'                => [
					'status'   => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'completed', 'skipped' ],
					],
					'settings' => [
						'required' => false,
						'type'     => 'object',
					],
				],
			]
		);
	}

	/**
	 * Whether the current user can save the review.
	 */
	public function can_save(): bool {
		return current_user_can( SettingsReview::CAPABILITY );
	}

	/**
	 * Saves the review.
	 *
	 * Completing the review saves the settings that were submitted, which are the ones the
	 * administrator changed. Settings that weren't changed keep their current stored value, or no
	 * stored value, so defaults that depend on the environment or change in a later version still
	 * apply to them. Skipping the review changes no settings. Either way, every setting currently in
	 * the review is recorded as reviewed.
	 *
	 * @param \WP_REST_Request<array{status:string,settings?:array<string,mixed>}> $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$status = (string) $request->get_param( 'status' );

		if ( 'completed' === $status ) {
			$settings = $request->get_param( 'settings' );
			$settings = is_array( $settings ) ? $settings : [];

			$prepared = $this->prepare_settings( $settings );

			if ( is_wp_error( $prepared ) ) {
				return $prepared;
			}

			foreach ( $prepared as $section => $values ) {
				$stored = get_option( $section, [] );
				$stored = is_array( $stored ) ? $stored : [];

				// The section's registered sanitize callback runs each field's sanitize_callback on save.
				update_option( $section, array_merge( $stored, $values ) );
			}
		}

		$state = $this->review->record_review( $status );

		return new WP_REST_Response(
			[
				'state'          => $state,
				'values'         => (object) $this->review->get_values(),
				'unreviewedKeys' => $this->review->get_unreviewed_field_keys(),
			]
		);
	}

	/**
	 * Validates the submitted settings and groups them by settings section.
	 *
	 * Settings are submitted as `{ section: { name: value } }` and only the submitted settings are
	 * saved. Settings that aren't in the review are rejected, and locked settings are left as they are.
	 *
	 * @param array<string,mixed> $settings The submitted settings.
	 *
	 * @return array<string,array<string,mixed>>|\WP_Error The values to save, grouped by section, or an error listing the invalid settings.
	 */
	public function prepare_settings( array $settings ) {
		$fields   = $this->review->get_fields();
		$errors   = [];
		$prepared = [];

		foreach ( $settings as $section => $values ) {
			if ( ! is_array( $values ) ) {
				$errors[ (string) $section ] = __( 'Settings must be grouped by settings section.', 'wp-graphql' );
				continue;
			}

			foreach ( array_keys( $values ) as $name ) {
				if ( ! isset( $fields[ $section . '.' . $name ] ) ) {
					$errors[ $section . '.' . $name ] = __( 'This setting is not part of the settings review.', 'wp-graphql' );
				}
			}
		}

		foreach ( $fields as $key => $field ) {
			if ( $field['locked'] ) {
				continue;
			}

			// Settings that weren't submitted are left as they are.
			if ( ! isset( $settings[ $field['section'] ] ) || ! is_array( $settings[ $field['section'] ] ) || ! array_key_exists( $field['name'], $settings[ $field['section'] ] ) ) {
				continue;
			}

			$value = $this->validate_value( $settings[ $field['section'] ][ $field['name'] ], $field );

			if ( null === $value ) {
				$errors[ $key ] = __( 'The value is not valid for this setting.', 'wp-graphql' );
				continue;
			}

			$prepared[ $field['section'] ][ $field['name'] ] = $value;
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'graphql_settings_review_invalid_settings',
				__( 'Some settings have invalid values.', 'wp-graphql' ),
				[
					'status' => 400,
					'errors' => $errors,
				]
			);
		}

		return $prepared;
	}

	/**
	 * Checks that a submitted value fits its field and converts it to the stored format.
	 *
	 * @param mixed               $value The submitted value.
	 * @param SettingsReviewField $field The field.
	 *
	 * @return mixed The value to store, or null if the value is not valid.
	 */
	private function validate_value( $value, array $field ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				if ( true === $value || 'on' === $value ) {
					return 'on';
				}
				if ( false === $value || 'off' === $value ) {
					return 'off';
				}
				return null;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return null;
				}
				$value = $value + 0;
				if ( ( null !== $field['min'] && $value < $field['min'] ) || ( null !== $field['max'] && $value > $field['max'] ) ) {
					return null;
				}
				return is_float( $value ) && floor( $value ) === $value ? (int) $value : $value;

			case 'select':
			case 'radio':
			case 'user_role_select':
				if ( ! is_string( $value ) ) {
					return null;
				}
				foreach ( $field['options'] as $option ) {
					if ( $option['value'] === $value ) {
						return $value;
					}
				}
				return null;

			case 'url':
				return is_string( $value ) ? esc_url_raw( $value ) : null;

			case 'textarea':
				return is_string( $value ) ? sanitize_textarea_field( $value ) : null;

			case 'text':
				return is_string( $value ) ? sanitize_text_field( $value ) : null;
		}

		return null;
	}
}

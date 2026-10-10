<?php

namespace WPGraphQL\Admin\SettingsReview;

/**
 * Class StepRegistry
 *
 * Holds the steps the settings review is divided into, and assembles the step list the review app
 * renders.
 *
 * Core registers `access`, `request-limits` and `diagnostics`. Plugins add their own with
 * register_graphql_settings_review_step(). A setting whose `settings_review` config doesn't name a
 * registered step falls back to a step for its settings section, which is built here rather than
 * registered, so an extension's settings show up without it having to register anything.
 *
 * @package WPGraphQL\Admin\SettingsReview
 *
 * @phpstan-import-type SettingsReviewStep from \WPGraphQL\Admin\SettingsReview\SettingsReview
 * @phpstan-import-type SettingsReviewStepConfig from \WPGraphQL\Admin\SettingsReview\SettingsReview
 * @phpstan-import-type SettingsReviewStepWithFields from \WPGraphQL\Admin\SettingsReview\SettingsReview
 * @phpstan-import-type SettingsReviewField from \WPGraphQL\Admin\SettingsReview\SettingsReview
 */
final class StepRegistry {

	/**
	 * The default order for a step that doesn't declare one.
	 */
	public const DEFAULT_ORDER = 100;

	/**
	 * The registered steps, keyed by slug.
	 *
	 * @var array<string,SettingsReviewStep>
	 */
	private array $steps = [];

	/**
	 * Registers a step.
	 *
	 * @param string                   $slug   The step slug.
	 * @param SettingsReviewStepConfig $config The step config.
	 */
	public function register( string $slug, array $config ): void {
		$slug = sanitize_key( $slug );

		if ( '' === $slug || empty( $config['title'] ) || ! is_string( $config['title'] ) ) {
			_doing_it_wrong( 'register_graphql_settings_review_step', esc_html__( 'A settings review step needs a slug and a title.', 'wp-graphql' ), '2.24.0' );
			return;
		}

		$this->steps[ $slug ] = [
			'slug'        => $slug,
			'title'       => $config['title'],
			'description' => isset( $config['description'] ) && is_string( $config['description'] ) ? $config['description'] : '',
			'order'       => isset( $config['order'] ) ? (int) $config['order'] : self::DEFAULT_ORDER,
		];
	}

	/**
	 * Registers the steps core ships.
	 */
	public function register_core_steps(): void {
		$this->register(
			'access',
			[
				'title'       => __( 'Access', 'wp-graphql' ),
				'description' => __( 'Choose who can use the GraphQL API and who can read its schema.', 'wp-graphql' ),
				'order'       => 10,
			]
		);

		$this->register(
			'request-limits',
			[
				'title'       => __( 'Request limits', 'wp-graphql' ),
				'description' => __( 'Choose how much work a single request can ask for.', 'wp-graphql' ),
				'order'       => 20,
			]
		);

		$this->register(
			'diagnostics',
			[
				'title'       => __( 'Diagnostics', 'wp-graphql' ),
				'description' => __( 'Choose which debugging information is added to responses, and who can see it.', 'wp-graphql' ),
				'order'       => 30,
			]
		);
	}

	/**
	 * Whether a step is registered.
	 *
	 * @param string $slug The step slug.
	 */
	public function has( string $slug ): bool {
		return isset( $this->steps[ $slug ] );
	}

	/**
	 * Returns the registered steps, keyed by slug.
	 *
	 * @return array<string,SettingsReviewStep>
	 */
	public function all(): array {
		return $this->steps;
	}

	/**
	 * Returns the steps that have settings to review, in display order.
	 *
	 * Includes a step for each settings section whose review settings don't name a registered step.
	 *
	 * @param array<string,SettingsReviewField> $fields   The settings in the review, keyed by "{section}.{name}".
	 * @param array<string,mixed>               $sections The registered settings sections.
	 *
	 * @return array<int,SettingsReviewStepWithFields>
	 */
	public function with_fields( array $fields, array $sections ): array {
		$steps = [];

		foreach ( $fields as $key => $field ) {
			$slug = $field['step'];

			if ( ! isset( $steps[ $slug ] ) ) {
				$step = $this->steps[ $slug ] ?? [
					'slug'        => $slug,
					'title'       => isset( $sections[ $field['section'] ]['title'] ) && is_string( $sections[ $field['section'] ]['title'] ) ? $sections[ $field['section'] ]['title'] : $field['section'],
					'description' => '',
					'order'       => self::DEFAULT_ORDER,
				];

				$steps[ $slug ] = array_merge( $step, [ 'fields' => [] ] );
			}

			$steps[ $slug ]['fields'][] = $key;
		}

		// Tie-broken on slug because every step built for a settings section gets DEFAULT_ORDER,
		// so two extensions' steps routinely share an order. Sorting is only stable as of PHP 8.0
		// and this plugin supports 7.4, so without a tiebreak their order is left to the sort.
		uasort(
			$steps,
			static function ( $a, $b ) {
				return [ $a['order'], $a['slug'] ] <=> [ $b['order'], $b['slug'] ];
			}
		);

		return array_values( $steps );
	}
}

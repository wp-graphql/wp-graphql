<?php

namespace WPGraphQL\Admin\SettingsReview;

use WPGraphQL\Admin\AdminNotices;
use WPGraphQL\Admin\Settings\SettingsRegistry;
use WP_REST_Request;

/**
 * Class SettingsReview
 *
 * A guided walkthrough of WPGraphQL settings that explains the tradeoffs of each choice.
 *
 * Settings opt in by adding a `settings_review` key to the config passed to
 * register_graphql_settings_field(). Steps are registered with register_graphql_settings_review_step(),
 * and a setting that doesn't name a registered step is shown in a step for its settings section.
 *
 * Site administrators are invited to the review until every setting in it has been reviewed, by
 * completing the review (which saves the settings) or skipping it (which changes nothing). When a
 * plugin update adds a setting to the review, administrators are invited again.
 *
 * This class is the entry point and the public surface. The work is split across four collaborators
 * it owns, and the methods here delegate to them:
 *
 * - {@see StepRegistry}  the steps the review is divided into
 * - {@see FieldFactory}  turning registered settings into reviewable fields, and reading their values
 * - {@see AdminPage}     the admin screen, its menu placement, assets and bootstrap data
 * - {@see RestController} the REST route the review saves through
 *
 * {@see ReviewState} reads and writes the option that records what has been reviewed.
 *
 * @package WPGraphQL\Admin\SettingsReview
 *
 * phpcs:disable -- For phpstan type hinting
 * @phpstan-type SettingsReviewStepConfig array{
 *   title: string,
 *   description?: string,
 *   order?: int,
 * }
 * @phpstan-type SettingsReviewStep array{
 *   slug: string,
 *   title: string,
 *   description: string,
 *   order: int,
 * }
 * @phpstan-type SettingsReviewStepWithFields array{
 *   slug: string,
 *   title: string,
 *   description: string,
 *   order: int,
 *   fields: string[],
 * }
 * @phpstan-type SettingsReviewOption array{
 *   value: string,
 *   label: string,
 * }
 * @phpstan-type SettingsReviewField array{
 *   key: string,
 *   section: string,
 *   name: string,
 *   type: string,
 *   step: string,
 *   order: int,
 *   label: string,
 *   description: string,
 *   options: SettingsReviewOption[],
 *   min: int|float|null,
 *   max: int|float|null,
 *   inputStep: int|float|null,
 *   default: mixed,
 *   benefits: string[],
 *   costs: string[],
 *   dependsOn: string|null,
 *   locked: bool,
 * }
 * @phpstan-type SettingsReviewState array{
 *   status?: string,
 *   reviewed?: string[],
 *   updated_at?: int,
 * }
 * phpcs:enable
 */
final class SettingsReview {

	/**
	 * The option that stores whether the review was completed or skipped, and which settings were reviewed.
	 */
	public const STATE_OPTION = 'graphql_settings_review';

	/**
	 * The admin page slug.
	 */
	public const PAGE_SLUG = 'graphql-settings-review';

	/**
	 * The slug of the admin notice that invites administrators to the review.
	 */
	public const NOTICE_SLUG = 'wpgraphql-settings-review';

	/**
	 * The REST API namespace.
	 */
	public const REST_NAMESPACE = 'wp-graphql/v1';

	/**
	 * The REST API route.
	 */
	public const REST_ROUTE = '/settings-review';

	/**
	 * The capability required to use the review. Matches the WPGraphQL settings page.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The settings field types the review can display.
	 */
	public const SUPPORTED_FIELD_TYPES = [ 'checkbox', 'number', 'select', 'radio', 'user_role_select', 'text', 'url', 'textarea' ];

	/**
	 * The settings registry the review reads settings from.
	 */
	private SettingsRegistry $registry;

	/**
	 * The steps the review is divided into.
	 */
	private StepRegistry $steps;

	/**
	 * Builds the reviewable fields from the settings registry.
	 */
	private FieldFactory $field_factory;

	/**
	 * The review's admin screen.
	 */
	private AdminPage $admin_page;

	/**
	 * The REST route the review saves through.
	 */
	private RestController $rest;

	/**
	 * Whether the steps have been registered.
	 */
	private bool $steps_initialized = false;

	/**
	 * SettingsReview constructor.
	 *
	 * @param \WPGraphQL\Admin\Settings\SettingsRegistry $registry The settings registry.
	 */
	public function __construct( SettingsRegistry $registry ) {
		$this->registry      = $registry;
		$this->steps         = new StepRegistry();
		$this->field_factory = new FieldFactory( $registry, $this->steps );
		$this->admin_page    = new AdminPage( $this );
		$this->rest          = new RestController( $this );
	}

	/**
	 * Registers the admin notice inviting administrators to the review.
	 *
	 * Must run before the admin notices are initialized. Settings aren't registered yet at that
	 * point, so the notice is removed later by maybe_remove_admin_notice() when there's nothing
	 * left to review.
	 */
	public static function register_admin_notice(): void {
		register_graphql_admin_notice(
			self::NOTICE_SLUG,
			[
				'type'           => 'info',
				'message'        => sprintf(
					/* translators: %s: URL of the settings review admin page */
					__( '<strong>Review your WPGraphQL settings.</strong> A short review explains the tradeoffs of settings for access, request limits and debugging. <a href="%s">Start the review</a>', 'wp-graphql' ),
					esc_url( self::get_page_url() )
				),
				// Each user can dismiss the invitation. The Review Settings menu item stays until the review is done.
				'is_dismissable' => true,
				'conditions'     => [ self::class, 'should_show_invitation' ],
			]
		);
	}

	/**
	 * Whether the invitation notice should show for the current user.
	 *
	 * The notice is also removed once every setting is reviewed (see maybe_remove_admin_notice()),
	 * and each user can dismiss it.
	 */
	public static function should_show_invitation(): bool {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		/**
		 * Filters whether administrators are invited to the settings review with an admin notice.
		 *
		 * The notice shows until the settings review is completed or skipped, or until each user
		 * dismisses it. Return false to never show it, for example on sites whose settings are
		 * managed in code. The Review Settings menu item and the link on the Settings page are not
		 * affected. To record the review as done without visiting it, run
		 * `wp graphql settings-review skip`.
		 *
		 * @param bool $show_invitation Whether to show the invitation notice. Default true.
		 *
		 * @hookGroup settings
		 * @since x-release-please-version
		 */
		return (bool) apply_filters( 'graphql_settings_review_show_invitation', true );
	}

	/**
	 * Returns the saved review state.
	 *
	 * @return SettingsReviewState
	 */
	public static function get_state(): array {
		return ReviewState::get();
	}

	/**
	 * Returns the URL of the review admin page.
	 */
	public static function get_page_url(): string {
		return AdminPage::get_url();
	}

	/**
	 * Initializes the review's admin page, assets and REST API route.
	 */
	public function init(): void {
		// After the settings registry is populated (priority 11).
		add_action( 'init', [ $this, 'maybe_remove_admin_notice' ], 20 );
		add_action( 'admin_menu', [ $this, 'register_admin_page' ] );
		add_filter( 'parent_file', [ $this, 'highlight_parent_menu' ] );
		add_filter( 'submenu_file', [ $this, 'highlight_settings_submenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Removes the invitation notice when every setting in the review has been reviewed.
	 */
	public function maybe_remove_admin_notice(): void {
		if ( ! $this->should_invite() ) {
			AdminNotices::get_instance()->remove_admin_notice( self::NOTICE_SLUG );
		}
	}

	/**
	 * Whether the current user should be invited to the review.
	 */
	public function should_invite(): bool {
		return current_user_can( self::CAPABILITY ) && ! empty( $this->get_unreviewed_field_keys() );
	}

	/**
	 * Registers a step.
	 *
	 * @param string                   $slug   The step slug.
	 * @param SettingsReviewStepConfig $config The step config.
	 */
	public function register_step( string $slug, array $config ): void {
		$this->steps->register( $slug, $config );
	}

	/**
	 * Returns the steps that have settings to review, in display order.
	 *
	 * Includes a step for each settings section whose review settings don't name a registered step.
	 *
	 * @return array<int,SettingsReviewStepWithFields>
	 */
	public function get_steps(): array {
		$this->init_steps();

		return $this->steps->with_fields( $this->get_fields(), $this->registry->get_settings_sections() );
	}

	/**
	 * Returns the settings shown in the review, keyed by "{section}.{name}".
	 *
	 * @return array<string,SettingsReviewField>
	 */
	public function get_fields(): array {
		$this->init_steps();

		return $this->field_factory->all();
	}

	/**
	 * Returns the current value of each setting in the review, keyed by "{section}.{name}".
	 *
	 * @return array<string,mixed>
	 */
	public function get_values(): array {
		$this->init_steps();

		return $this->field_factory->values();
	}

	/**
	 * Returns the keys of the settings in the review that haven't been reviewed.
	 *
	 * @return string[]
	 */
	public function get_unreviewed_field_keys(): array {
		return ReviewState::unreviewed( array_keys( $this->get_fields() ) );
	}

	/**
	 * Records that every setting currently in the review has been reviewed.
	 *
	 * @param string $status 'completed' or 'skipped'.
	 *
	 * @return SettingsReviewState The saved state.
	 */
	public function record_review( string $status ): array {
		return ReviewState::record( $status, array_keys( $this->get_fields() ) );
	}

	/**
	 * Registers the review admin page.
	 *
	 * The page has an item in the GraphQL menu while there are settings to review, the same rule as
	 * the invitation notice. Once the review is completed or skipped, the menu item is hidden and the
	 * review is reached from the Settings page, which stays highlighted in the menu while it's open.
	 */
	public function register_admin_page(): void {
		$this->admin_page->register();
	}

	/**
	 * Sets the browser tab title for the review page.
	 */
	public function set_page_title(): void {
		$this->admin_page->set_page_title();
	}

	/**
	 * Whether the review has an item in the GraphQL menu for the current request.
	 */
	public function shows_in_menu(): bool {
		return $this->admin_page->shows_in_menu();
	}

	/**
	 * Highlights the GraphQL menu while the review is open without a menu item of its own.
	 *
	 * @param string|null $parent_file The parent menu slug to highlight.
	 */
	public function highlight_parent_menu( $parent_file ): ?string {
		return $this->admin_page->highlight_parent_menu( $parent_file );
	}

	/**
	 * Highlights GraphQL > Settings while the review is open without a menu item of its own.
	 *
	 * @param string|null $submenu_file The submenu slug to highlight.
	 */
	public function highlight_settings_submenu( $submenu_file ): ?string {
		return $this->admin_page->highlight_settings_submenu( $submenu_file );
	}

	/**
	 * Renders the element the review app mounts into.
	 */
	public function render_admin_page(): void {
		$this->admin_page->render();
	}

	/**
	 * Returns the data the review app starts with.
	 *
	 * @return array<string,mixed>
	 */
	public function get_bootstrap_data(): array {
		return $this->admin_page->get_bootstrap_data();
	}

	/**
	 * Enqueues the review app on its admin page.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ): void {
		$this->admin_page->enqueue_scripts( $hook_suffix );
	}

	/**
	 * Registers the REST API route the review saves through.
	 */
	public function register_rest_routes(): void {
		$this->rest->register_routes();
	}

	/**
	 * Whether the current user can save the review.
	 */
	public function can_save(): bool {
		return $this->rest->can_save();
	}

	/**
	 * Saves the review.
	 *
	 * @param \WP_REST_Request<array{status:string,settings?:array<string,mixed>}> $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		return $this->rest->save( $request );
	}

	/**
	 * Validates the submitted settings and groups them by settings section.
	 *
	 * @param array<string,mixed> $settings The submitted settings.
	 *
	 * @return array<string,array<string,mixed>>|\WP_Error The values to save, grouped by section, or an error listing the invalid settings.
	 */
	public function prepare_settings( array $settings ) {
		return $this->rest->prepare_settings( $settings );
	}

	/**
	 * Registers the core steps and fires the action that lets plugins register theirs.
	 *
	 * The action passes this instance rather than the step registry, because
	 * register_graphql_settings_review_step() is the supported way to add a step and it calls back
	 * into register_step() here.
	 */
	private function init_steps(): void {
		if ( $this->steps_initialized ) {
			return;
		}

		$this->steps_initialized = true;

		$this->steps->register_core_steps();

		/**
		 * Fires when the settings review registers its steps.
		 *
		 * Use register_graphql_settings_review_step() rather than hooking this action directly.
		 *
		 * @param \WPGraphQL\Admin\SettingsReview\SettingsReview $settings_review The settings review instance.
		 *
		 * @hookGroup settings
		 * @since x-release-please-version
		 */
		do_action( 'graphql_settings_review_init', $this );
	}
}

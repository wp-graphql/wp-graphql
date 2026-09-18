<?php

namespace WPGraphQL\Admin\SetupWizard;

use WPGraphQL\Admin\AdminNotices;
use WPGraphQL\Admin\Settings\SettingsRegistry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class SetupWizard
 *
 * A guided walkthrough of WPGraphQL settings that explains the tradeoffs of each choice.
 *
 * Settings opt in by adding a `setup_wizard` key to the config passed to
 * register_graphql_settings_field(). Steps are registered with register_graphql_setup_wizard_step(),
 * and a setting that doesn't name a registered step is shown in a step for its settings section.
 *
 * Site administrators are invited to the wizard until every setting in it has been reviewed, by
 * completing the wizard (which saves the settings) or skipping it (which changes nothing). When a
 * plugin update adds a setting to the wizard, administrators are invited again.
 *
 * @package WPGraphQL\Admin\SetupWizard
 *
 * phpcs:disable -- For phpstan type hinting
 * @phpstan-type SetupWizardStepConfig array{
 *   title: string,
 *   description?: string,
 *   order?: int,
 * }
 * @phpstan-type SetupWizardStep array{
 *   slug: string,
 *   title: string,
 *   description: string,
 *   order: int,
 * }
 * @phpstan-type SetupWizardStepWithFields array{
 *   slug: string,
 *   title: string,
 *   description: string,
 *   order: int,
 *   fields: string[],
 * }
 * @phpstan-type SetupWizardOption array{
 *   value: string,
 *   label: string,
 * }
 * @phpstan-type SetupWizardField array{
 *   key: string,
 *   section: string,
 *   name: string,
 *   type: string,
 *   step: string,
 *   order: int,
 *   label: string,
 *   description: string,
 *   options: SetupWizardOption[],
 *   min: int|float|null,
 *   max: int|float|null,
 *   inputStep: int|float|null,
 *   default: mixed,
 *   benefits: string[],
 *   costs: string[],
 *   dependsOn: string|null,
 *   locked: bool,
 * }
 * @phpstan-type SetupWizardState array{
 *   status?: string,
 *   reviewed?: string[],
 *   updated_at?: int,
 * }
 * phpcs:enable
 */
final class SetupWizard {

	/**
	 * The option that stores whether the wizard was completed or skipped, and which settings were reviewed.
	 */
	public const STATE_OPTION = 'graphql_setup_wizard';

	/**
	 * The admin page slug.
	 */
	public const PAGE_SLUG = 'graphql-setup-wizard';

	/**
	 * The slug of the admin notice that invites administrators to the wizard.
	 */
	public const NOTICE_SLUG = 'wpgraphql-setup-wizard';

	/**
	 * The REST API namespace.
	 */
	public const REST_NAMESPACE = 'wp-graphql/v1';

	/**
	 * The REST API route.
	 */
	public const REST_ROUTE = '/setup-wizard';

	/**
	 * The capability required to use the wizard. Matches the WPGraphQL settings page.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The settings field types the wizard can display.
	 */
	public const SUPPORTED_FIELD_TYPES = [ 'checkbox', 'number', 'select', 'radio', 'user_role_select', 'text', 'url', 'textarea' ];

	/**
	 * The settings registry the wizard reads settings from.
	 */
	private SettingsRegistry $registry;

	/**
	 * The registered steps, keyed by slug.
	 *
	 * @var array<string,SetupWizardStep>
	 */
	private array $steps = [];

	/**
	 * Whether the steps have been registered.
	 */
	private bool $steps_initialized = false;

	/**
	 * The prepared wizard settings, once the settings registry is fully populated.
	 *
	 * @var array<string,SetupWizardField>|null
	 */
	private ?array $fields = null;

	/**
	 * The hook suffix of the admin page, set when the page is registered.
	 */
	private ?string $hook_suffix = null;

	/**
	 * Whether the wizard has an item in the GraphQL menu for the current request.
	 */
	private bool $shows_in_menu = false;

	/**
	 * SetupWizard constructor.
	 *
	 * @param \WPGraphQL\Admin\Settings\SettingsRegistry $registry The settings registry.
	 */
	public function __construct( SettingsRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * Registers the admin notice inviting administrators to the wizard.
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
					/* translators: %s: URL of the setup wizard admin page */
					__( '<strong>Review your WPGraphQL setup.</strong> A short wizard explains the tradeoffs of settings for access, request limits and debugging. <a href="%s">Start the setup wizard</a>', 'wp-graphql' ),
					esc_url( self::get_page_url() )
				),
				'is_dismissable' => false,
				'conditions'     => static function () {
					return current_user_can( self::CAPABILITY );
				},
			]
		);
	}

	/**
	 * Initializes the wizard's admin page, assets and REST API route.
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
	 * Removes the invitation notice when every setting in the wizard has been reviewed.
	 */
	public function maybe_remove_admin_notice(): void {
		if ( ! $this->should_invite() ) {
			AdminNotices::get_instance()->remove_admin_notice( self::NOTICE_SLUG );
		}
	}

	/**
	 * Whether the current user should be invited to the wizard.
	 */
	public function should_invite(): bool {
		return current_user_can( self::CAPABILITY ) && ! empty( $this->get_unreviewed_field_keys() );
	}

	/**
	 * Registers a step.
	 *
	 * @param string                $slug   The step slug.
	 * @param SetupWizardStepConfig $config The step config.
	 */
	public function register_step( string $slug, array $config ): void {
		$slug = sanitize_key( $slug );

		if ( '' === $slug || empty( $config['title'] ) || ! is_string( $config['title'] ) ) {
			_doing_it_wrong( 'register_graphql_setup_wizard_step', esc_html__( 'A setup wizard step needs a slug and a title.', 'wp-graphql' ), 'x-release-please-version' );
			return;
		}

		$this->steps[ $slug ] = [
			'slug'        => $slug,
			'title'       => $config['title'],
			'description' => isset( $config['description'] ) && is_string( $config['description'] ) ? $config['description'] : '',
			'order'       => isset( $config['order'] ) ? (int) $config['order'] : 100,
		];
	}

	/**
	 * Registers the core steps and fires the action that lets plugins register theirs.
	 */
	private function init_steps(): void {
		if ( $this->steps_initialized ) {
			return;
		}

		$this->steps_initialized = true;

		$this->register_step(
			'access',
			[
				'title'       => __( 'Access', 'wp-graphql' ),
				'description' => __( 'Choose who can use the GraphQL API and who can read its schema.', 'wp-graphql' ),
				'order'       => 10,
			]
		);

		$this->register_step(
			'request-limits',
			[
				'title'       => __( 'Request limits', 'wp-graphql' ),
				'description' => __( 'Choose how much work a single request can ask for.', 'wp-graphql' ),
				'order'       => 20,
			]
		);

		$this->register_step(
			'diagnostics',
			[
				'title'       => __( 'Diagnostics', 'wp-graphql' ),
				'description' => __( 'Choose which debugging information is added to responses, and who can see it.', 'wp-graphql' ),
				'order'       => 30,
			]
		);

		/**
		 * Fires when the setup wizard registers its steps.
		 *
		 * Use register_graphql_setup_wizard_step() rather than hooking this action directly.
		 *
		 * @param \WPGraphQL\Admin\SetupWizard\SetupWizard $setup_wizard The setup wizard instance.
		 *
		 * @hookGroup settings
		 * @since x-release-please-version
		 */
		do_action( 'graphql_setup_wizard_init', $this );
	}

	/**
	 * Returns the steps that have settings to review, in display order.
	 *
	 * Includes a step for each settings section whose wizard settings don't name a registered step.
	 *
	 * @return array<int,SetupWizardStepWithFields>
	 */
	public function get_steps(): array {
		$this->init_steps();

		$fields   = $this->get_fields();
		$sections = $this->registry->get_settings_sections();
		$steps    = [];

		foreach ( $fields as $key => $field ) {
			$slug = $field['step'];

			if ( ! isset( $steps[ $slug ] ) ) {
				$step = $this->steps[ $slug ] ?? [
					'slug'        => $slug,
					'title'       => isset( $sections[ $field['section'] ]['title'] ) && is_string( $sections[ $field['section'] ]['title'] ) ? $sections[ $field['section'] ]['title'] : $field['section'],
					'description' => '',
					'order'       => 100,
				];

				$steps[ $slug ] = array_merge( $step, [ 'fields' => [] ] );
			}

			$steps[ $slug ]['fields'][] = $key;
		}

		uasort(
			$steps,
			static function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);

		return array_values( $steps );
	}

	/**
	 * Returns the settings shown in the wizard, keyed by "{section}.{name}".
	 *
	 * @return array<string,SetupWizardField>
	 */
	public function get_fields(): array {
		if ( null !== $this->fields ) {
			return $this->fields;
		}

		$this->init_steps();

		$fields = [];
		$index  = 0;

		foreach ( $this->registry->get_settings_fields() as $section => $section_fields ) {
			foreach ( $section_fields as $field ) {
				++$index;

				$field = $this->prepare_field( (string) $section, $field, $index );

				if ( null !== $field ) {
					$fields[ $field['key'] ] = $field;
				}
			}
		}

		// Drop dependencies on settings that aren't in the wizard.
		foreach ( $fields as $key => $field ) {
			if ( null !== $field['dependsOn'] && ! isset( $fields[ $field['dependsOn'] ] ) ) {
				$fields[ $key ]['dependsOn'] = null;
			}
		}

		uasort(
			$fields,
			static function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);

		// Settings are registered on `init`. Only keep the result once registration is done.
		if ( did_action( 'graphql_init_settings' ) ) {
			$this->fields = $fields;
		}

		return $fields;
	}

	/**
	 * Prepares a registered settings field for the wizard.
	 *
	 * @param string              $section The settings section the field is registered to.
	 * @param array<string,mixed> $field   The registered field config.
	 * @param int                 $index   The position the field was registered in.
	 *
	 * @return SetupWizardField|null The prepared field, or null if the field isn't shown in the wizard.
	 */
	private function prepare_field( string $section, array $field, int $index ): ?array {
		$config = $field['setup_wizard'] ?? false;

		if ( true === $config ) {
			$config = [];
		}

		if ( ! is_array( $config ) || empty( $field['name'] ) || ! is_string( $field['name'] ) ) {
			return null;
		}

		$type = isset( $field['type'] ) && is_string( $field['type'] ) ? $field['type'] : 'text';

		if ( ! in_array( $type, self::SUPPORTED_FIELD_TYPES, true ) ) {
			_doing_it_wrong(
				'register_graphql_settings_field',
				sprintf(
					/* translators: 1: settings field name, 2: settings field type */
					esc_html__( 'The "%1$s" setting can\'t be shown in the setup wizard because the setup wizard doesn\'t support the "%2$s" field type.', 'wp-graphql' ),
					esc_html( $field['name'] ),
					esc_html( $type )
				),
				'x-release-please-version'
			);
			return null;
		}

		$name = $field['name'];
		$step = isset( $config['step'] ) && is_string( $config['step'] ) ? sanitize_key( $config['step'] ) : '';

		if ( '' === $step || ! isset( $this->steps[ $step ] ) ) {
			$step = sanitize_key( $section );
		}

		$label       = isset( $config['label'] ) && is_string( $config['label'] ) ? $config['label'] : ( isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : $name );
		$description = isset( $config['description'] ) && is_string( $config['description'] ) ? $config['description'] : ( isset( $field['desc'] ) && is_string( $field['desc'] ) ? wp_strip_all_tags( $field['desc'] ) : '' );

		return [
			'key'         => $section . '.' . $name,
			'section'     => $section,
			'name'        => $name,
			'type'        => $type,
			'step'        => $step,
			'order'       => isset( $config['order'] ) ? (int) $config['order'] : $index,
			'label'       => $label,
			'description' => $description,
			'options'     => $this->get_field_options( $type, $field ),
			'min'         => isset( $field['min'] ) && is_numeric( $field['min'] ) ? $field['min'] + 0 : null,
			'max'         => isset( $field['max'] ) && is_numeric( $field['max'] ) ? $field['max'] + 0 : null,
			'inputStep'   => isset( $field['step'] ) && is_numeric( $field['step'] ) ? $field['step'] + 0 : null,
			'default'     => $field['default'] ?? '',
			'benefits'    => self::string_list( $config['benefits'] ?? [] ),
			'costs'       => self::string_list( $config['costs'] ?? [] ),
			'dependsOn'   => isset( $field['depends_on'] ) && is_string( $field['depends_on'] ) && '' !== $field['depends_on'] ? $section . '.' . $field['depends_on'] : null,
			'locked'      => ! empty( $field['disabled'] ),
		];
	}

	/**
	 * Returns the choices for a select, radio or user role field.
	 *
	 * @param string              $type  The field type.
	 * @param array<string,mixed> $field The registered field config.
	 *
	 * @return SetupWizardOption[]
	 */
	private function get_field_options( string $type, array $field ): array {
		if ( 'user_role_select' === $type ) {
			$options = [
				[
					'value' => 'any',
					'label' => __( 'Any user, including logged-out visitors', 'wp-graphql' ),
				],
			];

			foreach ( wp_roles()->get_names() as $role => $role_name ) {
				$options[] = [
					'value' => (string) $role,
					'label' => translate_user_role( $role_name ),
				];
			}

			return $options;
		}

		if ( ! in_array( $type, [ 'select', 'radio' ], true ) || empty( $field['options'] ) || ! is_array( $field['options'] ) ) {
			return [];
		}

		$options = [];

		foreach ( $field['options'] as $value => $label ) {
			$options[] = [
				'value' => (string) $value,
				'label' => is_string( $label ) ? wp_strip_all_tags( $label ) : (string) $value,
			];
		}

		return $options;
	}

	/**
	 * Returns only the non-empty strings in a list.
	 *
	 * @param mixed $items The list.
	 *
	 * @return string[]
	 */
	private static function string_list( $items ): array {
		if ( ! is_array( $items ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return is_string( $item ) && '' !== $item;
				}
			)
		);
	}

	/**
	 * Returns the saved wizard state.
	 *
	 * @return SetupWizardState
	 */
	public static function get_state(): array {
		$state = get_option( self::STATE_OPTION, [] );

		return is_array( $state ) ? $state : [];
	}

	/**
	 * Returns the keys of the settings in the wizard that haven't been reviewed.
	 *
	 * @return string[]
	 */
	public function get_unreviewed_field_keys(): array {
		$state    = self::get_state();
		$reviewed = isset( $state['reviewed'] ) && is_array( $state['reviewed'] ) ? $state['reviewed'] : [];

		return array_values( array_diff( array_keys( $this->get_fields() ), $reviewed ) );
	}

	/**
	 * Returns the current value of each setting in the wizard, keyed by "{section}.{name}".
	 *
	 * @return array<string,mixed>
	 */
	public function get_values(): array {
		$registered = [];

		foreach ( $this->registry->get_settings_fields() as $section => $section_fields ) {
			foreach ( $section_fields as $field ) {
				if ( isset( $field['name'] ) && is_string( $field['name'] ) ) {
					$registered[ $section . '.' . $field['name'] ] = $field;
				}
			}
		}

		$values = [];

		foreach ( $this->get_fields() as $key => $field ) {
			$stored = get_option( $field['section'], [] );
			$stored = is_array( $stored ) ? $stored : [];

			$value = $stored[ $field['name'] ] ?? $field['default'];

			// A locked field can declare the value that applies, for example when a constant decides it.
			// Only locked fields use it: for other fields it was computed when settings were registered and
			// can be out of date after the wizard saves.
			if ( $field['locked'] && array_key_exists( 'value', $registered[ $key ] ?? [] ) ) {
				$value = $registered[ $key ]['value'];
			}

			if ( 'checkbox' === $field['type'] ) {
				$value = ( 'on' === $value || true === $value ) ? 'on' : 'off';
			}

			$values[ $key ] = $value;
		}

		return $values;
	}

	/**
	 * Registers the wizard admin page.
	 *
	 * The page has an item in the GraphQL menu while there are settings to review, the same rule as
	 * the invitation notice. Once the wizard is completed or skipped, the menu item is hidden and the
	 * wizard is reached from the Settings page, which stays highlighted in the menu while it's open.
	 */
	public function register_admin_page(): void {
		$this->shows_in_menu = $this->should_invite();

		// An empty parent registers the page (URL, capability check) without a menu item.
		$parent_slug = $this->shows_in_menu ? self::get_menu_parent_slug() : '';

		$hook_suffix = add_submenu_page(
			$parent_slug,
			__( 'WPGraphQL Setup Wizard', 'wp-graphql' ),
			__( 'Setup Wizard', 'wp-graphql' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);

		$this->hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : null;

		if ( null !== $this->hook_suffix ) {
			add_action( 'load-' . $this->hook_suffix, [ $this, 'set_page_title' ] );
		}
	}

	/**
	 * Sets the browser tab title for the wizard page.
	 *
	 * WordPress takes the title from the admin menu, and the wizard may not have a menu item.
	 */
	public function set_page_title(): void {
		global $title;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress reads the admin page title from this global.
		$title = __( 'WPGraphQL Setup Wizard', 'wp-graphql' );
	}

	/**
	 * Returns the URL of the wizard admin page.
	 */
	public static function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Whether the current admin page is the wizard.
	 */
	private function is_wizard_page(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return null !== $this->hook_suffix && $screen instanceof \WP_Screen && $this->hook_suffix === $screen->id;
	}

	/**
	 * Returns the slug of the GraphQL menu the wizard belongs to.
	 */
	private static function get_menu_parent_slug(): string {
		return 'off' === get_graphql_setting( 'graphiql_enabled' ) ? 'graphql-settings' : 'graphiql-ide';
	}

	/**
	 * Whether the wizard has an item in the GraphQL menu for the current request.
	 */
	public function shows_in_menu(): bool {
		return $this->shows_in_menu;
	}

	/**
	 * Highlights the GraphQL menu while the wizard is open without a menu item of its own.
	 *
	 * @param string|null $parent_file The parent menu slug to highlight.
	 */
	public function highlight_parent_menu( $parent_file ): ?string {
		if ( $this->shows_in_menu || ! $this->is_wizard_page() ) {
			return $parent_file;
		}

		return self::get_menu_parent_slug();
	}

	/**
	 * Highlights GraphQL > Settings while the wizard is open without a menu item of its own.
	 *
	 * @param string|null $submenu_file The submenu slug to highlight.
	 */
	public function highlight_settings_submenu( $submenu_file ): ?string {
		if ( $this->shows_in_menu || ! $this->is_wizard_page() ) {
			return $submenu_file;
		}

		return 'graphql-settings';
	}

	/**
	 * Renders the element the wizard app mounts into.
	 */
	public function render_admin_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WPGraphQL Setup Wizard', 'wp-graphql' ) . '</h1>';

		if ( ! file_exists( WPGRAPHQL_PLUGIN_DIR . 'build/setupWizard.asset.php' ) ) {
			$message = sprintf(
				/* translators: 1: npm ci command, 2: npm run build command, 3: releases URL */
				__( 'The Setup Wizard requires JavaScript assets that need to be built. Please run %1$s followed by %2$s in the plugin directory, or <a href="%3$s" target="_blank">download a release</a> that includes pre-built assets.', 'wp-graphql' ),
				'<code>npm ci</code>',
				'<code>npm run build</code>',
				'https://github.com/wp-graphql/wp-graphql/releases'
			);
			echo '<div class="notice notice-warning inline" style="margin-top: 20px;"><p>' . wp_kses_post( $message ) . '</p></div>';
		} else {
			echo '<div id="wpgraphql-setup-wizard"></div>';
		}

		echo '</div>';
	}

	/**
	 * Returns the data the wizard app starts with.
	 *
	 * @return array<string,mixed>
	 */
	public function get_bootstrap_data(): array {
		$state = self::get_state();

		return [
			'restPath'       => self::REST_NAMESPACE . self::REST_ROUTE,
			'steps'          => $this->get_steps(),
			'fields'         => array_values( $this->get_fields() ),
			'values'         => (object) $this->get_values(),
			'unreviewedKeys' => $this->get_unreviewed_field_keys(),
			'state'          => (object) $state,
			'settingsUrl'    => admin_url( 'admin.php?page=graphql-settings' ),
			'docsUrl'        => 'https://www.wpgraphql.com/docs/security',
		];
	}

	/**
	 * Enqueues the wizard app on its admin page.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ): void {
		if ( null === $this->hook_suffix || $this->hook_suffix !== $hook_suffix ) {
			return;
		}

		$asset_path = WPGRAPHQL_PLUGIN_DIR . 'build/setupWizard.asset.php';

		// Bail if build assets don't exist (e.g., dev install without running npm build).
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is validated with file_exists() above
		$asset_file = include $asset_path;

		wp_enqueue_script(
			'wpgraphql-setup-wizard',
			WPGRAPHQL_PLUGIN_URL . 'build/setupWizard.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		wp_set_script_translations( 'wpgraphql-setup-wizard', 'wp-graphql', WPGRAPHQL_PLUGIN_DIR . 'languages' );

		wp_localize_script( 'wpgraphql-setup-wizard', 'wpgraphqlSetupWizard', $this->get_bootstrap_data() );

		wp_enqueue_style( 'wp-components' );

		if ( file_exists( WPGRAPHQL_PLUGIN_DIR . 'build/setupWizard.css' ) ) {
			wp_enqueue_style(
				'wpgraphql-setup-wizard',
				WPGRAPHQL_PLUGIN_URL . 'build/setupWizard.css',
				[ 'wp-components' ],
				$asset_file['version']
			);
		}
	}

	/**
	 * Registers the REST API route the wizard saves through.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
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
	 * Whether the current user can save the wizard.
	 */
	public function can_save(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * Saves the wizard.
	 *
	 * Completing the wizard saves every setting in it. Skipping it changes no settings. Either way,
	 * the settings currently in the wizard are recorded as reviewed.
	 *
	 * @param \WP_REST_Request<array{status:string,settings?:array<string,mixed>}> $request The REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save( WP_REST_Request $request ) {
		$status = (string) $request->get_param( 'status' );

		if ( 'completed' === $status ) {
			$settings = $request->get_param( 'settings' );

			if ( ! is_array( $settings ) ) {
				return new WP_Error(
					'graphql_setup_wizard_missing_settings',
					__( 'Settings are required to complete the setup wizard.', 'wp-graphql' ),
					[ 'status' => 400 ]
				);
			}

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

		$state = [
			'status'     => $status,
			'reviewed'   => array_keys( $this->get_fields() ),
			'updated_at' => time(),
		];

		update_option( self::STATE_OPTION, $state, false );

		return new WP_REST_Response(
			[
				'state'          => $state,
				'values'         => (object) $this->get_values(),
				'unreviewedKeys' => $this->get_unreviewed_field_keys(),
			]
		);
	}

	/**
	 * Validates the submitted settings and groups them by settings section.
	 *
	 * Every setting in the wizard that isn't locked must be submitted, as
	 * `{ section: { name: value } }`. Settings that aren't in the wizard are rejected, and locked
	 * settings are left as they are.
	 *
	 * @param array<string,mixed> $settings The submitted settings.
	 *
	 * @return array<string,array<string,mixed>>|\WP_Error The values to save, grouped by section, or an error listing the invalid settings.
	 */
	public function prepare_settings( array $settings ) {
		$fields   = $this->get_fields();
		$errors   = [];
		$prepared = [];

		foreach ( $settings as $section => $values ) {
			if ( ! is_array( $values ) ) {
				$errors[ (string) $section ] = __( 'Settings must be grouped by settings section.', 'wp-graphql' );
				continue;
			}

			foreach ( array_keys( $values ) as $name ) {
				if ( ! isset( $fields[ $section . '.' . $name ] ) ) {
					$errors[ $section . '.' . $name ] = __( 'This setting is not part of the setup wizard.', 'wp-graphql' );
				}
			}
		}

		foreach ( $fields as $key => $field ) {
			if ( $field['locked'] ) {
				continue;
			}

			if ( ! isset( $settings[ $field['section'] ] ) || ! is_array( $settings[ $field['section'] ] ) || ! array_key_exists( $field['name'], $settings[ $field['section'] ] ) ) {
				$errors[ $key ] = __( 'A value is required.', 'wp-graphql' );
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
				'graphql_setup_wizard_invalid_settings',
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
	 * @param mixed            $value The submitted value.
	 * @param SetupWizardField $field The field.
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

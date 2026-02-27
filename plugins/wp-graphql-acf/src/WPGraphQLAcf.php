<?php

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Acf\Admin\OptionsPageRegistration;
use WPGraphQL\Acf\Admin\PostTypeRegistration;
use WPGraphQL\Acf\Admin\TaxonomyRegistration;
use WPGraphQL\Acf\Registry;
use WPGraphQL\Acf\ThirdParty;
use WPGraphQL\Registry\TypeRegistry;

class WPGraphQLAcf {

	/**
	 * @var \WPGraphQL\Acf\Admin\Settings
	 */
	protected $admin_settings;

	/**
	 * @var array<string>
	 */
	protected $plugin_load_error_messages = [];

	/**
	 * @var \WPGraphQL\Acf\Registry|null
	 */
	protected $registry;

	/**
	 * Initialize the plugin
	 */
	public function init(): void {

		// If the plugin cannot load (missing ACF, duplicate, or WPGraphQL version), show messages later.
		// We use a boolean check here to avoid triggering translation loading before the init action (WP 6.7+).
		if ( ! $this->can_load_plugin() ) {
			add_action( 'admin_init', [ $this, 'show_admin_notice' ] );
			add_action( 'graphql_init', [ $this, 'show_graphql_debug_messages' ] );
			return;
		}

		add_action( 'wpgraphql/acf/init', [ $this, 'init_third_party_support' ] );
		add_action( 'admin_init', [ $this, 'init_admin_settings' ] );
		// Run on init (not after_setup_theme) so translations load at init or later (WordPress 6.7+).
		add_action( 'init', [ $this, 'acf_internal_post_type_support' ], 20 );
		add_action( 'graphql_register_types', [ $this, 'init_registry' ] );

		add_filter( 'graphql_resolve_revision_meta_from_parent', [ $this, 'preview_support' ], 10, 4 );

		add_filter( 'graphql_data_loader_classes', [ $this, 'register_loaders' ], 10, 2 );
		add_filter( 'graphql_resolve_node_type', [ $this, 'resolve_acf_options_page_node' ], 10, 2 );
		/**
		 * This filters any field that returns the `ContentTemplate` type
		 * to pass the source node down to the template for added context
		 */
		add_filter( 'graphql_resolve_field', [ $this, 'page_template_resolver' ], 10, 9 );

		// Fire on init so any code using translations (e.g. third party init) runs at init or later (WordPress 6.7+).
		add_action( 'init', [ $this, 'fire_wpgraphql_acf_init' ], 15 );
	}

	/**
	 * Fires the wpgraphql/acf/init action. Called on the init hook so translations load at init or later.
	 */
	public function fire_wpgraphql_acf_init(): void {
		do_action( 'wpgraphql/acf/init' );
	}

	/**
	 * Initialize third party support (i.e. Smart Cache, ACF Extended)
	 */
	public function init_third_party_support(): void {
		$third_party = new ThirdParty();
		$third_party->init();
	}

	/**
	 * Initialize support for Admin Settings
	 */
	public function init_admin_settings(): void {
		$this->admin_settings = new WPGraphQL\Acf\Admin\Settings();
		$this->admin_settings->init();
	}

	/**
	 * Add functionality to the Custom Post Type and Custom Taxonomy registration screens
	 * and underlying functionality (like exports, php code generation)
	 */
	public function acf_internal_post_type_support(): void {
		$taxonomy_registration_screen = new TaxonomyRegistration();
		$taxonomy_registration_screen->init();

		$cpt_registration_screen = new PostTypeRegistration();
		$cpt_registration_screen->init();

		$options_page_registration_screen = new OptionsPageRegistration();
		$options_page_registration_screen->init();
	}

	/**
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public function init_registry( TypeRegistry $type_registry ): void {

		// Register general types that should be available to the Schema regardless
		// of the specific fields and field groups registered by ACF
		$this->registry = new Registry( $type_registry );
		$this->registry->register_initial_graphql_types();
		$this->registry->register_options_pages();

		// Get the field groups that should be mapped to the Schema
		$acf_field_groups = $this->registry->get_acf_field_groups();

		// If there are no acf field groups to show in GraphQL, do nothing
		if ( empty( $acf_field_groups ) ) {
			return;
		}

		$this->registry->register_acf_field_groups_to_graphql( $acf_field_groups );
	}

	/**
	 * @param int $post_id The ID of the post to check if it's a preview of another post
	 *
	 * @return bool|\WP_Post
	 */
	protected function is_preview_post( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return false; // Post does not exist
		}

		// Check if it's a revision (autosave)
		if ( 'revision' === $post->post_type && 'inherit' === $post->post_status ) {
			$parent_post = get_post( $post->post_parent );

			// Check if parent post is either a draft or published
			if ( $parent_post && in_array( $parent_post->post_status, [ 'draft', 'publish' ], true ) ) {
				return $parent_post; // It's a preview of a draft or published post
			}
		}

		return false; // Not a preview post
	}


	/**
	 * Add support for resolving ACF Fields when queried for asPreview
	 *
	 * NOTE: this currently only works if classic editor is not being used
	 *
	 * @param bool    $should Whether to resolve using the parent object. Default true.
	 * @param int     $object_id The ID of the object to resolve meta for
	 * @param ?string $meta_key The key for the meta to resolve (null when get_post_meta is called with only object_id).
	 * @param ?bool   $single Whether a single value should be returned
	 */
	public function preview_support( bool $should, int $object_id, ?string $meta_key, ?bool $single ): bool {
		if ( ! $this->registry instanceof Registry ) {
			return (bool) $should;
		}

		$preview_post = $this->is_preview_post( $object_id );
		if ( ! $preview_post instanceof WP_Post ) {
			return (bool) $should;
		}

		// If the block editor is being used for the post, bail early as the Block Editor doesn't
		// properly support revisions of post meta
		// see: https://github.com/WordPress/gutenberg/issues/16006#issuecomment-657965028
		if ( \use_block_editor_for_post( $preview_post ) ) {
			graphql_debug( __( 'The post you are querying as a preview uses the Block Editor and saving & previewing meta is not fully supported by the block editor. This is a WordPress block editor bug. See: https://github.com/WordPress/gutenberg/issues/16006#issuecomment-657965028', 'wpgraphql-acf' ) );
			return (bool) $should;
		}

		// When meta_key is null or empty (e.g. get_post_meta( $id ) with one argument), passthrough.
		if ( $meta_key === null || $meta_key === '' ) {
			return (bool) $should;
		}

		$registered_fields = $this->registry->get_registered_fields();

		if ( in_array( $meta_key, $registered_fields, true ) ) {
			return false;
		}

		foreach ( $registered_fields as $field_name ) {
			// For flex fields/repeaters, the meta keys are structured a bit funky.
			// This checks to see if the $meta_key starts with the same string as one of the
			// acf fields (a flex/repeater field) and then checks if it's preceeded by an underscore and a number.
			if ( strpos( $meta_key, $field_name ) === 0 ) {
				// match any string that starts with the field name, followed by an underscore, followed by a number, followed by another string
				// ex my_flex_field_0_text_field or some_repeater_field_12_25MostPopularDogToys
				$pattern = '/' . $field_name . '_\d+_\w+/m';
				preg_match( $pattern, $meta_key, $matches );

				// If the meta key matches the pattern, treat it as a sub-field of an ACF Field Group
				if ( null !== $matches ) {
					return false;
				}
			}
		}

		return $should;
	}

	/**
	 * Whether the plugin can load. Uses only boolean checks so it is safe to call before the init action
	 * (avoids triggering translation loading too early for WordPress 6.7+).
	 */
	public function can_load_plugin(): bool {
		if ( ! class_exists( 'ACF' ) ) {
			return false;
		}
		if ( class_exists( 'WPGraphQL\ACF\ACF' ) ) {
			return false;
		}
		if ( ! class_exists( 'WPGraphQL' ) || ! defined( 'WPGRAPHQL_VERSION' ) ) {
			return false;
		}
		if ( true === version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_FOR_ACF_VERSION_WPGRAPHQL_REQUIRED_MIN_VERSION, 'lt' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Translated error messages when the plugin cannot load. Call only from display contexts (admin_notice, graphql_debug)
	 * so translation loads at init or later (WordPress 6.7+).
	 *
	 * @return array<string>
	 */
	public function get_plugin_load_error_messages(): array {
		if ( ! empty( $this->plugin_load_error_messages ) ) {
			return $this->plugin_load_error_messages;
		}

		if ( ! class_exists( 'ACF' ) ) {
			$this->plugin_load_error_messages[] = __( 'Advanced Custom Fields must be installed and activated', 'wpgraphql-acf' );
		}

		if ( class_exists( 'WPGraphQL\ACF\ACF' ) ) {
			$this->plugin_load_error_messages[] = __( 'Multiple versions of WPGraphQL for ACF cannot be active at the same time', 'wpgraphql-acf' );
		}

		if ( ! class_exists( 'WPGraphQL' ) || ! defined( 'WPGRAPHQL_VERSION' ) || true === version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_FOR_ACF_VERSION_WPGRAPHQL_REQUIRED_MIN_VERSION, 'lt' ) ) {
			$this->plugin_load_error_messages[] = sprintf(
				/* translators: %s: minimum required WPGraphQL version */
				__( 'WPGraphQL v%s or higher is required to be installed and active', 'wpgraphql-acf' ),
				WPGRAPHQL_FOR_ACF_VERSION_WPGRAPHQL_REQUIRED_MIN_VERSION
			);
		}

		return $this->plugin_load_error_messages;
	}

	/**
	 * Show admin notice to admins if this plugin is active but either ACF and/or WPGraphQL
	 * are not active. Called on admin_init (after init), so translations are safe.
	 */
	public function show_admin_notice(): void {
		if ( $this->can_load_plugin() ) {
			return;
		}
		$can_load_messages = $this->get_plugin_load_error_messages();

		/**
		 * For users with lower capabilities, don't show the notice
		 */
		if ( empty( $can_load_messages ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $can_load_messages ) {
				?>
				<div class="error notice">
					<h3>
						<?php
							// translators: %s is the version of the plugin
							echo esc_html( sprintf( __( 'WPGraphQL for Advanced Custom Fields v%s cannot load', 'wpgraphql-acf' ), WPGRAPHQL_FOR_ACF_VERSION ) );
						?>
					</h3>
					<ol>
						<?php foreach ( $can_load_messages as $message ) : ?>
							<li><?php echo esc_html( $message ); ?></li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php
			}
		);
	}

	/**
	 * @param mixed $type The GraphQL Type to return based on the resolving node
	 * @param mixed $node The Node being resolved
	 *
	 * @return mixed
	 */
	public function resolve_acf_options_page_node( $type, $node ) {
		if ( $node instanceof \WPGraphQL\Acf\Model\AcfOptionsPage ) {
			return \WPGraphQL\Acf\Utils::get_field_group_name( $node->get_data() );
		}
		return $type;
	}

	/**
	 * @param array<mixed>          $loaders
	 * @param \WPGraphQL\AppContext $context
	 *
	 * @return array<mixed>
	 */
	public function register_loaders( array $loaders, \WPGraphQL\AppContext $context ): array {
		$loaders['acf_options_page'] = new \WPGraphQL\Acf\Data\Loader\AcfOptionsPageLoader( $context );
		return $loaders;
	}


	/**
	 * Output graphql debug messages if the plugin cannot load properly.
	 * Called on graphql_init (after init), so translations are safe.
	 */
	public function show_graphql_debug_messages(): void {
		if ( $this->can_load_plugin() ) {
			return;
		}
		$messages = $this->get_plugin_load_error_messages();

		if ( empty( $messages ) ) {
			return;
		}

		$prefix = sprintf( 'WPGraphQL for Advanced Custom Fields v%s cannot load', WPGRAPHQL_FOR_ACF_VERSION );
		foreach ( $messages as $message ) {
			graphql_debug( $prefix . ' because ' . $message );
		}
	}

		/**
		 * Add the $source node as the "node" passed to the resolver so ACF Fields assigned to Templates can resolve
		 * using the $source node as the object to get meta from.
		 *
		 * @param mixed                                    $result         The result of the field resolution
		 * @param mixed                                    $source         The source passed down the Resolve Tree
		 * @param array<mixed>                             $args           The args for the field
		 * @param \WPGraphQL\AppContext                    $context The AppContext passed down the ResolveTree
		 * @param \GraphQL\Type\Definition\ResolveInfo     $info The ResolveInfo passed down the ResolveTree
		 * @param string                                   $type_name      The name of the type the fields belong to
		 * @param string                                   $field_key      The name of the field
		 * @param \GraphQL\Type\Definition\FieldDefinition $field The Field Definition for the resolving field
		 * @param mixed                                    $field_resolver The default field resolver
		 *
		 * @return mixed
		 */
	public function page_template_resolver( $result, $source, $args, \WPGraphQL\AppContext $context, ResolveInfo $info, string $type_name, string $field_key, \GraphQL\Type\Definition\FieldDefinition $field, $field_resolver ) {
		if ( strtolower( 'ContentTemplate' ) !== strtolower( $info->returnType ) ) {
			return $result;
		}

		if ( is_array( $result ) && ! isset( $result['node'] ) && ! empty( $source ) ) {
			$result['node'] = $source;
		}

		return $result;
	}
}

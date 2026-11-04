<?php
namespace WPGraphQL\Acf;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Decides whether an ACF field group, field or options page resolves for the current request.
 *
 * Each level has one gate. A gate runs a list of predicates in order and stops at the first one
 * that denies, then passes the decision through the level's filter. A denied field group or field
 * resolves null, and a denied options page loads as null. The schema is the same for every viewer.
 *
 * Predicates are ordered cheapest and most general first. Today there is one, the capability
 * check. A check of the node's own visibility belongs before it.
 *
 * @since x-release-please-version
 */
class AccessControl {

	/**
	 * Get the capability a field group, field or options page requires to resolve.
	 *
	 * This is the one place that decides it: the `graphql_capability` setting, or none, which means
	 * public. If ACF adds a read capability of its own, this is where it belongs, after
	 * `graphql_capability`. ACF's existing `capability` setting on options pages controls who can
	 * edit the page in the admin, not who can read its data, so it isn't used here.
	 *
	 * @param array<mixed> $config The ACF field group, field or options page config.
	 *
	 * @return string|false|null The capability. Null if none is required. False if the setting is
	 *                           not a valid capability, which no one is considered to have.
	 */
	public static function get_required_capability( array $config ) {
		if ( ! array_key_exists( 'graphql_capability', $config ) ) {
			return null;
		}

		$capability = $config['graphql_capability'];

		// Unset, null, false and an empty string all mean public.
		if ( null === $capability || false === $capability ) {
			return null;
		}

		if ( ! is_string( $capability ) ) {
			// Anything else is a misconfiguration. Fail closed rather than open.
			return false;
		}

		$capability = trim( $capability );

		return '' === $capability ? null : $capability;
	}

	/**
	 * Whether a field group resolves for the current request.
	 *
	 * @param array<mixed>                         $acf_field_group The ACF Field Group config.
	 * @param mixed                                $node            The node the field group is resolving for.
	 * @param \WPGraphQL\AppContext                $context         The AppContext of the request.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info            The ResolveInfo of the field group field.
	 */
	public static function can_resolve_field_group( array $acf_field_group, $node, AppContext $context, ResolveInfo $info ): bool {
		$denial = self::get_first_denial(
			[
				'capability' => static function () use ( $acf_field_group ) {
					return self::check_capabilities( [ self::get_required_capability( $acf_field_group ) ] );
				},
			]
		);

		/**
		 * Filters whether an ACF Field Group resolves for the current request.
		 *
		 * Runs every time a field group resolves, after its `graphql_capability` (if any) has been
		 * checked. Return false to resolve the field group as null, which also leaves every field
		 * in it unresolved. Return true to resolve it even when its capability check failed.
		 *
		 * @param bool                                 $should_resolve  Whether the field group resolves. False when the current user lacks the field group's capability, or the setting isn't a valid capability.
		 * @param mixed                                $node            The node the field group is resolving for: usually a model, such as a \WPGraphQL\Model\Post, or the block being resolved for ACF blocks.
		 * @param array<mixed>                         $acf_field_group The ACF Field Group config.
		 * @param \WPGraphQL\AppContext                $context         The AppContext of the request.
		 * @param \GraphQL\Type\Definition\ResolveInfo $info            The ResolveInfo of the field group field.
		 *
		 * @since x-release-please-version
		 * @hookGroup authentication
		 */
		$should_resolve = (bool) apply_filters( 'wpgraphql/acf/field_group_should_resolve', null === $denial, $node, $acf_field_group, $context, $info );

		if ( ! $should_resolve ) {
			self::debug_denial( self::get_field_coordinate( $info ), $denial );
		}

		return $should_resolve;
	}

	/**
	 * Whether a field resolves for the current request.
	 *
	 * @param array<mixed>                         $acf_field             The ACF Field config.
	 * @param array<int,string|false|null>         $required_capabilities The capabilities the field requires besides its own: the capability of the field group it was defined in, when it resolves outside that field group (because it was cloned into another one).
	 * @param mixed                                $root                  The root the field is resolving for.
	 * @param \WPGraphQL\AppContext                $context               The AppContext of the request.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info                  The ResolveInfo of the field.
	 */
	public static function can_resolve_field( array $acf_field, array $required_capabilities, $root, AppContext $context, ResolveInfo $info ): bool {
		array_unshift( $required_capabilities, self::get_required_capability( $acf_field ) );

		$denial = self::get_first_denial(
			[
				'capability' => static function () use ( $required_capabilities ) {
					return self::check_capabilities( $required_capabilities );
				},
			]
		);

		/**
		 * Filters whether an ACF Field resolves for the current request.
		 *
		 * Runs every time a field resolves, after its `graphql_capability` (if any) has been
		 * checked, along with the capability of the field group the field was cloned from. Return
		 * false to resolve the field as null. Return true to resolve it even when a capability
		 * check failed. The field group the field belongs to is checked first, by the
		 * `wpgraphql/acf/field_group_should_resolve` filter, so returning true here can't make a
		 * field resolve in a field group that didn't.
		 *
		 * @param bool                                 $should_resolve Whether the field resolves. False when the current user lacks a capability the field requires, or a setting isn't a valid capability.
		 * @param mixed                                $root           The root the field is resolving for: an array with the node (under 'node') and field group (under 'acf_field_group') for fields at the top of a field group, or the value of the parent field (such as a row of a repeater or flexible content field) for fields nested in another field.
		 * @param array<mixed>                         $acf_field      The ACF Field config.
		 * @param \WPGraphQL\AppContext                $context        The AppContext of the request.
		 * @param \GraphQL\Type\Definition\ResolveInfo $info           The ResolveInfo of the field.
		 *
		 * @since x-release-please-version
		 * @hookGroup authentication
		 */
		$should_resolve = (bool) apply_filters( 'wpgraphql/acf/field_should_resolve', null === $denial, $root, $acf_field, $context, $info );

		if ( ! $should_resolve ) {
			self::debug_denial( self::get_field_coordinate( $info ), $denial );
		}

		return $should_resolve;
	}

	/**
	 * Whether an options page resolves for the current request.
	 *
	 * @param array<mixed> $options_page The ACF Options Page config.
	 */
	public static function can_resolve_options_page( array $options_page ): bool {
		$denial = self::get_first_denial(
			[
				'capability' => static function () use ( $options_page ) {
					return self::check_capabilities( [ self::get_required_capability( $options_page ) ] );
				},
			]
		);

		/**
		 * Filters whether an ACF Options Page resolves for the current request.
		 *
		 * Runs when an options page is loaded, after its `graphql_capability` (if any) has been
		 * checked. Return false to resolve the options page as null, wherever it's queried from
		 * (its root field or the `node` field), which also leaves the field groups on it
		 * unresolved. Return true to resolve it even when its capability check failed.
		 *
		 * @param bool         $should_resolve Whether the options page resolves. False when the current user lacks the options page's capability, or the setting isn't a valid capability.
		 * @param array<mixed> $options_page   The ACF Options Page config.
		 *
		 * @since x-release-please-version
		 * @hookGroup authentication
		 */
		$should_resolve = (bool) apply_filters( 'wpgraphql/acf/options_page_should_resolve', null === $denial, $options_page );

		if ( ! $should_resolve ) {
			self::debug_denial( self::get_options_page_type_name( $options_page ), $denial, true );
		}

		return $should_resolve;
	}

	/**
	 * Run the predicates in order and return the denial of the first one that denies.
	 *
	 * Predicates are ordered cheapest and most general first, so later (more specific or more
	 * expensive) ones don't run once an earlier one has denied.
	 *
	 * @param array<string,callable():(array{required_capability:string}|null)> $predicates The predicates, keyed by name. Each returns a denial, or null to allow.
	 *
	 * @return array{required_capability:string}|null The denial, or null if nothing denied.
	 */
	protected static function get_first_denial( array $predicates ): ?array {
		foreach ( $predicates as $predicate ) {
			$denial = $predicate();

			if ( null !== $denial ) {
				return $denial;
			}
		}

		return null;
	}

	/**
	 * The capability predicate: denies when the current user lacks any of the capabilities.
	 *
	 * @param array<int,string|false|null> $required_capabilities The capabilities to check, in order. Null entries are skipped. False (an invalid setting) always denies.
	 *
	 * @return array{required_capability:string}|null The denial, or null if the current user has every capability.
	 */
	protected static function check_capabilities( array $required_capabilities ): ?array {
		foreach ( $required_capabilities as $capability ) {
			if ( null === $capability ) {
				continue;
			}

			if ( false === $capability ) {
				return [ 'required_capability' => '' ];
			}

			if ( ! current_user_can( $capability ) ) {
				return [ 'required_capability' => $capability ];
			}
		}

		return null;
	}

	/**
	 * The GraphQL type name of an options page, for debug messages. Formatted the same way the
	 * options page type is named in the schema.
	 *
	 * @param array<mixed> $options_page The ACF Options Page config.
	 */
	protected static function get_options_page_type_name( array $options_page ): string {
		$name     = Utils::get_field_group_name( $options_page );
		$replaced = '' !== $name ? preg_replace( '/[\W_]+/u', ' ', $name ) : '';

		if ( empty( $replaced ) ) {
			return (string) ( $options_page['menu_slug'] ?? '' );
		}

		return \WPGraphQL\Utils\Utils::format_type_name( $replaced );
	}

	/**
	 * The "Type.field" coordinate of the field being resolved, for debug messages.
	 *
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo of the field.
	 */
	protected static function get_field_coordinate( ResolveInfo $info ): string {
		return $info->parentType->name . '.' . $info->fieldName;
	}

	/**
	 * Add a RESTRICTED_FIELD debug message for a field or options page that resolved null because
	 * it was denied.
	 *
	 * @param string                                 $subject         The "Type.field" coordinate of the field, or the type name of the options page.
	 * @param array{required_capability:string}|null $denial          The denial of the predicate that denied. Null if the decision came from a filter.
	 * @param bool                                   $is_options_page Whether the subject is an options page.
	 */
	protected static function debug_denial( string $subject, ?array $denial, bool $is_options_page = false ): void {
		$config = [
			'type'  => 'RESTRICTED_FIELD',
			'field' => $subject,
		];

		if ( null !== $denial && '' !== $denial['required_capability'] ) {
			$config['required_capability'] = $denial['required_capability'];

			$message = $is_options_page
				// translators: 1: the GraphQL type name of the options page, 2: the required capability.
				? __( 'The "%1$s" options page requires the "%2$s" capability and resolved to null.', 'wpgraphql-acf' )
				// translators: 1: the GraphQL type and field name, 2: the required capability.
				: __( 'The "%1$s" field requires the "%2$s" capability and resolved to null.', 'wpgraphql-acf' );

			graphql_debug( sprintf( $message, $subject, $denial['required_capability'] ), $config );
			return;
		}

		if ( null !== $denial ) {
			$message = $is_options_page
				// translators: %s: the GraphQL type name of the options page.
				? __( 'The "%s" options page has a graphql_capability setting that is not a valid capability, so it resolved to null for every user.', 'wpgraphql-acf' )
				// translators: %s: the GraphQL type and field name.
				: __( 'The "%s" field has a graphql_capability setting that is not a valid capability, so it resolved to null for every user.', 'wpgraphql-acf' );

			graphql_debug( sprintf( $message, $subject ), $config );
			return;
		}

		$message = $is_options_page
			// translators: %s: the GraphQL type name of the options page.
			? __( 'The "%s" options page is restricted and resolved to null.', 'wpgraphql-acf' )
			// translators: %s: the GraphQL type and field name.
			: __( 'The "%s" field is restricted and resolved to null.', 'wpgraphql-acf' );

		graphql_debug( sprintf( $message, $subject ), $config );
	}
}

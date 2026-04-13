<?php

namespace WPGraphQL\Acf\LocationRules;

use WPGraphQL\Utils\Utils;

/**
 * Class LocationRules
 *
 * Determine the WPGraphQL Schema Location rules based on the ACF Location rules.
 *
 * ACF Field Groups are now explicitly set to show on specific GraphQL Types in the Schema.
 *
 * Before, GraphQL Schema locations were inferred from the ACF Admin Location rules, but this
 * was often quite buggy as many ACF Location rules assume admin/editorial context that is not
 * present when the GraphQL Schema is being built.
 *
 * This Class is a polyfill for ACF Field Groups that were registered without a `graphql_types`
 * field, and need to fall back to the old Location Rules.
 *
 * Any Field Group that has `graphql_types` set, will use the explicit `graphql_types`
 * configuration.
 *
 * @package WPGraphQL\ACF
 */
class LocationRules {

	/**
	 * The field groups that have location rules mapped.
	 *
	 * @var array<mixed>
	 */
	public $mapped_field_groups = [];

	/**
	 * @var array<mixed>
	 */
	public $unset_types = [];

	/**
	 * The field groups to map to location rules
	 *
	 * @var array<mixed>
	 */
	public $acf_field_groups = [];

	/**
	 * LocationRules constructor.
	 *
	 * @param array<mixed> $acf_field_groups
	 */
	public function __construct( array $acf_field_groups = [] ) {
		$this->acf_field_groups = ! empty( $acf_field_groups ) ? $acf_field_groups : acf_get_field_groups();
	}

	/**
	 * Given the name of a GraphqL Field Group and the name of a GraphQL Type, this sets the
	 * field group to show in that Type
	 *
	 * @param string $field_group_name  The name of the ACF Field Group
	 * @param string $graphql_type_name The name of the GraphQL Type
	 */
	public function set_graphql_type( string $field_group_name, string $graphql_type_name ): void {
		$this->mapped_field_groups[ strtolower( Utils::format_field_name( $field_group_name, true ) ) ][] = ucfirst( Utils::format_field_name( $graphql_type_name, true ) );
	}

	/**
	 * Given the name of a GraphQL Field Group and the name of a GraphQL Type, this unsets the
	 * GraphQL Type for the field group
	 *
	 * @param string $field_group_name  The name of the ACF Field Group
	 * @param string $graphql_type_name The name of the GraphQL Type
	 */
	public function unset_graphql_type( string $field_group_name, string $graphql_type_name ): void {
		$this->unset_types[ Utils::format_field_name( $field_group_name, true ) ][] = ucfirst( Utils::format_field_name( $graphql_type_name, true ) );
	}

	/**
	 * Get the rules
	 *
	 * @return array<mixed>
	 */
	public function get_rules() {
		if ( empty( $this->mapped_field_groups ) ) {
			return [];
		}


		if ( empty( $this->unset_types ) ) {
			return $this->mapped_field_groups;
		}

		/**
		 * Remove any Types that were flagged to unset
		 */
		foreach ( $this->unset_types as $field_group => $types ) {

			// If there are no mapped field groups for the rule being unset, return the mapped groups as is
			if ( ! isset( $this->mapped_field_groups[ $field_group ] ) ) {
				return $this->mapped_field_groups;
			}

			// If the types to unset are empty or not an array, return the mapped field groups as is
			if ( empty( $types ) || ! is_array( $types ) ) {
				return $this->mapped_field_groups;
			}

			// Loop over the types to unset, find the key of the type in the array, then unset it
			foreach ( $types as $type ) {
				$key = array_search( $type, $this->mapped_field_groups[ $field_group ], true );
				if ( false !== $key ) {
					unset( $this->mapped_field_groups[ $field_group ][ $key ] );
				}
			}
		}

		// Return the mapped field groups, with the unset fields (if any) removed
		return $this->mapped_field_groups;
	}

	/**
	 * Checks for conflicting rule types to avoid impossible states.
	 *
	 * If a field group is assigned to a rule such as "post_type == post" AND "taxonomy == Tag"
	 * this would be an impossible state, as an object can't be a Post and a Tag.
	 *
	 * If we detect conflicting rules, the rule set is not applied at all.
	 *
	 * @param array<mixed> $and_params     The parameters of the rule group
	 * @param mixed        $param          The current param being evaluated
	 * @param array<mixed> $allowed_params The allowed params that shouldn't conflict
	 */
	public function check_for_conflicts( array $and_params, $param, array $allowed_params = [] ): bool {
		if ( empty( $and_params ) ) {
			return false;
		}

		$has_conflict = false;
		$keys         = array_keys( $and_params, $param, true );

		if ( isset( $keys[0] ) ) {
			unset( $and_params[ $keys[0] ] );
		}

		if ( ! empty( $and_params ) ) {
			foreach ( $and_params as $and_param ) {
				if ( false === array_search( $and_param, $allowed_params, true ) ) {
					$has_conflict = true;
				}
			}
		}

		return $has_conflict;
	}

	/**
	 * Checks for conflicting rule types to avoid impossible states.
	 *
	 * If a field group is assigned to a rule such as "post_type == post" AND "taxonomy == Tag"
	 * this would be an impossible state, as an object can't be a Post and a Tag.
	 *
	 * If we detect conflicting rules, the rule set is not applied at all.
	 *
	 * @param array<mixed> $and_params The parameters of the rule group
	 * @param string       $param      The current param being evaluated
	 */
	public function check_params_for_conflicts( array $and_params = [], string $param = '' ): bool {
		switch ( $param ) {
			case 'post_type':
				$allowed_and_params = [
					'post_status',
					'post_format',
					'post_category',
					'post_taxonomy',
					'post',
				];
				break;
			case 'post_template':
			case 'page_template':
				$allowed_and_params = [
					'page_type',
					'page_parent',
					'page',
				];
				break;
			case 'post_status':
				$allowed_and_params = [
					'post_type',
					'post_format',
					'post_category',
					'post_taxonomy',
				];
				break;
			case 'post_format':
			case 'post_category':
			case 'post':
			case 'post_taxonomy':
				$allowed_and_params = [
					'post_status',
					'post_type',
					'post_format',
					'post_category',
					'post_taxonomy',
					'post',
				];
				break;
			case 'page':
			case 'page_parent':
			case 'page_type':
				$allowed_and_params = [
					'page_template',
					'page_type',
					'page_parent',
					'page',
				];
				break;
			case 'current_user':
			case 'current_user_role':
				// @todo:
				// Right now, if you set current_user or current_user_role as the only rule,
				// ACF adds the field group to every possible location in the Admin.
				// This seems a bit heavy handed. 🤔
				// We need to think through this a bit more, and how this rule
				// Can be composed with other rules, etc.
				$allowed_and_params = [];
				break;
			case 'user_form':
			case 'user_role':
				$allowed_and_params = [
					'user_form',
					'user_role',
				];
				break;
			case 'taxonomy':
			case 'attachment':
			case 'comment':
			case 'widget':
			case 'nav_menu':
			case 'nav_menu_item':
			case 'options_page':
			default:
				$allowed_and_params = [];
				break;
		}

		return $this->check_for_conflicts( $and_params, $param, $allowed_and_params );
	}

	/**
	 * Determine how an ACF Location Rule should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_rules( string $field_group_name, string $param, string $operator, string $value ): void {

		// Depending on the param of the rule, there's different logic to
		// map to the Schema
		switch ( $param ) {
			case 'post_type':
				$this->determine_post_type_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'post_template':
			case 'page_template':
				$this->determine_post_template_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'post':
				$this->determine_post_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'page_type':
				$this->determine_page_type_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'page_parent':
			case 'page':
				// If page or page_parent is set, regardless of operator and value,
				// we can add the field group to the Page type
				$this->set_graphql_type( $field_group_name, 'Page' );
				break;
			case 'user_form':
			case 'user_role':
				// If user_role or user_form params are set, we need to expose the field group
				// to the User type
				$this->set_graphql_type( $field_group_name, 'User' );
				break;
			case 'taxonomy':
				$this->determine_taxonomy_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'attachment':
				$this->determine_attachment_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'comment':
				$this->determine_comment_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'block':
				$this->determine_block_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'nav_menu':
				$this->determine_nav_menu_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'nav_menu_item':
				$this->determine_nav_menu_item_item_rules( $field_group_name, $param, $operator, $value );
				break;
			case 'options_page':
				$this->determine_options_rules( $field_group_name, $param, $operator, $value );
				break;
			default:
				// If a built-in location rule could not be matched,
				// Custom rules (from extensions, etc) can hook in here and apply their
				// rules to the WPGraphQL Schema
				do_action( 'wpgraphql/acf/match_location_rule', $field_group_name, $param, $operator, $value, $this );
				break;
		}
	}

	/**
	 * Determine GraphQL Schema location rules based on ACF Location rules for field groups
	 * that are configured with no `graphql_types` field.
	 */
	public function determine_location_rules(): void {
		if ( ! empty( $this->acf_field_groups ) ) {
			foreach ( $this->acf_field_groups as $field_group ) {
				$field_group_name = $field_group['graphql_field_name'] ?? $field_group['title'];

				// If the field group is not active,
				// then the location rules won't be applied as the field group won't show in the admin
				if ( isset( $field_group['active'] ) && false === $field_group['active'] ) {
					continue;
				}

				if ( ! empty( $field_group['location'] ) && is_array( $field_group['location'] ) ) {
					foreach ( $field_group['location'] as $location_rule_group ) {
						if ( ! empty( $location_rule_group ) ) {
							foreach ( $location_rule_group as $rule ) {

								// Determine the and params for the rule group
								$and_params = wp_list_pluck( $location_rule_group, 'param' );
								$and_params = ! empty( $and_params ) ? array_values( $and_params ) : [];

								$operator = $rule['operator'] ?? '==';
								$param    = $rule['param'] ?? null;
								$value    = $rule['value'] ?? null;

								if ( empty( $param ) || empty( $value ) ) {
									continue;
								}

								if ( true === $this->check_params_for_conflicts( $and_params, $param ) ) {
									continue;
								}

								$this->determine_rules( $field_group_name, $param, $operator, $value );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Returns an array of Post Templates
	 *
	 * @return array<mixed>
	 */
	public function get_graphql_post_template_types(): array {
		$page_templates = [
			'default' => 'DefaultTemplate',
		];

		$allowed_post_types = \WPGraphQL::get_allowed_post_types();
		foreach ( $allowed_post_types as $post_type ) {
			$post_type_templates = wp_get_theme()->get_page_templates( null, $post_type );
			foreach ( $post_type_templates as $file => $name ) {
				$page_templates[ $file ] = Utils::format_type_name_for_wp_template( $name, $file );
			}
		}

		return $page_templates;
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_post_type_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		$allowed_post_types = get_post_types( [ 'show_in_graphql' => true ] );

		if ( empty( $allowed_post_types ) ) {
			return;
		}

		if ( '==' === $operator ) {

			// If all post types
			if ( 'all' === $value ) {

				// loop over and set all post types
				foreach ( $allowed_post_types as $allowed_post_type ) {
					$post_type_object = get_post_type_object( $allowed_post_type );
					$graphql_name     = $post_type_object->graphql_single_name ?? null;
					if ( ! empty( $graphql_name ) ) {
						$this->set_graphql_type( $field_group_name, $graphql_name );
					}
				}
			} elseif ( in_array( $value, $allowed_post_types, true ) ) {
				$post_type_object = get_post_type_object( $value );
				$graphql_name     = $post_type_object->graphql_single_name ?? null;
				if ( ! empty( $graphql_name ) ) {
					$this->set_graphql_type( $field_group_name, $graphql_name );
				}
			}
		}

		if ( '!=' === $operator ) {
			if ( 'all' !== $value ) {
				// loop over and set all post types
				foreach ( $allowed_post_types as $allowed_post_type ) {
					$post_type_object = get_post_type_object( $allowed_post_type );
					$graphql_name     = $post_type_object->graphql_single_name ?? null;
					if ( ! empty( $graphql_name ) ) {
						$this->set_graphql_type( $field_group_name, $graphql_name );
					}
				}
			}

			$post_type_object = get_post_type_object( $value );
			$graphql_name     = $post_type_object->graphql_single_name ?? null;
			if ( ! empty( $graphql_name ) ) {
				$this->unset_graphql_type( $field_group_name, $graphql_name );
			}
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_post_template_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		$templates = $this->get_graphql_post_template_types();

		if ( empty( $templates ) ) {
			return;
		}

		// If the template is available in GraphQL, set it
		if ( ( '==' === $operator ) && isset( $templates[ $value ] ) ) {
			$this->set_graphql_type( $field_group_name, $templates[ $value ] );
		}

		if ( '!=' === $operator ) {
			foreach ( $templates as $template_type ) {
				$this->set_graphql_type( $field_group_name, $template_type );
			}

			// If the Template is available in GraphQL, unset it
			if ( isset( $templates[ $value ] ) ) {
				$this->unset_graphql_type( $field_group_name, $templates[ $value ] );
			}
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_post_format_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		$post_format_taxonomy   = get_taxonomy( 'post_format' );
		$post_format_post_types = $post_format_taxonomy->object_type ?? [];

		if ( ! is_array( $post_format_post_types ) || empty( $post_format_post_types ) ) {
			return;
		}

		// If Post Format is used to qualify a field group location,
		// It will be added to the Schema for any Post Type that supports post formats
		// And shows in GraphQL
		$allowed_post_types = get_post_types( [ 'show_in_graphql' => true ] );
		foreach ( $allowed_post_types as $post_type ) {
			if ( in_array( $post_type, $post_format_post_types, true ) ) {
				$post_type_object = get_post_type_object( $value );
				$graphql_name     = $post_type_object->graphql_single_name ?? null;
				if ( ! empty( $graphql_name ) ) {
					$this->set_graphql_type( $field_group_name, $graphql_name );
				}
			}
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_post_rules( string $field_group_name, string $param, string $operator, string $value ): void {

		// If a Single post is used to qualify a field group location,
		// It will be added to the Schema for the GraphQL Type for the post_type of the Post
		// it is assigned to

		if ( ( '==' === $operator ) && absint( $value ) ) {
			$post = get_post( absint( $value ) );
			if ( $post instanceof \WP_Post ) {
				$post_type_object = get_post_type_object( $post->post_type );
				if ( $post_type_object && true === $post_type_object->show_in_graphql && isset( $post_type_object->graphql_single_name ) ) {
					$this->set_graphql_type( $field_group_name, $post_type_object->graphql_single_name );
				}
			}
		}

		// If a single post is used as not equal,
		// the field group should not be added to any type
		if ( '!=' === $operator ) {
			return;
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_page_type_rules( string $field_group_name, string $param, string $operator, string $value ): void {

		// If front_page or posts_page is set to equal_to or not_equal_to
		// then the field group should be shown on the Post type
		if ( in_array( $value, [ 'front_page', 'posts_page' ], true ) ) {
			$this->set_graphql_type( $field_group_name, 'Page' );
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_taxonomy_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		$allowed_taxonomies = get_taxonomies( [ 'show_in_graphql' => true ] );

		if ( empty( $allowed_taxonomies ) ) {
			return;
		}

		if ( '==' === $operator ) {

			// If all post types
			if ( 'all' === $value ) {

				// loop over and set all post types
				foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
					$tax_object   = get_taxonomy( $allowed_taxonomy );
					$graphql_name = $tax_object->graphql_single_name ?? null;
					if ( ! empty( $graphql_name ) ) {
						$this->set_graphql_type( $field_group_name, $graphql_name );
					}
				}
			} elseif ( in_array( $value, $allowed_taxonomies, true ) ) {
					$tax_object   = get_taxonomy( $value );
					$graphql_name = $tax_object->graphql_single_name ?? null;
				if ( ! empty( $graphql_name ) ) {
					$this->set_graphql_type( $field_group_name, $graphql_name );
				}
			}
		}

		if ( '!=' === $operator ) {
			if ( 'all' !== $value ) {

				// loop over and set all post types
				foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
					$tax_object   = get_taxonomy( $allowed_taxonomy );
					$graphql_name = $tax_object->graphql_single_name ?? null;
					if ( ! empty( $graphql_name ) ) {
						$this->set_graphql_type( $field_group_name, $graphql_name );
					}
				}

				$tax_object   = get_taxonomy( $value );
				$graphql_name = $tax_object->graphql_single_name ?? null;
				if ( ! empty( $graphql_name ) ) {
					$this->unset_graphql_type( $field_group_name, $graphql_name );
				}
			}
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_attachment_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		if ( '==' === $operator ) {
			$this->set_graphql_type( $field_group_name, 'MediaItem' );
		}

		if ( '!=' === $operator && 'all' === $value ) {
			$this->unset_graphql_type( $field_group_name, 'MediaItem' );
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_comment_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		if ( '==' === $operator ) {
			$this->set_graphql_type( $field_group_name, 'Comment' );
		}

		if ( '!=' === $operator ) {

			// If not equal to all, unset from all comments
			if ( 'all' === $value ) {
				$this->unset_graphql_type( $field_group_name, 'Comment' );

				// If not equal to just a specific post type/comment relationship,
				// show the field group on the Comment Type
			} else {
				$this->set_graphql_type( $field_group_name, 'Comment' );
			}
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_nav_menu_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		if ( '==' === $operator ) {
			$this->set_graphql_type( $field_group_name, 'Menu' );
		}

		if ( '!=' === $operator ) {

			// If not equal to all, unset from all Menu
			if ( 'all' === $value ) {
				$this->unset_graphql_type( $field_group_name, 'Menu' );

				// If not equal to just a Menu,
				// show the field group on all Menus
			} else {
				$this->set_graphql_type( $field_group_name, 'Menu' );
			}
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_nav_menu_item_item_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		if ( '==' === $operator ) {
			$this->set_graphql_type( $field_group_name, 'MenuItem' );
		}

		if ( '!=' === $operator ) {

			// If not equal to all, unset from all MenuItem
			if ( 'all' === $value ) {
				$this->unset_graphql_type( $field_group_name, 'MenuItem' );

				// If not equal to one Menu / location,
				// show the field group on all MenuItems
			} else {
				$this->set_graphql_type( $field_group_name, 'MenuItem' );
			}
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_block_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		if ( ! function_exists( 'acf_get_block_types' ) ) {
			return;
		}

		if ( '==' === $operator ) {
			$acf_block = acf_get_block_type( $value );

			if ( empty( $acf_block ) || ! \WPGraphQL\Acf\Utils::should_field_group_show_in_graphql( $acf_block ) ) {
				return;
			}

			$type_name = \WPGraphQL\Acf\Utils::get_field_group_name( $acf_block );
			$this->set_graphql_type( $field_group_name, $type_name );
		}

		if ( '!=' === $operator ) {
			$acf_blocks = acf_get_block_types();

			if ( empty( $acf_blocks ) || ! is_array( $acf_blocks ) ) {
				return;
			}

			foreach ( $acf_blocks as $acf_block ) {
				if ( ! isset( $acf_block['show_in_graphql'] ) || false === (bool) $acf_block['show_in_graphql'] ) {
					continue;
				}
				$type_name = isset( $acf_block['graphql_field_name'] ) ? Utils::format_type_name( $acf_block['graphql_field_name'] ) : Utils::format_type_name( $acf_block['name'] );
				$this->set_graphql_type( $field_group_name, $type_name );
			}

			$acf_block = acf_get_block_type( $value );
			if ( ! isset( $acf_block['show_in_graphql'] ) || false === (bool) $acf_block['show_in_graphql'] ) {
				return;
			}
			$type_name = isset( $acf_block['graphql_field_name'] ) ? Utils::format_type_name( $acf_block['graphql_field_name'] ) : Utils::format_type_name( $acf_block['name'] );
			$this->unset_graphql_type( $field_group_name, $type_name );
		}
	}

	/**
	 * Determines how the ACF Rules should apply to the WPGraphQL Schema
	 *
	 * @param string $field_group_name The name of the ACF Field Group the rule applies to
	 * @param string $param            The parameter of the rule
	 * @param string $operator         The operator of the rule
	 * @param string $value            The value of the rule
	 */
	public function determine_options_rules( string $field_group_name, string $param, string $operator, string $value ): void {
		$options_pages = \WPGraphQL\Acf\Utils::get_acf_options_pages();

		if ( empty( $options_pages ) ) {
			return;
		}

		if ( '==' === $operator ) {
			$options_page = acf_get_options_page( $value );

			if ( empty( $options_page ) || ! \WPGraphQL\Acf\Utils::should_field_group_show_in_graphql( $options_page ) ) {
				return;
			}

			$type_name = \WPGraphQL\Acf\Utils::get_field_group_name( $options_page );
			$this->set_graphql_type( $field_group_name, $type_name );
		}

		if ( '!=' === $operator ) {

			// Show all options pages
			foreach ( $options_pages as $options_page ) {
				if ( ! isset( $options_page['show_in_graphql'] ) || false === (bool) $options_page['show_in_graphql'] ) {
					continue;
				}

				if ( ! empty( $options_page['graphql_single_name'] ) ) {
					$type_name = Utils::format_type_name( $options_page['graphql_single_name'] );
				} else {
					$type_name = isset( $options_page['graphql_type_name'] ) ? Utils::format_type_name( $options_page['graphql_type_name'] ) : Utils::format_type_name( $options_page['menu_slug'] );
				}

				$this->set_graphql_type( $field_group_name, $type_name );
			}

			// Get the options page to unset
			$options_page = acf_get_options_page( $value );
			if ( ! isset( $options_page['show_in_graphql'] ) || false === (bool) $options_page['show_in_graphql'] ) {
				return;
			}
			if ( ! empty( $options_page['graphql_single_name'] ) ) {
				$type_name = Utils::format_type_name( $options_page['graphql_single_name'] );
			} else {
				$type_name = isset( $options_page['graphql_type_name'] ) ? Utils::format_type_name( $options_page['graphql_type_name'] ) : Utils::format_type_name( $options_page['menu_slug'] );
			}
			$this->unset_graphql_type( $field_group_name, $type_name );
		}
	}
}

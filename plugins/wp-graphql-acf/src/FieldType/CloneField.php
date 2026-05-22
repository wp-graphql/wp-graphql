<?php
namespace WPGraphQL\Acf\FieldType;

use WPGraphQL\Acf\AcfGraphQLFieldType;
use WPGraphQL\Acf\FieldConfig;
use WPGraphQL\Acf\Utils as AcfUtils;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

class CloneField {

	/**
	 * Register support for the 'clone' acf field type
	 */
	public static function register_field_type(): void {
		register_graphql_acf_field_type(
			'clone',
			[
				'resolve'      => [ self::class, 'resolve' ],
				'graphql_type' => static function ( FieldConfig $field_config, AcfGraphQLFieldType $acf_field_type ) {

					$sub_field_group = $field_config->get_raw_acf_field();
					$parent_type     = $field_config->get_parent_graphql_type_name( $sub_field_group );
					$field_name      = $field_config->get_graphql_field_name();
					$type_name       = Utils::format_type_name( $parent_type . ' ' . $field_name );
					$prefix_name     = $sub_field_group['prefix_name'] ?? false;

					// If the "Clone" field has not set a "prefix_name",
					// return NULL to prevent registering a new type
					// The cloned
					if ( ! $prefix_name ) {
						return 'NULL';
					}

					$cloned_fields = array_filter(
						array_map(
							static function ( $cloned ) {
								return acf_get_field( $cloned );
							},
							$sub_field_group['clone']
						)
					);

					$cloned_group_interfaces = array_filter(
						array_map(
							static function ( $cloned ) use ( $field_config ) {
								$cloned_group = acf_get_field_group( $cloned );
								if ( empty( $cloned_group ) ) {
									return null;
								}
								return $field_config->get_registry()->get_field_group_graphql_type_name( $cloned_group ) . '_Fields';
							},
							$sub_field_group['clone']
						)
					);

					if ( ! empty( $cloned_group_interfaces ) ) {
						$type_name = self::register_prefixed_clone_field_type( $type_name, $sub_field_group, $cloned_fields, $field_config );
						register_graphql_interfaces_to_types( $cloned_group_interfaces, [ $type_name ] );
						return $type_name;
					}

					// If the "Clone" field has cloned individual fields
					if ( ! empty( $cloned_fields ) ) {
						return self::register_prefixed_clone_field_type( $type_name, $sub_field_group, $cloned_fields, $field_config );
					}

					// Bail by returning a NULL type
					return 'NULL';
				},
				// The clone field adds its own settings field to display
				'admin_fields' => static function ( $default_admin_settings, $field, $config, \WPGraphQL\Acf\Admin\Settings $settings ) {

					// Return one GraphQL Field, ignoring the default admin settings
					return [
						'graphql_clone_field' => [
							'type'         => 'message',
							'label'        => __( 'GraphQL Settings for Clone Fields', 'wpgraphql-acf' ),
							'instructions' => __( 'Clone Fields will inherit their GraphQL settings from the field(s) being cloned. If all Fields from a Field Group are cloned, an Interface representing the cloned field Group will be applied to this field group.', 'wpgraphql-acf' ),
							'conditions'   => [],
						],
					];
				},
			]
		);
	}

	/**
	 * @param string                     $type_name The name of the GraphQL Type representing the prefixed clone field
	 * @param array<mixed>               $sub_field_group  The Field Group representing the cloned field
	 * @param array<mixed>               $cloned_fields The cloned fields to be registered to the Cloned Field Type
	 * @param \WPGraphQL\Acf\FieldConfig $field_config The ACF Field Config
	 *
	 * @throws \Exception
	 */
	public static function register_prefixed_clone_field_type( string $type_name, array $sub_field_group, array $cloned_fields, FieldConfig $field_config ): string {
		$sub_field_group['graphql_type_name']  = $type_name;
		$sub_field_group['graphql_field_name'] = $type_name;
		$sub_field_group['parent']             = $sub_field_group['key'];
		$sub_field_group['sub_fields']         = $cloned_fields;

		$field_config->get_registry()->register_acf_field_groups_to_graphql(
			[
				$sub_field_group,
			]
		);
		return $type_name;
	}

	/**
	 * Resolver for clone fields that produced a prefixed wrapper type.
	 *
	 * ACF stores values for `display=seamless` + `prefix_name=1` clones under
	 * **prefixed individual meta keys** (e.g. for a clone field named `yo`
	 * cloning a source field `title`, the value lives at `yo_title`). There
	 * is no single `yo` value in storage, so `get_field('yo', $post_id)`
	 * returns `null` — which means the default field resolver can't supply
	 * anything for the schema's `yo: <PrefixedType>` field, and the inner
	 * `title` resolver never gets a parent value to read from.
	 *
	 * This resolver constructs a synthetic wrapper object so the inner
	 * sub-field resolvers (which look up by their own source field name)
	 * find their values:
	 *
	 *  - **Nested case** (clone is a sub-field of a repeater row, group, or
	 *    flex layout): the parent's data is already in `$root` with the
	 *    prefixed keys present (e.g. `$root['yo_title']`). We strip the
	 *    prefix and return `['title' => ...]`.
	 *
	 *  - **Top-level case** (clone is a direct field of a post/term/etc.):
	 *    the prefixed values live in post meta. We iterate the clone's
	 *    sub_fields and fetch each by `<clone_name>_<source_name>` from the
	 *    node's storage via ACF's standard `get_field()`.
	 *
	 * For `display=group` clones (regardless of `prefix_name`), ACF's
	 * `get_field()` already returns a keyed array — the default resolver
	 * handles those cases correctly, so this resolver defers to it.
	 *
	 * @see https://github.com/wp-graphql/wpgraphql-acf/issues/269
	 *
	 * @param mixed                                  $root          The parent's resolved value.
	 * @param array<string,mixed>                    $args          GraphQL args.
	 * @param \WPGraphQL\AppContext                  $context       Request context.
	 * @param \GraphQL\Type\Definition\ResolveInfo   $info          Resolve info.
	 * @param \WPGraphQL\Acf\AcfGraphQLFieldType     $field_type    The clone field-type instance.
	 * @param \WPGraphQL\Acf\FieldConfig             $field_config  The per-field config.
	 *
	 * @return mixed
	 */
	public static function resolve( $root, array $args, AppContext $context, \GraphQL\Type\Definition\ResolveInfo $info, AcfGraphQLFieldType $field_type, FieldConfig $field_config ) {
		$acf_field   = $field_config->get_acf_field();
		$clone_name  = $acf_field['name'] ?? '';
		$display     = $acf_field['display'] ?? 'seamless';
		$prefix_name = ! empty( $acf_field['prefix_name'] );

		// Group-display clones: ACF returns a keyed array from get_field(), so
		// the default resolver works. Defer to it.
		if ( 'group' === $display ) {
			return $field_config->resolve_field( $root, $args, $context, $info );
		}

		// Seamless without a prefix never registers a wrapper field in the schema
		// (CloneField's graphql_type returns 'NULL'), so this branch shouldn't
		// fire — but if it does, fall back to default resolution defensively.
		if ( ! $prefix_name || '' === $clone_name ) {
			return $field_config->resolve_field( $root, $args, $context, $info );
		}

		$prefix     = $clone_name . '_';
		$prefix_len = strlen( $prefix );

		// Nested case: parent already has the data inline, prefixed keys present.
		if ( is_array( $root ) ) {
			$synthetic = [];
			foreach ( $root as $key => $value ) {
				if ( is_string( $key ) && 0 === strpos( $key, $prefix ) ) {
					$synthetic[ substr( $key, $prefix_len ) ] = $value;
				}
			}
			if ( ! empty( $synthetic ) ) {
				return $synthetic;
			}
		}

		// Top-level case: prefixed values live in post meta. Fetch each cloned
		// source field by its prefixed name via ACF's standard get_field()
		// (so any return-type formatting registered on the source field still
		// runs).
		$node    = is_array( $root ) && isset( $root['node'] ) ? $root['node'] : $root;
		$node_id = $node ? AcfUtils::get_node_acf_id( $node ) : null;

		if ( empty( $node_id ) || empty( $acf_field['sub_fields'] ) || ! is_array( $acf_field['sub_fields'] ) ) {
			return null;
		}

		$synthetic = [];
		foreach ( $acf_field['sub_fields'] as $sub_field ) {
			$source_name = $sub_field['name'] ?? '';
			if ( '' === $source_name ) {
				continue;
			}
			$value = get_field( $prefix . $source_name, $node_id );
			if ( null !== $value && '' !== $value && false !== $value ) {
				$synthetic[ $source_name ] = $value;
			}
		}

		return ! empty( $synthetic ) ? $synthetic : null;
	}
}

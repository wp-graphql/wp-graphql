<?php

namespace WPGraphQL\Acf;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

class FieldConfig {

	/**
	 * @var array<mixed>
	 */
	protected $acf_field;

	/**
	 * @var array<mixed>
	 */
	protected $raw_field;

	/**
	 * @var array<mixed>
	 */
	protected $acf_field_group;

	/**
	 * @var string|null
	 */
	protected $graphql_field_group_type_name;

	/**
	 * @var string
	 */
	protected $graphql_field_name;

	/**
	 * @var \WPGraphQL\Acf\AcfGraphQLFieldType|null
	 */
	protected $graphql_field_type;

	/**
	 * @var \WPGraphQL\Acf\Registry
	 */
	protected $registry;

	/**
	 * @var string
	 */
	protected $graphql_parent_type_name;

	/**
	 * @param array<mixed>            $acf_field The ACF Field being mapped to the GraphQL Schema
	 * @param array<mixed>            $acf_field_group The ACF Field Group the field belongs to
	 * @param \WPGraphQL\Acf\Registry $registry The WPGraphQL for ACF Type Registry
	 *
	 * @throws \GraphQL\Error\Error
	 */
	public function __construct( array $acf_field, array $acf_field_group, Registry $registry ) {
		$this->raw_field                     = $acf_field;
		$this->acf_field                     = ! empty( $acf_field['key'] ) && acf_get_field( $acf_field['key'] ) ? acf_get_field( $acf_field['key'] ) : $acf_field;
		$this->acf_field_group               = $acf_field_group;
		$this->registry                      = $registry;
		$this->graphql_field_group_type_name = $this->registry->get_field_group_graphql_type_name( $this->acf_field_group );
		$this->graphql_field_name            = $this->registry->get_graphql_field_name( $this->acf_field );
		$this->graphql_field_type            = Utils::get_graphql_field_type( $this->raw_field['type'] );
	}

	/**
	 * Get the WPGraphQL for ACF TypeRegistry instance
	 */
	public function get_registry(): Registry {
		return $this->registry;
	}

	/**
	 * Get the GraphQL Type Name for the current ACF Field Group
	 */
	public function get_graphql_field_group_type_name(): ?string {
		return $this->graphql_field_group_type_name;
	}

	/**
	 * Get the AcfGraphQLFieldType definition for an ACF Field
	 */
	public function get_graphql_field_type(): ?AcfGraphQLFieldType {
		return $this->graphql_field_type;
	}

	/**
	 * @param array<mixed> $acf_field
	 * @param string|null  $prepend
	 *
	 * @throws \GraphQL\Error\Error
	 */
	public function get_parent_graphql_type_name( array $acf_field, ?string $prepend = '' ): string {
		$type_name = '';

		if ( ! empty( $acf_field['parent_layout_group'] ) ) {
			$type_name = $this->registry->get_field_group_graphql_type_name( $acf_field['parent_layout_group'] );
		} elseif ( ! empty( $acf_field['parent'] ) ) {
			$parent_field = acf_get_field( $acf_field['parent'] );
			$parent_group = acf_get_field_group( $acf_field['parent'] );
			if ( ! empty( $parent_field ) ) {
				$type_name = $this->registry->get_field_group_graphql_type_name( $parent_field );
				$type_name = $this->get_parent_graphql_type_name( $parent_field, $type_name );
			} elseif ( ! empty( $parent_group ) ) {
				$type_name = $this->registry->get_field_group_graphql_type_name( $parent_group );
				$type_name = $this->get_parent_graphql_type_name( $parent_group, $type_name );
			}
		}

		return $type_name . $prepend;
	}

	/**
	 * Get the GraphQL field name of the ACF Field
	 */
	public function get_graphql_field_name(): string {
		return $this->graphql_field_name;
	}

	/**
	 * Determine whether an ACF Field is supported by GraphQL
	 */
	protected function is_supported_field_type(): bool {
		$supported_types = Utils::get_supported_acf_fields_types();
		return ! empty( $this->acf_field['type'] ) && in_array( $this->acf_field['type'], $supported_types, true );
	}

	/**
	 * Get the description of the field for the GraphQL Schema
	 *
	 * @throws \GraphQL\Error\Error
	 */
	public function get_field_description(): string {

		$graphql_field_type = $this->get_graphql_field_type();
		$field_type_config  = ( $graphql_field_type instanceof AcfGraphQLFieldType ) ? $graphql_field_type->get_config() : [];

		// Use the explicit graphql_description, if set
		if ( ! empty( $this->acf_field['graphql_description'] ) ) {
			$description = $this->acf_field['graphql_description'];

			// else use the instructions, if set
		} elseif ( ! empty( $this->acf_field['instructions'] ) ) {
			$description = $this->acf_field['instructions'];
		} else {
			// Fallback description
			// translators: %s is the name of the ACF Field Group
			$description = sprintf(
				// translators: %1$s is the ACF Field Type and %2$s is the name of the ACF Field Group
				__( 'Field of the "%1$s" Field Type added to the schema as part of the "%2$s" Field Group', 'wpgraphql-acf' ),
				$this->acf_field['type'] ?? '',
				$this->registry->get_field_group_graphql_type_name( $this->acf_field_group )
			);
		}

		if ( isset( $field_type_config['graphql_description_after'] ) ) {
			if ( is_callable( $field_type_config['graphql_description_after'] ) ) {
				$description .= ' ' . call_user_func( $field_type_config['graphql_description_after'], $this );
			} else {
				$description .= ' ' . $field_type_config['graphql_description_after'];
			}
		}

		return $description;
	}

	/**
	 * @return array<mixed>
	 */
	public function get_acf_field(): array {
		return $this->acf_field;
	}

	/**
	 * @return array<mixed>
	 */
	public function get_raw_acf_field(): array {
		return $this->raw_field;
	}

	/**
	 * @return array<mixed>
	 */
	public function get_acf_field_group(): array {
		return $this->acf_field_group;
	}

	/**
	 * @return array<mixed>|null
	 * @throws \GraphQL\Error\Error
	 * @throws \Exception
	 */
	public function get_graphql_field_config(): ?array {

		// if the field is explicitly set to not show in graphql, leave it out of the schema
		// if the field is explicitly set to not show in graphql, leave it out of the schema
		if ( isset( $this->acf_field['show_in_graphql'] ) && false === (bool) $this->acf_field['show_in_graphql'] ) {
			return null;
		}

		// if the field is not a supported type, don't add it to the schema
		if ( ! $this->is_supported_field_type() ) {
			return null;
		}

		if ( empty( $this->graphql_field_group_type_name ) || empty( $this->graphql_field_name ) ) {
			return null;
		}

		$field_config = [
			'type'            => 'String',
			'name'            => $this->graphql_field_name,
			'description'     => $this->get_field_description(),
			'acf_field'       => $this->get_acf_field(),
			'acf_field_group' => $this->acf_field_group,
			'resolve'         => function ( $root, $args, AppContext $context, ResolveInfo $info ) {
				return $this->resolve_field( $root, $args, $context, $info );
			},
		];


		if ( ! empty( $this->acf_field['type'] ) ) {
			$graphql_field_type = $this->get_graphql_field_type();

			// If the field type overrode the resolver, use it
			if ( null !== $graphql_field_type && method_exists( $graphql_field_type, 'get_resolver' ) ) {
				$resolver                = function ( $root, $args, AppContext $context, ResolveInfo $info ) use ( $graphql_field_type ) {
					return $graphql_field_type->get_resolver( $root, $args, $context, $info, $graphql_field_type, $this );
				};
				$field_config['resolve'] = $resolver;
			}

			if ( $graphql_field_type instanceof AcfGraphQLFieldType ) {
				$field_type = $graphql_field_type->get_resolve_type( $this );
			}

			if ( empty( $field_type ) ) {
				$field_type = 'String';
			}

			// if the field type returns a connection,
			// bail and let the connection handle registration to the schema
			// and resolution
			if ( 'connection' === $field_type ) {
				$this->registry->register_field( $this->acf_field );
				return null;
			}

			// if the field type returns a NULL type,
			// bail and prevent the field from being directly mapped to the Schema
			if ( 'NULL' === $field_type ) {
				return null;
			}

			switch ( $this->acf_field['type'] ) {
				case 'color_picker':
				case 'number':
				case 'range':
				case 'group':
				case 'wysiwyg':
				case 'google_map':
				case 'link':
				case 'oembed':
				case 'radio':
				case 'date_picker':
				case 'date_time_picker':
				case 'time_picker':
				case 'flexible_content':
				case 'button_group':
				case 'repeater':
					$field_config['type'] = $field_type;
					break;
				case 'true_false':
					$field_config['type']    = $field_type;
					$field_config['resolve'] = function ( $node, array $args, AppContext $context, ResolveInfo $info ) {
						$value = $this->resolve_field( $node, $args, $context, $info );
						return (bool) $value;
					};
					break;
				case 'checkbox':
				case 'select':
					$field_config['type']    = $field_type;
					$field_config['resolve'] = function ( $node, array $args, AppContext $context, ResolveInfo $info ) {
						$value = $this->resolve_field( $node, $args, $context, $info );

						if ( empty( $value ) && ! is_array( $value ) ) {
							return null;
						}

						return is_array( $value ) ? $value : [ $value ];
					};
					break;

				default:
					$field_config['type'] = $field_type;
					break;
			}
		}

		/**
		 * Filter the graphql_field_config for an ACF Field
		 *
		 * @param array|null                 $field_config   The field config array passed to the schema registration
		 * @param \WPGraphQL\Acf\FieldConfig $instance       Instance of the FieldConfig class
		 */
		return \apply_filters( 'wpgraphql/acf/get_graphql_field_config', $field_config, $this );
	}

	/**
	 * Determine if the field should ask ACF to format the response when retrieving
	 * the field using get_field()
	 *
	 * @param string $field_type The ACF Field Type of field being resolved
	 */
	public function should_format_field_value( string $field_type ): bool {

		// @todo: filter this? And it should be done at the registry level once, not the per-field level
		$types_to_format = [
			'select',
			'wysiwyg',
			'repeater',
			'flexible_content',
			'oembed',
			'clone',
		];

		return in_array( $field_type, $types_to_format, true );
	}

	/**
	 * @param string          $selector
	 * @param string|null     $parent_field_name
	 * @param string|int|null $post_id
	 * @param bool            $should_format
	 *
	 * @return false|mixed
	 */
	protected function get_field( string $selector, ?string $parent_field_name = null, $post_id = null, bool $should_format = false ) {
		if ( ! empty( $parent_field_name ) ) {
			$value = get_sub_field( $selector, $should_format );
		} else {
			$value = get_field( $selector, $post_id, $should_format );
		}

		return $value;
	}

	/**
	 * @param mixed                                $root
	 * @param array<mixed>                         $args
	 * @param \WPGraphQL\AppContext                $context
	 * @param \GraphQL\Type\Definition\ResolveInfo $info
	 * @param array<mixed>                         $acf_field The ACF Field to resolve for
	 *
	 * @return mixed
	 */
	public function resolve_field( $root, array $args, AppContext $context, ResolveInfo $info, $acf_field = [] ) {
		$field_config = $info->fieldDefinition->config['acf_field'] ?? $this->acf_field;
		// merge the field config and the acf_field passed in
		$field_config = array_merge( $field_config, $acf_field );
		$node         = $root['node'] ?? null;
		$node_id      = $node ? Utils::get_node_acf_id( $node ) : null;
		$return_value = null;
		$field_key    = null;
		$is_cloned    = false;

		if ( ! empty( $field_config['__key'] ) ) {
			$field_key = $field_config['__key'];
			$is_cloned = true;
		} elseif ( ! empty( $field_config['key'] ) ) {
			$field_key = $field_config['key'];
		}

		if ( empty( $field_key ) ) {
			return null;
		}

		if ( $is_cloned ) {
			if ( isset( $field_config['_name'] ) && ! empty( $node_id ) ) {
				$field_key = $field_config['_name'];
			} elseif ( ! empty( $field_config['__key'] ) ) {
				$field_key = $field_config['__key'];
			}
		}


		$should_format_value = false;

		if ( ! empty( $field_config['type'] ) && $this->should_format_field_value( $field_config['type'] ) ) {
			$should_format_value = true;
		}

		if ( empty( $field_key ) ) {
			return null;
		}

		// if the field_config is empty or not an array, set it as an empty array as a fallback
		$field_config = ! empty( $field_config ) ? $field_config : [];

		// If the root being passed down already has a value
		// for the field key, let's use it to resolve
		if ( isset( $field_config['key'] ) && ! empty( $root[ $field_config['key'] ] ) ) {
			return $this->prepare_acf_field_value( $root[ $field_config['key'] ], $node, $node_id, $field_config );
		}

		// Check if the cloned field key is being used to pass values down
		if ( isset( $field_config['__key'] ) && ! empty( $root[ $field_config['__key'] ] ) ) {
			return $this->prepare_acf_field_value( $root[ $field_config['__key'] ], $node, $node_id, $field_config );
		}

		// Else check if the values are being passed down via the name
		if ( isset( $field_config['name'] ) && ! empty( $root[ '_' . $field_config['name'] ] ) ) {
			return $this->prepare_acf_field_value( $root[ '_' . $field_config['name'] ], $node, $node_id, $field_config );
		}

		// Else check if the values are being passed down via the name
		if ( isset( $field_config['name'] ) && ! empty( $root[ $field_config['name'] ] ) ) {
			return $this->prepare_acf_field_value( $root[ $field_config['name'] ], $node, $node_id, $field_config );
		}

		/**
		 * Filter the field value before resolving.
		 *
		 * @param mixed            $value     The value of the ACF Field stored on the node
		 * @param mixed            $node      The object the field is connected to
		 * @param mixed|string|int $node_id   The ACF ID of the node to resolve the field with
		 * @param array            $acf_field The ACF Field config
		 * @param bool             $format    Whether to apply formatting to the field
		 * @param string           $field_key The key of the field being resolved
		 */
		$pre_value = \apply_filters( 'wpgraphql/acf/pre_resolve_acf_field', null, $root, $node_id, $field_config, $should_format_value, $field_key );

		// If the filter has returned a value, we can return the value that was returned.
		if ( null !== $pre_value ) {
			return $pre_value;
		}

		$parent_field_name = null;
		if ( ! empty( $field_config['parent'] ) ) {
			$parent_field = acf_get_field( $field_config['parent'] );
			if ( ! empty( $parent_field['name'] ) ) {
				$parent_field_name = $parent_field['name'];
			}
		}

		// resolve block field
		if ( is_array( $node ) && isset( $node['blockName'], $node['attrs'] ) ) {
			$block = $node['attrs'];

			// Ensure the block has an ID
			if ( ! isset( $block['id'] ) ) {
				$block['id'] = uniqid( 'block_', true );
			}

			$block    = acf_prepare_block( $block );
			$block_id = acf_get_block_id( $node['attrs'] );
			$block_id = acf_ensure_block_id_prefix( $block_id );

			acf_setup_meta( $block['data'] ?? [], $block_id, true );

			$return_value = $this->get_field( $field_config['name'], $parent_field_name, $block_id, $should_format_value );
			acf_reset_meta( $block_id );

			if ( empty( $return_value ) && isset( $node['attrs']['data'][ $field_config['name'] ] ) ) {
				$return_value = $node['attrs']['data'][ $field_config['name'] ];
			}

			if ( empty( $return_value ) && ( ! empty( $parent_field_name ) && ! empty( $node['attrs']['data'][ $parent_field_name . '_' . $field_config['name'] ] ) ) ) {
				$return_value = $node['attrs']['data'][ $parent_field_name . '_' . $field_config['name'] ];
			}
		}



		// If there's no node_id at this point, we can return null
		if ( empty( $return_value ) && empty( $node_id ) ) {
			return null;
		}

		// if a value hasn't been set yet, use the get_field() function to get the value
		if ( empty( $return_value ) ) {
			$return_value = $this->get_field( $field_key, $parent_field_name, $node_id, $should_format_value );
		}

		// Prepare the value for response
		$prepared_value = $this->prepare_acf_field_value( $return_value, $root, $node_id, $field_config );
		// Empty values are set to null
		if ( empty( $prepared_value ) ) {
			$prepared_value = null;
		}

		/**
		 * Filter the value before returning
		 *
		 * @param mixed $value
		 * @param array $field_config The ACF Field Config for the field being resolved
		 * @param mixed $root The Root node or object of the field being resolved
		 * @param mixed $node_id The ID of the node being resolved
		 */
		return \apply_filters( 'wpgraphql/acf/field_value', $prepared_value, $field_config, $root, $node_id );
	}

	/**
	 * Given a value of an ACF Field, this prepares it for response by applying formatting, etc based on
	 * the field type.
	 *
	 * @param mixed            $value   The value of the ACF field to return
	 * @param mixed            $root    The root node/object the field belongs to
	 * @param mixed|string|int $node_id The ID of the node the field belongs to
	 * @param ?array           $acf_field_config The ACF Field Config for the field being resolved
	 *
	 * @return mixed
	 */
	public function prepare_acf_field_value( $value, $root, $node_id, ?array $acf_field_config = [] ) {
		if ( empty( $acf_field_config ) || ! is_array( $acf_field_config ) ) {
			return $value;
		}

		// if the value is an array and the field return format is set to array,
		// map over the array to get the return values
		if ( isset( $acf_field_config['return_format'] ) && 'array' === $acf_field_config['return_format'] && is_array( $value ) ) {
			$value = array_map(
				static function ( $opt ) {
					if ( ! is_array( $opt ) ) {
						return $opt;
					}

					return $opt['value'] ?? null;
				},
				$value
			);
		}

		if ( isset( $acf_field_config['new_lines'] ) ) {
			if ( 'wpautop' === $acf_field_config['new_lines'] ) {
				$value = \wpautop( $value );
			}
			if ( 'br' === $acf_field_config['new_lines'] ) {
				$value = \nl2br( $value );
			}
		}

		// @todo: This was ported over, but I'm not 💯 sure what this is solving and
		// why it's only applied on options pages and not other pages 🤔
		if ( 'wysiwyg' === $acf_field_config['type'] ) {
			$value = \apply_filters( 'the_content', $value );
		}

		if ( ! empty( $acf_field_config['type'] ) && in_array(
			$acf_field_config['type'],
			[
				'date_picker',
				'time_picker',
				'date_time_picker',
			],
			true
		) ) {
			if ( ! empty( $value ) && ! empty( $acf_field_config['return_format'] ) ) {
				$value = gmdate( $acf_field_config['return_format'], strtotime( $value ) );
			}
		}

		if ( ! empty( $acf_field_config['type'] ) && in_array( $acf_field_config['type'], [ 'number', 'range' ], true ) ) {
			$value = (float) $value ?: null;
		}

		return $value;
	}

	/**
	 * @param string $to_type
	 */
	public function get_connection_name( string $to_type ): string {
		return \WPGraphQL\Utils\Utils::format_type_name( 'Acf' . ucfirst( $to_type ) . 'Connection' );
	}

	/**
	 * @param array<mixed> $config The Connection Config to use
	 *
	 * @throws \Exception
	 */
	public function register_graphql_connections( array $config ): void {
		$type_name = $this->get_graphql_field_group_type_name();
		$to_type   = $config['toType'] ?? null;

		// If there's no to_type or type_name, we can't proceed
		if ( empty( $to_type ) || empty( $type_name ) ) {
			return;
		}

		$connection_name = $this->get_connection_name( $to_type );

		$connection_config = array_merge(
			[
				'description'           => $this->get_field_description(),
				'acf_field'             => $this->get_acf_field(),
				'acf_field_group'       => $this->get_acf_field_group(),
				'fromType'              => $type_name,
				'toType'                => $to_type,
				'connectionTypeName'    => $connection_name,
				'fromFieldName'         => $this->get_graphql_field_name(),
				'allowFieldUnderscores' => true,
			],
			$config
		);

		$connection_key = $this->get_graphql_field_name() . ':' . $type_name . ':' . $to_type;

		if ( $this->registry->has_registered_field_group( $connection_key ) ) {
			return;
		}

		// Register the connection to the Field Group Type
		if ( defined( 'WPGRAPHQL_VERSION' ) && version_compare( WPGRAPHQL_VERSION, '1.23.0', '<=' ) ) {
			register_graphql_connection( $connection_config );
		}

		// Register the connection to the Field Group Fields Interface
		register_graphql_connection( array_merge( $connection_config, [ 'fromType' => $type_name . '_Fields' ] ) );

		$this->registry->register_field_group( $connection_key, $connection_config );
	}
}

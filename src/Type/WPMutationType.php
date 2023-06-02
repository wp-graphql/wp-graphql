<?php
namespace WPGraphQL\Type;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPMutationType
 *
 * @package WPGraphQL\Type
 */
class WPMutationType {
	/**
	 * Configuration for how auth should be handled on the connection field
	 *
	 * @var array
	 */
	protected $auth;

	/**
	 * The config for the connection
	 *
	 * @var array
	 */
	protected $config;

	/**
	 * The name of the mutation field
	 *
	 * @var string
	 */
	protected $mutation_name;

	/**
	 * Whether the user must be authenticated to use the mutation.
	 *
	 * @var bool
	 */
	protected $is_private;

	/**
	 * The mutation input field config.
	 *
	 * @var array
	 */
	protected $input_fields;

	/**
	 * The mutation output field config.
	 *
	 * @var array
	 */
	protected $output_fields;

	/**
	 * The resolver function to resole the connection
	 *
	 * @var callable|\Closure
	 */
	protected $resolve_mutation;

	/**
	 * The WPGraphQL TypeRegistry
	 *
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	protected $type_registry;

	/**
	 * WPMutationType constructor.
	 *
	 * @param array        $config        The config array for the mutation
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry Instance of the WPGraphQL Type Registry
	 *
	 * @throws \Exception
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {

		/**
		 * Filter the config of WPMutationType
		 *
		 * @param array        $config         Array of configuration options passed to the WPMutationType when instantiating a new type
		 * @param \WPGraphQL\Type\WPMutationType $wp_mutation_type The instance of the WPMutationType class
		 *
		 * @since 1.13.0
		 */
		$config = apply_filters( 'graphql_wp_mutation_type_config', $config, $this );

		if ( ! $this->is_config_valid( $config ) ) {
			return;
		}

		$this->config        = $config;
		$this->type_registry = $type_registry;
		$this->mutation_name = $config['name'];

		// Bail if the mutation should be excluded from the schema.
		if ( ! $this->should_register() ) {
			return;
		}

		$this->auth             = array_key_exists( 'auth', $config ) && is_array( $config['auth'] ) ? $config['auth'] : [];
		$this->is_private       = array_key_exists( 'isPrivate', $config ) ? $config['isPrivate'] : false;
		$this->input_fields     = $this->get_input_fields();
		$this->output_fields    = $this->get_output_fields();
		$this->resolve_mutation = $this->get_resolver();

		/**
		 * Run an action when the WPMutationType is instantiating.
		 *
		 * @param array        $config         Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param \WPGraphQL\Type\WPMutationType $wp_mutation_type The instance of the WPMutationType class
		 *
		 * @since 1.13.0
		 */
		do_action( 'graphql_wp_mutation_type', $config, $this );

		$this->register_mutation();
	}

	/**
	 * Validates that essential key/value pairs are passed to the connection config.
	 *
	 * @param array $config
	 *
	 * @return bool
	 */
	protected function is_config_valid( array $config ): bool {

		$is_valid = true;

		if ( ! array_key_exists( 'name', $config ) || ! is_string( $config['name'] ) ) {
			graphql_debug( __( 'Mutation config needs to have a valid name.', 'wp-graphql' ), [
				'config' => $config,
			] );
			$is_valid = false;
		}

		if ( ! array_key_exists( 'mutateAndGetPayload', $config ) || ! is_callable( $config['mutateAndGetPayload'] ) ) {
			graphql_debug( __( 'Mutation config needs to have "mutateAndGetPayload" defined as a callable.', 'wp-graphql' ), [
				'config' => $config,
			] );
			$is_valid = false;
		}

		return (bool) $is_valid;

	}

	/**
	 * Gets the mutation input fields.
	 */
	protected function get_input_fields() : array {
		$input_fields = [
			'clientMutationId' => [
				'type'        => 'String',
				'description' => __( 'This is an ID that can be passed to a mutation by the client to track the progress of mutations and catch possible duplicate mutation submissions.', 'wp-graphql' ),
			],
		];

		if ( ! empty( $this->config['inputFields'] ) && is_array( $this->config['inputFields'] ) ) {
			$input_fields = array_merge( $input_fields, $this->config['inputFields'] );
		}

		return $input_fields;
	}

	/**
	 * Gets the mutation output fields.
	 */
	protected function get_output_fields() : array {
		$output_fields = [
			'clientMutationId' => [
				'type'        => 'String',
				'description' => __( 'If a \'clientMutationId\' input is provided to the mutation, it will be returned as output on the mutation. This ID can be used by the client to track the progress of mutations and catch possible duplicate mutation submissions.', 'wp-graphql' ),
			],
		];

		if ( ! empty( $this->config['outputFields'] ) && is_array( $this->config['outputFields'] ) ) {
			$output_fields = array_merge( $output_fields, $this->config['outputFields'] );
		}

		return $output_fields;
	}

	protected function get_resolver() : callable {
		return function ( $root, array $args, AppContext $context, ResolveInfo $info ) {
			$unfiltered_input = $args['input'];

			$unfiltered_input = $args['input'];

			/**
			 * Filters the mutation input before it's passed to the `mutateAndGetPayload` callback.
			 *
			 * @param array $input The mutation input args.
			 * @param \WPGraphQL\AppContext $context The AppContext object.
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
			 * @param string $mutation_name The name of the mutation field.
			 */
			$input = apply_filters( 'graphql_mutation_input', $unfiltered_input, $context, $info, $this->mutation_name );

			/**
			 * Filter to short circuit the mutateAndGetPayload callback.
			 * Returning anything other than null will stop the callback for the mutation from executing,
			 * and will return your data or execute your callback instead.
			 *
			 * @param array|callable|null $payload. The payload returned from the callback. Null by default.
			 * @param string $mutation_name The name of the mutation field.
			 * @param callable|\Closure $mutateAndGetPayload The callback for the mutation.
			 * @param array $input The mutation input args.
			 * @param \WPGraphQL\AppContext $context The AppContext object.
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
			 */
			$pre = apply_filters( 'graphql_pre_mutate_and_get_payload', null, $this->mutation_name, $this->config['mutateAndGetPayload'], $input, $context, $info );

			if ( ! is_null( $pre ) ) {
				$payload = is_callable( $pre ) ? $pre( $input, $context, $info ) : $pre;
			} else {
				$payload = $this->config['mutateAndGetPayload']( $input, $context, $info );

				/**
				 * Filters the payload returned from the default mutateAndGetPayload callback.
				 *
				 * @param array $payload The payload returned from the callback.
				 * @param string $mutation_name The name of the mutation field.
				 * @param array $input The mutation input args.
				 * @param \WPGraphQL\AppContext $context The AppContext object.
				 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
				 */
				$payload = apply_filters( 'graphql_mutation_payload', $payload, $this->mutation_name, $input, $context, $info );
			}

			/**
			 * Fires after the mutation payload has been returned from the `mutateAndGetPayload` callback.
			 *
			 * @param array $payload The Payload returned from the mutation.
			 * @param array $input The mutation input args, after being filtered by 'graphql_mutation_input'.
			 * @param array $unfiltered_input The unfiltered input args of the mutation
			 * @param \WPGraphQL\AppContext $context The AppContext object.
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
			 * @param string $mutation_name The name of the mutation field.
			 */
			do_action( 'graphql_mutation_response', $payload, $input, $unfiltered_input, $context, $info, $this->mutation_name );

			// Add the client mutation ID to the payload
			if ( ! empty( $input['clientMutationId'] ) ) {
				$payload['clientMutationId'] = $input['clientMutationId'];
			}

			return $payload;
		};
	}

	/**
	 * Registers the input args for the mutation.
	 */
	protected function register_mutation_input() : void {
		$input_name = $this->mutation_name . 'Input';

		if ( $this->type_registry->has_type( $input_name ) ) {
			return;
		}

		$this->type_registry->register_input_type(
			$input_name,
			[
				'description'       => sprintf( __( 'Input for the %1$s mutation.', 'wp-graphql' ), $this->mutation_name ),
				'fields'            => $this->input_fields,
				'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
			]
		);
	}

	protected function register_mutation_payload() : void {
		$object_name = $this->mutation_name . 'Payload';

		if ( $this->type_registry->has_type( $object_name ) ) {
			return;
		}

		$this->type_registry->register_object_type(
			$object_name,
			[
				'description'       => sprintf( __( 'The payload for the %s mutation.', 'wp-graphql' ), $this->mutation_name ),
				'fields'            => $this->output_fields,
				'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
			]
		);
	}

	/**
	 * Registers the mutation in the Graph.
	 *
	 * @throws \Exception
	 */
	protected function register_mutation_field() : void {

		$field_config = array_merge( $this->config,
			[
				'args'        => [
					'input' => [
						'type'              => [ 'non_null' => $this->mutation_name . 'Input' ],
						'description'       => sprintf( __( 'Input for the %s mutation', 'wp-graphql' ), $this->mutation_name ),
						'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
					],
				],
				'auth'        => $this->auth,
				'description' => ! empty( $this->config['description'] ) ? $this->config['description'] : sprintf( __( 'The %s mutation', 'wp-graphql' ), $this->mutation_name ),
				'isPrivate'   => $this->is_private,
				'type'        => $this->mutation_name . 'Payload',
				'resolve'     => $this->resolve_mutation,
				'name'        => lcfirst( $this->mutation_name ),
			]
		);

		$this->type_registry->register_field(
			'RootMutation',
			lcfirst( $this->mutation_name ),
			$field_config
		);
	}

	/**
	 * Registers the Mutation Types and field to the Schema.
	 *
	 * @throws \Exception
	 */
	protected function register_mutation() :void {
		$this->register_mutation_payload();
		$this->register_mutation_input();
		$this->register_mutation_field();
	}

	/**
	 * Checks whether the mutation should be registered to the schema.
	 */
	protected function should_register() : bool {
		// Dont register mutations if they have been excluded from the schema.
		$excluded_mutations = $this->type_registry->get_excluded_mutations();
		if ( in_array( strtolower( $this->mutation_name ), $excluded_mutations, true ) ) {
			return false;
		}

		return true;
	}
}

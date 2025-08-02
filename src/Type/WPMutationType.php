<?php
namespace WPGraphQL\Type;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL;
use WPGraphQL\AppContext;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface;
use WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait;

/**
 * Class WPMutationType
 *
 * @package WPGraphQL\Type
 *
 * phpcs:disable SlevomatCodingStandard.Namespaces.FullyQualifiedClassNameInAnnotation -- for phpstan type hinting.
 *
 * @phpstan-type WPMutationTypeConfig array{
 *  auth: array<string,mixed>,
 *  deprecationReason?: string|callable(): ?string,
 *  description?: string|callable(): string,
 *  inputFields?: array<string,array<string,mixed>>,
 *  isPrivate: bool,
 *  mutateAndGetPayload: callable,
 *  name: string,
 *  outputFields?: array<string,array<string,mixed>>,
 * }
 *
 * @phpstan-implements \WPGraphQL\Registry\TypeAdapters\TypeAdapterInterface<WPMutationTypeConfig>
 *
 * phpcs:enable
 */
class WPMutationType implements TypeAdapterInterface {
	/** @use \WPGraphQL\Registry\TypeAdapters\TypeAdapterTrait<WPMutationTypeConfig> */
	use TypeAdapterTrait;

	/**
	 * The config for the connection
	 *
	 * @var WPMutationTypeConfig
	 */
	protected $config;

	/**
	 * The name of the mutation field
	 *
	 * @var string
	 */
	protected $mutation_name;

	/**
	 * The WPGraphQL TypeRegistry
	 *
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	protected $type_registry;

	/**
	 * {@inheritDoc}
	 */
	public static function get_kind(): string {
		return 'wp_mutation';
	}

	/**
	 * Prepares the configuration before registering the composite types.
	 *
	 * @param array<string,mixed> $config The config array for the mutation.
	 */
	public function __construct( array $config ) {
		$config = $this->prepare( $config );

		// @todo - we don't error for back-compat reasons, but we should.
		try {
			$this->validate_config( $config );
		} catch ( \Throwable $e ) {
			graphql_debug(
				sprintf(
					// translators: %1$s is the mutation name, %2$s is the error message.
					esc_html__( 'Mutation config for "%1$s" is invalid. Error: %2$s', 'wp-graphql' ),
					esc_html( $config['name'] ?? 'unknown' ),
					esc_html( $e->getMessage() )
				),
				[
					'config' => $config,
				]
			);
		}

		/** @var WPMutationTypeConfig $config */
		$this->config        = $config;
		$this->type_registry = WPGraphQL::get_type_registry();
		$this->mutation_name = $config['name'];

		// Bail if the mutation should be excluded from the schema.
		if ( ! $this->should_register() ) {
			return;
		}

		/**
		 * Run an action when the WPMutationType is instantiating.
		 *
		 * @param array<string,mixed>            $config           Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param \WPGraphQL\Type\WPMutationType $wp_mutation_type The instance of the WPMutationType class
		 *
		 * @since 1.13.0
		 */
		do_action( 'graphql_wp_mutation_type', $config, $this );

		$this->register_mutation();
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare( array $config ): array {
		// Prepare the 'auth' key.
		$config['auth'] = isset( $config['auth'] ) && is_array( $config['auth'] ) ? $config['auth'] : [];
		// Prepare the 'isPrivate' key.
		$config['isPrivate'] = isset( $config['isPrivate'] ) ? (bool) $config['isPrivate'] : false;

		// Ensure there's a default description.
		if ( ! isset( $config['description'] ) ) {
			$config['description'] = static function () use ( $config ) {
				// translators: %s is the name of the mutation.
				return sprintf( __( 'The %s mutation', 'wp-graphql' ), $config['name'] );
			};
		}

		/**
		 * Filter the config of WPMutationType
		 *
		 * @param array<string,mixed> $config Array of configuration options passed to the WPMutationType when instantiating a new type
		 *
		 * @since 1.13.0
		 */
		return apply_filters( 'graphql_wp_mutation_type_config', $config );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \GraphQL\Error\UserError If the configuration is invalid.
	 */
	public function validate( array $config ): void {
		if ( ! array_key_exists( 'name', $config ) || ! is_string( $config['name'] ) ) {
			throw new UserError(
				esc_html__( 'Mutation config must have a name.', 'wp-graphql' )
			);
		}

		if ( ! array_key_exists( 'mutateAndGetPayload', $config ) || ! is_callable( $config['mutateAndGetPayload'] ) ) {
			throw new UserError(
				sprintf(
					// translators: %s is the mutation name.
					esc_html__( 'Mutation config for "%s" must have a "mutateAndGetPayload" callable.', 'wp-graphql' ),
					esc_html( $config['name'] )
				)
			);
		}
	}

	/**
	 * Checks whether the mutation should be registered to the schema.
	 */
	protected function should_register(): bool {
		// Don't register mutations if they have been excluded from the schema.
		$excluded_mutations = $this->type_registry->get_excluded_mutations();

		return ! in_array( strtolower( $this->mutation_name ), $excluded_mutations, true );
	}

	/**
	 * Gets the mutation input fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_input_fields(): array {
		$input_fields = [
			'clientMutationId' => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'This is an ID that can be passed to a mutation by the client to track the progress of mutations and catch possible duplicate mutation submissions.', 'wp-graphql' );
				},
			],
		];

		if ( ! empty( $this->config['inputFields'] ) && is_array( $this->config['inputFields'] ) ) {
			$input_fields = array_merge( $input_fields, $this->config['inputFields'] );
		}

		return $input_fields;
	}

	/**
	 * Gets the mutation output fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_output_fields(): array {
		$output_fields = [
			'clientMutationId' => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'If a \'clientMutationId\' input is provided to the mutation, it will be returned as output on the mutation. This ID can be used by the client to track the progress of mutations and catch possible duplicate mutation submissions.', 'wp-graphql' );
				},
			],
		];

		if ( ! empty( $this->config['outputFields'] ) && is_array( $this->config['outputFields'] ) ) {
			$output_fields = array_merge( $output_fields, $this->config['outputFields'] );
		}

		return $output_fields;
	}

	/**
	 * Gets the resolver callable for the mutation.
	 *
	 * @return callable(mixed $root,array<string,mixed> $args,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info): array<string,mixed>
	 */
	protected function get_resolver(): callable {
		return function ( $root, array $args, AppContext $context, ResolveInfo $info ) {
			$unfiltered_input = $args['input'];

			$unfiltered_input = $args['input'];

			/**
			 * Filters the mutation input before it's passed to the `mutateAndGetPayload` callback.
			 *
			 * @param array<string,mixed>                  $input         The mutation input args.
			 * @param \WPGraphQL\AppContext                $context       The AppContext object.
			 * @param \GraphQL\Type\Definition\ResolveInfo $info          The ResolveInfo object.
			 * @param string                               $mutation_name The name of the mutation field.
			 */
			$input = apply_filters( 'graphql_mutation_input', $unfiltered_input, $context, $info, $this->mutation_name );

			/**
			 * Filter to short circuit the mutateAndGetPayload callback.
			 * Returning anything other than null will stop the callback for the mutation from executing,
			 * and will return your data or execute your callback instead.
			 *
			 * @param array<string,mixed>|callable|null   $payload.            The payload returned from the callback. Null by default.
			 * @param string                $mutation_name       The name of the mutation field.
			 * @param callable|\Closure     $mutateAndGetPayload The callback for the mutation.
			 * @param array<string,mixed>   $input               The mutation input args.
			 * @param \WPGraphQL\AppContext $context             The AppContext object.
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
				 * @param array<string,mixed>   $payload The payload returned from the callback.
				 * @param string                $mutation_name The name of the mutation field.
				 * @param array<string,mixed>   $input The mutation input args.
				 * @param \WPGraphQL\AppContext $context The AppContext object.
				 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo object.
				 */
				$payload = apply_filters( 'graphql_mutation_payload', $payload, $this->mutation_name, $input, $context, $info );
			}

			/**
			 * Fires after the mutation payload has been returned from the `mutateAndGetPayload` callback.
			 *
			 * @param array<string,mixed>                  $payload          The Payload returned from the mutation.
			 * @param array<string,mixed>                  $input            The mutation input args, after being filtered by 'graphql_mutation_input'.
			 * @param array<string,mixed>                  $unfiltered_input The unfiltered input args of the mutation
			 * @param \WPGraphQL\AppContext                $context          The AppContext object.
			 * @param \GraphQL\Type\Definition\ResolveInfo $info             The ResolveInfo object.
			 * @param string                               $mutation_name    The name of the mutation field.
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
	protected function register_mutation_input(): void {
		$input_name = $this->mutation_name . 'Input';

		if ( $this->type_registry->has_type( $input_name ) ) {
			return;
		}

		$this->type_registry->register_input_type(
			$input_name,
			[
				// translators: %s is the name of the mutation.
				'description'       => function () {
					// translators: %s is the name of the mutation.
					return sprintf( __( 'Input for the %1$s mutation.', 'wp-graphql' ), $this->mutation_name );
				},
				'fields'            => $this->get_input_fields(),
				'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
			]
		);
	}

	/**
	 * Registers the payload type to the Schema.
	 */
	protected function register_mutation_payload(): void {
		$object_name = $this->mutation_name . 'Payload';

		if ( $this->type_registry->has_type( $object_name ) ) {
			return;
		}

		$this->type_registry->register_object_type(
			$object_name,
			[
				// translators: %s is the name of the mutation.
				'description'       => function () {
					// translators: %s is the name of the mutation.
					return sprintf( __( 'The payload for the %s mutation.', 'wp-graphql' ), $this->mutation_name );
				},
				'fields'            => $this->get_output_fields(),
				'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
			]
		);
	}

	/**
	 * Registers the mutation in the Graph.
	 *
	 * @throws \Exception
	 */
	protected function register_mutation_field(): void {
		$this->type_registry->register_field(
			'RootMutation',
			lcfirst( $this->mutation_name ),
			array_merge(
				// Pass through other config options.
				$this->config,
				[
					'args'              => [
						'input' => [
							'type'              => [ 'non_null' => $this->mutation_name . 'Input' ],
							'description'       => function () {
								// translators: %s is the name of the mutation.
								return sprintf( __( 'Input for the %s mutation', 'wp-graphql' ), $this->mutation_name );
							},
							'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
						],
					],
					'auth'              => $this->config['auth'],
					'description'       => ! empty( $this->config['description'] ) ? $this->config['description'] : null,
					'deprecationReason' => ! empty( $this->config['deprecationReason'] ) ? $this->config['deprecationReason'] : null,
					'isPrivate'         => $this->config['isPrivate'],
					'type'              => $this->mutation_name . 'Payload',
					'resolve'           => $this->get_resolver(),
					'name'              => lcfirst( $this->mutation_name ),
				]
			)
		);
	}

	/**
	 * Registers the Mutation Types and field to the Schema.
	 *
	 * @throws \Exception
	 */
	protected function register_mutation(): void {
		$this->register_mutation_payload();
		$this->register_mutation_input();
		$this->register_mutation_field();
	}
}

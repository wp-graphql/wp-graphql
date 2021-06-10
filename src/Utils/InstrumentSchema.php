<?php

namespace WPGraphQL\Utils;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\WPSchema;

/**
 * Class InstrumentSchema
 *
 * @package WPGraphQL\Data
 */
class InstrumentSchema {

	/**
	 * Cache post for the resolvers so we can call the setup_postdata only when the actual
	 * source post changes
	 *
	 * @var mixed The WP_Post object, or null
	 */
	private static $cached_post = null;

	/**
	 * @param WPSchema $schema Instance of the Schema.
	 *
	 * @return WPSchema
	 */
	public static function instrument_schema( WPSchema $schema ) {

		$new_types = [];
		$types     = $schema->getTypeMap();

		if ( ! empty( $types ) && is_array( $types ) ) {
			foreach ( $types as $type_name => $type_object ) {
				if ( $type_object instanceof ObjectType ) {
					$fields                            = $type_object->getFields();
					$new_fields                        = self::wrap_fields( $fields, $type_name );
					$new_type_object                   = $type_object;
					$new_type_object->name             = ucfirst( esc_html( $type_object->name ) );
					$new_type_object->description      = ! empty( $type_object->description ) ? esc_html( $type_object->description ) : '';
					$new_type_object->config['fields'] = $new_fields;
					$new_types[ $type_name ]           = $new_type_object;
				}
			}
		}

		if ( ! empty( $new_types ) && is_array( $new_types ) ) {
			$schema->config->types = $new_types;
		}

		return $schema;

	}

	/**
	 * Wrap Fields
	 *
	 * This wraps fields to provide sanitization on fields output by introspection queries
	 * (description/deprecation reason) and provides hooks to resolvers.
	 *
	 * @param array  $fields    The fields configured for a Type
	 * @param string $type_name The Type name
	 *
	 * @return mixed
	 */
	protected static function wrap_fields( $fields, $type_name ) {

		if ( ! empty( $fields ) && is_array( $fields ) ) {

			foreach ( $fields as $field_key => $field ) {

				if ( $field instanceof FieldDefinition ) {

					/**
					 * Filter the field definition
					 *
					 * @param \GraphQL\Type\Definition\FieldDefinition $field     The field definition
					 * @param string                                   $type_name The name of the Type the field belongs to
					 */
					$field = apply_filters( 'graphql_field_definition', $field, $type_name );

					/**
					 * Get the fields resolve function
					 *
					 * @since 0.0.1
					 */
					$field_resolver = ! empty( $field->resolveFn ) ? $field->resolveFn : null;

					/**
					 * Sanitize the description and deprecation reason
					 */
					$field->description       = ! empty( $field->description ) && is_string( $field->description ) ? esc_html( $field->description ) : '';
					$field->deprecationReason = ! empty( $field->deprecationReason ) && is_string( $field->description ) ? esc_html( $field->deprecationReason ) : null;

					/**
					 * Replace the existing field resolve method with a new function that captures data about
					 * the resolver to be stored in the resolver_report
					 *
					 * @param mixed       $source  The source passed down the Resolve Tree
					 * @param array       $args    The args for the field
					 * @param AppContext  $context The AppContext passed down the ResolveTree
					 * @param ResolveInfo $info    The ResolveInfo passed down the ResolveTree
					 *
					 * @return mixed
					 * @throws Exception
					 * @since 0.0.1
					 */
					$field->resolveFn = function( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $field_resolver, $type_name, $field_key, $field ) {

						/**
						 * Setup the global post to the current post (if a post)
						 * This ensures that functions like get_the_content() work correctly
						 * so graphql queries can be used in the loop without issues.
						 */
						if ( is_a( $source, 'WP_Post' ) && self::$cached_post !== $source ) {
							self::$cached_post = $source;
							$GLOBALS['post']   = $source;
							setup_postdata( $source );
						}

						/**
						 * Fire an action BEFORE the field resolves
						 *
						 * @param mixed           $source         The source passed down the Resolve Tree
						 * @param array           $args           The args for the field
						 * @param AppContext      $context        The AppContext passed down the ResolveTree
						 * @param ResolveInfo     $info           The ResolveInfo passed down the ResolveTree
						 * @param callable        $field_resolver The Resolve function for the field
						 * @param string          $type_name      The name of the type the fields belong to
						 * @param string          $field_key      The name of the field
						 * @param FieldDefinition $field          The Field Definition for the resolving field
						 */
						do_action( 'graphql_before_resolve_field', $source, $args, $context, $info, $field_resolver, $type_name, $field_key, $field );

						/**
						 * Create unique custom "nil" value which is different from the build-in PHP null, false etc.
						 * When this custom "nil" is returned we can know that the filter did not try to preresolve
						 * the field because it does not equal with anything but itself.
						 */
						$nil = new \stdClass();

						/**
						 * When this filter return anything other than the $nil it will be used as the resolved value
						 * and the execution of the actual resolved is skipped. This filter can be used to implement
						 * field level caches or for efficiently hiding data by returning null.
						 *
						 * @param mixed           $nil            Unique nil value
						 * @param mixed           $source         The source passed down the Resolve Tree
						 * @param array           $args           The args for the field
						 * @param AppContext      $context        The AppContext passed down the ResolveTree
						 * @param ResolveInfo     $info           The ResolveInfo passed down the ResolveTree
						 * @param string          $type_name      The name of the type the fields belong to
						 * @param string          $field_key      The name of the field
						 * @param FieldDefinition $field          The Field Definition for the resolving field
						 * @param mixed           $field_resolver The default field resolver
						 */
						$result = apply_filters( 'graphql_pre_resolve_field', $nil, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver );

						/**
						 * Check if the field preresolved
						 */
						if ( $nil === $result ) {
							/**
							 * If the current field doesn't have a resolve function, use the defaultFieldResolver,
							 * otherwise use the $field_resolver
							 */
							if ( null === $field_resolver || ! is_callable( $field_resolver ) ) {
								$result = Executor::defaultFieldResolver( $source, $args, $context, $info );
							} else {
								$result = call_user_func( $field_resolver, $source, $args, $context, $info );
							}
						}

						/**
						 * Fire an action before the field resolves
						 *
						 * @param mixed           $result         The result of the field resolution
						 * @param mixed           $source         The source passed down the Resolve Tree
						 * @param array           $args           The args for the field
						 * @param AppContext      $context        The AppContext passed down the ResolveTree
						 * @param ResolveInfo     $info           The ResolveInfo passed down the ResolveTree
						 * @param string          $type_name      The name of the type the fields belong to
						 * @param string          $field_key      The name of the field
						 * @param FieldDefinition $field          The Field Definition for the resolving field
						 * @param mixed           $field_resolver The default field resolver
						 */
						$result = apply_filters( 'graphql_resolve_field', $result, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver );

						/**
						 * Fire an action AFTER the field resolves
						 *
						 * @param mixed           $source    The source passed down the Resolve Tree
						 * @param array           $args      The args for the field
						 * @param AppContext      $context   The AppContext passed down the ResolveTree
						 * @param ResolveInfo     $info      The ResolveInfo passed down the ResolveTree
						 * @param string          $type_name The name of the type the fields belong to
						 * @param string          $field_key The name of the field
						 * @param FieldDefinition $field     The Field Definition for the resolving field
						 * @param mixed           $result    The result of the field resolver
						 */
						do_action( 'graphql_after_resolve_field', $source, $args, $context, $info, $field_resolver, $type_name, $field_key, $field, $result );

						return $result;

					};

				}
			}
		}

		/**
		 * Return the fields
		 */
		return $fields;

	}

	/**
	 * Check field permissions when resolving.
	 *
	 * This takes into account auth params defined in the Schema
	 *
	 * @param mixed                 $source         The source passed down the Resolve Tree
	 * @param array                 $args           The args for the field
	 * @param AppContext            $context        The AppContext passed down the ResolveTree
	 * @param ResolveInfo           $info           The ResolveInfo passed down the ResolveTree
	 * @param mixed|callable|string $field_resolver The Resolve function for the field
	 * @param string                $type_name      The name of the type the fields belong to
	 * @param string                $field_key      The name of the field
	 * @param FieldDefinition       $field          The Field Definition for the resolving field
	 *
	 * @return bool|mixed
	 */
	public static function check_field_permissions( $source, array $args, AppContext $context, ResolveInfo $info, $field_resolver, string $type_name, string $field_key, FieldDefinition $field ) {

		/**
		 * Set the default auth error message
		 */
		$default_auth_error_message = __( 'You do not have permission to view this', 'wp-graphql' );

		/**
		 * Filter the $auth_error
		 */
		$auth_error = apply_filters( 'graphql_field_resolver_auth_error_message', $default_auth_error_message, $field );

		/**
		 * Check to see if
		 */
		if ( $field instanceof FieldDefinition && (
				isset( $field->config['isPrivate'] ) ||
				( ! empty( $field->config['auth'] ) && is_array( $field->config['auth'] ) ) )
		) {

			/**
			 * If the schema for the field is configured to "isPrivate" or has "auth" configured,
			 * make sure the user is authenticated before resolving the field
			 */
			if ( empty( get_current_user_id() ) ) {
				throw new UserError( $auth_error );
			}

			/**
			 * If the user is authenticated, and the field has a custom auth callback configured,
			 * execute the callback before continuing resolution
			 */
			if ( ! empty( $field->config['auth']['callback'] ) && is_callable( $field->config['auth']['callback'] ) ) {

				return call_user_func( $field->config['auth']['callback'], $field, $field_key, $source, $args, $context, $info, $field_resolver );
			}

			/**
			 * If the user is authenticated and the field has "allowedCaps" configured,
			 * ensure the user has at least one of the allowedCaps before resolving
			 */
			if ( ! empty( $field->config['auth']['allowedCaps'] ) && is_array( $field->config['auth']['allowedCaps'] ) ) {
				$caps = ! empty( wp_get_current_user()->allcaps ) ? wp_get_current_user()->allcaps : [];
				if ( empty( array_intersect( array_keys( $caps ), array_values( $field->config['auth']['allowedCaps'] ) ) ) ) {
					throw new UserError( $auth_error );
				}
			}

			/**
			 * If the user is authenticated and the field has "allowedRoles" configured,
			 * ensure the user has at least one of the allowedRoles before resolving
			 */
			if ( ! empty( $field->config['auth']['allowedRoles'] ) && is_array( $field->config['auth']['allowedRoles'] ) ) {
				$roles         = ! empty( wp_get_current_user()->roles ) ? wp_get_current_user()->roles : [];
				$allowed_roles = array_values( $field->config['auth']['allowedRoles'] );
				if ( empty( array_intersect( array_values( $roles ), array_values( $allowed_roles ) ) ) ) {
					throw new UserError( $auth_error );
				}
			}
		}

	}

}

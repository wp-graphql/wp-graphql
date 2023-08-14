<?php

namespace WPGraphQL\Utils;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use WPGraphQL\AppContext;

/**
 * Class InstrumentSchema
 *
 * @package WPGraphQL\Data
 */
class InstrumentSchema {

	/**
	 * @param \GraphQL\Type\Definition\Type $type Instance of the Schema.
	 * @param string $type_name Name of the Type
	 *
	 * @return \GraphQL\Type\Definition\Type
	 */
	public static function instrument_resolvers( Type $type, string $type_name ): Type {

		if ( ! method_exists( $type, 'getFields' ) ) {
			return $type;
		}

		$fields = $type->getFields();

		$fields                 = ! empty( $fields ) ? self::wrap_fields( $fields, $type->name ) : [];
		$type->name             = ucfirst( esc_html( $type->name ) );
		$type->description      = ! empty( $type->description ) ? esc_html( $type->description ) : '';
		$type->config['fields'] = $fields;

		return $type;

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
	protected static function wrap_fields( array $fields, string $type_name ) {

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( $fields as $field_key => $field ) {

			/**
			 * Filter the field definition
			 *
			 * @param \GraphQL\Type\Definition\FieldDefinition $field The field definition
			 * @param string          $type_name The name of the Type the field belongs to
			 */
			$field = apply_filters( 'graphql_field_definition', $field, $type_name );

			if ( ! $field instanceof FieldDefinition ) {
				return $field;
			}

			/**
			 * Get the fields resolve function
			 *
			 * @since 0.0.1
			 */
			$field_resolver = is_callable( $field->resolveFn ) ? $field->resolveFn : null;

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
			 * @param \WPGraphQL\AppContext $context The AppContext passed down the ResolveTree
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the ResolveTree
			 *
			 * @return mixed
			 * @throws \Exception
			 * @since 0.0.1
			 */
			$field->resolveFn = static function ( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $field_resolver, $type_name, $field_key, $field ) {

				/**
				 * Fire an action BEFORE the field resolves
				 *
				 * @param mixed           $source         The source passed down the Resolve Tree
				 * @param array           $args           The args for the field
				 * @param \WPGraphQL\AppContext $context The AppContext passed down the ResolveTree
				 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the ResolveTree
				 * @param ?callable       $field_resolver The Resolve function for the field
				 * @param string          $type_name      The name of the type the fields belong to
				 * @param string          $field_key      The name of the field
				 * @param \GraphQL\Type\Definition\FieldDefinition $field The Field Definition for the resolving field
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
				 * @param \WPGraphQL\AppContext $context The AppContext passed down the ResolveTree
				 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the ResolveTree
				 * @param string          $type_name      The name of the type the fields belong to
				 * @param string          $field_key      The name of the field
				 * @param \GraphQL\Type\Definition\FieldDefinition $field The Field Definition for the resolving field
				 * @param mixed           $field_resolver The default field resolver
				 */
				$result = apply_filters( 'graphql_pre_resolve_field', $nil, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver );

				/**
				 * Check if the field pre-resolved
				 */
				if ( $nil === $result ) {
					/**
					 * If the current field doesn't have a resolve function, use the defaultFieldResolver,
					 * otherwise use the $field_resolver
					 */
					if ( null === $field_resolver || ! is_callable( $field_resolver ) ) {
						$result = Executor::defaultFieldResolver( $source, $args, $context, $info );
					} else {
						$result = $field_resolver( $source, $args, $context, $info );

					}
				}

				/**
				 * Fire an action before the field resolves
				 *
				 * @param mixed           $result         The result of the field resolution
				 * @param mixed           $source         The source passed down the Resolve Tree
				 * @param array           $args           The args for the field
				 * @param \WPGraphQL\AppContext $context The AppContext passed down the ResolveTree
				 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the ResolveTree
				 * @param string          $type_name      The name of the type the fields belong to
				 * @param string          $field_key      The name of the field
				 * @param \GraphQL\Type\Definition\FieldDefinition $field The Field Definition for the resolving field
				 * @param mixed           $field_resolver The default field resolver
				 */
				$result = apply_filters( 'graphql_resolve_field', $result, $source, $args, $context, $info, $type_name, $field_key, $field, $field_resolver );

				/**
				 * Fire an action AFTER the field resolves
				 *
				 * @param mixed           $source         The source passed down the Resolve Tree
				 * @param array           $args           The args for the field
				 * @param \WPGraphQL\AppContext $context The AppContext passed down the ResolveTree
				 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the ResolveTree
				 * @param ?callable        $field_resolver The Resolve function for the field
				 * @param string          $type_name      The name of the type the fields belong to
				 * @param string          $field_key      The name of the field
				 * @param \GraphQL\Type\Definition\FieldDefinition $field The Field Definition for the resolving field
				 * @param mixed           $result         The result of the field resolver
				 */
				do_action( 'graphql_after_resolve_field', $source, $args, $context, $info, $field_resolver, $type_name, $field_key, $field, $result );

				return $result;

			};
		}

		/**
		 * Return the fields
		 */
		return $fields;

	}

	/**
	 * Check field permissions when resolving. If the check fails, an error will be thrown.
	 *
	 * This takes into account auth params defined in the Schema
	 *
	 * @param mixed                 $source         The source passed down the Resolve Tree
	 * @param array                 $args           The args for the field
	 * @param \WPGraphQL\AppContext $context The AppContext passed down the ResolveTree
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the ResolveTree
	 * @param mixed|callable|string $field_resolver The Resolve function for the field
	 * @param string                $type_name      The name of the type the fields belong to
	 * @param string                $field_key      The name of the field
	 * @param \GraphQL\Type\Definition\FieldDefinition $field The Field Definition for the resolving field
	 *
	 * @return void
	 *             
	 * @throws \GraphQL\Error\UserError
	 */
	public static function check_field_permissions( $source, array $args, AppContext $context, ResolveInfo $info, $field_resolver, string $type_name, string $field_key, FieldDefinition $field ) {

		if ( ( ! isset( $field->config['auth'] ) || ! is_array( $field->config['auth'] ) ) && ! isset( $field->config['isPrivate'] ) ) {
			return;
		}

		/**
		 * Set the default auth error message
		 */
		$default_auth_error_message = __( 'You do not have permission to view this', 'wp-graphql' );
		$default_auth_error_message = apply_filters( 'graphql_field_resolver_auth_error_message', $default_auth_error_message, $field );

		/**
		 * Retrieve permissions error message.
		 */
		$auth_error = isset( $field->config['auth']['errorMessage'] ) && ! empty( $field->config['auth']['errorMessage'] )
			? $field->config['auth']['errorMessage']
			: $default_auth_error_message;

		/**
		 * If the user is authenticated, and the field has a custom auth callback configured
		 * execute the callback before continuing resolution
		 */
		if ( isset( $field->config['auth']['callback'] ) && is_callable( $field->config['auth']['callback'] ) ) {

			$authorized = call_user_func( $field->config['auth']['callback'], $field, $field_key, $source, $args, $context, $info, $field_resolver );

			// If callback returns explicit false throw.
			if ( false === $authorized ) {
				throw new UserError( $auth_error );
			}

			return;
		}

		/**
		 * If the schema for the field is configured to "isPrivate" or has "auth" configured,
		 * make sure the user is authenticated before resolving the field
		 */
		if ( isset( $field->config['isPrivate'] ) && true === $field->config['isPrivate'] && empty( get_current_user_id() ) ) {
			throw new UserError( $auth_error );
		}

		/**
		 * If the user is authenticated and the field has "allowedCaps" configured,
		 * ensure the user has at least one of the allowedCaps before resolving
		 */
		if ( isset( $field->config['auth']['allowedCaps'] ) && is_array( $field->config['auth']['allowedCaps'] ) ) {
			$caps = ! empty( wp_get_current_user()->allcaps ) ? wp_get_current_user()->allcaps : [];
			if ( empty( array_intersect( array_keys( $caps ), array_values( $field->config['auth']['allowedCaps'] ) ) ) ) {
				throw new UserError( $auth_error );
			}
		}

		/**
		 * If the user is authenticated and the field has "allowedRoles" configured,
		 * ensure the user has at least one of the allowedRoles before resolving
		 */
		if ( isset( $field->config['auth']['allowedRoles'] ) && is_array( $field->config['auth']['allowedRoles'] ) ) {
			$roles         = ! empty( wp_get_current_user()->roles ) ? wp_get_current_user()->roles : [];
			$allowed_roles = array_values( $field->config['auth']['allowedRoles'] );
			if ( empty( array_intersect( array_values( $roles ), array_values( $allowed_roles ) ) ) ) {
				throw new UserError( $auth_error );
			}
		}

	}

}
